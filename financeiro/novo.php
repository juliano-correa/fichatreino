<?php
// Financeiro - Nova Transação
$titulo_pagina = 'Nova Transação';
$subtitulo_pagina = 'Cadastrar Receita ou Despesa';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Buscar dados para preenchimento do formulário
try {
    // Buscar alunos
    $stmt = $pdo->prepare("SELECT id, nome, telefone FROM students WHERE gym_id = :gym_id AND status = 'ativo' ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $alunos = $stmt->fetchAll();
    
    // Buscar caixas abertos
    $stmt = $pdo->prepare("SELECT id, nome, tipo, saldo_atual FROM cash_registers WHERE gym_id = :gym_id AND status = 'aberto' ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $caixas = $stmt->fetchAll();
    
    // Buscar categorias existentes
    $stmt = $pdo->prepare("SELECT DISTINCT categoria FROM transactions WHERE gym_id = :gym_id AND categoria IS NOT NULL ORDER BY categoria");
    $stmt->execute([':gym_id' => getGymId()]);
    $categorias_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    $alunos = [];
    $caixas = [];
    $categorias_existentes = [];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $categoria = trim($_POST['categoria'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $valor = str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0');
    $valor = (float)$valor;
    $data_vencimento = $_POST['data_vencimento'] ?? '';
    $data_pagamento = $_POST['data_pagamento'] ?? '';
    $status = $_POST['status'] ?? 'pago';
    $forma_pagamento = $_POST['forma_pagamento'] ?? '';
    $caixa_id = $_POST['caixa_id'] ?? '';
    $aluno_id = $_POST['aluno_id'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações
    if (empty($tipo)) {
        $error = 'Selecione o tipo de transação (Receita ou Despesa).';
    } elseif (empty($categoria)) {
        $error = 'A categoria é obrigatória.';
    } elseif (empty($descricao)) {
        $error = 'A descrição é obrigatória.';
    } elseif (empty($valor) || $valor <= 0) {
        $error = 'O valor deve ser maior que zero.';
    } elseif (empty($data_vencimento)) {
        $error = 'A data de vencimento é obrigatória.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Se for pagamento (status = pago), verificar e atualizar caixa
            $caixa_atualizado = false;
            if ($status === 'pago' && !empty($caixa_id) && $tipo === 'receita') {
                // Verificar se caixa existe e está aberto
                $stmt = $pdo->prepare("SELECT id, saldo_atual FROM cash_registers WHERE id = :id AND gym_id = :gym_id AND status = 'aberto' FOR UPDATE");
                $stmt->execute([':id' => $caixa_id, ':gym_id' => getGymId()]);
                $caixa = $stmt->fetch();
                
                if ($caixa) {
                    // Atualizar saldo do caixa
                    $stmt = $pdo->prepare("UPDATE cash_registers SET saldo_atual = saldo_atual + :valor WHERE id = :id");
                    $stmt->execute([':id' => $caixa_id, ':valor' => $valor]);
                    
                    // Registrar movimentação no caixa
                    $stmt = $pdo->prepare("INSERT INTO cash_movements (caixa_id, gym_id, tipo, valor, observacoes, usuario_id) VALUES (:caixa_id, :gym_id, 'entrada', :valor, :observacoes, :usuario_id)");
                    $stmt->execute([
                        ':caixa_id' => $caixa_id,
                        ':gym_id' => getGymId(),
                        ':valor' => $valor,
                        ':observacoes' => 'Receita: ' . $descricao,
                        ':usuario_id' => $_SESSION['user_id']
                    ]);
                    
                    $caixa_atualizado = true;
                }
            }
            
            // Inserir transação
            $stmt = $pdo->prepare("INSERT INTO transactions (
                gym_id, tipo, categoria, descricao, valor, data_vencimento, data_pagamento, status, forma_pagamento, caixa_id, observacoes, aluno_id
            ) VALUES (
                :gym_id, :tipo, :categoria, :descricao, :valor, :data_vencimento, :data_pagamento, :status, :forma_pagamento, :caixa_id, :observacoes, :aluno_id
            )");
            
            $stmt->execute([
                ':gym_id' => getGymId(),
                ':tipo' => $tipo,
                ':categoria' => $categoria,
                ':descricao' => $descricao,
                ':valor' => $valor,
                ':data_vencimento' => $data_vencimento,
                ':data_pagamento' => !empty($data_pagamento) ? $data_pagamento : null,
                ':status' => $status,
                ':forma_pagamento' => !empty($forma_pagamento) ? $forma_pagamento : null,
                ':caixa_id' => !empty($caixa_id) ? $caixa_id : null,
                ':observacoes' => $observacoes,
                ':aluno_id' => !empty($aluno_id) ? $aluno_id : null
            ]);
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Transação cadastrada com sucesso!';
            header('Location: index.php');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao salvar transação: ' . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Financeiro</a></li>
        <li class="breadcrumb-item active">Nova Transação</li>
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
            <!-- Tipo de Transação -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-currency-exchange me-2"></i>Tipo de Transação
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tipo *</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipo_receita" value="receita" <?= ($_POST['tipo'] ?? '') === 'receita' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tipo_receita">
                                        <i class="bi bi-arrow-up-circle text-success me-1"></i>Receita
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipo_despesa" value="despesa" <?= ($_POST['tipo'] ?? '') === 'despesa' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tipo_despesa">
                                        <i class="bi bi-arrow-down-circle text-danger me-1"></i>Despesa
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="categoria" class="form-label fw-bold">Categoria *</label>
                            <input type="text" class="form-control" id="categoria" name="categoria" value="<?= sanitizar($_POST['categoria'] ?? '') ?>" placeholder="Ex: Mensalidade, Aluguel, Salário" list="categorias_list" required>
                            <datalist id="categorias_list">
                                <?php foreach ($categorias_existentes as $cat): ?>
                                    <option value="<?= sanitizar($cat) ?>">
                                <?php endforeach; ?>
                                <option value="Mensalidade">
                                <option value="Matrícula">
                                <option value="Aluguel">
                                <option value="Salários">
                                <option value="Água">
                                <option value="Luz">
                                <option value="Internet">
                                <option value="Equipamentos">
                                <option value="Material de Limpeza">
                                <option value="Outros">
                            </datalist>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dados da Transação -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Dados da Transação
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="descricao" class="form-label fw-bold">Descrição *</label>
                            <input type="text" class="form-control" id="descricao" name="descricao" value="<?= sanitizar($_POST['descricao'] ?? '') ?>" placeholder="Ex: Mensalidade de Janeiro - João Silva" required>
                        </div>
                        <div class="col-md-6">
                            <label for="valor" class="form-label fw-bold">Valor (R$) *</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control valor-input" id="valor" name="valor" value="<?= sanitizar($_POST['valor'] ?? '') ?>" placeholder="0,00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="data_vencimento" class="form-label fw-bold">Data de Vencimento *</label>
                            <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" value="<?= sanitizar($_POST['data_vencimento'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="data_pagamento" class="form-label">Data de Pagamento</label>
                            <input type="date" class="form-control" id="data_pagamento" name="data_pagamento" value="<?= sanitizar($_POST['data_pagamento'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-bold">Status *</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pago" <?= ($_POST['status'] ?? 'pago') === 'pago' ? 'selected' : '' ?>>Pago</option>
                                <option value="pendente" <?= ($_POST['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                <option value="cancelado" <?= ($_POST['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                            <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                                <option value="">Selecione...</option>
                                <option value="Dinheiro" <?= ($_POST['forma_pagamento'] ?? '') === 'Dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                                <option value="Cartão de Crédito" <?= ($_POST['forma_pagamento'] ?? '') === 'Cartão de Crédito' ? 'selected' : '' ?>>Cartão de Crédito</option>
                                <option value="Cartão de Débito" <?= ($_POST['forma_pagamento'] ?? '') === 'Cartão de Débito' ? 'selected' : '' ?>>Cartão de Débito</option>
                                <option value="PIX" <?= ($_POST['forma_pagamento'] ?? '') === 'PIX' ? 'selected' : '' ?>>PIX</option>
                                <option value="Transferência" <?= ($_POST['forma_pagamento'] ?? '') === 'Transferência' ? 'selected' : '' ?>>Transferência Bancária</option>
                                <option value="Boleto" <?= ($_POST['forma_pagamento'] ?? '') === 'Boleto' ? 'selected' : '' ?>>Boleto</option>
                                <option value="Cheque" <?= ($_POST['forma_pagamento'] ?? '') === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Caixa (apenas para receitas pagas) -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-safe2 me-2"></i>Caixa
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="caixa_id" class="form-label">Receber em (para receitas pagas)</label>
                            <select class="form-select" id="caixa_id" name="caixa_id">
                                <option value="">Selecione o caixa...</option>
                                <?php foreach ($caixas as $caixa): ?>
                                    <option value="<?= $caixa['id'] ?>" <?= ($_POST['caixa_id'] ?? '') == $caixa['id'] ? 'selected' : '' ?>>
                                        <?= sanitizar($caixa['nome']) ?> (Saldo: R$ <?= number_format($caixa['saldo_atual'], 2, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecione apenas para receitas que já foram pagas</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Aluno (para receitas) -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-badge me-2"></i>Vinculação com Aluno
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="aluno_id" class="form-label">Aluno Relacionado</label>
                            <select class="form-select" id="aluno_id" name="aluno_id">
                                <option value="">Nenhum (transação geral)</option>
                                <?php foreach ($alunos as $aluno): ?>
                                    <option value="<?= $aluno['id'] ?>" <?= ($_POST['aluno_id'] ?? '') == $aluno['id'] ? 'selected' : '' ?>>
                                        <?= sanitizar($aluno['nome']) ?> (<?= formatarTelefone($aluno['telefone']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecione um aluno para vincular esta transação (ex: mensalidade)</small>
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
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais sobre esta transação..."><?= sanitizar($_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Barra Lateral -->
        <div class="col-lg-4">
            <!-- Ações -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Salvar Transação
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
            
            <!-- Ajuda -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-lightbulb me-2"></i>Dicas
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3 small">
                        <li class="mb-2">Para <strong>mensalidades</strong>, selecione "Receita" e vincule o aluno</li>
                        <li class="mb-2">Para <strong>despesas fixas</strong>, selecione "Despesa"</li>
                        <li class="mb-2">Vincule um aluno para rastrear pagamentos individualmente</li>
                        <li class="mb-2">Use a <strong>caixa</strong> para registrar o recebimento imediato</li>
                        <li class="mb-2">Use a <strong>data de pagamento</strong> quando a transação for quitada</li>
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
    
    // Formatar ao perder foco
    input.addEventListener('blur', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 0) {
            value = (parseInt(value) / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            this.value = value;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
