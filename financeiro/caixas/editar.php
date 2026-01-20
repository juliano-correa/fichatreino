<?php
// Caixas - Editar Caixa
$titulo_pagina = 'Editar Caixa';
$subtitulo_pagina = 'Alterar Dados do Caixa';

require_once '../../includes/auth_check.php';
require_once '../../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem acessar o gerenciamento de caixas.';
    redirecionar('../../index.php');
}

$error = '';
$success = '';

// Verificar ID do caixa
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirecionar('index.php');
}

$caixa_id = (int)$_GET['id'];

// Buscar caixa existente
try {
    $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $caixa_id, ':gym_id' => getGymId()]);
    $caixa = $stmt->fetch();
    
    if (!$caixa) {
        redirecionar('index.php');
    }
} catch (PDOException $e) {
    $error = 'Erro ao carregar caixa: ' . $e->getMessage();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? 'tesouraria';
    $status = $_POST['status'] ?? 'aberto';
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações
    if (empty($nome)) {
        $error = 'O nome do caixa é obrigatório.';
    } elseif (strlen($nome) < 3) {
        $error = 'O nome deve ter pelo menos 3 caracteres.';
    } else {
        try {
            // Se estiver fechando o caixa, registrar data de fechamento
            $data_fechamento = '';
            if ($status === 'fechado' && $caixa['status'] === 'aberto') {
                $data_fechamento = ', data_fechamento = NOW()';
            } elseif ($status === 'aberto' && $caixa['status'] === 'fechado') {
                $data_fechamento = ', data_abertura = NOW(), data_fechamento = NULL';
            }
            
            $stmt = $pdo->prepare("UPDATE cash_registers SET 
                nome = :nome, 
                tipo = :tipo,
                status = :status,
                observacoes = :observacoes
                $data_fechamento
            WHERE id = :id AND gym_id = :gym_id");
            
            $stmt->execute([
                ':id' => $caixa_id,
                ':gym_id' => getGymId(),
                ':nome' => $nome,
                ':tipo' => $tipo,
                ':status' => $status,
                ':observacoes' => !empty($observacoes) ? $observacoes : null
            ]);
            
            $success = 'Caixa atualizado com sucesso!';
            
            // Recarregar dados do caixa
            $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $caixa_id, ':gym_id' => getGymId()]);
            $caixa = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar caixa: ' . $e->getMessage();
        }
    }
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../index.php">Financeiro</a></li>
        <li class="breadcrumb-item"><a href="index.php">Caixas</a></li>
        <li class="breadcrumb-item active">Editar Caixa</li>
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

<form method="POST">
    <div class="row g-4">
        <!-- Formulário Principal -->
        <div class="col-lg-8">
            <!-- Dados do Caixa -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-safe2 me-2"></i>Dados do Caixa
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="nome" class="form-label fw-bold">Nome do Caixa *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= sanitizar($caixa['nome'] ?? $_POST['nome'] ?? '') ?>" placeholder="Ex: Caixa Tesouraria Principal" required>
                        </div>
                        <div class="col-md-4">
                            <label for="tipo" class="form-label fw-bold">Tipo *</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="tesouraria" <?= ($caixa['tipo'] ?? $_POST['tipo'] ?? '') === 'tesouraria' ? 'selected' : '' ?>>Tesouraria</option>
                                <option value="banco" <?= ($caixa['tipo'] ?? $_POST['tipo'] ?? '') === 'banco' ? 'selected' : '' ?>>Banco</option>
                                <option value="pix" <?= ($caixa['tipo'] ?? $_POST['tipo'] ?? '') === 'pix' ? 'selected' : '' ?>>PIX</option>
                                <option value="cartao" <?= ($caixa['tipo'] ?? $_POST['tipo'] ?? '') === 'cartao' ? 'selected' : '' ?>>Cartão</option>
                                <option value="outros" <?= ($caixa['tipo'] ?? $_POST['tipo'] ?? '') === 'outros' ? 'selected' : '' ?>>Outros</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Saldo Atual</label>
                            <div class="form-control bg-light fw-bold <?= ($caixa['saldo_atual'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                R$ <?= number_format($caixa['saldo_atual'] ?? 0, 2, ',', '.') ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label fw-bold">Status *</label>
                            <select class="form-select" id="status" name="status">
                                <option value="aberto" <?= ($caixa['status'] ?? $_POST['status'] ?? '') === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                                <option value="fechado" <?= ($caixa['status'] ?? $_POST['status'] ?? '') === 'fechado' ? 'selected' : '' ?>>Fechado</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Observações -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-sticky me-2"></i>Observações
                    </h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais sobre este caixa..."><?= sanitizar($caixa['observacoes'] ?? $_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Barra Lateral -->
        <div class="col-lg-4">
            <!-- Ações -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Salvar Alterações
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary w-100 mb-2">
                        <i class="bi bi-arrow-left me-2"></i>Voltar
                    </a>
                    <a href="excluir.php?id=<?= $caixa_id ?>" class="btn btn-outline-danger w-100" onclick="return confirm('Tem certeza que deseja excluir este caixa?');">
                        <i class="bi bi-trash me-2"></i>Excluir
                    </a>
                </div>
            </div>
            
            <!-- Info do Caixa -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Informações
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small list-unstyled">
                        <li class="mb-2">
                            <strong>Criado em:</strong><br>
                            <?= formatarDataHora($caixa['created_at'] ?? '') ?>
                        </li>
                        <?php if (!empty($caixa['data_abertura'])): ?>
                            <li class="mb-2">
                                <strong>Abertura:</strong><br>
                                <?= formatarDataHora($caixa['data_abertura']) ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($caixa['data_fechamento'])): ?>
                            <li class="mb-2">
                                <strong>Fechamento:</strong><br>
                                <?= formatarDataHora($caixa['data_fechamento']) ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
