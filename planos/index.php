<?php
// Planos - Lista
$titulo_pagina = 'Planos';
$subtitulo_pagina = 'Gerenciar Planos';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $nome = trim($_POST['nome'] ?? '');
        $preco = str_replace(',', '.', $_POST['preco'] ?? '0');
        $duracao_dias = (int)($_POST['duracao_dias'] ?? 30);
        $descricao = trim($_POST['descricao'] ?? '');
        $modalidades_selecionadas = $_POST['modalidades'] ?? [];
        
        if (empty($nome) || empty($preco)) {
            $error = 'Nome e preço são obrigatórios.';
        } else {
            try {
                $pdo->beginTransaction();
                
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    // Atualizar
                    $stmt = $pdo->prepare("UPDATE plans SET nome = :nome, preco = :preco, duracao_dias = :duracao_dias, descricao = :descricao WHERE id = :id AND gym_id = :gym_id");
                    $stmt->execute([
                        ':id' => $_POST['id'],
                        ':gym_id' => getGymId(),
                        ':nome' => $nome,
                        ':preco' => $preco,
                        ':duracao_dias' => $duracao_dias,
                        ':descricao' => $descricao
                    ]);
                    $plano_id = $_POST['id'];
                    
                    // Remover vínculos antigos
                    $stmt = $pdo->prepare("DELETE FROM plan_modalities WHERE plan_id = ?");
                    $stmt->execute([$plano_id]);
                    $success = 'Plano atualizado com sucesso!';
                } else {
                    // Inserir
                    $stmt = $pdo->prepare("INSERT INTO plans (gym_id, nome, preco, duracao_dias, descricao, ativo) VALUES (:gym_id, :nome, :preco, :duracao_dias, :descricao, 1)");
                    $stmt->execute([
                        ':gym_id' => getGymId(),
                        ':nome' => $nome,
                        ':preco' => $preco,
                        ':duracao_dias' => $duracao_dias,
                        ':descricao' => $descricao
                    ]);
                    $plano_id = $pdo->lastInsertId();
                    $success = 'Plano cadastrado com sucesso!';
                }
                
                // Inserir novos vínculos com modalidades
                foreach ($modalidades_selecionadas as $modalidade_id) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO plan_modalities (plan_id, modalidade_id) VALUES (?, ?)");
                    $stmt->execute([$plano_id, $modalidade_id]);
                }
                
                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    } elseif ($acao === 'ativar_desativar') {
        $id = $_POST['id'] ?? 0;
        $ativo = $_POST['ativo'] ?? 0;
        try {
            $stmt = $pdo->prepare("UPDATE plans SET ativo = :ativo WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $id, ':gym_id' => getGymId(), ':ativo' => $ativo]);
            $success = $ativo ? 'Plano ativado.' : 'Plano desativado.';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar status.';
        }
    } elseif ($acao === 'excluir') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM plans WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $id, ':gym_id' => getGymId()]);
            $success = 'Plano excluído.';
        } catch (PDOException $e) {
            $error = 'Não é possível excluir este plano. Verifique se há alunos vinculados.';
        }
    }
}

