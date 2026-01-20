<?php
// Caixas - Listagem
$titulo_pagina = 'Caixas';
$subtitulo_pagina = 'Gerenciamento de Caixas';

require_once '../../includes/auth_check.php';
require_once '../../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem acessar o gerenciamento de caixas.';
    redirecionar('../../index.php');
}

$error = '';
$success = '';

// Obter lista de caixas
try {
    $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE gym_id = :gym_id ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $caixas = $stmt->fetchAll();
    
    // Calcular total em caixa
    $total_caixas = 0;
    foreach ($caixas as $caixa) {
        $total_caixas += $caixa['saldo_atual'];
    }
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar caixas: ' . $e->getMessage();
    $caixas = [];
    $total_caixas = 0;
}

// Processar abertura/fechamento de caixa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'abrir' && isset($_POST['caixa_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE cash_registers SET status = 'aberto', data_abertura = NOW() WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $_POST['caixa_id'], ':gym_id' => getGymId()]);
            $success = 'Caixa aberto com sucesso!';
            
            // Recarregar caixas
            $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE gym_id = :gym_id ORDER BY nome");
            $stmt->execute([':gym_id' => getGymId()]);
            $caixas = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $error = 'Erro ao abrir caixa: ' . $e->getMessage();
        }
    } elseif ($_POST['acao'] === 'fechar' && isset($_POST['caixa_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE cash_registers SET status = 'fechado', data_fechamento = NOW() WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $_POST['caixa_id'], ':gym_id' => getGymId()]);
            $success = 'Caixa fechado com sucesso!';
            
            // Recarregar caixas
            $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE gym_id = :gym_id ORDER BY nome");
            $stmt->execute([':gym_id' => getGymId()]);
            $caixas = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $error = 'Erro ao fechar caixa: ' . $e->getMessage();
        }
    }
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../index.php">Financeiro</a></li>
        <li class="breadcrumb-item active">Caixas</li>
    </ol>
</nav>

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

<!-- Resumo -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-0 text-white-50">Total em Caixa</p>
                        <h3 class="mb-0">R$ <?= number_format($total_caixas, 2, ',', '.') ?></h3>
                    </div>
                    <i class="bi bi-safe2 fs-1 text-white-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-0 text-white-50">Caixas Abertos</p>
                        <h3 class="mb-0"><?= count(array_filter($caixas, fn($c) => $c['status'] === 'aberto')) ?></h3>
                    </div>
                    <i class="bi bi-unlock-fill fs-1 text-white-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-0 text-white-50">Caixas Fechados</p>
                        <h3 class="mb-0"><?= count(array_filter($caixas, fn($c) => $c['status'] === 'fechado')) ?></h3>
                    </div>
                    <i class="bi bi-lock-fill fs-1 text-white-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ações -->
<div class="d-flex gap-2 mb-4">
    <a href="novo.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Novo Caixa
    </a>
    <a href="../relatorio_caixa.php" class="btn btn-outline-dark">
        <i class="bi bi-file-earmark-pdf me-2"></i>Relatório PDF
    </a>
    <a href="../transferir.php" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left-right me-2"></i>Transferir
    </a>
    <a href="../suprimento.php" class="btn btn-outline-success">
        <i class="bi bi-box-arrow-in-right me-2"></i>Suprimento
    </a>
    <a href="../sangria.php" class="btn btn-outline-danger">
        <i class="bi bi-box-arrow-out-right me-2"></i>Sangria
    </a>
</div>

<!-- Lista de Caixas -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">
            <i class="bi bi-list me-2"></i>Lista de Caixas
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($caixas)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                <p class="mb-0">Nenhum caixa cadastrado ainda.</p>
                <a href="novo.php" class="btn btn-primary btn-sm mt-2">
                    <i class="bi bi-plus-lg me-1"></i>Cadastrar primeiro caixa
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Caixa</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th class="text-end">Saldo Atual</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caixas as $caixa): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= sanitizar($caixa['nome']) ?></div>
                                    <small class="text-muted"><?= sanitizar($caixa['descricao'] ?? 'Sem descrição') ?></small>
                                </td>
                                <td>
                                    <?php
                                    $tipo_labels = [
                                        'principal' => 'Principal',
                                        'banco' => 'Banco',
                                        'reserva' => 'Reserva',
                                        'eventos' => 'Eventos'
                                    ];
                                    echo $tipo_labels[$caixa['tipo']] ?? sanitizar($caixa['tipo']);
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $caixa['status'] === 'aberto' ? 'success' : 'secondary' ?>">
                                        <?= $caixa['status'] === 'aberto' ? 'Aberto' : 'Fechado' ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold <?= $caixa['saldo_atual'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    R$ <?= number_format($caixa['saldo_atual'], 2, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="historico.php?id=<?= $caixa['id'] ?>" class="btn btn-outline-secondary" title="Histórico">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="editar.php?id=<?= $caixa['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($caixa['status'] === 'fechado'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="acao" value="abrir">
                                                <input type="hidden" name="caixa_id" value="<?= $caixa['id'] ?>">
                                                <button type="submit" class="btn btn-outline-success" title="Abrir Caixa">
                                                    <i class="bi bi-unlock"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="acao" value="fechar">
                                                <input type="hidden" name="caixa_id" value="<?= $caixa['id'] ?>">
                                                <button type="submit" class="btn btn-outline-warning" title="Fechar Caixa" onclick="return confirm('Tem certeza que deseja fechar este caixa?');">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="excluir.php?id=<?= $caixa['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este caixa?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
