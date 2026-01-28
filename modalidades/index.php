<?php
// Modalidades - Lista
$titulo_pagina = 'Modalidades';
$subtitulo_pagina = 'Gerenciar Modalidades';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $cor = $_POST['cor'] ?? '#0d6efd';
        $icone = $_POST['icone'] ?? 'dumbbell';
        
        if (empty($nome)) {
            $error = 'O nome da modalidade é obrigatório.';
        } else {
            try {
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    // Atualizar
                    $stmt = $pdo->prepare("UPDATE modalities SET nome = :nome, descricao = :descricao, cor = :cor, icone = :icone WHERE id = :id AND gym_id = :gym_id");
                    $stmt->execute([
                        ':id' => $_POST['id'],
                        ':gym_id' => getGymId(),
                        ':nome' => $nome,
                        ':descricao' => $descricao,
                        ':cor' => $cor,
                        ':icone' => $icone
                    ]);
                    $success = 'Modalidade atualizada com sucesso!';
                } else {
                    // Inserir
                    $stmt = $pdo->prepare("INSERT INTO modalities (gym_id, nome, descricao, cor, icone, ativa) VALUES (:gym_id, :nome, :descricao, :cor, :icone, 1)");
                    $stmt->execute([
                        ':gym_id' => getGymId(),
                        ':nome' => $nome,
                        ':descricao' => $descricao,
                        ':cor' => $cor,
                        ':icone' => $icone
                    ]);
                    $success = 'Modalidade cadastrada com sucesso!';
                }
            } catch (PDOException $e) {
                $error = 'Erro ao salvar: ' . (strpos($e->getMessage(), '1062') !== false ? 'Esta modalidade já existe.' : $e->getMessage());
            }
        }
    } elseif ($acao === 'ativar_desativar') {
        $id = $_POST['id'] ?? 0;
        $ativa = $_POST['ativa'] ?? 0;
        try {
            $stmt = $pdo->prepare("UPDATE modalities SET ativa = :ativa WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $id, ':gym_id' => getGymId(), ':ativa' => $ativa]);
            $success = $ativa ? 'Modalidade ativada.' : 'Modalidade desativada.';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar status.';
        }
    } elseif ($acao === 'excluir') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM modalities WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $id, ':gym_id' => getGymId()]);
            $success = 'Modalidade excluída.';
        } catch (PDOException $e) {
            $error = 'Não é possível excluir esta modalidade. Verifique se há alunos ou planos vinculados.';
        }
    }
}

