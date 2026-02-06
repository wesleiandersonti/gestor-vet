<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;
use App\Models\Pagamento;
use App\Models\Cliente;
use Illuminate\Http\Request;
use App\Models\PlanoRenovacao;
use Illuminate\Support\Facades\Auth;
use App\Models\CompanyDetail;
use App\Http\Controllers\SendMessageController;
use App\Models\Plano;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Template;
use App\Models\User;
use Carbon\Carbon;

class EcommerceOrderDetails extends Controller
{
  public function __construct()
  {
    // Aplicar middleware de autenticação
    $this->middleware('auth');
  }

  public function index(Request $request)
  {
      $user = Auth::user();
  
      // Buscar a cobrança específica
      $paymentId = $request->query('order_id');
  
      $payment = Pagamento::find($paymentId);
  
      // Verificar se o pagamento foi encontrado
      if (!$payment) {
          return redirect()->back()->with('error', 'Pagamento não encontrado.');
      }
  
    
      $cliente = Cliente::find($payment->cliente_id);
      if (!$cliente) {
          return redirect()->back()->with('error', 'Nenhum Pagamento encontrado para este Cliente.');
      }
  
      $empresa = CompanyDetail::where('user_id', $payment->user_id)->first();
      if (!$empresa) {
          return redirect()->back()->with('error', 'Empresa não encontrada.');
      }
  
      $plano = Plano::find($cliente->plano_id);
      if (!$plano) {
          return redirect()->back()->with('error', 'Plano não encontrado.');
      }
    
      $planos_revenda = PlanoRenovacao::all();
   
  
      $current_plan_id = $user->plano_id;
      return view('content.apps.detalhes', compact('payment', 'cliente', 'planos_revenda', 'current_plan_id', 'empresa', 'plano'));
    }

