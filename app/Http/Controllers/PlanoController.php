<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\PlanoRenovacao;

class PlanoController extends Controller
{
    public function __construct()
    {
        // Aplicar middleware de autenticação
        $this->middleware('auth');
    }

    public function index()
    {
        if (Auth::check()) {
            // Usuário está autenticado
            $user = Auth::user();
            $userId = $user->id;
            $userRole = $user->role->name;
    
            // Verificar se o usuário é administrador
            if ($userRole === 'admin') {
                // Administrador vê todos os planos
                $planos_revenda = PlanoRenovacao::all();
                $current_plan_id = $user->plano_id;
            } else {
                // Usuário comum vê apenas seus próprios planos
                $planos_revenda = PlanoRenovacao::all();
                $current_plan_id = $user->plano_id;
            }
    
            // Buscar todos os usuários (opcional, dependendo do seu caso de uso)
            $users = User::all();
    
            return view('planos.index', compact('users', 'planos_revenda', 'current_plan_id'));
        } else {
            // Redirecionar para a página de login se o usuário não estiver autenticado
            return redirect()->route('auth-login-basic');
        }
    }


    public function list(Request $request)
    {
        Log::info('Acessando a listagem de planos com paginação e busca.');
    
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Usuário não autenticado'], 401);
            }
    