// Buscar modalidades
try {
    $stmt = $pdo->prepare("SELECT m.*, 
        (SELECT COUNT(*) FROM plans p WHERE p.modalidade_id = m.id) as total_planos,
        (SELECT COUNT(*) FROM students s WHERE s.plano_atual_id IN (SELECT id FROM plans WHERE modalidade_id = m.id) AND s.status = 'ativo') as total_alunos
        FROM modalities m WHERE m.gym_id = :gym_id ORDER BY m.nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $modalidades = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Erro ao buscar dados: ' . $e->getMessage();
    $modalidades = [];
}

// Ícones disponíveis
$icones = [
    'dumbbell' => 'Dumbbell',
    'activity' => 'Activity',
    'music' => 'Music',
    'zap' => 'Zap',
    'heart' => 'Heart',
    'target' => 'Target',
    'running' => 'Running',
    'bicycle' => 'Bicycle',
    'swim' => 'Swim',
    'yoga' => 'Yoga',
];

// Cores disponíveis
$cores = ['#0d6efd', '#198754', '#dc2626', '#ec4899', '#ca8a04', '#7c3aed', '#0891b2', '#059669', '#ea580c', '#475569'];
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

<!-- Botão Nova Modalidade -->
<div class="d-flex justify-content-end mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalidadeModal">
        <i class="bi bi-plus-lg me-2"></i>Nova Modalidade
    </button>
</div>

<!-- Grid de Modalidades -->
<div class="row g-4">
    <?php if (empty($modalidades)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-activity d-block fs-1 text-muted mb-3"></i>
                    <p class="text-muted mb-3">Nenhuma modalidade cadastrada</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalidadeModal">
                        <i class="bi bi-plus-lg me-1"></i>Cadastrar Primeira Modalidade
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($modalidades as $mod): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="rounded p-3 me-3" style="background-color: <?= $mod['cor'] ?>20; color: <?= $mod['cor'] ?>;">
                                <i class="bi bi-<?= $mod['icone'] ?> fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-1"><?= sanitizar($mod['nome']) ?></h5>
                                <?= getStatusBadge($mod['ativa'] ? 'ativo' : 'inativo') ?>
                            </div>
                        </div>
                        
                        <?php if ($mod['descricao']): ?>
                            <p class="text-muted small mb-3"><?= sanitizar($mod['descricao']) ?></p>
                        <?php endif; ?>
                        
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="bg-light rounded p-2">
                                    <h5 class="mb-0"><?= $mod['total_planos'] ?></h5>
                                    <small class="text-muted">Planos</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light rounded p-2">
                                    <h5 class="mb-0"><?= $mod['total_alunos'] ?></h5>
                                    <small class="text-muted">Alunos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="editarModalidade(<?= $mod['id'] ?>, '<?= sanitizar($mod['nome']) ?>', '<?= sanitizar($mod['descricao']) ?>', '<?= $mod['cor'] ?>', '<?= $mod['icone'] ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="flex-grow-1">
                                <input type="hidden" name="acao" value="ativar_desativar">
                                <input type="hidden" name="id" value="<?= $mod['id'] ?>">
                                <input type="hidden" name="ativa" value="<?= $mod['ativa'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $mod['ativa'] ? 'warning' : 'success' ?> w-100">
                                    <i class="bi bi-<?= $mod['ativa'] ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="flex-grow-1">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= $mod['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir esta modalidade?');">
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

<!-- Modal Nova/Editar Modalidade -->
<div class="modal fade" id="modalidadeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="modal_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal_title">Nova Modalidade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_nome" class="form-label fw-bold">Nome *</label>
                        <input type="text" class="form-control" id="modal_nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="modal_descricao" name="descricao" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cor</label>
                        <div class="d-flex gap-2">
                            <?php foreach ($cores as $cor): ?>
                                <input type="radio" class="btn-check" name="cor" id="cor_<?= $cor ?>" value="<?= $cor ?>" <?= $cor === '#0d6efd' ? 'checked' : '' ?>>
                                <label class="btn btn-sm" style="background-color: <?= $cor ?>;" for="cor_<?= $cor ?>"></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ícone</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($icones as $key => $nome): ?>
                                <input type="radio" class="btn-check" name="icone" id="icone_<?= $key ?>" value="<?= $key ?>" <?= $key === 'dumbbell' ? 'checked' : '' ?>>
                                <label class="btn btn-sm btn-outline-secondary" for="icone_<?= $key ?>">
                                    <i class="bi bi-<?= $key ?>"></i>
                                </label>
                            <?php endforeach; ?>
                        </div>
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
function editarModalidade(id, nome, descricao, cor, icone) {
    document.getElementById('modal_id').value = id;
    document.getElementById('modal_nome').value = nome;
    document.getElementById('modal_descricao').value = descricao;
    document.getElementById('modal_title').textContent = 'Editar Modalidade';
    
    // Selecionar cor
    document.querySelectorAll('input[name="cor"]').forEach(radio => {
        radio.checked = radio.value === cor;
    });
    
    // Selecionar ícone
    document.querySelectorAll('input[name="icone"]').forEach(radio => {
        radio.checked = radio.value === icone;
    });
    
    // Resetar cores dos labels
    document.querySelectorAll('input[name="cor"]').forEach(radio => {
        const label = document.querySelector(`label[for="${radio.id}"]`);
        if (radio.checked) {
            label.style.border = '2px solid #000';
        } else {
            label.style.border = 'none';
        }
    });
    
    var modalElement = document.getElementById('modalidadeModal');
    var modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
    modal.show();
}

// Resetar modal ao fechar
document.getElementById('modalidadeModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modal_id').value = '';
    document.getElementById('modal_nome').value = '';
    document.getElementById('modal_descricao').value = '';
    document.getElementById('modal_title').textContent = 'Nova Modalidade';
});
</script>

<?php include '../includes/footer.php'; ?>
