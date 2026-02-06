<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardTotalsService
{
    /**
     * ===== CONFIGURAÇÕES =====
     * Ajuste estes nomes se sua base usar outros campos/tabelas.
     */
    protected string $table = 'transactions';     // Tabela de transações
    protected string $amountCol = 'amount';       // Coluna de valor (ex.: amount, valor, total)
    protected string $statusCol = 'status';       // Coluna de status
    protected ?string $paidAtCol = 'paid_at';     // Coluna da data de pagamento (ou null para usar created_at)
    protected ?string $dueAtCol  = 'due_at';      // Coluna da data prevista/vencimento (ou null para usar created_at)

    // Quais status consideramos "pagos" e "pendentes"
    protected array $paidStatuses    = ['paid','approved','completed'];
    protected array $pendingStatuses = ['pending','awaiting','open','unpaid'];

    // Colunas para escopo do dono (ajuste conforme seu schema)
    protected ?string $ownerUserCol    = 'user_id';     // Para admin/revenda (padrão)
    protected ?string $ownerClientCol  = 'cliente_id';  // Para guard 'cliente'

    /**
     * Retorna: [
     *   'mes_recebido' => float,
     *   'mes_a_receber' => float,
     *   'total_recebido' => float,
     * ]
     */
    public function getTotalsFor(?Authenticatable $user, ?string $guard = null): array
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        // RECEBIDO NO MÊS
        $mesRecebido = $this->baseQuery($user, $guard)
            ->when($this->paidAtCol, fn($q) => $q->whereBetween($this->paidAtCol, [$start, $end]),
                               fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->whereIn($this->statusCol, $this->paidStatuses)
            ->sum($this->amountCol);

        // A RECEBER NO MÊS (pendências)
        $mesAReceber = $this->baseQuery($user, $guard)
            ->when($this->dueAtCol, fn($q) => $q->whereBetween($this->dueAtCol, [$start, $end]),
                              fn($q) => $q->whereBetween('created_at', [$start, $end]))
            ->whereIn($this->statusCol, $this->pendingStatuses)
            ->sum($this->amountCol);

        // TOTAL RECEBIDO (VITALÍCIO)
        $totalRecebido = $this->baseQuery($user, $guard)
            ->whereIn($this->statusCol, $this->paidStatuses)
            ->sum($this->amountCol);

        // Se seus valores vierem em centavos, descomente:
        // $mesRecebido   = $mesRecebido / 100;
        // $mesAReceber   = $mesAReceber / 100;
        // $totalRecebido = $totalRecebido / 100;

        return [
            'mes_recebido'   => (float) $mesRecebido,
            'mes_a_receber'  => (float) $mesAReceber,
            'total_recebido' => (float) $totalRecebido,
        ];
    }

    /**
     * Query base já com escopo do dono conforme guard/regra.
     */
    protected function baseQuery(?Authenticatable $user, ?string $guard): Builder
    {
        $q = DB::table($this->table);

        // ADMIN vê tudo. Se seu sistema usa Spatie:
        $isAdmin = false;
        if ($user && method_exists($user, 'hasRole')) {
            $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');
        }

        // Guard do cliente: limitar por cliente_id
        if ($guard === 'cliente' && $this->ownerClientCol && $user) {
            return $q->where($this->ownerClientCol, $user->id)->whereNull('deleted_at');
        }

        // Usuário comum / revenda: limitar por user_id (se não for admin)
        if (!$isAdmin && $this->ownerUserCol && $user) {
            return $q->where($this->ownerUserCol, $user->id)->whereNull('deleted_at');
        }

        // Admin: sem filtro de dono
        return $q->whereNull('deleted_at');
    }
}