// Buscar planos e modalidades
try {
    // Buscar planos com suas modalidades vinculadas
    $stmt = $pdo->prepare("
        SELECT p.*, 
            GROUP_CONCAT(m.id) as modalidades_ids,
            GROUP_CONCAT(m.nome ORDER BY m.nome SEPARATOR ', ') as modalidades_nomes,
            (SELECT COUNT(*) FROM subscriptions s WHERE s.plano_id = p.id AND s.status = 'ativo') as total_assinantes
        FROM plans p 
        LEFT JOIN plan_modalities pm ON p.id = pm.plan_id
        LEFT JOIN modalities m ON pm.modalidade_id = m.id
        WHERE p.gym_id = :gym_id 
        GROUP BY p.id
        ORDER BY p.nome
    ");
    $stmt->execute([':gym_id' => getGymId()]);
    $planos = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM modalities WHERE gym_id = :gym_id AND ativa = 1 ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $modalidades = $stmt->fetchAll();
    
    // Estatísticas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(preco), 0) as receita_mensal FROM plans WHERE gym_id = :gym_id AND ativo = 1");
    $stmt->execute([':gym_id' => getGymId()]);
    $estatisticas = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = 'Erro ao buscar dados: ' . $e->getMessage();
    $planos = [];
    $modalidades = [];
    $estatisticas = ['total' => 0, 'receita_mensal' => 0];
}
?>

<?php include '../includes/header.php'; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 bg-primary bg-opacity-10">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <i class="bi bi-credit-card fs-1 text-primary"></i>
                </div>
                <div>
                    <p class="mb-0 text-muted">Planos Ativos</p>
                    <h3 class="mb-0"><?= $estatisticas['total'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 bg-success bg-opacity-10">
            <div class="card-body d-flex align-items-center">
                <div class="me-3">
                    <i class="bi bi-currency-dollar fs-1 text-success"></i>
                </div>
                <div>
                    <p class="mb-0 text-muted">Receita Potencial Mensal</p>
                    <h3 class="mb-0"><?= formatarMoeda($estatisticas['receita_mensal']) ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

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

<!-- Botão Novo Plano -->
<div class="d-flex justify-content-end mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planoModal">
        <i class="bi bi-plus-lg me-2"></i>Novo Plano
    </button>
</div>

<!-- Grid de Planos -->
<div class="row g-4">
    <?php if (empty($planos)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-credit-card d-block fs-1 text-muted mb-3"></i>
                    <p class="text-muted mb-3">Nenhum plano cadastrado</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planoModal">
                        <i class="bi bi-plus-lg me-1"></i>Cadastrar Primeiro Plano
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($planos as $plano): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <?php 
                                $modalidades_array = !empty($plano['modalidades_ids']) 
                                    ? array_filter(array_map('trim', explode(',', $plano['modalidades_nomes'] ?? ''))) 
                                    : [];
                                ?>
                                <?php if (!empty($modalidades_array)): ?>
                                    <div class="mb-2">
                                        <?php foreach ($modalidades_array as $mod): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary me-1">
                                                <?= sanitizar($mod) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary mb-2">
                                        Geral
                                    </span>
                                <?php endif; ?>
                                <h5 class="mb-0"><?= sanitizar($plano['nome']) ?></h5>
                            </div>
                            <?= getStatusBadge($plano['ativo'] ? 'ativo' : 'inativo') ?>
                        </div>
                        
                        <div class="text-center my-4">
                            <span class="display-4 fw-bold text-primary"><?= formatarMoeda($plano['preco']) ?></span>
                            <span class="text-muted">/<?= $plano['duracao_dias'] === 365 ? 'ano' : ($plano['duracao_dias'] === 30 ? 'mês' : $plano['duracao_dias'] . ' dias') ?></span>
                        </div>
                        
                        <?php if ($plano['descricao']): ?>
                            <p class="text-muted small text-center mb-3"><?= sanitizar($plano['descricao']) ?></p>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-people me-1"></i><?= $plano['total_assinantes'] ?> assinantes
                            </span>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="editarPlano(<?= $plano['id'] ?>, '<?= sanitizar($plano['nome']) ?>', '<?= $plano['preco'] ?>', '<?= $plano['duracao_dias'] ?>', '<?= sanitizar($plano['descricao']) ?>', '<?= $plano['modalidades_ids'] ?? '' ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="flex-grow-1">
                                <input type="hidden" name="acao" value="ativar_desativar">
                                <input type="hidden" name="id" value="<?= $plano['id'] ?>">
                                <input type="hidden" name="ativo" value="<?= $plano['ativo'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $plano['ativo'] ? 'warning' : 'success' ?> w-100">
                                    <i class="bi bi-<?= $plano['ativo'] ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="flex-grow-1">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= $plano['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir este plano?');">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Novo/Editar Plano -->
<div class="modal fade" id="planoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="plano_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="plano_modal_title">Novo Plano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="plano_nome" class="form-label fw-bold">Nome do Plano *</label>
                        <input type="text" class="form-control" id="plano_nome" name="nome" placeholder="Ex: Plano Mensal, Anual Gold" required>
                    </div>
                    
                    <!-- Seleção de Modalidades -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Modalidades</label>
                        <div class="card bg-light border-0">
                            <div class="card-body py-3">
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-info-circle me-1"></i>Selecione uma ou mais modalidades (opcional)
                                </p>
                                <div class="row g-2" id="modalidades_container">
                                    <?php if (empty($modalidades)): ?>
                                        <div class="col-12">
                                            <p class="text-muted small mb-0">Nenhuma modalidade cadastrada.</p>
                                            <a href="modalidades/index.php" class="btn btn-sm btn-outline-primary mt-2">
                                                <i class="bi bi-plus-lg me-1"></i>Cadastrar Modalidades
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($modalidades as $mod): ?>
                                            <div class="col-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                        name="modalidades[]" 
                                                        value="<?= $mod['id'] ?>" 
                                                        id="modalidade_<?= $mod['id'] ?>">
                                                    <label class="form-check-label small" for="modalidade_<?= $mod['id'] ?>">
                                                        <i class="bi bi-tag me-1"></i><?= sanitizar($mod['nome']) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="plano_preco" class="form-label fw-bold">Preço (R$) *</label>
                            <input type="number" class="form-control" id="plano_preco" name="preco" step="0.01" min="0" placeholder="0,00" required>
                        </div>
                        <div class="col-6">
                            <label for="plano_duracao" class="form-label fw-bold">Duração (dias)</label>
                            <input type="number" class="form-control" id="plano_duracao" name="duracao_dias" min="1" value="30">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="plano_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="plano_descricao" name="descricao" rows="2" placeholder="Descrição opcional do plano..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarPlano(id, nome, preco, duracao, descricao, modalidades_ids) {
    document.getElementById('plano_id').value = id;
    document.getElementById('plano_nome').value = nome;
    document.getElementById('plano_preco').value = preco;
    document.getElementById('plano_duracao').value = duracao;
    document.getElementById('plano_descricao').value = descricao;
    document.getElementById('plano_modal_title').textContent = 'Editar Plano';
    
    // Limpar checkboxes anteriores
    document.querySelectorAll('input[name="modalidades[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Marcar modalidades vinculadas
    if (modalidades_ids) {
        const ids = modalidades_ids.split(',');
        ids.forEach(id_mod => {
            const checkbox = document.getElementById('modalidade_' + id_mod.trim());
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
    
    var modal = new bootstrap.Modal(document.getElementById('planoModal'));
    modal.show();
}

// Resetar modal ao fechar
document.getElementById('planoModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('plano_id').value = '';
    document.getElementById('plano_nome').value = '';
    document.getElementById('plano_preco').value = '';
    document.getElementById('plano_duracao').value = '30';
    document.getElementById('plano_descricao').value = '';
    document.getElementById('plano_modal_title').textContent = 'Novo Plano';
    
    // Limpar checkboxes
    document.querySelectorAll('input[name="modalidades[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
});
</script>

<?php include '../includes/footer.php'; ?>
