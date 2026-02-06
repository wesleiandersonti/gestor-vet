<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Pagamento;
use App\Models\Cliente;
use App\Models\PlanoRenovacao;
use Carbon\Carbon;

class EcommerceDashboard extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('auth-login-basic');
        }

        $user     = Auth::user();
        $userId   = $user->id;
        $userRole = $user->role->name ?? 'user';

        // Período do mês atual (mais robusto que whereMonth)
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes    = Carbon::now()->endOfMonth();

        // =========================
        // BLOCO DE DADOS
        // =========================
        if ($userRole === 'admin') {
            // Admin vê tudo
            $pagamentos = Pagamento::where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            $clientes = Cliente::all();

            // Totais financeiros (geral)
            $totalMes = Pagamento::where('status', 'approved')
                ->whereBetween('created_at', [$inicioMes, $fimMes])
                ->sum('valor');

            // A receber no mês (qualquer status diferente de approved)
            $totalAReceberMes = Pagamento::where('status', '!=', 'approved')
                ->whereBetween('created_at', [$inicioMes, $fimMes])
                ->sum('valor');

            // Histórico total recebido
            $totalHistorico = Pagamento::where('status', 'approved')->sum('valor');
        } else {
            // =========================
            // Escopo do revendedor/usuário
            // Inclui:
            // - pagamentos com user_id = do usuário
            // - OU pagamentos cujo cliente pertence ao usuário
            // =========================
            $scoped = function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('cliente', function ($q2) use ($userId) {
                      $q2->where('user_id', $userId);
                  });
            };

            // Últimas 5 transações aprovadas do escopo do usuário
            $pagamentos = Pagamento::with('cliente')
                ->where('status', 'approved')
                ->where($scoped)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            // Clientes do usuário
            $clientes = Cliente::where('user_id', $userId)->get();

            // Totais financeiros do mês (aprovados)
            $totalMes = Pagamento::where('status', 'approved')
                ->whereBetween('created_at', [$inicioMes, $fimMes])
                ->where($scoped)
                ->sum('valor');

            // A receber no mês (qualquer status != approved)
            $totalAReceberMes = Pagamento::where('status', '!=', 'approved')
                ->whereBetween('created_at', [$inicioMes, $fimMes])
                ->where($scoped)
                ->sum('valor');

            // Histórico total recebido do usuário
            $totalHistorico = Pagamento::where('status', 'approved')
                ->where($scoped)
                ->sum('valor');
        }

        // =========================
        // ESTATÍSTICAS DE CLIENTES
        // =========================
        $totalClientes = $clientes->count();

        $inadimplentes = $clientes->filter(function ($cliente) {
            return $cliente->vencimento
                ? Carbon::parse($cliente->vencimento)->isBefore(Carbon::today())
                : false;
        })->count();

        $ativos = $clientes->filter(function ($cliente) {
            if (!$cliente->vencimento) return false;
            $v = Carbon::parse($cliente->vencimento);
            return $v->isAfter(Carbon::today()) || $v->isSameDay(Carbon::today());
        })->count();

        $expiramHoje = $clientes
            ->where('vencimento', Carbon::today()->format('Y-m-d'))
            ->count();

        // =========================
        // CLIENTE COM MAIS COMPRAS
        // =========================
        $clienteMaisCompras = null;
        $totalComprasClienteMaisCompras = 0;

        $clienteMaisComprasData = Pagamento::select('cliente_id')
            ->whereNotNull('cliente_id')
            ->where('status', 'approved')
            ->when($userRole !== 'admin', function ($query) use ($userId) {
                // Mesmo escopo do usuário para este cálculo
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->orWhereHas('cliente', function ($q2) use ($userId) {
                          $q2->where('user_id', $userId);
                      });
                });
            })
            ->selectRaw('COUNT(*) as total, SUM(valor) as total_valor, cliente_id')
            ->groupBy('cliente_id')
            ->orderByDesc('total')
            ->first();

        if ($clienteMaisComprasData) {
            $clienteMaisCompras = Cliente::find($clienteMaisComprasData->cliente_id);
            $totalComprasClienteMaisCompras = $clienteMaisComprasData->total_valor;
        }

        $planos_revenda = PlanoRenovacao::all();
        $sessionData    = Session::all();

        return view('content.apps.app-ecommerce-dashboard', [
            'user_id'   => $userId,
            'user_role' => $userRole,
            'session_data' => $sessionData,

            // lista
            'pagamentos' => $pagamentos,

            // estatísticas
            'totalClientes' => $totalClientes,
            'inadimplentes' => $inadimplentes,
            'ativos'        => $ativos,
            'expiramHoje'   => $expiramHoje,

            // “quem mais compra”
            'clienteMaisCompras'               => $clienteMaisCompras,
            'totalComprasClienteMaisCompras'   => $totalComprasClienteMaisCompras,

            // planos
            'planos_revenda'  => $planos_revenda,
            'current_plan_id' => $user->plano_id,

            // totais financeiros
            'totalMes'         => (float) $totalMes,
            'totalAReceberMes' => (float) $totalAReceberMes,
            'totalHistorico'   => (float) $totalHistorico,
        ]);
    }
}
