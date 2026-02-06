<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('vendor-style'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/apex-charts/apex-charts.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>" />
    <style>
        #updateLogsContainer {
            font-family: monospace;
            white-space: pre-wrap;
            background-color: #2f3349;
            padding: 10px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
            display: none; /* Escondido por padrão */
        }
        .update-log-success {
            color: #28a745;
        }
        .update-log-error {
            color: #dc3545;
        }
        .update-log-warning {
            color: #ffc107;
        }
        .update-log-info {
            color: #a2a2a2;
        }
        /* ===== Welcome Premium ===== */
.welcome-premium{
  position:relative;border:0;color:#fff;border-radius:16px;overflow:hidden;
  background:
    radial-gradient(900px 260px at -10% -60%, rgba(115,103,240,.25), transparent 60%),
    linear-gradient(135deg,#2f3349 0%, #3b3f5c 55%, #242842 100%);
  box-shadow:0 14px 38px rgba(0,0,0,.35);
}
.welcome-premium::before{
  content:"";position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(120deg, rgba(255,255,255,.06), transparent 30%, transparent 70%, rgba(255,255,255,.04));
}
.welcome-title{margin:0;font-weight:800;letter-spacing:.2px}
.welcome-name{
  background:linear-gradient(90deg,#9aa0ff,#6f66f0);
  -webkit-background-clip:text;background-clip:text;color:transparent;
}
.welcome-sub{color:rgba(255,255,255,.78)}
.welcome-chip{
  display:inline-flex;align-items:center;gap:.5rem;
  padding:.5rem 1rem;border-radius:999px;font-weight:600;
  background:linear-gradient(90deg,#1f5131,#1a7f42); /* verde */
  color:#d8ffe9;border:1px solid rgba(255,255,255,.08);
  box-shadow:0 0 0 3px rgba(46,204,113,.12), 0 10px 24px rgba(0,0,0,.25);
}
.welcome-chip.warn{background:linear-gradient(90deg,#5a4a1a,#a27d1a);color:#fff3cd}
.welcome-chip.danger{background:linear-gradient(90deg,#5a1a1a,#a21a1a);color:#ffe0e0}
.welcome-chip.muted{background:linear-gradient(90deg,#3a3f58,#2d334a);color:#e0e0e0}
/* ===== Expiração: chip compacta + subnota ===== */
.welcome-chip.compact{
  padding:.35rem .75rem;      /* menor */
  font-size:.85rem;           /* menor */
  line-height:1;
  gap:.4rem;
  box-shadow:0 6px 16px rgba(0,0,0,.22);
}
.welcome-subnote{
  display:block;
  margin-top:.35rem;
  font-size:.825rem;
  opacity:.9;
  color:#c7ffd9;              /* tom combinando com a chip verde */
}
.welcome-chip.compact.warn + .welcome-subnote{ color:#fff1b6; }   /* aviso */
.welcome-chip.compact.danger + .welcome-subnote{ color:#ffd1d1; } /* expirado */
.welcome-chip.compact.muted + .welcome-subnote{ color:#e0e0e0; }  /* sem data */
/* ===== Tamanho reduzido do card ===== */
.welcome-premium .card-body{ padding:14px 18px !important; }
.welcome-title{ font-size:1.15rem; }          /* título menor */
.welcome-sub{ font-size:.92rem; }             /* subtítulo menor */
.welcome-chip.compact{ padding:.28rem .6rem; font-size:.8rem; } /* chip menor */
    </style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
    <script src="<?php echo e(asset('assets/vendor/libs/apex-charts/apexcharts.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <script src="<?php echo e(asset('assets/js/app-ecommerce-dashboard.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/dashboards-crm.js')); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos DOM reutilizáveis
            const elements = {
                checkUpdateBtn: document.getElementById('checkUpdateBtn'),
                updateStatus: document.getElementById('updateStatus'),
                updateMessage: document.getElementById('updateMessage'),
                updateProgressBar: document.getElementById('updateProgressBar'),
                updateDetails: document.getElementById('updateDetails'),
                updateLogsContainer: document.getElementById('updateLogsContainer'),
                detailedStatisticsChartEl: document.querySelector('#detailedStatisticsChart'),
                currentVersion: '<?php echo e(config("app.version")); ?>'.trim() // Pega a versão do .env
            };

            
            // ==================== GRÁFICOS E ESTATÍSTICAS ==================== //

            if (elements.detailedStatisticsChartEl) {
                const estatisticas = {
                    totalClientes: <?php echo json_encode($totalClientes, 15, 512) ?>,
                    inadimplentes: <?php echo json_encode($inadimplentes, 15, 512) ?>,
                    ativos: <?php echo json_encode($ativos, 15, 512) ?>,
                    expiramHoje: <?php echo json_encode($expiramHoje, 15, 512) ?>
                };

                const chartOptions = {
                    series: [
                        { name: 'Total de Clientes', data: [estatisticas.totalClientes] },
                        { name: 'Inadimplentes', data: [estatisticas.inadimplentes] },
                        { name: 'Ativos', data: [estatisticas.ativos] },
                        { name: 'Expiram Hoje', data: [estatisticas.expiramHoje] }
                    ],
                    chart: {
                        height: 350,
                        type: 'bar',
                        toolbar: { show: false },
                        foreColor: '#333'
                    },
                    colors: ['#008ffb', '#ff4560', '#00e396', '#feb019'],
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%',
                            endingShape: 'rounded'
                        }
                    },
                    dataLabels: { enabled: false },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Estatísticas'],
                        labels: { style: { colors: '#a2a2a2' } }
                    },
                    yaxis: {
                        title: {
                            text: 'Quantidade',
                            style: { color: '#a2a2a2' }
                        },
                        labels: { style: { colors: '#a2a2a2' } }
                    },
                    fill: { opacity: 1 },
                    tooltip: {
                        theme: 'light',
                        y: { formatter: val => val }
                    },
                    legend: { labels: { colors: '#a2a2a2' } }
                };

                new ApexCharts(elements.detailedStatisticsChartEl, chartOptions).render();
            }

            // ==================== FILTRO DE TRANSAÇÕES ==================== //

            window.filterTransactions = async function(period) {
                try {
                    const response = await fetch(`/api/transactions?period=${period}&user_id=<?php echo e(auth()->user()->id); ?>`);
                    const data = await response.json();
                    
                    elements.transactionList.innerHTML = data.payments.map(pagamento => `
                        <li class="d-flex mb-3 pb-1 align-items-center">
                            <div class="p-2 rounded badge bg-label-primary me-3">
                                <i class="ti ti-wallet ti-sm"></i>
                            </div>
                            <div class="flex-wrap gap-2 d-flex w-100 align-items-center justify-content-between">
                                <div class="me-2">
                                    <h6 class="mb-0">Mercado Pago</h6>
                                    <small class="text-muted d-block">ID: ${pagamento.mercado_pago_id}</small>
                                </div>
                                <div class="gap-1 user-progress d-flex align-items-center">
                                    <h6 class="mb-0 text-success">+R$${parseFloat(pagamento.valor).toFixed(2).replace('.', ',')}</h6>
                                </div>
                            </div>
                        </li>
                    `).join('');
                    
                    elements.transactionCount.textContent = `Total de ${data.payments.length} transações realizadas`;
                } catch (error) {
                    console.error('Erro ao buscar transações:', error);
                    elements.transactionList.innerHTML = `
                        <li class="text-danger">
                            <i class="ti ti-alert-circle me-2"></i>
                            Erro ao carregar transações
                        </li>
                    `;
                }
            }
        });
        // ==================== MOSTRAR/OCULTAR VALORES (com persistência e animação) ==================== //
document.addEventListener('DOMContentLoaded', function () {
  const toggleValoresBtn = document.getElementById('toggleValoresBtn');
  const statusValores = document.getElementById('statusValores');
  if (!toggleValoresBtn || !statusValores) return;

  let valoresVisiveis = localStorage.getItem('dashboard_valores_visiveis');
  valoresVisiveis = valoresVisiveis === null ? true : (valoresVisiveis === 'true');

  const aplicarVisibilidade = () => {
    const valores = document.querySelectorAll('.valor-financeiro');
    valores.forEach(v => {
      const real = v.dataset.realvalor ?? v.textContent.trim();
      v.style.transition = 'opacity 0.3s ease';
      v.style.opacity = '0';
      setTimeout(() => {
        v.textContent = valoresVisiveis ? real : '••••••';
        v.style.opacity = '1';
      }, 200);
    });

    toggleValoresBtn.innerHTML = valoresVisiveis
      ? '<i class="ti ti-eye-off"></i>'
      : '<i class="ti ti-eye"></i>';

    statusValores.textContent = valoresVisiveis ? 'Valores visíveis' : 'Valores ocultos';
    statusValores.style.opacity = '0';
    setTimeout(() => (statusValores.style.opacity = '1'), 200);
    toggleValoresBtn.title = valoresVisiveis ? 'Ocultar valores' : 'Mostrar valores';
  };

  aplicarVisibilidade();

  toggleValoresBtn.addEventListener('click', () => {
    valoresVisiveis = !valoresVisiveis;
    localStorage.setItem('dashboard_valores_visiveis', valoresVisiveis ? 'true' : 'false');
    aplicarVisibilidade();
  });
});



    </script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <?php if(session('warning')): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo e(session('warning')); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(session('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo e(session('error')); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(session('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e(session('success')); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if($errors->any()): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul>
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

    <?php
    $user = auth()->user();
    $expirationDate = $user->trial_ends_at;
    $daysUntilExpiration = now()->diffInDays($expirationDate, false);
    $isAdmin = $user->isAdmin();
    $isClient = $user->role_id == 2;
?>

<?php if($user->role_id == 2 && $daysUntilExpiration <= 7): ?>
<div class="mb-4 col-xl-8 col-lg-7 col-12" style="width:100%">
    <div class="card h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <?php if($daysUntilExpiration > 0): ?>
                    <span>
                        Seu acesso expira em <strong><?php echo e($daysUntilExpiration); ?> <?php echo e(Str::plural('dia', $daysUntilExpiration)); ?></strong>.
                        Renove agora mesmo para manter seu painel ativo.
                    </span>
                <?php else: ?>
                    <span>
                        Seu acesso expirou em <strong class="text-danger"><?php echo e($expirationDate->format('d/m/Y')); ?></strong>.
                        Renove agora para reativar seu painel.
                    </span>
                <?php endif; ?>
                
                <button id="renewSubscriptionBtn" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#pricingModal">
                    <i class="ti ti-refresh me-1"></i> Renovar
                </button>
            </div>
        </div>
    </div>
</div>

<?php echo $__env->make('_partials._modals.modal-pricing', [
    'isAdmin' => $isAdmin,
    'isClient' => $isClient
], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php endif; ?>
<?php
  use Carbon\Carbon;
  use Illuminate\Support\Str;

  Carbon::setLocale('pt_BR');

  $user = auth()->user();
  $hora = (int) now()->format('H');
  $saud = $hora < 12 ? '🌅 Bom dia' : ($hora < 18 ? '🌇 Boa tarde' : '🌙 Boa noite');
  $nome = strtoupper($user->name ?? 'Usuário');

  // ====== MENSAGEM DINÂMICA (Opção 1) ======
  $mensagemDia = match (true) {
      $hora < 12 => '☕️ Tenha uma manhã produtiva e cheia de resultados!',
      $hora < 18 => '🚀 Continue focado — cada cliente novo é um passo a mais no sucesso!',
      default    => '🌙 Boa noite! Aproveite para revisar seus relatórios e planejar o amanhã.',
  };

  // ====== CÁLCULO DE EXPIRAÇÃO ======
  $expiracao = $user->trial_ends_at ?? $user->expires_at ?? null;
  $dias = $expiracao ? now()->diffInDays(Carbon::parse($expiracao), false) : null;
  $expData = $expiracao ? Carbon::parse($expiracao) : null;
  $mostrarChip = !$user->isAdmin();

  if ($dias === null) {
      $chipTexto   = '<i class="ti ti-calendar"></i> Sem data de expiração';
      $chipClasse  = 'muted';
      $chipSubnote = '';
  } elseif ($dias < 0) {
      $chipTexto   = '<i class="ti ti-alert-circle"></i> Expirado em <strong>'
                   . $expData->format('d/m/Y') . '</strong>';
      $chipClasse  = 'danger';
      $chipSubnote = 'Entre em contato para reativar seu acesso.';
  } elseif ($dias === 0) {
      $chipTexto   = '<i class="ti ti-clock"></i> Expira <strong>hoje</strong>';
      $chipClasse  = 'warn';
      $chipSubnote = 'Recomendamos renovar agora para evitar interrupção do acesso.';
  } else {
      $chipTexto   = '<i class="ti ti-calendar-check"></i> Vencimento: <strong>'
                   . $expData->format('d/m/Y') . '</strong> • restam apenas <strong>'
                   . $dias . '</strong> ' . Str::plural('dia', $dias) . ' para expirar';
      $chipClasse  = ($dias <= 5) ? 'warn' : ''; // ===== Opção 2: alerta se faltar 5 dias =====
      $chipSubnote = ($dias <= 5)
          ? '⚠️ Atenção: seu plano expira em poucos dias.'
          : 'Recomendamos renovar antes de vencer para evitar ficar sem acesso.';
  }
?>

<div class="col-12">
  <div class="card welcome-premium mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-start gap-3">
        <div class="p-3 rounded-3" style="background:rgba(115,103,240,.18)">
          <i class="ti ti-sparkles" style="font-size:26px"></i>
        </div>
        <div>
          <h4 class="welcome-title">
            <?php echo e($saud); ?>, <span class="welcome-name"><?php echo e($nome); ?></span>!
          </h4>
          <p class="welcome-sub mb-1">
            👋 <strong>Bem-vindo(a) de volta!</strong> Seu painel está pronto — acompanhe clientes, planos e resultados em tempo real 🚀
          </p>
          <p class="welcome-sub mb-0"><?php echo e($mensagemDia); ?></p>
        </div>
      </div>

      <?php if($mostrarChip): ?>
      <div class="text-end">
        <span class="welcome-chip compact <?php echo e($chipClasse); ?>">
          <?php echo $chipTexto; ?>

        </span>
        <?php if(!empty($chipSubnote)): ?>
          <small class="welcome-subnote"><?php echo e($chipSubnote); ?></small>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>



<?php
  $user        = auth()->user();
  $ownerName   = $user->name ?? 'Usuário';
  $brandName   = config('variables.templateName') ?? 'SPXTV';

  // Número do suporte (DDI + DDD + número, só dígitos)
  $waNumberRaw = '5581987902294';

  // Saudação dinâmica
  $hora = (int) now()->format('H');
  $saudacao = $hora < 12 ? '🌅 Bom dia' : ($hora < 18 ? '🌇 Boa tarde' : '🌙 Boa noite');

  // Dados do usuário
  $painelHost   = request()->getHost();
  $cadastro     = optional($user->created_at)->format('d/m/Y') ?? '—';

  // Vencimento e dias restantes
  $vencimento   = isset($expData) && $expData ? $expData->format('d/m/Y') : '—';
  $diasRest     = isset($dias) ? $dias : null;
  $diaWord      = ($diasRest === 1 ? 'dia' : 'dias');

  if ($diasRest === null) {
    $diasTxt = 'sem data definida';
  } elseif ($diasRest >= 0) {
    $diasTxt = $diasRest . ' ' . $diaWord . ' restantes';
  } else {
    $diasTxt = 'expirado há ' . abs($diasRest) . ' ' . (abs($diasRest) === 1 ? 'dia' : 'dias');
  }

  // Número do cliente (tenta vários campos comuns)
  $clienteNumero = $user->phone
                  ?? $user->whatsapp
                  ?? $user->celular
                  ?? $user->telefone
                  ?? '—';

 // Mensagem estilizada (versão profissional com emojis reais)
  $waMsgRaw =
    "👋 Olá, tudo bem?\n"
  . "Estou precisando de suporte no Gestor V5 🔰\n"
  . "Aqui estão minhas informações para agilizar o atendimento:\n\n"
  . "━━━━━━━━━━━━━━━━━━━\n"
  . "👤 Usuário: {$ownerName}\n"
  . "🆔 ID: {$user->id}\n"
  . "📞 Número: {$clienteNumero}\n"
  . "🌐 Painel: {$painelHost}\n"
  . "📅 Data de cadastro: {$cadastro}\n"
  . "📆 Vencimento: {$vencimento} ({$diasTxt})\n"
  . "━━━━━━━━━━━━━━━━━━━\n\n"
  . "✍️ Mensagem: ";

  $waMsg  = rawurlencode($waMsgRaw);
  $waLink = "https://wa.me/{$waNumberRaw}?text={$waMsg}";

  // Canais oficiais
  $grupoLink = 'https://chat.whatsapp.com/JAlqMuFZO0QClstldhv5x7?mode=wwt';
  $canalLink = 'https://whatsapp.com/channel/0029Vb6m7O089infCzWqtt1c';
?>
<div class="col-12">
  <div class="card mb-4 border-0 shadow-sm"
       style="background:linear-gradient(145deg,#2f3349,#3a3e5a); border-radius:14px; color:#fff;">
    <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">

      <div class="d-flex align-items-center gap-3">
        <div class="p-2 rounded-circle" style="background:rgba(115,103,240,.2)">
          <i class="ti ti-headset" style="font-size:20px; color:#9aa0ff"></i>
        </div>
        <div>
          <h6 class="mb-0 fw-semibold text-white">Suporte & Comunidade — Gestor V5</h6>
          <small class="text-muted">Dúvidas rápidas? Atendimento humano e canais oficiais 👇</small>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo e($waLink); ?>" target="_blank" class="btn btn-sm text-white" 
           style="background:linear-gradient(90deg,#25d366,#128c7e); border-radius:999px; box-shadow:0 0 10px rgba(37,211,102,.5);">
          <i class="ti ti-brand-whatsapp me-1"></i> Suporte
        </a>
        <a href="<?php echo e($canalLink); ?>" target="_blank" class="btn btn-sm text-white"
           style="background:linear-gradient(90deg,#6f66f0,#4a45a3); border-radius:999px; box-shadow:0 0 10px rgba(115,103,240,.4);">
          <i class="ti ti-speakerphone me-1"></i> Canal
        </a>
        <a href="<?php echo e($grupoLink); ?>" target="_blank" class="btn btn-sm text-white"
           style="background:linear-gradient(90deg,#f39c12,#e67e22); border-radius:999px; box-shadow:0 0 10px rgba(243,156,18,.5);">
          <i class="ti ti-users-group me-1"></i> Grupo
        </a>
      </div>

    </div>
  </div>
</div>

        <!-- Estatísticas -->
        <div class="mb-4 col-xl-8 col-lg-7 col-12" style="width:100%">
            <div class="card h-100">
                <div class="card-header">
                    <div class="mb-3 d-flex justify-content-between">
                        <h5 class="mb-0 card-title">Estatísticas</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row gy-3">
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center">
                                <div class="p-2 badge rounded-pill bg-label-primary me-3"><i class="ti ti-users ti-sm"></i>
                                </div>
                                <div class="card-info">
                                    <h5 class="mb-0"><?php echo e(number_format($totalClientes, 0, ',', '.')); ?></h5>
                                    <small>Total de clientes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center">
                                <div class="p-2 badge rounded-pill bg-label-info me-3"><i
                                        class="ti ti-chart-pie-2 ti-sm"></i></div>
                                <div class="card-info">
                                    <h5 class="mb-0"><?php echo e(number_format($inadimplentes, 0, ',', '.')); ?></h5>
                                    <small>Inadimplentes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center">
                                <div class="p-2 badge rounded-pill bg-label-danger me-3"><i
                                        class="ti ti-shopping-cart ti-sm"></i></div>
                                <div class="card-info">
                                    <h5 class="mb-0"><?php echo e(number_format($ativos, 0, ',', '.')); ?></h5>
                                    <small>Ativos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center">
                                <div class="p-2 badge rounded-pill bg-label-success me-3"><i
                                        class="ti ti-currency-dollar ti-sm"></i></div>
                                <div class="card-info">
                                    <h5 class="mb-0"><?php echo e(number_format($expiramHoje, 0, ',', '.')); ?></h5>
                                    <small>Expiram hoje</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Totais Financeiros -->
<div class="mb-4 col-xl-8 col-lg-7 col-12" style="width:100%">
  <div class="card h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
  <div>
    <h5 class="mb-0 card-title">Totais Financeiros</h5>
    <small class="text-muted">Resumo geral de valores</small>
  </div>
  <div class="d-flex align-items-center gap-2">
  <button id="toggleValoresBtn" class="btn btn-sm btn-outline-secondary" title="Ocultar valores">
    <i class="ti ti-eye-off"></i>
  </button>
  <small id="statusValores" class="text-muted" style="transition: opacity 0.3s ease;">Valores visíveis</small>
</div>

</div>
    <div class="card-body">
      <div class="row gy-3">
        <div class="col-md-4 col-12">
          <div class="d-flex align-items-center">
            <div class="p-2 badge rounded-pill bg-label-primary me-3">
              <i class="ti ti-calendar-stats ti-sm"></i>
            </div>
            <div class="card-info">
              <h5 class="mb-0 valor-financeiro" data-realvalor="R$ <?php echo e(number_format($totalMes ?? 0, 2, ',', '.')); ?>">   R$ <?php echo e(number_format($totalMes ?? 0, 2, ',', '.')); ?> </h5>
              <small>Total recebido no mês</small>
            </div>
          </div>
        </div>

        <div class="col-md-4 col-12">
          <div class="d-flex align-items-center">
            <div class="p-2 badge rounded-pill bg-label-warning me-3">
              <i class="ti ti-wallet ti-sm"></i>
            </div>
            <div class="card-info">
              <h5   class="mb-0 valor-financeiro"   data-realvalor="R$ <?php echo e(number_format($totalAReceberMes ?? 0, 2, ',', '.')); ?>" >   R$ <?php echo e(number_format($totalAReceberMes ?? 0, 2, ',', '.')); ?> </h5>
              <small>Total a receber no mês</small>
            </div>
          </div>
        </div>

        <div class="col-md-4 col-12">
          <div class="d-flex align-items-center">
            <div class="p-2 badge rounded-pill bg-label-success me-3">
              <i class="ti ti-currency-dollar ti-sm"></i>
            </div>
            <div class="card-info">
              <h5   class="mb-0 valor-financeiro"   data-realvalor="R$ <?php echo e(number_format($totalHistorico ?? 0, 2, ',', '.')); ?>" >   R$ <?php echo e(number_format($totalHistorico ?? 0, 2, ',', '.')); ?> </h5>
              <small>Total geral recebido</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


        <!-- Estatísticas Detalhadas -->
        <div class="mb-4 col-xl-6 col-md-6">
            <div class="card h-100">
                <div class="p-0 card-body">
                    <div class="row g-0">
                        <div class="p-4 col-12 position-relative">
                            <div class="p-0 card-header d-inline-block text-wrap position-absolute">
                                <h5 class="m-0 card-title">Estatísticas Detalhadas</h5>
                            </div>
                            <br>
                            <div id="detailedStatisticsChart" class="mt-n1"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transações -->
        <div class="mb-4 col-xl-6 col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="m-0 card-title me-2">
                        <h5 class="m-0 me-2">Transações</h5>
                        <small class="text-muted" id="transactionCount">Ultimas <?php echo e($pagamentos->count()); ?> transações
                            realizadas</small>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="p-0 m-0" id="transactionList">
                        <?php $__currentLoopData = $pagamentos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pagamento): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="pb-1 mb-3 d-flex align-items-center">
                                <div class="p-2 rounded badge bg-label-primary me-3">
                                    <i class="ti ti-wallet ti-sm"></i>
                                </div>
                                <div class="flex-wrap gap-2 d-flex w-100 align-items-center justify-content-between">
                                    <div class="me-2">
                                        <h6 class="mb-0">Pagamento</h6>
                                        <small class="text-muted d-block">ID da Transação:
                                            <?php echo e($pagamento->mercado_pago_id); ?></small>
                                    </div>
                                    <div class="gap-1 user-progress d-flex align-items-center">
                                        <h6 class="mb-0 text-success">
                                            +R$<?php echo e(number_format($pagamento->valor, 2, ',', '.')); ?></h6>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Relatórios de Ganhos -->
        <div class="mb-4 col-xl-6 col-md-6" style="width:100%">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="mb-0 card-title">
                        <h5 class="mb-0">Relatórios de Ganhos</h5>
                        <small class="text-muted">Visão geral dos ganhos anuais</small>
                    </div>
                    <div class="dropdown">
                        <button class="p-0 btn" type="button" id="earningReportsTabsId" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                <ul class="gap-4 pb-3 mx-1 nav nav-tabs widget-nav-tabs d-flex flex-nowrap" role="tablist" style="place-content: center;">
                        <li class="nav-item">
                            <a href="javascript:void(0);"
                                class="nav-link btn active d-flex flex-column align-items-center justify-content-center"
                                role="tab" data-bs-toggle="tab" data-bs-target="#navs-orders-id"
                                aria-controls="navs-orders-id" aria-selected="true">
                                <div class="p-2 rounded badge bg-label-secondary"><i
                                        class="ti ti-shopping-cart ti-sm"></i></div>
                                <h6 class="mt-2 mb-0 tab-widget-title">Pedidos</h6>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="javascript:void(0);"
                                class="nav-link btn d-flex flex-column align-items-center justify-content-center"
                                role="tab" data-bs-toggle="tab" data-bs-target="#navs-sales-id"
                                aria-controls="navs-sales-id" aria-selected="false">
                                <div class="p-2 rounded badge bg-label-secondary"><i class="ti ti-chart-bar ti-sm"></i>
                                </div>
                                <h6 class="mt-2 mb-0 tab-widget-title">Receita</h6>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="javascript:void(0);"
                                class="nav-link btn d-flex flex-column align-items-center justify-content-center"
                                role="tab" data-bs-toggle="tab" data-bs-target="#navs-earnings-id"
                                aria-controls="navs-earnings-id" aria-selected="false">
                                <div class="p-2 rounded badge bg-label-secondary"><i
                                        class="ti ti-currency-dollar ti-sm"></i></div>
                                <h6 class="mt-2 mb-0 tab-widget-title">Ganhos</h6>
                            </a>
                        </li>
                    </ul>
                    <div class="p-0 tab-content ms-0 ms-sm-2">
                        <div class="tab-pane fade show active" id="navs-orders-id" role="tabpanel">
                            <div id="earningReportsTabsOrders"></div>
                        </div>
                        <div class="tab-pane fade" id="navs-sales-id" role="tabpanel">
                            <div id="earningReportsTabsSales"></div>
                        </div>
                        <div class="tab-pane fade" id="navs-profit-id" role="tabpanel">
                            <div id="earningReportsTabsProfit"></div>
                        </div>
                        <div class="tab-pane fade" id="navs-income-id" role="tabpanel">
                            <div id="earningReportsTabsIncome"></div>
                        </div>
                        <div class="tab-pane fade" id="navs-earnings-id" role="tabpanel">
                            <div id="earningsLast7Days"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmação de Atualização -->
    <div class="modal fade" id="updateConfirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="text-white modal-title">ATENÇÃO: Confirmação de Atualização</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-primary" style="background-color: #2f3349;border-color: #2f3349;color: #7367f0;">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <strong>Recomendamos fortemente fazer backup do sistema antes de continuar.</strong>
                    </div>
                    <p>Esta operação atualizará o sistema para a versão mais recente. Deseja continuar?</p>
                    
                    <div class="mt-3">
                        <button id="confirmUpdateBtn" class="btn btn-primary me-2">
                            <i class="ti ti-check me-1"></i> Sim, Atualizar
                        </button>
                        <button class="btn btn-danger" data-bs-dismiss="modal">
                            <i class="ti ti-x me-1"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts/layoutMaster', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/u403845897/domains/gestor.spxtv.top/public_html/resources/views/content/apps/app-ecommerce-dashboard.blade.php ENDPATH**/ ?>