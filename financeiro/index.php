<?php
// Financeiro - Dashboard
$titulo_pagina = 'Financeiro';
$subtitulo_pagina = 'Controle Financeiro';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Configuração de filtros
$mes_filtro = $_GET['mes'] ?? date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');
$tipo_filtro = $_GET['tipo'] ?? 'todos';
$status_filtro = $_GET['status'] ?? 'todos';

// Obter dados resumidos do financeiro
try {
    $sql_resumo = "SELECT 
        CAST(COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS DECIMAL(10,2)) as total_receitas,
        CAST(COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) AS DECIMAL(10,2)) as total_despesas,
        COALESCE(COUNT(*), 0) as total_transacoes
    FROM transactions 
    WHERE gym_id = :gym_id
    AND MONTH(data_vencimento) = :mes
    AND YEAR(data_vencimento) = :ano";

    $stmt_resumo = $pdo->prepare($sql_resumo);
    $stmt_resumo->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_resumo->bindValue(':mes', $mes_filtro, PDO::PARAM_STR);
    $stmt_resumo->bindValue(':ano', $ano_filtro, PDO::PARAM_STR);
    $stmt_resumo->execute();
    $resumo = $stmt_resumo->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $resumo = ['total_receitas' => 0, 'total_despesas' => 0, 'total_transacoes' => 0];
}

$saldo = (float)($resumo['total_receitas'] ?? 0) - (float)($resumo['total_despesas'] ?? 0);

// Obter saldo dos caixas (apenas abertos)
try {
    $stmt_caixas = $pdo->prepare("SELECT id, nome, tipo, saldo_atual FROM cash_registers WHERE gym_id = :gym_id AND status = 'aberto' ORDER BY nome");
    $stmt_caixas->execute([':gym_id' => getGymId()]);
    $caixas = $stmt_caixas->fetchAll(PDO::FETCH_ASSOC);
    
    $total_caixas = 0;
    foreach ($caixas as $caixa) {
        $total_caixas += (float)$caixa['saldo_atual'];
    }
} catch (PDOException $e) {
    $caixas = [];
    $total_caixas = 0;
}

// Obter lista de transações
try {
    $sql_transacoes = "SELECT t.*, s.nome as aluno_nome, c.nome as caixa_nome
                       FROM transactions t
                       LEFT JOIN students s ON t.aluno_id = s.id
                       LEFT JOIN cash_registers c ON t.caixa_id = c.id
                       WHERE t.gym_id = :gym_id 
                       AND MONTH(t.data_vencimento) = :mes 
                       AND YEAR(t.data_vencimento) = :ano";

    if ($tipo_filtro !== 'todos') {
        $sql_transacoes .= " AND t.tipo = :tipo";
    }
    
    if ($status_filtro !== 'todos') {
        $sql_transacoes .= " AND t.status = :status";
    }

    $sql_transacoes .= " ORDER BY t.data_vencimento DESC, t.created_at DESC";

    $stmt_transacoes = $pdo->prepare($sql_transacoes);
    $stmt_transacoes->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_transacoes->bindValue(':mes', $mes_filtro, PDO::PARAM_STR);
    $stmt_transacoes->bindValue(':ano', $ano_filtro, PDO::PARAM_STR);
    if ($tipo_filtro !== 'todos') {
        $stmt_transacoes->bindValue(':tipo', $tipo_filtro, PDO::PARAM_STR);
    }
    if ($status_filtro !== 'todos') {
        $stmt_transacoes->bindValue(':status', $status_filtro, PDO::PARAM_STR);
    }
    $stmt_transacoes->execute();
    $transacoes = $stmt_transacoes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $transacoes = [];
}

// Obter alunos para o filtro
try {
    $sql_alunos = "SELECT id, nome FROM students WHERE gym_id = :gym_id ORDER BY nome";
    $stmt_alunos = $pdo->prepare($sql_alunos);
    $stmt_alunos->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_alunos->execute();
    $alunos = $stmt_alunos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alunos = [];
}

// Meses para o select
$meses = [
    '01' => 'Janeiro',
    '02' => 'Fevereiro',
    '03' => 'Março',
    '04' => 'Abril',
    '05' => 'Maio',
    '06' => 'Junho',
    '07' => 'Julho',
    '08' => 'Agosto',
    '09' => 'Setembro',
    '10' => 'Outubro',
    '11' => 'Novembro',
    '12' => 'Dezembro'
];

