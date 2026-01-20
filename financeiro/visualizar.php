<?php
// Financeiro - Visualizar Transação
$titulo_pagina = 'Detalhes da Transação';
$subtitulo_pagina = 'Informações Completas';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';

// Verificar ID da transação
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirecionar('index.php');
}

$transacao_id = (int)$_GET['id'];

// Buscar transação com dados relacionados
try {
    $stmt = $pdo->prepare("
        SELECT t.*, s.nome as aluno_nome, s.telefone as aluno_telefone, s.email as aluno_email,
               c.nome as caixa_nome, c.saldo_atual as caixa_saldo
        FROM transactions t
        LEFT JOIN students s ON t.aluno_id = s.id
        LEFT JOIN cash_registers c ON t.caixa_id = c.id
        WHERE t.id = :id AND t.gym_id = :gym_id
    ");
    $stmt->execute([':id' => $transacao_id, ':gym_id' => getGymId()]);
    $transacao = $stmt->fetch();
    
    if (!$transacao) {
        redirecionar('index.php');
    }
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar transação: ' . $e->getMessage();
}
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Financeiro</a></li>
        <li class="breadcrumb-item active">Visualizar Transação #<?= $transacao_id ?></li>
    </ol>
</nav>

<!-- Mensagens de Erro -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Coluna Principal -->
    <div class="col-lg-8">
        <!-- Cabeçalho da Transação -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge bg-<?= $transacao['tipo'] === 'receita' ? 'success' : 'danger' ?> mb-2">
                            <?= $transacao['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?>
                        </span>
                        <h3 class="mb-1"><?= sanitizar($transacao['descricao']) ?></h3>
                        <span class="badge bg-<?= $transacao['status'] === 'pago' ? 'success' : ($transacao['status'] === 'pendente' ? 'warning' : 'danger') ?>">
                            <?= $transacao['status'] === 'pago' ? 'Pago' : ($transacao['status'] === 'pendente' ? 'Pendente' : 'Cancelado') ?>
                        </span>
                    </div>
                    <div class="text-end">
                        <h2 class="mb-0 <?= $transacao['tipo'] === 'receita' ? 'text-success' : 'text-danger' ?>">
                            <?= $transacao['tipo'] === 'receita' ? '+' : '-' ?>R$ <?= number_format($transacao['valor'], 2, ',', '.') ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detalhes da Transação -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Detalhes da Transação
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small">Data de Vencimento</label>
                        <p class="mb-0 fw-bold"><?= formatarData($transacao['data_vencimento']) ?></p>
                    </div>
                    <?php if (!empty($transacao['data_pagamento'])): ?>
                        <div class="col-md-4">
                            <label class="text-muted small">Data de Pagamento</label>
                            <p class="mb-0 fw-bold text-success"><?= formatarData($transacao['data_pagamento']) ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="text-muted small">Categoria</label>
                        <p class="mb-0 fw-bold"><?= sanitizar($transacao['categoria']) ?></p>
                    </div>
                    <?php if (!empty($transacao['forma_pagamento'])): ?>
                        <div class="col-md-4">
                            <label class="text-muted small">Forma de Pagamento</label>
                            <p class="mb-0"><?= sanitizar($transacao['forma_pagamento']) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($transacao['caixa_nome'])): ?>
                        <div class="col-md-4">
                            <label class="text-muted small">Caixa</label>
                            <p class="mb-0">
                                <i class="bi bi-safe2 text-primary me-1"></i>
                                <?= sanitizar($transacao['caixa_nome']) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="text-muted small">Criado em</label>
                        <p class="mb-0"><?= formatarDataHora($transacao['created_at']) ?></p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Última atualização</label>
                        <p class="mb-0"><?= formatarDataHora($transacao['updated_at']) ?></p>
                    </div>
                    <?php if (!empty($transacao['observacoes'])): ?>
                        <div class="col-12">
                            <label class="text-muted small">Observações</label>
                            <p class="mb-0"><?= nl2br(sanitizar($transacao['observacoes'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Aluno Vinculado -->
        <?php if (!empty($transacao['aluno_id'])): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-badge me-2"></i>Aluno Vinculado
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1"><?= sanitizar($transacao['aluno_nome']) ?></h5>
                            <p class="mb-0 text-muted">
                                <i class="bi bi-telephone me-1"></i><?= formatarTelefone($transacao['aluno_telefone']) ?>
                                <?php if (!empty($transacao['aluno_email'])): ?>
                                    <span class="mx-2">|</span>
                                    <i class="bi bi-envelope me-1"></i><?= sanitizar($transacao['aluno_email']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="../alunos/visualizar.php?id=<?= $transacao['aluno_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Ver Aluno
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Barra Lateral -->
    <div class="col-lg-4">
        <!-- Ações Rápidas -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightning me-2"></i>Ações
                </h5>
            </div>
            <div class="card-body">
                <a href="editar.php?id=<?= $transacao_id ?>" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-pencil me-2"></i>Editar Transação
                </a>
                <a href="index.php" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="bi bi-arrow-left me-2"></i>Voltar à Lista
                </a>
                <a href="excluir.php?id=<?= $transacao_id ?>" class="btn btn-outline-danger w-100" onclick="return confirm('Tem certeza que deseja excluir esta transação? Esta ação não pode ser desfeita.');">
                    <i class="bi bi-trash me-2"></i>Excluir Transação
                </a>
            </div>
        </div>
        
        <!-- Resumo -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart me-2"></i>Resumo
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Valor</span>
                    <span class="fw-bold <?= $transacao['tipo'] === 'receita' ? 'text-success' : 'text-danger' ?>">
                        R$ <?= number_format($transacao['valor'], 2, ',', '.') ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tipo</span>
                    <span class="<?= $transacao['tipo'] === 'receita' ? 'text-success' : 'text-danger' ?>">
                        <?= $transacao['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status</span>
                    <span class="badge bg-<?= $transacao['status'] === 'pago' ? 'success' : ($transacao['status'] === 'pendente' ? 'warning' : 'danger') ?>">
                        <?= $transacao['status'] === 'pago' ? 'Pago' : ($transacao['status'] === 'pendente' ? 'Pendente' : 'Cancelado') ?>
                    </span>
                </div>
                <?php if (!empty($transacao['caixa_nome'])): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Caixa</span>
                    <span><?= sanitizar($transacao['caixa_nome']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Histórico
                </h5>
            </div>
            <div class="card-body">
                <ul class="timeline mb-0">
                    <li class="timeline-item">
                        <small class="text-muted">Criação</small>
                        <p class="mb-0"><?= formatarDataHora($transacao['created_at']) ?></p>
                    </li>
                    <?php if (!empty($transacao['updated_at']) && $transacao['updated_at'] !== $transacao['created_at']): ?>
                        <li class="timeline-item">
                            <small class="text-muted">Última atualização</small>
                            <p class="mb-0"><?= formatarDataHora($transacao['updated_at']) ?></p>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    list-style: none;
    padding: 0;
    margin: 0;
}
.timeline-item {
    position: relative;
    padding-left: 20px;
    padding-bottom: 15px;
    border-left: 2px solid #e9ecef;
}
.timeline-item:last-child {
    border-left: none;
    padding-bottom: 0;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0d6efd;
}
</style>

<?php include '../includes/footer.php'; ?>
