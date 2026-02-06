<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Template;
use App\Models\ScheduleSetting;
use App\Models\Indicacoes;
use App\Models\PlanoRenovacao;
use App\Models\CompanyDetail;
use Carbon\Carbon;
use App\Http\Controllers\SendMessageController;
use Illuminate\Support\Facades\Log;

class RegisterBasic extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('content.authentications.auth-register-basic', ['pageConfigs' => $pageConfigs]);
    }

    public function register(Request $request)
    {
        Log::info('Requisição de registro recebida em RegisterBasic.', ['request_data' => $request->all()]);

        // Validação dos dados
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:users,name',
            'whatsapp' => 'required|string|max:15|unique:users,whatsapp',
            'password' => 'required|string|min:8',
            'terms' => 'accepted',
            'ref' => 'nullable|string', // O campo 'ref' é opcional
        ], [
            'name.required' => 'O campo nome é obrigatório.',
            'name.unique' => 'Este nome já está em uso.',
            'whatsapp.required' => 'O campo WhatsApp é obrigatório.',
            'whatsapp.max' => 'O campo WhatsApp não pode ter mais que 15 caracteres.',
            'whatsapp.unique' => 'Este WhatsApp já está em uso.',
            'password.required' => 'O campo senha é obrigatório.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'terms.accepted' => 'Você deve aceitar os termos e condições.',
        ]);

        if ($validator->fails()) {
            Log::warning('Falha na validação do registro.', ['errors' => $validator->errors()->all()]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Buscar o plano Essencial na tabela planos_renovacao
        $planoEssencial = PlanoRenovacao::where('nome', 'Essencial')->first();

        // Verificar se o plano Essencial foi encontrado
        if (!$planoEssencial) {
            Log::error('Plano Essencial não encontrado durante o registro.', ['request_data' => $request->all()]);
            return redirect()->back()->with('error', 'Plano Essencial não encontrado.')->withInput();
        }

        $referredByUserId = null;
        $revendedorRoleId = 4; // ID para o papel 'revendedor'
        $masterRevendedorRoleId = 2; // ID para o papel 'master revendedor' - AJUSTE ESTE ID SE NECESSÁRIO!

        // Lógica para determinar a role_id e o user_id (referenciador)
        // Se há um 'ref' válido, é um revendedor indicado.
        if ($request->has('ref') && !empty($request->ref)) {
            $referrerId = (int) $request->input('ref');
            $referrer = User::where('id', $referrerId)->first();

            if ($referrer) {
                $referredByUserId = $referrer->id;
                $assignedRoleId = $revendedorRoleId; // Se veio por indicação e ref é válida, é revendedor
                Log::info('Usuário indicado por.', ['referrer_id' => $referredByUserId, 'referrer_role_id' => $referrer->role_id, 'referrer_role_name' => $referrer->role->name ?? 'N/A']);
            } else {
                Log::warning('ID de referência inválido ou não encontrado. Usuário será registrado como Master Revendedor (sem indicação válida).', ['ref_id' => $referrerId]);
                $assignedRoleId = $masterRevendedorRoleId; // Se a ref é inválida, mas o campo foi enviado, tratar como master revendedor
            }
        } else {
            // Se não há 'ref' no request, o usuário é um Master Revendedor
            $assignedRoleId = $masterRevendedorRoleId;
            Log::info('Usuário registrado sem indicação, atribuindo papel Master Revendedor.', ['request_data' => $request->all()]);
        }

        // Criação do usuário
        $user = User::create([
            'name' => $request->name,
            'whatsapp' => $request->whatsapp,
            'password' => Hash::make($request->password),
            'role_id' => $assignedRoleId, // <-- Role definida dinamicamente
            'trial_ends_at' => Carbon::now()->addDays(7),
            'status' => 'ativo',
            'plano_id' => $planoEssencial->id,
            'limite' => $planoEssencial->limite,
            'creditos' => 0,
            'profile_photo_url' => '/assets/img/avatars/14.png',
            'user_id' => $referredByUserId, // Atribui o ID do revendedor que indicou (o "DONO")
        ]);
        
        // ===================================================================
        // ============= CRIAÇÃO DE TEMPLATES E HORÁRIOS PADRÃO ==============
        // ===================================================================

        $conteudoCobranca = "👋 Olá *{nome_cliente}*, Tudo bem ?{saudacao}\n\nPassando para informar que *{text_expirate}*.\n\n✅ Plano: *{plano_valor}*\n⏳Vencimento: *{vencimento_cliente}*\n\nNão fique sem assistir, vamos renovar ?\nSegue Link de Pagamento: \nCartão de crédito\n\n👋{plano_link}\n\n🔑 PIX Direto\n❖ seu pix aqui\n\nAtt:*{nome_empresa}*";
        $conteudoPagamentoAprovado = "👋 Olá *{nome_cliente}*, Tudo bem?{saudacao}\n\nEsta é a confirmação que sua assinatura foi renovada com sucesso !\n\nSegue dados da assinatura !\n\n✅ Plano: *{plano_valor}*\n\n⏳Próximo Vencimento *{vencimento_cliente}* \n\n🤝Muito obrigado pela preferencia!";

        // Lista completa de finalidades para criar TEMPLATES
        $finalidadesParaTemplates = [
            'cobranca_1_dia_futuro', 'cobranca_2_dias_futuro', 'cobranca_3_dias_futuro', 'cobranca_5_dias_futuro', 'cobranca_7_dias_futuro',
            'cobranca_hoje',
            'cobranca_1_dia_atras', 'cobranca_2_dias_atras', 'cobranca_3_dias_atras', 'cobranca_5_dias_atras', 'cobranca_7_dias_atras',
            'pagamentos',
            'cobranca_manual' // Adicionado conforme solicitado
        ];

        foreach ($finalidadesParaTemplates as $finalidade) {
            // Define o conteúdo correto para cada tipo de template
            $conteudo = $conteudoCobranca;
            if ($finalidade === 'pagamentos') {
                $conteudo = $conteudoPagamentoAprovado;
            }

            // 1. Cria o Template
            Template::create([
                'user_id' => $user->id,
                'nome' => 'Padrão ' . ucwords(str_replace('_', ' ', $finalidade)), // Nome dinâmico e amigável
                'finalidade' => $finalidade,
                'conteudo' => $conteudo,
                'tipo_mensagem' => 'texto',
            ]);

            // 2. CONDIÇÃO: Cria horário apenas se não for uma ação manual ou de confirmação
            if (!in_array($finalidade, ['pagamentos', 'cobranca_manual'])) {
                ScheduleSetting::create([
                    'user_id' => $user->id,
                    'finalidade' => $finalidade,
                    'command' => $finalidade,
                    'execution_time' => '08:00:00',
                    'status' => true,
                ]);
            }
        }
        
        Log::info('Templates e agendamentos padrão criados para o novo usuário.', ['user_id' => $user->id]);
        
        // ===================================================================
        // ======================= FIM DO BLOCO DE CRIAÇÃO ===================
        // ===================================================================

        Log::info('Novo usuário registrado.', ['user_id' => $user->id, 'role_id' => $user->role_id, 'assigned_user_id_pai' => $user->user_id]);

        // Criar registro na tabela company_details
        CompanyDetail::create([
            'user_id' => $user->id,
            'company_name' => $user->name,
            'company_whatsapp' => $request->whatsapp,
            'company_logo' => '/assets/img/logos/ico%20ds.png',
            'company_logo_dark' => '/assets/img/logos/logo-dark.png',
            'company_logo_light' => '/assets/img/logos/logo-light.png',
            'favicon' => '/assets/img/favicons/favico.png',
        ]);

        // Criar uma entrada na tabela indicacoes se houver uma referência VÁLIDA
        if ($referredByUserId) {
            Indicacoes::create([
                'user_id' => $referredByUserId,
                'referred_id' => $user->id,
                'status' => 'pending',
                'ganhos' => 5.00,
            ]);
            Log::info('Indicação registrada na tabela indicacoes.', ['referrer_id' => $referredByUserId, 'referred_user_id' => $user->id]);
        }

        $this->sendConfirmationMessage(
            $user->whatsapp,
            $user->name,
            $request->whatsapp,
            $request->password,
            route('auth-login-basic')
        );

        auth()->login($user);

        return redirect()->route('app-ecommerce-dashboard');
    }

    private function sendConfirmationMessage($phone, $name, $whatsapp, $password, $loginUrl)
    {
        $adminUser = User::where('role_id', 1)->first();

        if ($adminUser) {
            $message = "*Olá $name*,\n\n" .
                        "✨ *CADASTRO CONFIRMADO!* ✨\n\n" .
                        "📱 *Usuario:* $whatsapp\n" .
                        "🔐 *Senha:* $password\n" .
                        "⏳ *Teste:* 7 dias grátis\n\n" .
                        "🔗 *Acesse Seu Painel:*\n" .
                        "👉 " . str_replace('https://', '', $loginUrl);

            $this->sendMessage($phone, $message, $adminUser->id);
        }
    }

    private function sendMessage($phone, $message, $user_id)
    {
        $sendMessageController = new SendMessageController();
        $request = new Request([
            'phone' => $phone,
            'message' => $message,
            'user_id' => $user_id,
        ]);
        $sendMessageController->sendMessageWithoutAuth($request);
    }
}