// Anos para o select (últimos 5 anos)
$anos = range(date('Y') - 2, date('Y') + 1);
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

<!-- Cards de Resumo -->
<div class="row mb-4 g-3">
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-up-circle fs-3 text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Receitas</p>
                        <h4 class="mb-0 fw-bold text-success">R$ <?= number_format((float)$resumo['total_receitas'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-down-circle fs-3 text-danger"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Despesas</p>
                        <h4 class="mb-0 fw-bold text-danger">R$ <?= number_format((float)$resumo['total_despesas'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
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
    
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-info bg-opacity-10 p-3 me-3">
                        <i class="bi bi-cash-coin fs-3 text-info"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total em Caixa</p>
                        <h4 class="mb-0 fw-bold text-info">R$ <?= number_format($total_caixas, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isAdmin() && count($caixas) > 0): ?>
<!-- Resumo dos Caixas -->
<div class="row mb-4 g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-safe2 me-2"></i>Situação dos Caixas</h5>
                    <a href="caixas/index.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-gear me-1"></i>Gerenciar Caixas
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($caixas as $caixa): ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong><?= sanitizar($caixa['nome']) ?></strong>
                                    <span class="badge bg-success">Aberto</span>
                                </div>
                                <h5 class="mb-0 <?= $caixa['saldo_atual'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    R$ <?= number_format($caixa['saldo_atual'], 2, ',', '.') ?>
                                </h5>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros e Ações -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h5>
            <div class="d-flex gap-2">
                <?php if (isAdmin()): ?>
                    <a href="caixas/index.php" class="btn btn-outline-info">
                        <i class="bi bi-safe2 me-2"></i>Caixas
                    </a>
                    <a href="transferir.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left-right me-2"></i>Transferir
                    </a>
                    <a href="sangria.php" class="btn btn-outline-danger">
                        <i class="bi bi-cash-stack me-2"></i>Sangria
                    </a>
                    <a href="suprimento.php" class="btn btn-outline-success">
                        <i class="bi bi-plus-circle me-2"></i>Suprimento
                    </a>
                <?php endif; ?>
                <a href="novo.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Nova Transação
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="mes" class="form-label">Mês</label>
                <select class="form-select" id="mes" name="mes">
                    <?php foreach ($meses as $key => $mes): ?>
                        <option value="<?= $key ?>" <?= $mes_filtro == $key ? 'selected' : '' ?>><?= $mes ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="ano" class="form-label">Ano</label>
                <select class="form-select" id="ano" name="ano">
                    <?php foreach ($anos as $ano): ?>
                        <option value="<?= $ano ?>" <?= $ano_filtro == $ano ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="tipo" class="form-label">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="todos" <?= $tipo_filtro == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="receita" <?= $tipo_filtro == 'receita' ? 'selected' : '' ?>>Receitas</option>
                    <option value="despesa" <?= $tipo_filtro == 'despesa' ? 'selected' : '' ?>>Despesas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="todos" <?= $status_filtro == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="pago" <?= $status_filtro == 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="pendente" <?= $status_filtro == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelado" <?= $status_filtro == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="index.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-clockwise me-2"></i>Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Transações -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table me-2"></i>Transações</h5>
            <a href="../relatorios/index.php?data_inicio=<?= $ano_filtro ?>-<?= $mes_filtro ?>-01&data_fim=<?= $ano_filtro ?>-<?= $mes_filtro ?>-<?= date('t', mktime(0, 0, 0, $mes_filtro, 1, $ano_filtro)) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-graph-up me-1"></i>Relatório
            </a>
        </div>
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
                            <th>Caixa</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Valor</th>
                            <th>Ações</th>
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
                                    <?php if (!empty($transacao['caixa_nome'])): ?>
                                        <span class="badge bg-info"><?= sanitizar($transacao['caixa_nome']) ?></span>
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
                                <td class="fw-bold <?= $transacao['tipo'] == 'receita' ? 'text-success' : 'text-danger' ?>">
                                    <?= $transacao['tipo'] == 'receita' ? '+' : '-' ?>R$ <?= number_format($transacao['valor'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="visualizar.php?id=<?= $transacao['id'] ?>" class="btn btn-outline-primary" title="Visualizar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="editar.php?id=<?= $transacao['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="excluir.php?id=<?= $transacao['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir esta transação?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-currency-exchange fs-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Nenhuma transação encontrada</h5>
                <p class="text-muted">Não há transações registradas para o período selecionado.</p>
                <a href="novo.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Nova Transação
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
