<?php
// Histórico de Movimentações
$titulo_pagina = 'Histórico de Movimentações';
$subtitulo_pagina = 'Registro de Todas as Movimentações';

require_once '../../includes/auth_check.php';
require_once '../../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem visualizar o histórico de movimentações.';
    redirecionar('../../index.php');
}

$error = '';
$success = '';

// Configuração de filtros
$caixa_filtro = $_GET['caixa'] ?? 'todos';
$tipo_filtro = $_GET['tipo'] ?? 'todos';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Obter caixas para filtro
try {
    $stmt = $pdo->prepare("SELECT id, nome FROM cash_registers WHERE gym_id = :gym_id ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $caixas_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $caixas_disponiveis = [];
}

// Obter movimentações
try {
    $sql = "SELECT 
                m.*,
                c.nome as caixa_nome,
                c.tipo as caixa_tipo,
                u.nome as usuario_nome,
                cd.nome as caixa_destino_nome
            FROM cash_movements m
            LEFT JOIN cash_registers c ON m.caixa_id = c.id
            LEFT JOIN users u ON m.usuario_id = u.id
            LEFT JOIN cash_registers cd ON m.caixa_destino_id = cd.id
            WHERE m.gym_id = :gym_id";
    
    if ($caixa_filtro !== 'todos') {
        $sql .= " AND m.caixa_id = :caixa";
    }
    
    if ($tipo_filtro !== 'todos') {
        $sql .= " AND m.tipo = :tipo";
    }
    
    $sql .= " AND DATE(m.created_at) BETWEEN :data_inicio AND :data_fim";
    $sql .= " ORDER BY m.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    
    if ($caixa_filtro !== 'todos') {
        $stmt->bindValue(':caixa', $caixa_filtro, PDO::PARAM_INT);
    }
    
    if ($tipo_filtro !== 'todos') {
        $stmt->bindValue(':tipo', $tipo_filtro, PDO::PARAM_STR);
    }
    
    $stmt->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
    $stmt->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
    $stmt->execute();
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erro ao carregar movimentações: ' . $e->getMessage();
    $movimentacoes = [];
}

// Labels para tipos
$tipo_labels = [
    'entrada' => ['label' => 'Entrada', 'class' => 'text-success', 'icon' => 'bi-arrow-down-circle'],
    'saida' => ['label' => 'Saída', 'class' => 'text-danger', 'icon' => 'bi-arrow-up-circle'],
    'transferencia_entrada' => ['label' => 'Transf. Entrada', 'class' => 'text-info', 'icon' => 'bi-arrow-left-circle'],
    'transferencia_saida' => ['label' => 'Transf. Saída', 'class' => 'text-warning', 'icon' => 'bi-arrow-right-circle'],
    'sangria' => ['label' => 'Sangria', 'class' => 'text-danger', 'icon' => 'bi-cash-stack'],
    'suprimento' => ['label' => 'Suprimento', 'class' => 'text-success', 'icon' => 'bi-plus-circle'],
    'ajuste' => ['label' => 'Ajuste', 'class' => 'text-secondary', 'icon' => 'bi-arrow-repeat']
];

// Totais por tipo
$totais = [
    'entradas' => 0,
    'saidas' => 0
];
foreach ($movimentacoes as $m) {
    $tipo = $m['tipo'];
    if (in_array($tipo, ['entrada', 'transferencia_entrada', 'suprimento'])) {
        $totais['entradas'] += (float)$m['valor'];
    } else {
        $totais['saidas'] += (float)$m['valor'];
    }
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../index.php">Financeiro</a></li>
        <li class="breadcrumb-item"><a href="index.php">Caixas</a></li>
        <li class="breadcrumb-item active">Histórico</li>
    </ol>
</nav>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Resumo -->
<div class="row mb-4 g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-down-circle fs-3 text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Entradas</p>
                        <h4 class="mb-0 fw-bold text-success">R$ <?= number_format($totais['entradas'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-up-circle fs-3 text-danger"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Saídas</p>
                        <h4 class="mb-0 fw-bold text-danger">R$ <?= number_format($totais['saidas'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="caixa" class="form-label">Caixa</label>
                <select class="form-select" id="caixa" name="caixa">
                    <option value="todos" <?= $caixa_filtro == 'todos' ? 'selected' : '' ?>>Todos os Caixas</option>
                    <?php foreach ($caixas_disponiveis as $caixa): ?>
                        <option value="<?= $caixa['id'] ?>" <?= $caixa_filtro == $caixa['id'] ? 'selected' : '' ?>>
                            <?= sanitizar($caixa['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="tipo" class="form-label">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="todos" <?= $tipo_filtro == 'todos' ? 'selected' : '' ?>>Todos os Tipos</option>
                    <option value="entrada" <?= $tipo_filtro == 'entrada' ? 'selected' : '' ?>>Entrada</option>
                    <option value="saida" <?= $tipo_filtro == 'saida' ? 'selected' : '' ?>>Saída</option>
                    <option value="transferencia_entrada" <?= $tipo_filtro == 'transferencia_entrada' ? 'selected' : '' ?>>Transferência (Entrada)</option>
                    <option value="transferencia_saida" <?= $tipo_filtro == 'transferencia_saida' ? 'selected' : '' ?>>Transferência (Saída)</option>
                    <option value="sangria" <?= $tipo_filtro == 'sangria' ? 'selected' : '' ?>>Sangria</option>
                    <option value="suprimento" <?= $tipo_filtro == 'suprimento' ? 'selected' : '' ?>>Suprimento</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= $data_inicio ?>">
            </div>
            <div class="col-md-2">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= $data_fim ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Movimentações -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Movimentações</h5>
            <span class="badge bg-secondary"><?= count($movimentacoes) ?> registros</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (count($movimentacoes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data/Hora</th>
                            <th>Caixa</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Destino/Origem</th>
                            <th>Observações</th>
                            <th>Usuário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimentacoes as $m): ?>
                            <?php $tipo_info = $tipo_labels[$m['tipo']] ?? ['label' => $m['tipo'], 'class' => '', 'icon' => 'bi-circle']; ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                                <td><?= sanitizar($m['caixa_nome']) ?></td>
                                <td>
                                    <span class="<?= $tipo_info['class'] ?>">
                                        <i class="bi <?= $tipo_info['icon'] ?> me-1"></i>
                                        <?= $tipo_info['label'] ?>
                                    </span>
                                </td>
                                <td class="fw-bold <?= in_array($m['tipo'], ['entrada', 'transferencia_entrada', 'suprimento']) ? 'text-success' : 'text-danger' ?>">
                                    <?= in_array($m['tipo'], ['entrada', 'transferencia_entrada', 'suprimento']) ? '+' : '-' ?>R$ <?= number_format($m['valor'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?php if (!empty($m['caixa_destino_nome'])): ?>
                                        <?= sanitizar($m['caixa_destino_nome']) ?>
                                    <?php elseif (!empty($m['observacoes'])): ?>
                                        <small class="text-muted"><?= substr(sanitizar($m['observacoes']), 0, 30) ?><?= strlen($m['observacoes']) > 30 ? '...' : '' ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= !empty($m['observacoes']) ? substr(sanitizar($m['observacoes']), 0, 40) . (strlen($m['observacoes']) > 40 ? '...' : '') : '-' ?></small>
                                </td>
                                <td>
                                    <small><?= sanitizar($m['usuario_nome']) ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Nenhuma movimentação encontrada</h5>
                <p class="text-muted">Não há registros para os filtros selecionados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Voltar -->
<div class="mt-4">
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Voltar aos Caixas
    </a>
</div>

<?php include '../../includes/footer.php'; ?>
