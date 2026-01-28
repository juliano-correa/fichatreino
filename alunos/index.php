<?php
// Alunos - Lista
$titulo_pagina = 'Alunos';
$subtitulo_pagina = 'Gestão de Alunos';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

if (isAluno()) {
    redirecionar('../dashboard.php');
}

$error = '';
$success = '';

// Processar ações em lote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    
    if ($acao === 'excluir' && isset($_POST['alunos'])) {
        $alunos = $_POST['alunos'];
        try {
            $stmt = $pdo->prepare("UPDATE students SET status = 'inativo' WHERE id = :id AND gym_id = :gym_id");
            foreach ($alunos as $id) {
                $stmt->execute([':id' => $id, ':gym_id' => getGymId()]);
            }
            $success = count($alunos) . ' aluno(s) marcado(s) como inativo(s).';
        } catch (PDOException $e) {
            $error = 'Erro ao processar: ' . $e->getMessage();
        }
    }
}

// Buscar filtros
$busca = $_GET['busca'] ?? '';
$status_filtro = $_GET['status'] ?? '';
$plano_filtro = $_GET['plano'] ?? '';

// Construir query
$where = "WHERE s.gym_id = :gym_id";
$params = [':gym_id' => getGymId()];

if (!empty($busca)) {
    $where .= " AND (s.nome LIKE :busca OR s.cpf LIKE :busca OR s.telefone LIKE :busca OR s.email LIKE :busca)";
    $params[':busca'] = "%$busca%";
}

if (!empty($status_filtro)) {
    $where .= " AND s.status = :status";
    $params[':status'] = $status_filtro;
}

if (!empty($plano_filtro)) {
    $where .= " AND s.plano_atual_id = :plano";
    $params[':plano'] = $plano_filtro;
}

// Buscar alunos
try {
    $sql = "SELECT s.*, p.nome as plano_nome 
            FROM students s 
            LEFT JOIN plans p ON s.plano_atual_id = p.id
            $where 
            ORDER BY s.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alunos = $stmt->fetchAll();
    
    // Planos para filtro
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE gym_id = :gym_id ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $planos = $stmt->fetchAll();
    
    // Estatísticas
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
        SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) as inativos,
        SUM(CASE WHEN status = 'suspenso' THEN 1 ELSE 0 END) as suspensos
        FROM students WHERE gym_id = :gym_id");
    $stmt->execute([':gym_id' => getGymId()]);
    $estatisticas = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = 'Erro ao buscar dados: ' . $e->getMessage();
    $alunos = [];
    $planos = [];
    $estatisticas = ['total' => 0, 'ativos' => 0, 'inativos' => 0, 'suspensos' => 0];
}
?>

<?php include '../includes/header.php'; ?>

