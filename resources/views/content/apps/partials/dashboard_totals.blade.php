<div class="row g-4 mb-4" id="lcs-dashboard-totals">
  <div class="col-sm-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="mb-1">Recebido neste mês</span>
            <h3 class="mb-2" id="totalMesRecebido">R$ 0,00</h3>
            <small class="text-muted" id="totalMesRecebidoLabel"></small>
          </div>
          <span class="badge bg-label-success rounded p-2">
            <i class="ti ti-cash ti-sm"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="mb-1">A receber neste mês</span>
            <h3 class="mb-2" id="totalMesAReceber">R$ 0,00</h3>
            <small class="text-muted" id="totalMesAReceberLabel"></small>
          </div>
          <span class="badge bg-label-warning rounded p-2">
            <i class="ti ti-calendar-stats ti-sm"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="mb-1">Total histórico recebido</span>
            <h3 class="mb-2" id="totalHistoricoRecebido">R$ 0,00</h3>
            <small class="text-muted">Soma de todos os pagamentos aprovados</small>
          </div>
          <span class="badge bg-label-primary rounded p-2">
            <i class="ti ti-chart-line ti-sm"></i>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  // ---- Config base
  const userId   = {{ auth()->id() }};
  const now      = new Date();
  const month    = now.getMonth();      // 0-11
  const year     = now.getFullYear();   // ex.: 2025
  const pt       = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  // ---- Alvos
  const elMesRec   = document.getElementById('totalMesRecebido');
  const elMesRecLb = document.getElementById('totalMesRecebidoLabel');
  const elMesAr    = document.getElementById('totalMesAReceber');
  const elMesArLb  = document.getElementById('totalMesAReceberLabel');
  const elHist     = document.getElementById('totalHistoricoRecebido');

  // ---- Helpers robustos (resistentes a diferenças de campos)
  const getNumber = (obj, keys) => {
    for (const k of keys) {
      if (obj && obj[k] != null && !isNaN(Number(obj[k]))) return Number(obj[k]);
    }
    return 0;
  };
  const getString = (obj, keys) => {
    for (const k of keys) {
      if (obj && typeof obj[k] === 'string' && obj[k].length) return obj[k];
    }
    return '';
  };
  const parseDate = (str) => {
    if (!str) return null;
    // Tenta "2025-11-07 12:34:56" ou ISO
    const s = str.replace(' ', 'T');
    const d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
  };
  const sameMonthYear = (d, m, y) => d && d.getMonth() === m && d.getFullYear() === y;

  // ---- Regras de status (ajuste se preciso)
  const isPaid = (p) => {
    const status = (getString(p, ['status','status_pagamento','situacao']) || '').toLowerCase();
    const pagoFlag = p && (p.pago === true || p.pago === 1 || p.pago === '1');
    return pagoFlag || ['paid','pago','approved','aprovado','concluido','concluído'].includes(status);
  };
  const isPending = (p) => {
    const status = (getString(p, ['status','status_pagamento','situacao']) || '').toLowerCase();
    return ['pending','pendente','aguardando','em_aberto','aberto'].includes(status);
  };

  // ---- Campos possíveis
  const amountKeys  = ['valor_pago','valor','amount','valor_total'];
  const createdKeys = ['created_at','data_pagamento','paid_at','dt_criacao'];
  const dueKeys     = ['vencimento','due_date','data_vencimento'];

  // ---- Busca a API já usada no dashboard
  //   Observação: existe /api/transactions no seu projeto. Vamos pedir "period=todos" (indiferente),
  //   e filtrar no front por mês/ano atual para NÃO quebrar nada no back.
  const url = `/api/transactions?period=todos&user_id=${userId}`;

  fetch(url)
    .then(r => r.json())
    .then(res => {
      // res pode ser {data:[...]} ou array direto
      const lista = Array.isArray(res) ? res : (Array.isArray(res.data) ? res.data : []);

      let totalMesRecebido = 0;
      let totalMesAReceber = 0;
      let totalHistorico   = 0;

      for (const p of lista) {
        const valor   = getNumber(p, amountKeys);
        const dtC     = parseDate(getString(p, createdKeys));
        const dtVenc  = parseDate(getString(p, dueKeys));

        if (isPaid(p)) {
          totalHistorico += valor;
          if (dtC && sameMonthYear(dtC, month, year)) {
            totalMesRecebido += valor;
          }
        } else if (isPending(p)) {
          // Só conta "a receber no mês" se tiver vencimento no mês atual
          if (dtVenc && sameMonthYear(dtVenc, month, year)) {
            totalMesAReceber += valor;
          }
        }
      }

      // Render
      elMesRec.textContent = pt.format(totalMesRecebido);
      elMesRecLb.textContent = new Date(year, month, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });

      elMesAr.textContent = pt.format(totalMesAReceber);
      elMesArLb.textContent = 'Vencimentos do mês';

      elHist.textContent = pt.format(totalHistorico);
    })
    .catch(err => {
      console.error('Erro ao carregar /api/transactions', err);
      // Mantém zeros na UI sem quebrar nada
    });
})();
</script>
@endpush
