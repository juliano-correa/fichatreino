<?php
// Caixas - Novo Caixa
$titulo_pagina = 'Novo Caixa';
$subtitulo_pagina = 'Cadastrar Novo Caixa';

require_once '../../includes/auth_check.php';
require_once '../../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem acessar o gerenciamento de caixas.';
    redirecionar('../../index.php');
}

$error = '';
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? 'tesouraria';
    $saldo_inicial = str_replace(['.', ','], ['', '.'], $_POST['saldo_inicial'] ?? '0');
    $saldo_inicial = (float)$saldo_inicial;
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações
    if (empty($nome)) {
        $error = 'O nome do caixa é obrigatório.';
    } elseif (strlen($nome) < 3) {
        $error = 'O nome deve ter pelo menos 3 caracteres.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO cash_registers (
                gym_id, nome, tipo, saldo_atual, saldo_inicial, status, data_abertura, observacoes
            ) VALUES (
                :gym_id, :nome, :tipo, :saldo_atual, :saldo_inicial, 'aberto', NOW(), :observacoes
            )");
            
            $stmt->execute([
                ':gym_id' => getGymId(),
                ':nome' => $nome,
                ':tipo' => $tipo,
                ':saldo_atual' => $saldo_inicial,
                ':saldo_inicial' => $saldo_inicial,
                ':observacoes' => !empty($observacoes) ? $observacoes : null
            ]);
            
            $caixa_id = $pdo->lastInsertId();
            
            // Se houver saldo inicial, registrar movimentação
            if ($saldo_inicial > 0) {
                $stmt = $pdo->prepare("INSERT INTO cash_movements (
                    caixa_id, gym_id, tipo, valor, observacoes, usuario_id
                ) VALUES (
                    :caixa_id, :gym_id, 'suprimento', :valor, 'Saldo inicial do caixa', :usuario_id
                )");
                $stmt->execute([
                    ':caixa_id' => $caixa_id,
                    ':gym_id' => getGymId(),
                    ':valor' => $saldo_inicial,
                    ':usuario_id' => $_SESSION['user_id']
                ]);
            }
            
            $_SESSION['success'] = 'Caixa "' . sanitizar($nome) . '" criado com sucesso!';
            header('Location: index.php');
            exit;
            
        } catch (PDOException $e) {
            $error = 'Erro ao criar caixa: ' . $e->getMessage();
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
        <li class="breadcrumb-item active">Novo Caixa</li>
    </ol>
</nav>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
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
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= sanitizar($_POST['nome'] ?? '') ?>" placeholder="Ex: Caixa Tesouraria Principal" required>
                            <small class="text-muted">Nome identificador do caixa</small>
                        </div>
                        <div class="col-md-4">
                            <label for="tipo" class="form-label fw-bold">Tipo *</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="tesouraria" <?= ($_POST['tipo'] ?? '') === 'tesouraria' ? 'selected' : '' ?>>Tesouraria</option>
                                <option value="banco" <?= ($_POST['tipo'] ?? '') === 'banco' ? 'selected' : '' ?>>Banco</option>
                                <option value="pix" <?= ($_POST['tipo'] ?? '') === 'pix' ? 'selected' : '' ?>>PIX</option>
                                <option value="cartao" <?= ($_POST['tipo'] ?? '') === 'cartao' ? 'selected' : '' ?>>Cartão</option>
                                <option value="outros" <?= ($_POST['tipo'] ?? '') === 'outros' ? 'selected' : '' ?>>Outros</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="saldo_inicial" class="form-label fw-bold">Saldo Inicial (R$)</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control valor-input" id="saldo_inicial" name="saldo_inicial" value="<?= sanitizar($_POST['saldo_inicial'] ?? '0,00') ?>" placeholder="0,00">
                            </div>
                            <small class="text-muted">Valor inicial no caixa</small>
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
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais sobre este caixa..."><?= sanitizar($_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Barra Lateral -->
        <div class="col-lg-4">
            <!-- Ações -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Salvar Caixa
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
            
            <!-- Info -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Informações
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small list-unstyled text-muted">
                        <li class="mb-2">O caixa será criado com status <strong>Aberto</strong></li>
                        <li class="mb-2">O saldo inicial será registrado como um suprimento</li>
                        <li class="mb-2">Apenas administradores podem criar e editar caixas</li>
                    </ul>
                </div>
            </div>
            
            <!-- Tipos de Caixa -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-lightbulb me-2"></i>Tipos de Caixa
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small list-unstyled">
                        <li class="mb-2"><span class="badge bg-primary">Tesouraria</span> - Dinheiro físico em mãos</li>
                        <li class="mb-2"><span class="badge bg-info">Banco</span> - Conta bancária da academia</li>
                        <li class="mb-2"><span class="badge bg-success">PIX</span> - Chave PIX da academia</li>
                        <li class="mb-2"><span class="badge bg-warning">Cartão</span> - Máquina de cartão</li>
                        <li class="mb-2"><span class="badge bg-secondary">Outros</span> - Outros meios</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Formatação de valor monetário
document.querySelectorAll('.valor-input').forEach(input => {
    input.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 0) {
            value = (parseInt(value) / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        this.value = value;
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
