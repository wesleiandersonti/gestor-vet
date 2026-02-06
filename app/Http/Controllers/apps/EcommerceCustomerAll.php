<?php
namespace App\Http\Controllers\apps;
require_once '../vendor/autoload.php';
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Servidor;
use Illuminate\Support\Facades\Auth;
use App\Models\Plano;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\ConexaoController;
use App\Models\Template;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use App\Models\Pagamento;
use App\Models\CompanyDetail;
use App\Models\Conexao;
use App\Models\PlanoRenovacao;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
class EcommerceCustomerAll extends Controller
{
    private $apikey;
    private $urlapi;
    public function __construct()
    {
        // Aplicar middleware de autenticação
        $this->middleware('auth');
    }
    public function index()
    {
        Log::info('Acessando a página de listagem de clientes.');
        $user = Auth::user();
        $planos = Plano::all();
        $current_plan_id = $user->plano_id;
        // Adicione a linha abaixo para obter os clientes
        $clientes = Cliente::all();
        $planos_revenda = PlanoRenovacao::all();
        $current_plan_id = $user->plano_id;
        $loginUrl = route('client.login.form');
        // Acessar dados da sessão
        $sessionData = Session::all();
        return view('content.apps.app-ecommerce-customer-all', compact('planos', 'current_plan_id', 'clientes', 'planos_revenda', 'current_plan_id', 'sessionData', 'user', 'loginUrl'));
    }
    
    public function store(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'iptv_nome' => 'nullable|string|max:255',
                'iptv_senha' => 'nullable|string|max:255',
                'whatsapp' => 'required|string|max:20',
                'password' => 'required|string|min:6',
                'vencimento' => 'required|date|after_or_equal:today',
                'servidor_id' => 'required|exists:servidores,id',
                'notificacoes' => 'required|boolean',
                'sync_qpanel' => 'required|boolean',
                'plano_id' => 'nullable|exists:planos,id',
                'numero_de_telas' => 'required|integer|min:1|max:10',
                'notas' => 'nullable|string|max:500',
            ]);
            $plainPassword = $validated['password']; // <- guarda a senha em texto para o template
    
            $user = Auth::user();
            
            // Verificação de limite do plano
            if ($user->role_id != 1) {
                $planoUsuario = PlanoRenovacao::find($user->plano_id);
                if ($planoUsuario && $planoUsuario->limite > 0 && $user->limite <= 0) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Você atingiu o limite máximo de clientes permitidos pelo seu plano.');
                }
            }
    
            $qpanelCliente = null;
            $planoId = $validated['plano_id'] ?? null;
    
            if ($validated['sync_qpanel']) {
                if (empty($validated['iptv_nome'])) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Para sincronizar com QPanel, o nome de usuário IPTV é obrigatório.');
                }
    
                // Verifica credenciais do admin
                $adminUser = User::where('role_id', 1)->first();
                $adminCredentials = $adminUser ? CompanyDetail::where('user_id', $adminUser->id)->first() : null;
                
                if (!$adminCredentials || empty($adminCredentials->qpanel_api_url) || empty($adminCredentials->qpanel_api_key)) {
                    Log::error('Credenciais do QPanel não configuradas para o admin', [
                        'admin_id' => $adminUser->id ?? null
                    ]);
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Credenciais do QPanel não configuradas no sistema. Entre em contato com o administrador.');
                }
    
                $qpanelCliente = $this->buscarClienteQPanel($validated['iptv_nome']);
                
                if (!$qpanelCliente) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Cliente não encontrado no QPanel. Verifique o nome de usuário IPTV.');
                }
                
                if (isset($qpanelCliente['is_trial']) && $qpanelCliente['is_trial'] === "YES") {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Você inseriu um cliente com plano de teste, apenas clientes ativos ou expirados podem ser cadastrados na plataforma.');
                }
    
                if ($user->id_qpanel && $qpanelCliente['user_id'] != $user->id_qpanel) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'O cliente IPTV que você está tentando adicionar não pertence ao seu usuário QPanel.');
                }
    
                if (Cliente::where('iptv_nome', $validated['iptv_nome'])->exists()) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Já existe um cliente cadastrado com este nome de usuário IPTV.');
                }
    
                if (empty($validated['iptv_senha']) && !empty($qpanelCliente['password'])) {
                    $validated['iptv_senha'] = $qpanelCliente['password'];
                }
    
                // Processamento do plano
                if (isset($qpanelCliente['package_id'])) {
                    $planoExistente = Plano::where('id_qpanel', $qpanelCliente['package_id'])
                                        ->where('user_id', $user->id)
                                        ->first();
                    
                    if ($planoExistente) {
                        $planoId = $planoExistente->id;
                    } else {
                        $qpanelPlanos = $this->buscarPlanosQPanel();
                        
                        if ($qpanelPlanos) {
                            $planoEncontrado = collect($qpanelPlanos['data'])->firstWhere('id', $qpanelCliente['package_id']);
                            
                            if ($planoEncontrado) {
                                $duracaoDias = $this->calcularDuracaoDias(
                                    $planoEncontrado['duration'], 
                                    $planoEncontrado['duration_in']
                                );
                                
                                $plano = Plano::create([
                                    'user_id' => $user->id,
                                    'nome' => $planoEncontrado['name'],
                                    'preco' => $planoEncontrado['plan_price'] / 100,
                                    'duracao' => $duracaoDias,
                                    'id_qpanel' => $planoEncontrado['id']
                                ]);
                                
                                $planoId = $plano->id;
                            }
                        }
                    }
                }
            }
    
            $validated['plano_id'] = $planoId;
    
            $clienteData = [
                'user_id' => $user->id,
                'nome' => $validated['nome'],
                'iptv_nome' => $validated['iptv_nome'] ?? null,
                'iptv_senha' => $validated['iptv_senha'] ?? null,
                'whatsapp' => $validated['whatsapp'],
                'password' => bcrypt($validated['password']),
                'vencimento' => $validated['vencimento'],
                'servidor_id' => $validated['servidor_id'],
                'notificacoes' => $validated['notificacoes'],
                'sync_qpanel' => $validated['sync_qpanel'],
                'plano_id' => $validated['plano_id'],
                'numero_de_telas' => $validated['numero_de_telas'],
                'notas' => $validated['notas'] ?? null,
                'role_id' => 3,
                'qpanel_id' => $validated['sync_qpanel'] ? ($qpanelCliente['id'] ?? null) : null,
            ];
    
            Log::debug('Criando cliente com dados:', $clienteData);
    
            $cliente = Cliente::create($clienteData);
    
            if ($validated['sync_qpanel'] && !empty($validated['iptv_nome'])) {
                $this->sincronizarClienteQPanel($cliente);
            }
    
            if (isset($planoUsuario) && $planoUsuario->limite > 0) {
                $user->decrement('limite');
            }
    
            DB::commit();
            
            // === Enviar mensagem de boas-vindas automaticamente ===
