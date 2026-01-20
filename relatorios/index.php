<?php
// Relatórios Financeiros
$titulo_pagina = 'Relatórios Financeiros';
$subtitulo_pagina = 'Análise e Exportação';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Processar filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$acao = $_GET['acao'] ?? '';

// Validar datas
if (empty($data_inicio)) $data_inicio = date('Y-m-01');
if (empty($data_fim)) $data_fim = date('Y-m-t');

// Obter dados resumidos do financeiro
try {
    $sql_resumo = "SELECT 
        COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) as total_receitas,
        COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) as total_despesas,
        COALESCE(COUNT(*), 0) as total_transacoes
    FROM transactions 
    WHERE gym_id = :gym_id AND data_vencimento BETWEEN :data_inicio AND :data_fim";

    $stmt_resumo = $pdo->prepare($sql_resumo);
    $stmt_resumo->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_resumo->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
    $stmt_resumo->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
    $stmt_resumo->execute();
    $resumo = $stmt_resumo->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $resumo = ['total_receitas' => 0, 'total_despesas' => 0, 'total_transacoes' => 0];
}

$saldo = (float)($resumo['total_receitas'] ?? 0) - (float)($resumo['total_despesas'] ?? 0);

// Obter evolução mensal (últimos 6 meses)
try {
    $sql_evolucao = "SELECT 
        DATE_FORMAT(data_vencimento, '%Y-%m') as mes,
        DATE_FORMAT(data_vencimento, '%m/%Y') as mes_formatado,
        CAST(COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS DECIMAL(10,2)) as receitas,
        CAST(COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) AS DECIMAL(10,2)) as despesas
    FROM transactions 
    WHERE gym_id = :gym_id AND data_vencimento >= DATE_SUB(:data_fim, INTERVAL 5 MONTH)
    GROUP BY mes
    ORDER BY mes ASC";

    $stmt_evolucao = $pdo->prepare($sql_evolucao);
    $stmt_evolucao->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_evolucao->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
    $stmt_evolucao->execute();
    $evolucao = $stmt_evolucao->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $evolucao = [];
}

// Obter receitas por categoria
try {
    $sql_categorias = "SELECT 
        t.categoria,
        CAST(COALESCE(SUM(t.valor), 0) AS DECIMAL(10,2)) as total
    FROM transactions t
    WHERE t.gym_id = :gym_id AND t.tipo = 'receita' AND t.data_vencimento BETWEEN :data_inicio AND :data_fim
    GROUP BY t.categoria
    ORDER BY total DESC";

    $stmt_categorias = $pdo->prepare($sql_categorias);
    $stmt_categorias->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_categorias->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
    $stmt_categorias->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
    $stmt_categorias->execute();
    $receitas_categoria = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $receitas_categoria = [];
}

// Obter despesas por categoria
try {
    $sql_despesas = "SELECT 
        t.categoria,
        CAST(COALESCE(SUM(t.valor), 0) AS DECIMAL(10,2)) as total
    FROM transactions t
    WHERE t.gym_id = :gym_id AND t.tipo = 'despesa' AND t.data_vencimento BETWEEN :data_inicio AND :data_fim
    GROUP BY t.categoria
    ORDER BY total DESC";

    $stmt_despesas = $pdo->prepare($sql_despesas);
    $stmt_despesas->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_despesas->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
    $stmt_despesas->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
    $stmt_despesas->execute();
    $despesas_categoria = $stmt_despesas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $despesas_categoria = [];
}

// Obter detalhamento de transações
try {
    $sql_transacoes = "SELECT 
        t.*,
        s.nome as aluno_nome
    FROM transactions t
    LEFT JOIN students s ON t.aluno_id = s.id AND s.gym_id = t.gym_id
    WHERE t.gym_id = :gym_id AND t.data_vencimento BETWEEN :data_inicio AND :data_fim
    ORDER BY t.data_vencimento DESC, t.created_at DESC";

    $stmt_transacoes = $pdo->prepare($sql_transacoes);
    $stmt_transacoes->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_transacoes->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
    $stmt_transacoes->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
    $stmt_transacoes->execute();
    $transacoes = $stmt_transacoes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $transacoes = [];
}

// Preparar dados para gráficos
$labels_meses = [];
$dados_receitas = [];
$dados_despesas = [];

foreach ($evolucao as $row) {
    $labels_meses[] = "'" . $row['mes_formatado'] . "'";
    $dados_receitas[] = number_format($row['receitas'], 2, '.', '');
    $dados_despesas[] = number_format($row['despesas'], 2, '.', '');
}

$labels_categorias = [];
$dados_categorias = [];
$cores_categorias = [];
$cores_padrao = ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#6f42c1', '#20c997', '#fd7e14', '#6c757d', '#0dcaf0', '#198754'];

foreach ($receitas_categoria as $index => $row) {
    $nome = !empty($row['categoria']) ? $row['categoria'] : 'Outros';
    $labels_categorias[] = "'" . sanitizar($nome) . "'";
    $dados_categorias[] = number_format($row['total'], 2, '.', '');
    $cores_categorias[] = "'" . ($cores_padrao[$index % count($cores_padrao)] ?? '#6c757d') . "'";
}

// Cores para gráfico de categorias
$labels_despesas = [];
$dados_despesas_cat = [];
$cor_despesas = [];

foreach ($despesas_categoria as $index => $row) {
    $nome = !empty($row['categoria']) ? $row['categoria'] : 'Outros';
    $labels_despesas[] = "'" . sanitizar($nome) . "'";
    $dados_despesas_cat[] = number_format($row['total'], 2, '.', '');
    $cor_despesas[] = "'" . ($cores_padrao[$index % count($cores_padrao)] ?? '#dc3545') . "'";
}
?>