    public function addPayment(Request $request)
    {
        try {
            Log::info('Iniciando processamento de pagamento', ['request' => $request->all()]);

            $validated = $request->validate([
                'payment_id' => 'required|exists:pagamentos,id',
                'invoiceAmount' => 'required|numeric|min:0',
                'payment_date' => 'required|date',
                'payment_status' => 'required|string|in:pending,approved',
            ]);

            return DB::transaction(function () use ($validated) {
                $payment = Pagamento::with(['cliente', 'cliente.plano'])->findOrFail($validated['payment_id']);
                
                try {
                    $paymentDate = Carbon::parse($validated['payment_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    throw new \Exception("Formato de data inválido. Use o formato AAAA-MM-DD");
                }

                $payment->valor = $validated['invoiceAmount'];
                $payment->status = $validated['payment_status'];
                $payment->payment_date = $paymentDate;
                $payment->updated_at = now();

                if (!$payment->save()) {
                    throw new \Exception("Falha ao salvar os dados do pagamento");
                }

                $payment->refresh();
                if ($payment->payment_date != $paymentDate) {
                    throw new \Exception("A data de pagamento não foi salva corretamente");
                }

                if ($payment->status === 'approved') {
                    $this->processApprovedPayment($payment);
                }

                $this->notifyClientAndOwner($payment, $payment->status === 'approved');

                return redirect()->back()->with('success', 'Pagamento atualizado com sucesso.');
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
            
        } catch (\Exception $e) {
            Log::error('Erro ao processar pagamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Erro ao processar pagamento: ' . $e->getMessage());
        }
    }
    
    protected function processApprovedPayment(Pagamento $payment)
    {
        if (!$payment->cliente) {
            throw new \Exception("Cliente associado ao pagamento não encontrado");
        }

        $vencimento = $payment->cliente->vencimento 
            ? Carbon::parse($payment->cliente->vencimento)
            : Carbon::now();

        $paymentDate = Carbon::parse($payment->payment_date);

        if ($vencimento->lt($paymentDate)) {
            $vencimento = $paymentDate;
        }

        if ($payment->cliente->plano && $payment->cliente->plano->duracao) {
            $novoVencimento = $vencimento->addDays($payment->cliente->plano->duracao);
            
            $payment->cliente->vencimento = $novoVencimento;
            
            if (!$payment->cliente->save()) {
                throw new \Exception("Falha ao atualizar vencimento do cliente");
            }
        }
    }
    
    protected function calcularDiasRenovacao($plano, $paymentDate)
    {
        switch ($plano->tipo_duracao) {
            case 'meses':
                return $paymentDate->daysInMonth;
                
            case 'anos':
                return 365;
                
            case 'dias':
            default:
                return $plano->duracao_em_dias;
        }
    }
    
    private function notifyClientAndOwner($paymentRecord, $shouldProcessRenewal = false)
    {
        if (!$cliente = Cliente::find($paymentRecord->cliente_id)) return;
    
        $template = Template::where('finalidade', 'pagamentos')
            ->where('user_id', $cliente->user_id)
            ->first() ?? Template::where('finalidade', 'pagamentos')
            ->whereNull('user_id')
            ->firstOrFail();
    
        $statusPagamento = [
            'paid' => 'Pago', 'pending' => 'Pendente', 'failed' => 'Falhou',
            'in_process' => 'Em Processo', 'approved' => 'Aprovado'
        ][$paymentRecord->status] ?? $paymentRecord->status ?? 'Status do Pagamento';
    
        $company = CompanyDetail::where('user_id', $cliente->user_id)->first();
        $owner = User::find($cliente->user_id);
        $plano = Plano::find($paymentRecord->plano_id);
    
        $dadosCliente = [
            '{nome_cliente}' => $cliente->nome ?? 'Nome do Cliente',
            '{telefone_cliente}' => $cliente->whatsapp ?? '(11) 99999-9999',
            '{notas}' => $cliente->notas ?? 'Notas',
            '{vencimento_cliente}' => Carbon::parse($cliente->vencimento)->format('d/m/Y') ?? 'Vencimento do Cliente',
            '{plano_nome}' => $plano->nome ?? 'Nome do Plano',
            '{plano_valor}' => $plano->preco ?? 'Valor do Plano',
            '{data_atual}' => Carbon::now()->format('d/m/Y'),
            '{plano_link}' => $paymentRecord->link_pagamento ?? 'Link de Pagamento',
            '{text_expirate}' => $this->getTextExpirate($cliente->vencimento),
            '{saudacao}' => $this->getSaudacao(),
            '{payload_pix}' => $paymentRecord->payload_pix ?? 'Pix Copia e Cola',
            '{whatsap_empresa}' => $company->company_whatsapp ?? '{whatsapp_empresa}',
            '{status_pagamento}' => $statusPagamento,
            '{nome_empresa}' => $company->company_name ?? '{nome_empresa}',
            '{nome_dono}' => $owner->name ?? '{nome_dono}',
        ];
    
        $sendMessageController = new SendMessageController();
        $sendMessageController->sendMessageWithoutAuth(new Request([
            'phone' => $cliente->whatsapp,
            'message' => $this->substituirPlaceholders($template->conteudo, $dadosCliente),
            'user_id' => $cliente->user_id,
            'image' => $template->imagem ? config('app.url') . $template->imagem : null
        ]));
    
        if ($owner) {
            $mensagemDono = "Olá, tudo bem?\nO cliente {$cliente->nome} fez o pagamento do plano *{$plano->nome}*.\n"
                . "No valor de: R$ {$plano->preco}.\nData do Pagamento: " . Carbon::now()->format('d/m/Y') . ".\n"
                . "Nova data de vencimento: " . Carbon::parse($cliente->vencimento)->format('d/m/Y') . ".\n\n";
    
            if ($cliente->sync_qpanel == 1 && $shouldProcessRenewal && $paymentRecord->status === 'approved') {
                $info = $this->obterCreditosPlanoQPanel($cliente->plano_qpanel);
                $resultado = $this->renovarNoQPanel($cliente);
                $mensagemDono .= $resultado['success'] 
                    ? "Cliente renovado no Qpanel. Créditos deduzidos: {$info['credits']}." 
                    : (str_contains($resultado['message'] ?? '', 'You don\'t have enough credits')
                        ? "Painel sem créditos suficientes para renovação automática."
                        : "Erro ao renovar: {$resultado['message']}");
            }
    
            $sendMessageController->sendMessageWithoutAuth(new Request([
                'phone' => $owner->whatsapp,
                'message' => $mensagemDono,
                'user_id' => $owner->id
            ]));
        }
    }
    
    private function renovarNoQPanel($cliente)
    {
        if ($cliente->sync_qpanel != 1 || empty($cliente->iptv_nome) || empty($cliente->plano_qpanel)) {
            return ['success' => false, 'message' => 'Cliente não configurado para sincronização com QPanel'];
        }
    
        $dono = User::find($cliente->user_id);
        if (!$dono || empty($dono->id_qpanel)) {
            return ['success' => false, 'message' => 'Dono do cliente não configurado no QPanel'];
        }
    
        try {
            // Busca as credenciais do QPanel do admin (user_id = 1)
            $companyDetails = CompanyDetail::where('user_id', 1)->first();
            
            if (!$companyDetails || !$companyDetails->qpanel_api_url || !$companyDetails->qpanel_api_key) {
                Log::error('Credenciais do QPanel não configuradas no sistema');
                return ['success' => false, 'message' => 'Configurações do QPanel não encontradas'];
            }
    
            $curl = curl_init();
    
            $postData = [
                'userId' => $dono->id_qpanel,
                'username' => $cliente->iptv_nome,
                'packageId' => $cliente->plano_qpanel
            ];
    
            // Monta a URL completa
            $urlCompleta = rtrim($companyDetails->qpanel_api_url, '/') . '/api/webhook/customer/renew';
    
            curl_setopt_array($curl, [
                CURLOPT_URL => $urlCompleta,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $companyDetails->qpanel_api_key
                ],
            ]);
    
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
    
            $responseData = json_decode($response, true);
    
            if ($httpCode !== 200) {
                Log::error('Falha ao renovar no QPanel', [
                    'cliente_id' => $cliente->id,
                    'response' => $response,
                    'http_code' => $httpCode,
                    'api_url' => $urlCompleta
                ]);
                return ['success' => false, 'message' => $responseData['message'] ?? 'Erro ao renovar no QPanel'];
            }
    
            return ['success' => true, 'message' => 'Renovação no QPanel realizada com sucesso'];
    
        } catch (\Exception $e) {
            Log::error('Exceção ao renovar no QPanel', [
                'cliente_id' => $cliente->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function obterCreditosPlanoQPanel($planoId)
    {
        try {
            // Busca as credenciais do QPanel do admin (user_id = 1)
            $companyDetails = CompanyDetail::where('user_id', 1)->first();
            
            if (!$companyDetails || !$companyDetails->qpanel_api_url || !$companyDetails->qpanel_api_key) {
                Log::error('Credenciais do QPanel não configuradas no sistema');
                return ['success' => false, 'credits' => 1];
            }
    
            $curl = curl_init();
    
            // Monta a URL completa
            $urlCompleta = rtrim($companyDetails->qpanel_api_url, '/') . '/api/webhook/package';
    
            curl_setopt_array($curl, [
                CURLOPT_URL => $urlCompleta,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $companyDetails->qpanel_api_key
                ],
            ]);
    
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
    
            if ($httpCode !== 200) {
                Log::error('Falha ao obter créditos do plano QPanel', [
                    'plano_id' => $planoId,
                    'http_code' => $httpCode,
                    'api_url' => $urlCompleta
                ]);
                return ['success' => false, 'credits' => 1];
            }
    
            $responseData = json_decode($response, true);
            $planos = $responseData['data'] ?? [];
    
            foreach ($planos as $plano) {
                if ($plano['id'] === $planoId) {
                    return ['success' => true, 'credits' => $plano['credits'] ?? 1];
                }
            }
    
            return ['success' => false, 'credits' => 1];
    
        } catch (\Exception $e) {
            Log::error('Erro ao obter créditos do plano QPanel', [
                'plano_id' => $planoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'credits' => 1];
        }
    }

    private function getTextExpirate($vencimento)
    {

      $dataVencimento = Carbon::parse($vencimento);
      $dataAtual = Carbon::now();
      $intervalo = $dataAtual->diff($dataVencimento);

      if ($intervalo->invert) {
          return 'expirou há ' . $intervalo->days . ' dias';
      } elseif ($intervalo->days == 0) {
          return 'expira hoje';
      } else {
          return 'expira em ' . $intervalo->days . ' dias';
      }
      
    }

    private function getSaudacao()
    {
      $hora = date('H');
      if ($hora < 12) {
          return 'Bom dia!';
      } elseif ($hora < 18) {
          return 'Boa tarde!';
      } else {
          return 'Boa noite!';
      }
    }

    private function substituirPlaceholders($conteudo, $dados)
    {
      foreach ($dados as $placeholder => $valor) {
          $conteudo = str_replace($placeholder, $valor, $conteudo);
      }
      return $conteudo;
    }

    private function sendMessage($phone, $message, $user_id, $image = null)
    {
        $sendMessageController = new SendMessageController();
        
        $requestData = [
            'phone' => $phone,
            'message' => $message,
            'user_id' => $user_id,
        ];
        
        if ($image) {

            $caminhoImagem = ltrim($image, '/');

            $imagemUrl = rtrim(env('APP_URL'), '/') . '/' . $caminhoImagem;
            
            if (filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
                $requestData['image'] = $imagemUrl;
                Log::info('Enviando mensagem com imagem: ' . $imagemUrl);
            } else {
                Log::warning('URL da imagem inválida: ' . $imagemUrl);
            }
        }
        
        $request = new Request($requestData);
        $sendMessageController->sendMessageWithoutAuth($request);
    }
}