try {
    $this->sendWelcomeOnCreate($cliente, $plainPassword); // <- passa a senha em texto
} catch (\Throwable $e) {
    \Log::error('Falha ao enviar boas-vindas no cadastro: '.$e->getMessage(), ['cliente_id' => $cliente->id]);
}
    
            return redirect()->route('app-ecommerce-customer-all')
                ->with('success', 'Cliente cadastrado com sucesso.' . 
                      ($validated['sync_qpanel'] ? ' Sincronizado com QPanel.' : ''));
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', 'Erro de validação: ' . $e->getMessage());
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao cadastrar cliente: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao cadastrar cliente: ' . $e->getMessage());
        }
    }

    private function calcularDuracaoDias($duracao, $duracaoEm)
    {
        switch ($duracaoEm) {
            case 'MONTHS':
                return $duracao * 31;
            case 'YEARS':
                return $duracao * 365;
            case 'HOURS':
                return ceil($duracao / 24);
            default:
                return $duracao;
        }
    }
    
    private function buscarPlanosQPanel()
    {
        try {
            // 1. Encontrar o usuário admin pelo role_id = 1
            $adminUser = User::where('role_id', 1)->first();
            
            if (!$adminUser) {
                Log::error('Usuário admin não encontrado no sistema');
                return null;
            }
            
            // 2. Buscar as credenciais QPanel do admin
            $adminCredentials = CompanyDetail::where('user_id', $adminUser->id)->first();
            
            if (!$adminCredentials) {
                Log::error('Configurações da empresa admin não encontradas');
                return null;
            }
            
            if (empty($adminCredentials->qpanel_api_url)) {
                Log::error('URL do QPanel não configurada no admin', [
                    'admin_id' => $adminUser->id
                ]);
                return null;
            }
            
            if (empty($adminCredentials->qpanel_api_key)) {
                Log::error('Chave da API do QPanel não configurada no admin', [
                    'admin_id' => $adminUser->id
                ]);
                return null;
            }

            $curl = curl_init();
            $urlCompleta = rtrim($adminCredentials->qpanel_api_url, '/') . '/api/webhook/package';

            Log::debug('Tentando buscar planos no QPanel', [
                'admin_id' => $adminUser->id,
                'url' => $urlCompleta,
                'api_key' => substr($adminCredentials->qpanel_api_key, 0, 5) . '...' // Mostra parcialmente por segurança
            ]);

            curl_setopt_array($curl, [
                CURLOPT_URL => $urlCompleta,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $adminCredentials->qpanel_api_key,
                    'Accept: application/json',
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                throw new \Exception('Erro cURL: ' . $curlError);
            }

            if ($httpCode !== 200) {
                Log::error('Falha ao buscar planos do QPanel', [
                    'http_code' => $httpCode,
                    'response' => $response,
                    'api_url' => $urlCompleta
                ]);
                return null;
            }

            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Resposta JSON inválida: ' . json_last_error_msg());
            }

            Log::debug('Resposta completa da API QPanel para planos', [
                'response' => $data
            ]);
            
            return $data ?? null;
            
        } catch (\Exception $e) {
            Log::error('Erro na requisição ao QPanel para buscar planos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function sincronizarClienteQPanel(Cliente $cliente)
    {
        try {
            $dadosQPanel = $this->buscarDadosQPanel($cliente->iptv_nome);
            
            if (!$dadosQPanel) {
                Log::error('Dados do QPanel não encontrados para o cliente', [
                    'cliente_id' => $cliente->id,
                    'iptv_nome' => $cliente->iptv_nome
                ]);
                return false;
            }
    
            $atualizacoes = [];
            
            if (!empty($dadosQPanel['expires_at_tz'])) {
                $vencimento = Carbon::parse($dadosQPanel['expires_at_tz'])->format('Y-m-d');
                if ($cliente->vencimento != $vencimento) {
                    $atualizacoes['vencimento'] = $vencimento;
                }
            }
    
            if (!empty($dadosQPanel['package_id'])) {
                $atualizacoes['plano_qpanel'] = $dadosQPanel['package_id'];
            }
    
            if (!empty($dadosQPanel['password'])) {
                $atualizacoes['iptv_senha'] = $dadosQPanel['password'];
            }
    
            if (!empty($atualizacoes)) {
                $cliente->update($atualizacoes);
            }
    
            return true;
    
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar cliente com QPanel', [
                'cliente_id' => $cliente->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    private function buscarDadosQPanel($username)
    {
        try {
            // 1. Encontrar o usuário admin pelo role_id = 1
            $adminUser = User::where('role_id', 1)->first();
            
            if (!$adminUser) {
                Log::error('Usuário admin não encontrado no sistema', ['username' => $username]);
                return null;
            }
            
            // 2. Buscar as credenciais QPanel do admin
            $adminCredentials = CompanyDetail::where('user_id', $adminUser->id)->first();
            
            if (!$adminCredentials) {
                Log::error('Configurações da empresa admin não encontradas', ['username' => $username]);
                return null;
            }
            
            if (empty($adminCredentials->qpanel_api_url)) {
                Log::error('URL do QPanel não configurada no admin', [
                    'username' => $username,
                    'admin_id' => $adminUser->id
                ]);
                return null;
            }
            
            if (empty($adminCredentials->qpanel_api_key)) {
                Log::error('Chave da API do QPanel não configurada no admin', [
                    'username' => $username,
                    'admin_id' => $adminUser->id
                ]);
                return null;
            }

            $curl = curl_init();
            $urlCompleta = rtrim($adminCredentials->qpanel_api_url, '/') . '/api/webhook/customer?username=' . urlencode($username);

            Log::debug('Tentando buscar dados no QPanel', [
                'username' => $username,
                'admin_id' => $adminUser->id,
                'url' => $urlCompleta,
                'api_key' => substr($adminCredentials->qpanel_api_key, 0, 5) . '...' // Mostra parcialmente por segurança
            ]);

            curl_setopt_array($curl, [
                CURLOPT_URL => $urlCompleta,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $adminCredentials->qpanel_api_key,
                    'Accept: application/json',
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                throw new \Exception('Erro cURL: ' . $curlError);
            }

            if ($httpCode !== 200) {
                Log::error('Falha ao buscar dados do QPanel', [
                    'username' => $username,
                    'http_code' => $httpCode,
                    'response' => $response,
                    'api_url' => $urlCompleta
                ]);
                return null;
            }

            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Resposta JSON inválida: ' . json_last_error_msg());
            }

            Log::debug('Resposta completa da API QPanel', [
                'username' => $username,
                'response' => $data
            ]);
            
            return $data['data'] ?? null;
            
        } catch (\Exception $e) {
            Log::error('Erro na requisição ao QPanel', [
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    private function buscarClienteQPanel($username)
    {
        try {
            // 1. Encontrar o usuário admin pelo role_id
            $adminUser = User::where('role_id', 1)->first();
            
            if (!$adminUser) {
                Log::error('Usuário admin não encontrado no sistema', ['username' => $username]);
                return null;
            }
            
            // 2. Buscar as credenciais QPanel do admin
            $adminCredentials = CompanyDetail::where('user_id', $adminUser->id)->first();
            
            if (!$adminCredentials) {
                Log::error('Configurações da empresa admin não encontradas', ['username' => $username]);
                return null;
            }
            
            if (empty($adminCredentials->qpanel_api_url)) {
                Log::error('URL do QPanel não configurada no admin', [
                    'username' => $username,
                    'admin_id' => $adminUser->id
                ]);
                return null;
            }
            
            if (empty($adminCredentials->qpanel_api_key)) {
                Log::error('Chave da API do QPanel não configurada no admin', [
                    'username' => $username,
                    'admin_id' => $adminUser->id
                ]);
                return null;
            }
    
            $curl = curl_init();
            $urlCompleta = rtrim($adminCredentials->qpanel_api_url, '/') . '/api/webhook/customer?username=' . urlencode($username);
    
            Log::debug('Tentando buscar cliente no QPanel', [
                'admin_id' => $adminUser->id,
                'url' => $urlCompleta,
                'api_key' => substr($adminCredentials->qpanel_api_key, 0, 5) . '...' // Mostra parcialmente por segurança
            ]);
    
            curl_setopt_array($curl, [
                CURLOPT_URL => $urlCompleta,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $adminCredentials->qpanel_api_key,
                    'Accept: application/json',
                    'Content-Type: application/json'
                ],
            ]);
    
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
    
            if ($curlError) {
                throw new \Exception('Erro cURL: ' . $curlError);
            }
    
            if ($httpCode !== 200) {
                Log::error('Falha ao buscar dados do QPanel', [
                    'username' => $username,
                    'http_code' => $httpCode,
                    'response' => $response,
                    'api_url' => $urlCompleta
                ]);
                return null;
            }
    
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Resposta JSON inválida: ' . json_last_error_msg());
            }
    
            Log::debug('Resposta completa da API QPanel para cliente', [
                'username' => $username,
                'response' => $data
            ]);
            
            return $data['data'] ?? null;
            
        } catch (\Exception $e) {
            Log::error('Erro na requisição ao QPanel para cliente', [
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);
        try {
            $request->validate([
                'nome' => 'required|string|unique:clientes,nome,' . $cliente->id,
                'iptv_nome' => 'nullable|string|max:255',
                'iptv_senha' => 'nullable|string|max:255',
                'whatsapp' => 'required|string',
                'password' => 'nullable|string|min:6', // Alterado para nullable
                'vencimento' => 'required|date',
                'servidor_id' => 'required|exists:servidores,id',
                'notificacoes' => 'required|boolean',
                'sync_qpanel' => 'required|boolean',
                'plano_id' => 'nullable|exists:planos,id',
                'numero_de_telas' => 'required|integer',
                'notas' => 'nullable|string',
            ]);
    
            $dadosAtualizados = [
                'nome' => $request->nome,
                'iptv_nome' => $request->iptv_nome,
                'iptv_senha' => $request->iptv_senha,
                'whatsapp' => $request->whatsapp,
                'vencimento' => $request->vencimento,
                'servidor_id' => $request->servidor_id,
                'notificacoes' => $request->notificacoes,
                'sync_qpanel' => $request['sync_qpanel'],
                'plano_id' => $request->plano_id,
                'numero_de_telas' => $request->numero_de_telas,
                'notas' => $request->notas,
            ];
    
            // Aplica hash apenas se a senha foi alterada
            if ($request->filled('password')) {
                $dadosAtualizados['password'] = Hash::make($request->password);
            }
    
            $cliente->update($dadosAtualizados);
    
            return redirect()->route('app-ecommerce-customer-all')->with('success', 'Cliente atualizado com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('app-ecommerce-customer-all')->with('error', 'Nome ou WhatsApp já estão em uso.');
        }
    }
    
    public function list(Request $request)
    {
        Log::info('Acessando a listagem de clientes com paginação e busca.');
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $userRole = $user->role->name;
                $search = $request->input('search');
                $sort = $request->input('sort', 'id');
                $order = $request->input('order', 'DESC');
                $vencimento = $request->input('vencimento');
                // Mostrar apenas os clientes do usuário logado
                $clientes = Cliente::where('user_id', $user->id)->with('plano', 'servidor');
                $planos = Plano::where('user_id', $user->id)->get();
                $servidores = Servidor::where('user_id', $user->id)->get();
                $planos_revenda = PlanoRenovacao::all();
                $current_plan_id = $user->plano_id;
                if ($search) {
                    $clientes = $clientes->where('nome', 'like', '%' . $search . '%');
                }
                if ($vencimento) {
                    Log::info('Aplicando filtro de vencimento', ['vencimento' => $vencimento]);
                    switch ($vencimento) {
                        case 'vencido':
                            $clientes = $clientes->whereDate('vencimento', '<', Carbon::today());
                            Log::info('Filtro aplicado: Vencido', ['query' => $clientes->toSql(), 'bindings' => $clientes->getBindings()]);
                            break;
                        case 'ainda_vai_vencer':
                            $clientes = $clientes->whereDate('vencimento', '>', Carbon::today());
                            Log::info('Filtro aplicado: Ainda vai vencer', ['query' => $clientes->toSql(), 'bindings' => $clientes->getBindings()]);
                            break;
                        case 'hoje':
                            $clientes = $clientes->whereDate('vencimento', Carbon::today());
                            Log::info('Filtro aplicado: Vence hoje', ['query' => $clientes->toSql(), 'bindings' => $clientes->getBindings()]);
                            break;
                        case 'todos':
                        default:
                            // Não aplica nenhum filtro de data
                            Log::info('Filtro aplicado: Todos', ['query' => $clientes->toSql(), 'bindings' => $clientes->getBindings()]);
                            break;
                    }
                }
                $totalClientes = $clientes->count();
                $canEdit = true; // Defina a lógica para verificar se o usuário pode editar
                $canDelete = true; // Defina a lógica para verificar se o usuário pode deletar
                $clientes = $clientes->orderBy($sort, $order)
                    ->paginate($request->input('limit', 10))
                    ->through(function ($cliente) use ($canEdit, $canDelete, $planos, $servidores) {
                        $actions = '<div class="gap-3 d-grid">
                                        <div class="row g-3">
                                            <div class="mb-2 col-6">
                                                <form action="' . route('app-ecommerce-customer-destroy', $cliente->id) . '" method="POST" style="display:inline;">
                                                    ' . csrf_field() . '
                                                    ' . method_field('DELETE') . '
                                                    <button type="submit" class="btn btn-sm btn-danger w-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Deletar">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="mb-2 col-6">
                                                <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#editClient' . $cliente->id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                            <div class="mt-2 col-6">
                                                <form action="' . route('app-ecommerce-customer-charge', $cliente->id) . '" method="POST" style="display:inline;">
                                                    ' . csrf_field() . '
                                                    ' . method_field('POST') . '
                                                    <button type="submit" class="btn btn-sm btn-warning w-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Cobrança Manual">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="mt-2 col-6">
                                                <a href="' . route('app-ecommerce-order-list', ['order_id' => $cliente->id]) . '" class="btn btn-sm btn-success w-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Detalhes da Cobrança">
                                                    <i class="fas fa-thumbs-up"></i>
                                                </a>
                                            </div>
                                            <div class="mt-2 col-6">
                                                <button class="btn btn-sm btn-info w-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Enviar Dados de Login" onclick="sendLoginDetails(\'' . $cliente->id . '\')">
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>';
                        $modal = '<div class="modal fade" id="editClient' . $cliente->id . '" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-md modal-simple modal-edit-client">
                                            <div class="p-3 modal-content p-md-5">
                                                <div class="modal-body">
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    <div class="mb-4 text-center">
                                                        <h3 class="mb-2">Editar Cliente</h3>
                                                        <p class="text-muted">Atualize os detalhes do cliente.</p>
                                                    </div>
                                                    <form id="editClientForm' . $cliente->id . '" class="row g-3" action="' . route('app-ecommerce-customer-update', $cliente->id) . '" method="POST">
                                                        ' . csrf_field() . '
                                                        ' . method_field('PUT') . '
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientNome' . $cliente->id . '">Nome</label>
                                                            <input type="text" id="editClientNome' . $cliente->id . '" name="nome" class="form-control" value="' . $cliente->nome . '" required />
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientPassword' . $cliente->id . '">Senha</label>
                                                            <div class="input-group">
                                                                <input type="password" id="editClientPassword' . $cliente->id . '" name="password" placeholder="Deixe em branco para manter a atual" class="form-control" />
                                                                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword(\'editClientPassword' . $cliente->id . '\')">
                                                                    <i class="fas fa-random"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility(\'editClientPassword' . $cliente->id . '\')">
                                                                    <i class="fas fa-eye" id="togglePasswordIcon' . $cliente->id . '"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientIPTVNome' . $cliente->id . '">Usuário IPTV</label>
                                                            <input type="text" id="editClientIPTVNome' . $cliente->id . '" name="iptv_nome" class="form-control" value="' . $cliente->iptv_nome . '" />
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientIPTVSenha' . $cliente->id . '">Senha IPTV</label>
                                                            <div class="input-group">
                                                                <input type="text" id="editClientIPTVSenha' . $cliente->id . '" name="iptv_senha" class="form-control" value="' . $cliente->iptv_senha . '" />
                                                                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword(\'editClientIPTVSenha' . $cliente->id . '\')">
                                                                    <i class="fas fa-random"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientWhatsApp' . $cliente->id . '">WhatsApp</label>
                                                            <input type="text" id="editClientWhatsApp' . $cliente->id . '" name="whatsapp" maxlength="15" class="form-control" value="' . $cliente->whatsapp . '" required oninput="mask(this, masktel)" />
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientVencimento' . $cliente->id . '">Vencimento</label>
                                                            <input type="date" id="editClientVencimento' . $cliente->id . '" name="vencimento" class="form-control" value="' . $cliente->vencimento . '" required />
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientServidor' . $cliente->id . '">Servidor</label>
                                                            <select id="editClientServidor' . $cliente->id . '" name="servidor_id" class="form-select" required>';
                        foreach ($servidores as $servidor) {
                            $modal .= '<option value="' . $servidor->id . '" ' . ($cliente->servidor_id == $servidor->id ? 'selected' : '') . '>' . $servidor->nome . '</option>';
                        }
                        $modal .= '</select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientNotificacoes' . $cliente->id . '">Notificações</label>
                                                            <select id="editClientNotificacoes' . $cliente->id . '" name="notificacoes" class="form-select" required>
                                                                <option value="1" ' . ($cliente->notificacoes ? 'selected' : '') . '>Sim</option>
                                                                <option value="0" ' . (!$cliente->notificacoes ? 'selected' : '') . '>Não</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientSyncQpanel' . $cliente->id . '">Sincronizar Qpanel</label>
                                                            <select id="editClientSyncQpanel' . $cliente->id . '" name="sync_qpanel" class="form-select" required>
                                                                <option value="1" ' . ($cliente->sync_qpanel ? 'selected' : '') . '>Sim</option>
                                                                <option value="0" ' . (!$cliente->sync_qpanel ? 'selected' : '') . '>Não</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientPlano' . $cliente->id . '">Plano</label>
                                                            <select id="editClientPlano' . $cliente->id . '" name="plano_id" class="form-select" required>';
                        foreach ($planos as $plano) {
                            $modal .= '<option value="' . $plano->id . '" ' . ($cliente->plano_id == $plano->id ? 'selected' : '') . '>' . $plano->nome . '</option>';
                        }
                        $modal .= '</select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientNumeroDeTelas' . $cliente->id . '">Telas</label>
                                                            <input type="number" id="editClientNumeroDeTelas' . $cliente->id . '" name="numero_de_telas" class="form-control" value="' . $cliente->numero_de_telas . '" required />
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label" for="editClientNotas' . $cliente->id . '">Notas</label>
                                                            <textarea id="editClientNotas' . $cliente->id . '" name="notas" class="form-control">' . $cliente->notas . '</textarea>
                                                        </div>
                                                        <div class="text-center col-12">
                                                            <button type="submit" class="btn btn-primary me-sm-3 me-1">Salvar</button>
                                                            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancelar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
                        // Calcular a diferença entre a data de vencimento e a data atual
                        $vencimento = Carbon::parse($cliente->vencimento);
                        $hoje = Carbon::today();
                        $diasDiferenca = $hoje->diffInDays($vencimento, false);
                        if ($diasDiferenca == 0) {
                            $vencimentoTexto = '<span class="badge bg-warning">Vence hoje</span>';
                        } elseif ($diasDiferenca == 1) {
                            $vencimentoTexto = '<span class="badge bg-info">Vence amanhã</span>';
                        } elseif ($diasDiferenca == -1) {
                            $vencimentoTexto = '<span class="badge bg-danger">Venceu ontem</span>';
                        } elseif ($diasDiferenca > 1) {
                            $vencimentoTexto = '<span class="badge bg-success">Vence em ' . $diasDiferenca . ' dias</span>';
                        } elseif ($diasDiferenca < -1) {
                            $vencimentoTexto = '<span class="badge bg-danger">Venceu há ' . abs($diasDiferenca) . ' dias</span>';
                        } else {
                            $vencimentoTexto = '<span class="badge bg-danger">Vencido</span>';
                        }
                        return [
                            'id' => $cliente->id,
                            'nome' => $cliente->nome,
                            'senha' => $cliente->senha,
                            'iptv_nome' => $cliente->iptv_nome,
                            'whatsapp' => $cliente->whatsapp,
                            'vencimento' => $vencimentoTexto,
                            'servidor' => $cliente->servidor ? $cliente->servidor->nome : 'N/A',
                            'mac' => $cliente->mac,
                            'notificacoes' => $cliente->notificacoes ? 'Sim' : 'Não',
                            'sync_qpanel' => $cliente->sync_qpanel ? 'Sim' : 'Não',
                            'plano' => $cliente->plano ? $cliente->plano->nome : 'N/A',
                            'valor' => $cliente->plano ? 'R$ ' . number_format($cliente->plano->preco, 2, ',', '.') : 'N/A',
                            'numero_de_telas' => $cliente->numero_de_telas,
                            'notas' => $cliente->notas,
                            'actions' => $actions . $modal
                        ];
                    });
                // Fetch user preferences for visible columns
                $userId = getAuthenticatedUser(true);
                $preferences = DB::table('user_client_preferences')
                    ->where('user_id', $userId)
                    ->where('table_name', 'clientes')
                    ->value('visible_columns');
                $visibleColumns = json_decode($preferences, true) ?: [
                    'id',
                    'nome',
                    'senha',
                    'iptv_nome',
                    'whatsapp',
                    'vencimento',
                    'servidor',
                    'mac',
                    'notificacoes',
                    'sync_qpanel',
                    'plano',
                    'valor',
                    'numero_de_telas',
                    'notas',
                    'actions'
                ];
                $filteredClientes = $clientes->map(function ($cliente) use ($visibleColumns) {
                    return array_filter($cliente, function ($key) use ($visibleColumns) {
                        return in_array($key, $visibleColumns);
                    }, ARRAY_FILTER_USE_KEY);
                });
                return response()->json([
                    'rows' => $filteredClientes,
                    'total' => $totalClientes,
                    'planos' => $planos,
                    'servidores' => $servidores,
                    'planos_revenda' => $planos_revenda,
                    'current_plan_id' => $current_plan_id,
                    'sessionData' => Session::all(),
                    'user' => $user,
                    'loginUrl' => route('client.login.form')
                ]);
            } else {
                // Usuário não está autenticado
                return response()->json(['error' => 'Usuário não autenticado'], 401);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao acessar a listagem de clientes: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao acessar a listagem de clientes'], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            $user = User::findOrFail($cliente->user_id);
            if ($user->limite !== -1) {
                $user->limite += 1;
                $user->save();
            }
            $pagamentosDeletados = Pagamento::where('cliente_id', $cliente->id)->delete();
            Log::info("Deletados $pagamentosDeletados pagamentos do cliente ID: $id");
            $companyDetail = CompanyDetail::where('user_id', $cliente->user_id)->first();
            if ($companyDetail && !$companyDetail->not_gateway) {
                $pagamentosPendentes = Pagamento::where('cliente_id', $cliente->id)
                    ->where('status', 'pending')
                    ->get();
                foreach ($pagamentosPendentes as $pagamento) {
                    try {
                        MercadoPagoConfig::setAccessToken($companyDetail->access_token);
                        $paymentClient = new PaymentClient();
                        $paymentClient->cancel($pagamento->mercado_pago_id);
                        Log::info("Pagamento MercadoPago cancelado: " . $pagamento->mercado_pago_id);
                    } catch (\Exception $e) {
                        Log::warning('Erro ao cancelar pagamento no MercadoPago: ' . $e->getMessage());
                    }
                }
            }
            $cliente->delete();
            return redirect()->route('app-ecommerce-customer-all')->with('success', 'Cliente e seus pagamentos associados foram deletados com sucesso.');
        } catch (\Exception $e) {
            Log::error('Erro ao deletar cliente: ' . $e->getMessage());
            return redirect()->route('app-ecommerce-customer-all')->with('error', 'Ocorreu um erro ao deletar o cliente: ' . $e->getMessage());
        }
    }
    
    public function destroy_multiple(Request $request)
    {
        Log::info('Tentando excluir múltiplos clientes.', ['ids' => $request->input('ids')]);
        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:clientes,id'
        ]);
        
        $ids = $validatedData['ids'];
        
        foreach ($ids as $id) {
            try {
                $cliente = Cliente::findOrFail($id);
                $user = User::findOrFail($cliente->user_id);
                
                if ($user->limite !== -1) {
                    $user->limite += 1;
                    $user->save();
                }
                
                // Deleta todos os pagamentos do cliente
                $pagamentosDeletados = Pagamento::where('cliente_id', $cliente->id)->delete();
                Log::info("Deletados $pagamentosDeletados pagamentos do cliente ID: $id");
                
                // Verifica e cancela pagamentos pendentes no MercadoPago
                $companyDetail = CompanyDetail::where('user_id', $cliente->user_id)->first();
                if ($companyDetail && !$companyDetail->not_gateway) {
                    $pagamentosPendentes = Pagamento::where('cliente_id', $cliente->id)
                        ->where('status', 'pending')
                        ->get();
                        
                    foreach ($pagamentosPendentes as $pagamento) {
                        try {
                            MercadoPagoConfig::setAccessToken($companyDetail->access_token);
                            $paymentClient = new PaymentClient();
                            $paymentClient->cancel($pagamento->mercado_pago_id);
                            Log::info("Pagamento MercadoPago cancelado: " . $pagamento->mercado_pago_id);
                        } catch (\Exception $e) {
                            Log::warning('Erro ao cancelar pagamento no MercadoPago: ' . $e->getMessage());
                        }
                    }
                }
                
                $cliente->delete();
                
            } catch (\Exception $e) {
                Log::error('Erro ao deletar cliente ID ' . $id . ': ' . $e->getMessage());
                // Continua para o próximo ID mesmo se houver erro em um
            }
        }
        
        return response()->json(['error' => false, 'message' => 'Clientes excluídos com sucesso.']);
    }
    
    private function recuperarLinkMercadoPago($mercadoPagoId)
    {
        try {
            // Verifica se o mercado_pago_id é válido
            if (empty($mercadoPagoId)) {
                throw new \Exception('ID do Mercado Pago inválido.');
            }
            $paymentClient = new PaymentClient();
            $response = $paymentClient->get($mercadoPagoId);
            // Verifica se a cobrança ainda está pendente
            if ($response->status !== 'pending') {
                throw new \Exception('A cobrança não está mais pendente.');
            }
            // Recupera o link de pagamento
            return $response->point_of_interaction->transaction_data->external_resource_url 
                ?? $response->point_of_interaction->transaction_data->ticket_url;
        } catch (MPApiException $e) {
            Log::error('Erro ao recuperar link do Mercado Pago: ' . $e->getMessage());
            throw new \Exception('Erro ao recuperar link do Mercado Pago: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Erro ao recuperar link do Mercado Pago: ' . $e->getMessage());
            throw new \Exception('Erro ao recuperar link do Mercado Pago: ' . $e->getMessage());
        }
    }
    
    private function criarCobrancaMercadoPago($cliente, $plano, $companyDetail)
    {
        try {
            MercadoPagoConfig::setAccessToken($companyDetail->access_token);
            $preference = [
                'transaction_amount' => (float) $plano->preco,
                'description' => $plano->nome,
                'payment_method_id' => 'pix',
                'notification_url' => $companyDetail->notification_url,
                'payer' => [
                    'email' => 'cliente@cliente.com',
                    'first_name' => $cliente->nome,
                    'identification' => [
                        'type' => 'CPF',
                        'number' => '12345678909'
                    ]
                ]
            ];
            $response = (new PaymentClient())->create($preference, new RequestOptions());
            // Salva o pagamento no banco de dados
            Pagamento::updateOrCreate(
                ['cliente_id' => $cliente->id, 'status' => 'pending'],
                [
                    'user_id' => $cliente->user_id,
                    'mercado_pago_id' => $response->id,
                    'valor' => $plano->preco,
                    'status' => 'pending',
                    'plano_id' => $cliente->plano_id,
                    'isAnual' => false,
                    'updated_at' => now() // Atualiza a coluna updated_at
                ]
            );
            // Retorna o link de pagamento
            return $response->point_of_interaction->transaction_data->external_resource_url 
                ?? $response->point_of_interaction->transaction_data->ticket_url;
        } catch (MPApiException $e) {
            Log::error('Erro ao criar cobrança no Mercado Pago: ' . $e->getMessage());
            throw new \Exception('Erro ao criar cobrança no Mercado Pago: ' . $e->getMessage());
        }
    }
    
    public function cobrancaManual($clienteId)
    {
        try {
            $cliente = Cliente::findOrFail($clienteId);
            
            if (!$cliente->notificacoes) {
                return redirect()->route('app-ecommerce-customer-all')->with('warning', 'Este cliente não pode receber notificações.');
            }
    
            $conexao = Conexao::where('user_id', $cliente->user_id)->where('conn', 1)->first();
            if (!$conexao) {
                return redirect()->route('app-ecommerce-customer-all')->with('warning', 'Você precisa conectar seu WhatsApp.');
            }
    
            $template = Template::where('finalidade', 'cobranca_manual')
                ->where(function($query) use ($cliente) {
                    $query->where('user_id', $cliente->user_id)
                          ->orWhereNull('user_id');
                })
                ->orderBy('user_id', 'desc')
                ->firstOrFail();
    
            $companyDetail = CompanyDetail::where('user_id', $cliente->user_id)->firstOrFail();
            $this->removerCobrancasPendentes($cliente, $companyDetail);
    
            $plano = Plano::findOrFail($cliente->plano_id);
            $dadosCliente = [
                'nome' => $cliente->nome,
                'telefone' => $cliente->whatsapp,
                'notas' => $cliente->notas,
                'vencimento' => Carbon::parse($cliente->vencimento)->format('d/m/Y'),
                'plano_nome' => $plano->nome,
                'plano_valor' => $plano->preco,
                'nome_empresa' => $companyDetail->company_name,
                'whatsapp_empresa' => $companyDetail->company_whatsapp,
                'iptv_nome' => $cliente->iptv_nome ?? 'Nome de Usuário IPTV',
                'iptv_senha' => $cliente->iptv_senha ?? 'Senha IPTV',
            ];
    
            $conteudo = $this->substituirPlaceholders($template->conteudo, $dadosCliente);
            $requestData = ['phone' => $cliente->whatsapp, 'message' => $conteudo];
    
            if ($template->imagem) {
                $imagemUrl = url($template->imagem);
                if (filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
                    $requestData['image'] = $imagemUrl;
                }
            }
    
            $conexaoController = new ConexaoController();
    
            if ($companyDetail->not_gateway) {
                $requestData['message'] = str_replace(
                    'http://linkdopagamento.com',
                    "\n\nChave PIX: " . $companyDetail->pix_manual,
                    $conteudo
                );
    
                $conexaoController->sendMessage(new Request($requestData));
                
                Pagamento::create([
                    'cliente_id' => $cliente->id,
                    'user_id' => $cliente->user_id,
                    'mercado_pago_id' => uniqid(),
                    'valor' => $plano->preco,
                    'status' => 'pending',
                    'plano_id' => $cliente->plano_id,
                    'isAnual' => false,
                    'metodo_pagamento' => 'pix_manual',
                    'pix_manual' => $companyDetail->pix_manual
                ]);
            } else {
                $accessToken = $companyDetail->access_token;
                $adminCompanyDetail = CompanyDetail::whereHas('user', function($q) {
                    $q->where('role_id', 1);
                })->firstOrFail();
    
                MercadoPagoConfig::setAccessToken($accessToken);
                $paymentClient = new PaymentClient();
                
                $response = $paymentClient->create([
                    'transaction_amount' => (float)$plano->preco,
                    'description' => $plano->nome,
                    'payment_method_id' => 'pix',
                    'notification_url' => $adminCompanyDetail->notification_url,
                    'payer' => [
                        'email' => 'cliente@cliente.com',
                        'first_name' => $cliente->nome,
                        'identification' => ['type' => 'CPF', 'number' => '12345678909']
                    ]
                ], (new RequestOptions())->setCustomHeaders(["X-Idempotency-Key: " . uniqid()]));
    
                Pagamento::create([
                    'cliente_id' => $cliente->id,
                    'user_id' => $cliente->user_id,
                    'mercado_pago_id' => $response->id,
                    'valor' => $plano->preco,
                    'status' => 'pending',
                    'plano_id' => $cliente->plano_id,
                    'isAnual' => false,
                    'metodo_pagamento' => 'mercado_pago'
                ]);
    
                $requestData['message'] = str_replace(
                    'http://linkdopagamento.com',
                    $response->point_of_interaction->transaction_data->ticket_url,
                    $conteudo
                );
                $conexaoController->sendMessage(new Request($requestData));
            }
    
            return redirect()->route('app-ecommerce-customer-all')->with('success', 'Cobrança manual enviada com sucesso.');
    
        } catch (\Exception $e) {
            return redirect()->route('app-ecommerce-customer-all')
                ->with('error', 'Ocorreu um erro ao processar a cobrança manual: ' . $e->getMessage());
        }
    }
    
    protected function removerCobrancasPendentes($cliente, $company)
    {
        $cobrancasPendentes = Pagamento::where('cliente_id', $cliente->id)
            ->where('status', 'pending')
            ->get();
    
        if ($cobrancasPendentes->isNotEmpty()) {
            Log::info('Removendo ' . $cobrancasPendentes->count() . ' cobrança(s) pendente(s) para o cliente ID: ' . $cliente->id);
    
            foreach ($cobrancasPendentes as $cobranca) {
                // Se for MercadoPago, cancela a cobrança na API antes de remover
                if (!$company->not_gateway && $cobranca->mercado_pago_id && ($cobranca->mercado_pago_id)) {
                    $this->cancelarCobrancaMercadoPago($company, $cobranca->mercado_pago_id);
                }
                
                $cobranca->delete();
                Log::info('Cobrança pendente removida: ' . $cobranca->id);
            }
        }
    }
    
    protected function cancelarCobrancaMercadoPago($company, $mercadoPagoId)
    {
        try {
            MercadoPagoConfig::setAccessToken($company->access_token);
            $paymentClient = new PaymentClient();
            $paymentClient->cancel($mercadoPagoId);
            Log::info('Cobrança MercadoPago cancelada com sucesso: ' . $mercadoPagoId);
        } catch (\Exception $e) {
            Log::error('Erro ao cancelar cobrança no MercadoPago: ' . $e->getMessage());
        }
    }
    
    public function getCustomerData(Request $request)
    {
        $user = Auth::user();
        if ($user->role->name === 'admin') {
            $clientes = Cliente::with('plano', 'servidor')->select('clientes.*');
        } else {
            $clientes = Cliente::where('user_id', $user->id)->with('plano', 'servidor')->select('clientes.*');
        }
        return DataTables::of($clientes)
            ->addColumn('servidor', function ($cliente) {
                return $cliente->servidor ? $cliente->servidor->nome : 'Sem Servidor';
            })
            ->addColumn('plano', function ($cliente) {
                return $cliente->plano ? $cliente->plano->nome : 'Sem Plano';
            })
            ->addColumn('valor', function ($cliente) {
                return $cliente->plano ? 'R$ ' . number_format($cliente->plano->preco, 2, ',', '.') : 'Sem Plano';
            })
            ->addColumn('acoes', function ($cliente) {
                return view('partials.actions', compact('cliente'))->render();
            })
            ->make(true);
    }
    
    public function sendLoginDetails($clienteId)
    {
        try {
            $cliente = Cliente::with('plano')->findOrFail($clienteId);
            if (!$cliente->notificacoes) {
                return redirect()->route('app-ecommerce-customer-all')->with('warning', 'Notificações desativadas para este cliente.');
            }
            $conexao = Conexao::where('user_id', $cliente->user_id)->where('conn', 1)->first();
            if (!$conexao) {
                return redirect()->route('app-ecommerce-customer-all')->with('error', 'Conexão WhatsApp não disponível.');
            }
            $template = Template::where('finalidade', 'dados_iptv')
                ->where(function($q) use ($cliente) {
                    $q->where('user_id', $cliente->user_id)
                      ->orWhereNull('user_id');
                })
                ->orderBy('user_id', 'desc')
                ->firstOrFail();
    
            $companyDetail = CompanyDetail::where('user_id', $cliente->user_id)->firstOrFail();
            $plano = Plano::find($cliente->plano_id);
              if (!$plano) {
                  return redirect()->back()->with('error', 'Plano não encontrado.');
              }
            $dadosCliente = [
                'nome' => $cliente->nome,
                'telefone' => $cliente->whatsapp,
                'iptv_nome' => $cliente->iptv_nome,
                'iptv_senha' => $cliente->iptv_senha,
                'password' => $cliente->password,
                'vencimento' => Carbon::parse($cliente->vencimento)->format('d/m/Y'),
                'plano_nome' => $plano->nome ?? 'Nome do Plano',
                'plano_valor' => $cliente->plano->preco, // Valor do plano do relacionamento
                'login_url' => url('/client/login'),
                'nome_empresa' => $companyDetail->company_name,
                'whatsapp_empresa' => $companyDetail->company_whatsapp,
                'text_expirate' => $this->getTextExpirate($cliente->vencimento),
            ];
    
            $conteudo = $this->substituirPlaceholders($template->conteudo, $dadosCliente);
    
            $requestData = [
                'phone' => $cliente->whatsapp,
                'message' => $conteudo,
                'user_id' => $cliente->user_id
            ];
    
            if ($template->imagem) {
                $imagemUrl = url($template->imagem);
                if (filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
                    $requestData['image'] = $imagemUrl;
                }
            }
            $conexaoController = new ConexaoController();
            $conexaoController->sendMessage(new Request($requestData));
            return redirect()->route('app-ecommerce-customer-all')->with('success', 'Credenciais enviadas com sucesso.');
        } catch (ModelNotFoundException $e) {
            return redirect()->route('app-ecommerce-customer-all')->with('error', 'Recurso necessário não encontrado: ' . $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('app-ecommerce-customer-all')->with('error', 'Falha no envio das credenciais: ' . $e->getMessage());
        }
    }
    
    private function substituirPlaceholders($conteudo, $dadosCliente)
{
    // --- prepara variantes de WhatsApp ---
    $foneBruto  = $dadosCliente['telefone'] ?? '';
    $foneDigits = preg_replace('/\D+/', '', (string)$foneBruto); // só números
    if (!$foneDigits) {
        $foneDigits = '(11999999999)'; // fallback
    }

    // monta a linha de vencimento, se existir data
    $vencimentoLinha = '🧾 Vencimento: —';
    if (!empty($dadosCliente['vencimento'])) {
        $txtExp = $dadosCliente['text_expirate'] ?? '';
        $vencimentoLinha = "🧾 Vencimento: {$dadosCliente['vencimento']}" . ($txtExp ? " ({$txtExp})" : '');
    }

    $placeholders = [
        // Cliente
        '{nome_cliente}'           => ($dadosCliente['nome_cliente'] ?? $dadosCliente['nome'] ?? 'Nome do Cliente'),
        '{telefone_cliente}'       => $dadosCliente['telefone'] ?? '(11) 99999-9999',
        '{whatsapp_cliente}'       => $dadosCliente['telefone'] ?? '(11) 99999-9999',
        '{whatsapp_cliente_num}'   => $foneDigits, // << agora funciona

        // Notas e vencimento
        '{notas}'                  => $dadosCliente['notas'] ?? '—',
        '{vencimento_cliente}'     => $dadosCliente['vencimento'] ?? '01/01/2025',
        '{vencimento_linha}'       => $vencimentoLinha,

        // Plano e valores
        '{plano_nome}'             => $dadosCliente['plano_nome'] ?? 'Plano Básico',
        '{plano_valor}'            => $dadosCliente['plano_valor'] ?? 'R$ 0,00',

        // Datas
        '{data_atual}'             => date('d/m/Y'),
        '{data_hora_cadastro}'     => $dadosCliente['data_hora_cadastro'] ?? date('d/m/Y H:i'),

        // Links e pix
        '{plano_link}'             => $dadosCliente['plano_link'] ?? 'http://linkdopagamento.com',
        '{payload_pix}'            => $dadosCliente['payload_pix'] ?? '',

        // Textos auxiliares
        '{text_expirate}'          => $dadosCliente['text_expirate'] ?? '',
        '{saudacao}'               => $this->getSaudacao(),

        // Empresa (aceita {nome_empresa} e {empresa_nome})
        '{nome_empresa}'           => $dadosCliente['nome_empresa'] ?? ($dadosCliente['empresa_nome'] ?? 'Sua Empresa'),
        '{empresa_nome}'           => $dadosCliente['empresa_nome'] ?? ($dadosCliente['nome_empresa'] ?? 'Sua Empresa'),
        '{whatsapp_empresa}'       => $dadosCliente['whatsapp_empresa'] ?? '(11) 99999-9999',
        '{whatsap_empresa}'        => $dadosCliente['whatsapp_empresa'] ?? '(11) 99999-9999',

        // Credenciais IPTV
        '{iptv_nome}'              => $dadosCliente['iptv_nome'] ?? '—',
        '{iptv_senha}'             => $dadosCliente['iptv_senha'] ?? '—',
        '{usuario_iptv}'           => $dadosCliente['iptv_nome'] ?? '—',
        '{senha_iptv}'             => $dadosCliente['iptv_senha'] ?? '—',

        // Senha da área do cliente (texto puro vindo do store)
        '{password}'               => $dadosCliente['password'] ?? '—',

        // Área do cliente
        '{login_url}'              => $dadosCliente['login_url'] ?? url('/client/login'),
        '{area_cliente_link}'      => $dadosCliente['area_cliente_link'] ?? ($dadosCliente['login_url'] ?? url('/client/login')),
    ];

    foreach ($placeholders as $placeholder => $valor) {
        $conteudo = str_replace($placeholder, $valor, $conteudo);
    }
    return $conteudo;
}

    /**
 * Dispara a mensagem de boas-vindas automaticamente logo após o cadastro.
 */
private function sendWelcomeOnCreate(Cliente $cliente, ?string $plainPassword = null): void
{
    // 1) só envia se o cliente aceita notificações
    if (!$cliente->notificacoes) {
        \Log::info('[novo_cliente] notificações desativadas para o cliente', ['cliente_id' => $cliente->id]);
        return;
    }

    // 2) precisa existir conexão ativa do WhatsApp para o dono do cliente
    $conexao = \App\Models\Conexao::where('user_id', $cliente->user_id)->where('conn', 1)->first();
    if (!$conexao) {
        \Log::warning('[novo_cliente] sem conexão WhatsApp ativa para o user', ['user_id' => $cliente->user_id]);
        return;
    }

    // 3) carrega empresa e plano
    $companyDetail = \App\Models\CompanyDetail::where('user_id', $cliente->user_id)->first();
    $plano = \App\Models\Plano::find($cliente->plano_id);

    // 4) buscar todos os templates de "novo_cliente" (prioriza do usuário, depois global)
    $templates = \App\Models\Template::where('finalidade', 'novo_cliente')
        ->where(function($q) use ($cliente) {
            $q->where('user_id', $cliente->user_id)
              ->orWhereNull('user_id');
        })
        ->orderBy('user_id', 'desc')
        ->get();

    // 4.1) escolher o primeiro template que NÃO contenha campos de IPTV
    $template = $templates->first(function($t) {
        $c = $t->conteudo ?? '';
        return (stripos($c, '{iptv_nome}') === false) && (stripos($c, '{iptv_senha}') === false);
    }) ?: $templates->first();

    // 5) se não tiver template, usar um fallback SEM IPTV
    if (!$template) {
        $mensagemFallback =
            "✅ Cadastro confirmado!\n\n"
            ."Olá {$cliente->nome}, seu cadastro foi concluído com sucesso.\n\n"
            ."📦 Plano: ".($plano->nome ?? '—')."\n"
            ."🧾 Vencimento: ".(\Carbon\Carbon::parse($cliente->vencimento)->format('d/m/Y'))."\n\n"
            ."🔐 Área do Cliente\n"
            ."* Link: ".url('/client/login')."\n"
            ."* Usuário: ".$cliente->whatsapp."\n"
            ."* Senha: ".($plainPassword ?: '—')."\n\n"
            ."Qualquer dúvida, estamos à disposição!";
        
        (new \App\Http\Controllers\ConexaoController())->sendMessage(new \Illuminate\Http\Request([
            'phone'   => $cliente->whatsapp,
            'message' => $mensagemFallback,
            'user_id' => $cliente->user_id
        ]));
        \Log::info('[novo_cliente] enviado via fallback sem IPTV', ['cliente_id' => $cliente->id]);
        return;
    }

    // 6) montar dados pro template (sem IPTV)
    $foneBruto  = (string)($cliente->whatsapp ?? '');
    $foneDigits = preg_replace('/\D+/', '', $foneBruto) ?: '(11999999999)';
    $vencimentoFmt = \Carbon\Carbon::parse($cliente->vencimento)->format('d/m/Y');
    $vencimentoLinha = "🧾 Vencimento: {$vencimentoFmt} (".$this->getTextExpirate($cliente->vencimento).")";

    $dadosCliente = [
        // Cliente
        'nome_cliente'           => $cliente->nome,
        'telefone'               => $cliente->whatsapp,
        'whatsapp_cliente'       => $cliente->whatsapp,
        'whatsapp_cliente_num'   => $foneDigits,

        // Plano e datas
        'plano_nome'             => $plano->nome ?? '—',
        'plano_valor'            => $plano->preco ?? '—',
        'vencimento'             => $vencimentoFmt,
        'vencimento_linha'       => $vencimentoLinha,

        // Empresa e links
        'nome_empresa'           => $companyDetail->company_name ?? 'Sua Empresa',
        'empresa_nome'           => $companyDetail->company_name ?? 'Sua Empresa',
        'whatsapp_empresa'       => $companyDetail->company_whatsapp ?? '',
        'login_url'              => url('/client/login'),
        'area_cliente_link'      => url('/client/login'),

        // Senha da área do cliente (texto puro que você digitou no cadastro)
        'password'               => $plainPassword ?: '—',

        // Auxiliares
        'text_expirate'          => $this->getTextExpirate($cliente->vencimento),
        'data_hora_cadastro'     => now()->format('d/m/Y H:i'),
    ];

    // 7) renderizar o texto do template e enviar
    $conteudo = $this->substituirPlaceholders($template->conteudo, $dadosCliente);

    $payload = [
        'phone'   => $cliente->whatsapp,
        'message' => $conteudo,
        'user_id' => $cliente->user_id
    ];

    if (!empty($template->imagem)) {
        $imagemUrl = url($template->imagem);
        if (filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
            $payload['image'] = $imagemUrl;
        }
    }

    (new \App\Http\Controllers\ConexaoController())->sendMessage(new \Illuminate\Http\Request($payload));
    \Log::info('[novo_cliente] mensagem enviada (sem IPTV)', ['cliente_id' => $cliente->id, 'template_id' => $template->id ?? null]);
}

    // ==================== FUNÇÕES AUXILIARES ==================== //
    private function getTextExpirate($vencimento)
    {
        // Converte a data de yyyy-mm-dd para um objeto Carbon
        $dataVencimento = \Carbon\Carbon::parse($vencimento);
        $dataAtual = \Carbon\Carbon::now();
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
}