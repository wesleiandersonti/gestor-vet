<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardTotalsController extends Controller
{
    /**
     * Ajuste estes nomes se sua tabela/colunas forem diferentes.
     * Se sua tabela for "payments", troque $table = 'transactions' -> 'payments' etc.
     */
    private string $table = 'transactions';     // ou 'payments'
    private string $colAmount = 'amount';       // valor da transação
    private string $colStatus = 'status';       // 'paid', 'pending', etc.
    private string $colPaidAt = 'paid_at';      // data de pagamento (datetime)
    private string $colDueAt  = 'due_date';     // data de vencimento (se não tiver, usaremos created_at)
    private string $colUser   = 'user_id';      // dono (revenda/admin)
    private string $colClient = 'cliente_id';   // cliente (painel do cliente)

    public function index(Request $request)
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        // Base query
        $q = DB::table($this->table);

        // Escopo por papel:
        // - Admin: vê tudo
        // - Cliente (guard 'cliente'): filtra pelo cliente_id
        // - Revenda/usuário comum: filtra pelo user_id
        if (Auth::guard('cliente')->check()) {
            $clienteId = Auth::guard('cliente')->id();
            $q = $q->where($this->colClient, $clienteId);
        } else {
            $user = Auth::user();
            if ($user && !$user->hasRole('admin')) {
                $q = $q->where($this->colUser, $user->id);
            }
        }

        // ======= Total do mês RECEBIDO =======
        $monthReceived = (clone $q)
            ->where($this->colStatus, 'paid')
            ->whereBetween($this->colPaidAt, [$start, $end])
            ->sum($this->colAmount);

        // ======= Total do mês A RECEBER =======
        // usa due_date; se não existir, cai para created_at
        $dueColumn = $this->columnExists($this->table, $this->colDueAt) ? $this->colDueAt : 'created_at';

        $monthReceivable = (clone $q)
            ->whereIn($this->colStatus, ['pending', 'awaiting', 'unpaid'])
            ->whereBetween($dueColumn, [$start, $end])
            ->sum($this->colAmount);

        // ======= Total histórico RECEBIDO =======
        $lifetimeReceived = (clone $q)
            ->where($this->colStatus, 'paid')
            ->sum($this->colAmount);

        return response()->json([
            'month_received'   => (float) $monthReceived,
            'month_receivable' => (float) $monthReceivable,
            'lifetime_received'=> (float) $lifetimeReceived,
            'range'            => [$start->toDateString(), $end->toDateString()],
        ]);
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return DB::getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