            $user = Auth::user();
            $search = $request->input('search');
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'DESC');
            $limit = $request->input('limit', 10);
    
            // Construir query base
            $query = Plano::where('user_id', $user->id);
    
            // Aplicar filtro de busca se existir
            if ($search) {
                $query->where('nome', 'like', '%' . $search . '%');
            }
    
            // Obter total de registros antes da paginação
            $totalPlanos = $query->count();
    
            // Aplicar ordenação e paginação
            $planos = $query->orderBy($sort, $order)
                ->paginate($limit)
                ->through(function ($plano) {
                    // Botões de ação
                    $actions = '<div class="d-grid gap-3">
                                    <div class="row g-3">
                                        <div class="col-4 mb-2">
                                            <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" 
                                                data-bs-target="#editPlano' . $plano->id . '" 
                                                title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                        <div class="col-4 mb-2">
                                            <form action="' . route('planos.duplicate', $plano->id) . '" method="POST" style="display:inline;">
                                                ' . csrf_field() . '
                                                <button type="submit" class="btn btn-sm btn-info w-100" title="Duplicar">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-4 mb-2">
                                            <form action="' . route('planos.destroy', $plano->id) . '" method="POST" style="display:inline;">
                                                ' . csrf_field() . '
                                                ' . method_field('DELETE') . '
                                                <button type="submit" class="btn btn-sm btn-danger w-100" title="Deletar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>';
    
                    // Modal de edição
                    $modal = '<div class="modal fade" id="editPlano' . $plano->id . '" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-simple modal-edit-plano">
                                    <div class="modal-content p-3 p-md-5">
                                        <div class="modal-body">
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            <div class="text-center mb-4">
                                                <h3 class="mb-2">Editar Plano</h3>
                                                <p class="text-muted">Atualize os detalhes do plano.</p>
                                            </div>
                                            <form id="editPlanoForm' . $plano->id . '" class="row g-3" action="' . route('planos.update', $plano->id) . '" method="POST">
                                                ' . csrf_field() . '
                                                ' . method_field('PUT') . '
                                                <div class="col-12">
                                                    <label class="form-label" for="editPlanoNome' . $plano->id . '">Nome</label>
                                                    <input type="text" id="editPlanoNome' . $plano->id . '" name="nome" class="form-control" value="' . $plano->nome . '" required />
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="editPlanoPreco' . $plano->id . '">Preço</label>
                                                    <input type="number" step="0.01" id="editPlanoPreco' . $plano->id . '" name="preco" class="form-control" value="' . $plano->preco . '" required />
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="editPlanoDuracao' . $plano->id . '">Duração</label>
                                                    <input type="number" id="editPlanoDuracao' . $plano->id . '" name="duracao" class="form-control" value="' . $plano->duracao . '" required />
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="editPlanoTipoDuracao' . $plano->id . '">Tipo de Duração</label>
                                                    <select id="editPlanoTipoDuracao' . $plano->id . '" name="tipo_duracao" class="form-control" required>
                                                        <option value="dias"' . ($plano->tipo_duracao === 'dias' ? ' selected' : '') . '>Dias</option>
                                                        <option value="meses"' . ($plano->tipo_duracao === 'meses' ? ' selected' : '') . '>Meses</option>
                                                        <option value="anos"' . ($plano->tipo_duracao === 'anos' ? ' selected' : '') . '>Anos</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 text-center">
                                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Atualizar</button>
                                                    <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancelar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>';
    
                    return [
                        'id' => $plano->id,
                        'nome' => $plano->nome,
                        'preco' => 'R$ ' . number_format($plano->preco, 2, ',', '.'),
                        'duracao' => $plano->duracao . ' ' . ucfirst($plano->tipo_duracao),
                        'duracao_dias' => $plano->duracao_em_dias . ' dias',
                        'created_at' => $plano->created_at->format('d/m/Y H:i'),
                        'updated_at' => $plano->updated_at->format('d/m/Y H:i'),
                        'user_name' => $plano->user ? $plano->user->name : 'N/A',
                        'actions' => $actions . $modal
                    ];
                });
    
            // Obter preferências do usuário para colunas visíveis
            $preferences = DB::table('user_client_preferences')
                ->where('user_id', $user->id)
                ->where('table_name', 'planos')
                ->value('visible_columns');
    
            $visibleColumns = json_decode($preferences, true) ?: [
                'id', 'nome', 'preco', 'duracao', 'duracao_dias', 'created_at', 'updated_at', 'user_name', 'actions'
            ];
    
            // Filtrar colunas baseado nas preferências
            $filteredPlanos = $planos->map(function ($plano) use ($visibleColumns) {
                return array_filter($plano, function ($key) use ($visibleColumns) {
                    return in_array($key, $visibleColumns);
                }, ARRAY_FILTER_USE_KEY);
            });
    
            // Dados adicionais para a view
            $planos_revenda = PlanoRenovacao::all();
            $current_plan_id = $user->plano_id;
            $users = User::all();
    
            return response()->json([
                'rows' => $filteredPlanos,
                'total' => $totalPlanos,
                'planos' => $planos,
                'planos_revenda' => $planos_revenda,
                'current_plan_id' => $current_plan_id,
                'users' => $users
            ]);
    
        } catch (\Exception $e) {
            Log::error('Erro ao acessar a listagem de planos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erro ao carregar a listagem de planos'], 500);
        }
    }
    
    public function duplicate(Plano $plano)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $userId = $user->id;
            $userRole = $user->role->name;
    
            // Verificar se o plano pertence ao usuário autenticado ou se o usuário é administrador
            if ($plano->user_id === $userId || $userRole === 'admin') {
                try {
                    // Criar uma cópia do plano
                    $newPlano = $plano->replicate();
                    $newPlano->nome = $plano->nome . ' - Cópia';
                    $newPlano->save();
    
                    return redirect()->route('planos.index')->with('success', 'Plano duplicado com sucesso.');
                } catch (\Exception $e) {
                    Log::error('Erro ao duplicar plano: ' . $e->getMessage());
                    return redirect()->route('planos.index')->with('error', 'Erro ao duplicar o plano.');
                }
            } else {
                return redirect()->route('planos.index')->with('error', 'Você não tem permissão para duplicar este plano.');
            }
        } else {
            return redirect()->route('auth-login-basic');
        }
    }
    
    public function create()
    {
        // Buscar todos os usuários (opcional, dependendo do seu caso de uso)
        $users = User::all();
        return view('planos.create', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'preco' => 'required|numeric',
            'duracao' => 'required|integer',
            'tipo_duracao' => 'required|in:dias,meses,anos'
        ]);

        // Calcular duração em dias
        $duracao_em_dias = $this->calcularDuracaoEmDias(
            $request->duracao, 
            $request->tipo_duracao
        );

        $plano = new Plano($request->all());
        $plano->user_id = auth()->user()->id;
        $plano->duracao_em_dias = $duracao_em_dias;
        $plano->save();

        return redirect()->route('planos.index')->with('success', 'Plano criado com sucesso.');
    }

    public function show($id)
    {
        $plano = Plano::findOrFail($id);
        return view('planos.show', compact('plano'));
    }

    public function edit(Plano $plano)
    {
        if (Auth::check()) {
            // Usuário está autenticado
            $user = Auth::user();
            $userId = $user->id;
            $userRole = $user->role->name;

            // Verificar se o plano pertence ao usuário autenticado ou se o usuário é administrador
            if ($plano->user_id === $userId || $userRole === 'admin') {
                // Buscar todos os usuários (opcional, dependendo do seu caso de uso)
                $users = User::all();
                return view('planos.edit', compact('plano', 'users'));
            } else {
                return redirect()->route('planos.index')->with('error', 'Você não tem permissão para editar este plano.');
            }
        } else {
            // Redirecionar para a página de login se o usuário não estiver autenticado
            return redirect()->route('auth-login-basic');
        }
    }

    public function update(Request $request, Plano $plano)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $userId = $user->id;
            $userRole = $user->role->name;

            if ($plano->user_id === $userId || $userRole === 'admin') {
                $request->validate([
                    'nome' => 'required|string|max:255',
                    'preco' => 'required|numeric',
                    'duracao' => 'required|integer',
                    'tipo_duracao' => 'required|in:dias,meses,anos'
                ]);

                // Calcular duração em dias
                $duracao_em_dias = $this->calcularDuracaoEmDias(
                    $request->duracao, 
                    $request->tipo_duracao
                );

                $plano->update(array_merge($request->all(), [
                    'duracao_em_dias' => $duracao_em_dias
                ]));

                return redirect()->route('planos.index')->with('success', 'Plano atualizado com sucesso.');
            } else {
                return redirect()->route('planos.index')->with('error', 'Você não tem permissão para atualizar este plano.');
            }
        } else {
            return redirect()->route('auth-login-basic');
        }
    }
    
    private function calcularDuracaoEmDias($duracao, $tipo_duracao)
    {
        switch ($tipo_duracao) {
            case 'meses':
                return $duracao * 31; // 1 mês = 31 dias
            case 'anos':
                return $duracao * 365; // 1 ano = 365 dias
            default: // dias
                return $duracao;
        }
    }

    public function destroy(Plano $plano)
    {
        if (Auth::check()) {
            // Usuário está autenticado
            $user = Auth::user();
            $userId = $user->id;
            $userRole = $user->role->name;

            // Verificar se o plano pertence ao usuário autenticado ou se o usuário é administrador
            if ($plano->user_id === $userId || $userRole === 'admin') {
                $plano->delete();
                return redirect()->route('planos.index')->with('success', 'Plano deletado com sucesso.');
            } else {
                return redirect()->route('planos.index')->with('error', 'Você não tem permissão para deletar este plano.');
            }
        } else {
            // Redirecionar para a página de login se o usuário não estiver autenticado
            return redirect()->route('auth-login-basic');
        }
    }


   
    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids');
        if (is_array($ids)) {
            Plano::whereIn('id', $ids)->delete();
            return response()->json(['error' => false, 'message' => 'Planos excluídos com sucesso.']);
        } else {
            return response()->json(['error' => true, 'message' => 'Nenhum plano selecionado.']);
        }
    }
}