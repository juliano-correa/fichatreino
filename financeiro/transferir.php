<?php
// Transferir entre Caixas
$titulo_pagina = 'Transferência';
$subtitulo_pagina = 'Transferir Valores entre Caixas';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem realizar transferências.';
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
    $caixa_origem_id = $_POST['caixa_origem_id'] ?? '';
    $caixa_destino_id = $_POST['caixa_destino_id'] ?? '';
    $valor = str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0');
    $valor = (float)$valor;
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações
    if (empty($caixa_origem_id)) {
        $error = 'Selecione o caixa de origem.';
    } elseif (empty($caixa_destino_id)) {
        $error = 'Selecione o caixa de destino.';
    } elseif ($caixa_origem_id === $caixa_destino_id) {
        $error = 'O caixa de origem e destino devem ser diferentes.';
    } elseif (empty($valor) || $valor <= 0) {
        $error = 'O valor deve ser maior que zero.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verificar saldo do caixa de origem
            $stmt = $pdo->prepare("SELECT id, nome, saldo_atual FROM cash_registers WHERE id = :id AND gym_id = :gym_id AND status = 'aberto' FOR UPDATE");
            $stmt->execute([':id' => $caixa_origem_id, ':gym_id' => getGymId()]);
            $caixa_origem = $stmt->fetch();
            
            if (!$caixa_origem) {
                throw new Exception('Caixa de origem não encontrado ou está fechado.');
            }
            
            if ((float)$caixa_origem['saldo_atual'] < $valor) {
                throw new Exception('Saldo insuficiente no caixa de origem. Saldo atual: R$ ' . number_format($caixa_origem['saldo_atual'], 2, ',', '.'));
            }
            
            // Debitar do caixa de origem
            $stmt = $pdo->prepare("UPDATE cash_registers SET saldo_atual = saldo_atual - :valor WHERE id = :id");
            $stmt->execute([':id' => $caixa_origem_id, ':valor' => $valor]);
            
            // Creditar no caixa de destino
            $stmt = $pdo->prepare("UPDATE cash_registers SET saldo_atual = saldo_atual + :valor WHERE id = :id");
            $stmt->execute([':id' => $caixa_destino_id, ':valor' => $valor]);
            
            // Registrar movimentação de saída
            $stmt = $pdo->prepare("INSERT INTO cash_movements (caixa_id, gym_id, tipo, valor, caixa_destino_id, observacoes, usuario_id) VALUES (:caixa_id, :gym_id, 'transferencia_saida', :valor, :caixa_destino_id, :observacoes, :usuario_id)");
            $stmt->execute([
                ':caixa_id' => $caixa_origem_id,
                ':gym_id' => getGymId(),
                ':valor' => $valor,
                ':caixa_destino_id' => $caixa_destino_id,
                ':observacoes' => !empty($observacoes) ? 'Transferência: ' . $observacoes : 'Transferência entre caixas',
                ':usuario_id' => $_SESSION['user_id']
            ]);
            
            // Registrar movimentação de entrada
            $stmt = $pdo->prepare("INSERT INTO cash_movements (caixa_id, gym_id, tipo, valor, caixa_destino_id, observacoes, usuario_id) VALUES (:caixa_id, :gym_id, 'transferencia_entrada', :valor, :caixa_origem_id, :observacoes, :usuario_id)");
            $stmt->execute([
                ':caixa_id' => $caixa_destino_id,
                ':gym_id' => getGymId(),
                ':valor' => $valor,
                ':caixa_destino_id' => $caixa_origem_id,
                ':observacoes' => !empty($observacoes) ? 'Transferência: ' . $observacoes : 'Transferência entre caixas',
                ':usuario_id' => $_SESSION['user_id']
            ]);
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Transferência de R$ ' . number_format($valor, 2, ',', '.') . ' realizada com sucesso!';
            header('Location: caixas/index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao realizar transferência: ' . $e->getMessage();
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
        <li class="breadcrumb-item active">Transferência</li>
    </ol>
</nav>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (count($caixas) < 2): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Você precisa de pelo menos 2 caixas abertos para realizar transferências.
        <a href="caixas/novo.php" class="alert-link">Criar novo caixa</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="row g-4">
        <!-- Formulário Principal -->
        <div class="col-lg-8">
            <!-- Dados da Transferência -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-arrow-left-right me-2"></i>Dados da Transferência
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="caixa_origem_id" class="form-label fw-bold">Caixa de Origem *</label>
                            <select class="form-select" id="caixa_origem_id" name="caixa_origem_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($caixas as $caixa): ?>
                                    <option value="<?= $caixa['id'] ?>" <?= ($_POST['caixa_origem_id'] ?? '') == $caixa['id'] ? 'selected' : '' ?>>
                                        <?= sanitizar($caixa['nome']) ?> (R$ <?= number_format($caixa['saldo_atual'], 2, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">De onde sairá o valor</small>
                        </div>
                        <div class="col-md-6">
                            <label for="caixa_destino_id" class="form-label fw-bold">Caixa de Destino *</label>
                            <select class="form-select" id="caixa_destino_id" name="caixa_destino_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($caixas as $caixa): ?>
                                    <option value="<?= $caixa['id'] ?>" <?= ($_POST['caixa_destino_id'] ?? '') == $caixa['id'] ? 'selected' : '' ?>>
                                        <?= sanitizar($caixa['nome']) ?> (R$ <?= number_format($caixa['saldo_atual'], 2, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Para onde irá o valor</small>
                        </div>
                        <div class="col-md-6">
                            <label for="valor" class="form-label fw-bold">Valor (R$) *</label>
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
                        <i class="bi bi-sticky me-2"></i>Observações
                    </h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="2" placeholder="Motivo da transferência..."><?= sanitizar($_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Barra Lateral -->
        <div class="col-lg-4">
            <!-- Ações -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2" <?= count($caixas) < 2 ? 'disabled' : '' ?>>
                        <i class="bi bi-check-lg me-2"></i>Confirmar Transferência
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
                        <i class="bi bi-info-circle me-2"></i>Informações
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small list-unstyled text-muted">
                        <li class="mb-2">A transferência é instantânea</li>
                        <li class="mb-2">O saldo dos caixas é atualizado automaticamente</li>
                        <li class="mb-2">Um histórico completo é registrado</li>
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

// Validar que origem e destino são diferentes
document.querySelector('form').addEventListener('submit', function(e) {
    const origem = document.getElementById('caixa_origem_id').value;
    const destino = document.getElementById('caixa_destino_id').value;
    
    if (origem && destino && origem === destino) {
        e.preventDefault();
        alert('O caixa de origem e destino devem ser diferentes!');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