<!-- Filtros e Ações - Mobile Optimized -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-12">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="busca" placeholder="Buscar por nome, CPF, telefone..." value="<?= sanitizar($busca) ?>" inputmode="search">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <select class="form-select" name="status">
                    <option value="">Todos os status</option>
                    <option value="ativo" <?= $status_filtro === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                    <option value="inativo" <?= $status_filtro === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                    <option value="suspenso" <?= $status_filtro === 'suspenso' ? 'selected' : '' ?>>Suspensos</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select class="form-select" name="plano">
                    <option value="">Todos os planos</option>
                    <?php foreach ($planos as $plan): ?>
                        <option value="<?= $plan['id'] ?>" <?= $plano_filtro == $plan['id'] ? 'selected' : '' ?>>
                            <?= sanitizar($plan['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100 h-100">
                    <i class="bi bi-funnel me-1 d-none d-md-inline"></i>Filtrar
                </button>
            </div>
            <div class="col-12 col-md-2">
                <a href="index.php" class="btn btn-outline-secondary w-100 h-100">
                    <i class="bi bi-x-lg me-1 d-none d-md-inline"></i>Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<!-- Stats - Mobile Optimized -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-primary bg-opacity-10 h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h3 class="mb-0"><?= $estatisticas['total'] ?></h3>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-success bg-opacity-10 h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h3 class="mb-0 text-success"><?= $estatisticas['ativos'] ?? 0 ?></h3>
                <small class="text-muted">Ativos</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-warning bg-opacity-10 h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h3 class="mb-0 text-warning"><?= $estatisticas['suspensos'] ?? 0 ?></h3>
                <small class="text-muted">Suspensos</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-secondary bg-opacity-10 h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h3 class="mb-0 text-secondary"><?= $estatisticas['inativos'] ?? 0 ?></h3>
                <small class="text-muted">Inativos</small>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Alunos - Mobile Optimized -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <h5 class="card-title mb-0">
                <i class="bi bi-people me-2"></i>Lista de Alunos
                <span class="badge bg-secondary ms-2"><?= count($alunos) ?></span>
            </h5>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Novo Aluno
            </a>
        </div>
    </div>
    <form method="POST">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 alunos-table">
                    <thead>
                        <tr>
                            <th class="d-none d-md-table-cell" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll">
                            </th>
                            <th>Aluno</th>
                            <th class="d-none d-lg-table-cell">Contato</th>
                            <th class="d-none d-md-table-cell">Plano</th>
                            <th class="d-none d-sm-table-cell">Status</th>
                            <th class="d-none d-lg-table-cell">Data</th>
                            <th class="text-end" style="width: 100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alunos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-people d-block fs-1 text-muted mb-3"></i>
                                    <p class="text-muted mb-2">Nenhum aluno encontrado</p>
                                    <a href="create.php" class="btn btn-primary">
                                        <i class="bi bi-plus-lg me-1"></i>Cadastrar Primeiro Aluno
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alunos as $aluno): ?>
                                <tr data-id="<?= $aluno['id'] ?>">
                                    <td class="d-none d-md-table-cell">
                                        <input type="checkbox" class="form-check-input student-checkbox" name="alunos[]" value="<?= $aluno['id'] ?>">
                                    </td>
                                    <td data-label="Aluno">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3 d-none d-sm-flex" style="width: 45px; height: 45px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-person text-primary"></i>
                                            </div>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <strong class="d-block text-truncate"><?= sanitizar($aluno['nome']) ?></strong>
                                                <small class="text-muted d-block text-truncate">
                                                    <?= formatarCPF($aluno['cpf']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="d-none d-lg-table-cell" data-label="Contato">
                                        <div class="d-flex flex-column">
                                            <span>
                                                <i class="bi bi-phone me-1 text-muted"></i>
                                                <?= formatarTelefone($aluno['telefone']) ?>
                                            </span>
                                            <?php if (!empty($aluno['email'])): ?>
                                                <small class="text-muted text-truncate">
                                                    <i class="bi bi-envelope me-1"></i><?= sanitizar($aluno['email']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell" data-label="Plano">
                                        <?= sanitizar($aluno['plano_nome'] ?? 'Não definido') ?>
                                    </td>
                                    <td class="d-none d-sm-table-cell" data-label="Status">
                                        <?= getStatusBadge($aluno['status']) ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell" data-label="Data">
                                        <?= formatarData($aluno['created_at']) ?>
                                    </td>
                                    <td class="text-end" data-label="Ações">
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?= $aluno['id'] ?>" class="btn btn-outline-primary" title="Ver">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $aluno['id'] ?>" class="btn btn-outline-secondary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete.php?id=<?= $aluno['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir este aluno?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!empty($alunos)): ?>
            <div class="card-footer bg-white">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                    <div class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        <span class="d-none d-sm-inline">Marque os alunos desejados para ações em lote</span>
                        <span class="d-sm-none">Selecione para ações em lote</span>
                    </div>
                    <div class="btn-group w-100 w-sm-auto">
                        <button type="submit" name="acao" value="excluir" class="btn btn-outline-danger btn-sm w-100" onclick="return confirmarExclusao(event, 'Tem certeza que deseja inativar os alunos selecionados?');">
                            <i class="bi bi-trash me-1"></i>
                            <span class="d-none d-md-inline">Inativar Selecionados</span>
                            <span class="d-md-none">Inativar</span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All functionality
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.student-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
    
    // Mobile table row click handler
    if (window.innerWidth < 992) {
        document.querySelectorAll('.alunos-table tbody tr').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                // Don't trigger if clicking on action buttons
                if (e.target.closest('.btn') || e.target.closest('.form-check-input')) return;
                
                const viewLink = this.querySelector('a[title="Ver"]');
                if (viewLink) {
                    window.location.href = viewLink.href;
                }
            });
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
