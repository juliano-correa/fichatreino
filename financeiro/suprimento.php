<?php
// Suprimento - Entrada de valores no caixa
$titulo_pagina = 'Suprimento';
$subtitulo_pagina = 'Adicionar Valores ao Caixa';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem realizar suprimentos.';
    redirecionar('index.php');
}

$error = '';
$success = '';

// Obter caixas abertos
try {
    $stmt = $pdo->prepare("SELECT id, nome, tipo, saldo_atual FROM cash_registers WHERE gym_id = :gym_id AND status = 'aberto' ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $caixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erro ao carregar caixas: ' . $e->getMessage();
    $caixas = [];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caixa_id = $_POST['caixa_id'] ?? '';
    $valor = str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0');
    $valor = (float)$valor;
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações
    if (empty($caixa_id)) {
        $error = 'Selecione o caixa.';
    } elseif (empty($valor) || $valor <= 0) {
        $error = 'O valor deve ser maior que zero.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verificar se o caixa existe e está aberto
            $stmt = $pdo->prepare("SELECT id, nome FROM cash_registers WHERE id = :id AND gym_id = :gym_id AND status = 'aberto' FOR UPDATE");
            $stmt->execute([':id' => $caixa_id, ':gym_id' => getGymId()]);
            $caixa = $stmt->fetch();
            
            if (!$caixa) {
                throw new Exception('Caixa não encontrado ou está fechado.');
            }
            
            // Creditar no caixa
            $stmt = $pdo->prepare("UPDATE cash_registers SET saldo_atual = saldo_atual + :valor WHERE id = :id");
            $stmt->execute([':id' => $caixa_id, ':valor' => $valor]);
            
            // Registrar movimentação
            $stmt = $pdo->prepare("INSERT INTO cash_movements (caixa_id, gym_id, tipo, valor, observacoes, usuario_id) VALUES (:caixa_id, :gym_id, 'suprimento', :valor, :observacoes, :usuario_id)");
            $stmt->execute([
                ':caixa_id' => $caixa_id,
                ':gym_id' => getGymId(),
                ':valor' => $valor,
                ':observacoes' => !empty($observacoes) ? 'Suprimento: ' . $observacoes : 'Suprimento de valores',
                ':usuario_id' => $_SESSION['user_id']
            ]);
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Suprimento de R$ ' . number_format($valor, 2, ',', '.') . ' adicionado com sucesso!';
            header('Location: caixas/index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao realizar suprimento: ' . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Financeiro</a></li>
        <li class="breadcrumb-item"><a href="caixas/index.php">Caixas</a></li>
        <li class="breadcrumb-item active">Suprimento</li>
    </ol>
</nav>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (count($caixas) === 0): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Não há caixas abertos para realizar suprimento.
        <a href="caixas/novo.php" class="alert-link">Criar novo caixa</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="row g-4">
        <!-- Formulário Principal -->
        <div class="col-lg-8">
            <!-- Dados do Suprimento -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus-circle me-2"></i>Dados do Suprimento
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="caixa_id" class="form-label fw-bold">Caixa *</label>
                            <select class="form-select" id="caixa_id" name="caixa_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($caixas as $caixa): ?>
                                    <option value="<?= $caixa['id'] ?>" <?= ($_POST['caixa_id'] ?? '') == $caixa['id'] ? 'selected' : '' ?>>
                                        <?= sanitizar($caixa['nome']) ?> (Saldo: R$ <?= number_format($caixa['saldo_atual'], 2, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="valor" class="form-label fw-bold">Valor do Suprimento (R$) *</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control valor-input" id="valor" name="valor" value="<?= sanitizar($_POST['valor'] ?? '') ?>" placeholder="0,00" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Observações -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-sticky me-2"></i>Origem do Valor
                    </h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="2" placeholder="Ex: Depósito inicial, aporte do proprietário, fundo fixo, etc."><?= sanitizar($_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Barra Lateral -->
        <div class="col-lg-4">
            <!-- Ações -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-success w-100 mb-2" <?= count($caixas) === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-plus-circle me-2"></i>Confirmar Suprimento
                    </button>
                    <a href="caixas/index.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
            
            <!-- Info -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>O que é Suprimento?
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        <strong>Suprimento</strong> é a entrada de valores no caixa para fins como:
                    </p>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Aporte inicial de capital</li>
                        <li>Fundo fixo para despesas</li>
                        <li>Retorno de valores externos</li>
                        <li>Capital de giro</li>
                    </ul>
                </div>
            </div>
            
            <!-- Alerta -->
            <div class="card border-0 shadow-sm mt-4 bg-success bg-opacity-10">
                <div class="card-body">
                    <p class="small text-success mb-0">
                        <i class="bi bi-check-circle me-1"></i>
                        O suprimento aumenta o saldo do caixa selecionado.
                    </p>
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

<?php include '../includes/footer.php'; ?>