<?php include '../includes/header.php'; ?>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros do Relatório</h5>
            <div class="d-flex gap-2">
                <a href="exportar_pdf.php?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" class="btn btn-outline-danger" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Exportar PDF
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= $data_inicio ?>">
            </div>
            <div class="col-md-4">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= $data_fim ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="d-flex gap-2 w-100">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search me-2"></i>Gerar Relatório
                    </button>
                    <a href="?" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Cards de Resumo -->
<div class="row mb-4 g-3">
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-up-circle fs-3 text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Receitas</p>
                        <h4 class="mb-0 fw-bold text-success">R$ <?= number_format($resumo['total_receitas'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-down-circle fs-3 text-danger"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Despesas</p>
                        <h4 class="mb-0 fw-bold text-danger">R$ <?= number_format($resumo['total_despesas'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-primary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-wallet2 fs-3 text-primary"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Saldo do Período</p>
                        <h4 class="mb-0 fw-bold <?= $saldo >= 0 ? 'text-success' : 'text-danger' ?>">R$ <?= number_format($saldo, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row mb-4 g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Evolução Financeira</h5>
            </div>
            <div class="card-body">
                <?php if (count($evolucao) > 0): ?>
                    <canvas id="graficoEvolucao"></canvas>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-graph-up fs-1"></i>
                        <p class="mt-3">Não há dados suficientes para gerar o gráfico.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Receitas por Categoria</h5>
            </div>
            <div class="card-body">
                <?php if (count($receitas_categoria) > 0): ?>
                    <canvas id="graficoCategorias"></canvas>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-pie-chart fs-1"></i>
                        <p class="mt-3">Não há receitas por categoria no período.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Resumo por Categoria -->
<div class="row mb-4 g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-currency-dollar me-2"></i>Receitas por Categoria</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">% do Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_receitas = $resumo['total_receitas'];
                            foreach ($receitas_categoria as $row): 
                                $percentual = $total_receitas > 0 ? ($row['total'] / $total_receitas) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?= sanitizar($row['categoria'] ?? 'Outros') ?></td>
                                    <td class="text-end fw-bold text-success">R$ <?= number_format($row['total'], 2, ',', '.') ?></td>
                                    <td class="text-end">
                                        <small class="text-muted"><?= number_format($percentual, 1, ',', '.') ?>%</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($receitas_categoria) === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Nenhuma receita encontrada no período.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-currency-exchange me-2"></i>Despesas por Categoria</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">% do Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_despesas = $resumo['total_despesas'];
                            foreach ($despesas_categoria as $row): 
                                $percentual = $total_despesas > 0 ? ($row['total'] / $total_despesas) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?= sanitizar($row['categoria'] ?? 'Outros') ?></td>
                                    <td class="text-end fw-bold text-danger">R$ <?= number_format($row['total'], 2, ',', '.') ?></td>
                                    <td class="text-end">
                                        <small class="text-muted"><?= number_format($percentual, 1, ',', '.') ?>%</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($despesas_categoria) === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Nenhuma despesa encontrada no período.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detalhamento de Transações -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detalhamento de Transações</h5>
    </div>
    <div class="card-body p-0">
        <?php if (count($transacoes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data Venc.</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Aluno</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transacoes as $transacao): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($transacao['data_vencimento'])) ?></td>
                                <td><strong><?= sanitizar($transacao['descricao']) ?></strong></td>
                                <td><?= sanitizar($transacao['categoria']) ?></td>
                                <td>
                                    <?php if (!empty($transacao['aluno_nome'])): ?>
                                        <?= sanitizar($transacao['aluno_nome']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $transacao['tipo'] == 'receita' ? 'success' : 'danger' ?>">
                                        <?= $transacao['tipo'] == 'receita' ? 'Receita' : 'Despesa' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'secondary';
                                    $status_text = $transacao['status'];
                                    if ($transacao['status'] == 'pago') {
                                        $status_class = 'success';
                                        $status_text = 'Pago';
                                    } elseif ($transacao['status'] == 'pendente') {
                                        $status_class = 'warning';
                                        $status_text = 'Pendente';
                                    } elseif ($transacao['status'] == 'cancelado') {
                                        $status_class = 'danger';
                                        $status_text = 'Cancelado';
                                    }
                                    ?>
                                    <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td class="text-end fw-bold <?= $transacao['tipo'] == 'receita' ? 'text-success' : 'text-danger' ?>">
                                    <?= $transacao['tipo'] == 'receita' ? '+' : '-' ?>R$ <?= number_format($transacao['valor'], 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Nenhuma transação encontrada</h5>
                <p class="text-muted">Não há transações registradas no período selecionado.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Gráfico de Evolução Financeira
<?php if (count($evolucao) > 0): ?>
const ctxEvolucao = document.getElementById('graficoEvolucao').getContext('2d');
new Chart(ctxEvolucao, {
    type: 'line',
    data: {
        labels: [<?= implode(', ', $labels_meses) ?>],
        datasets: [
            {
                label: 'Receitas',
                data: [<?= implode(', ', $dados_receitas) ?>],
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Despesas',
                data: [<?= implode(', ', $dados_despesas) ?>],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Gráfico de Receitas por Categoria
<?php if (count($receitas_categoria) > 0): ?>
const ctxCategorias = document.getElementById('graficoCategorias').getContext('2d');
new Chart(ctxCategorias, {
    type: 'doughnut',
    data: {
        labels: [<?= implode(', ', $labels_categorias) ?>],
        datasets: [{
            data: [<?= implode(', ', $dados_categorias) ?>],
            backgroundColor: [<?= implode(', ', $cores_categorias) ?>],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
