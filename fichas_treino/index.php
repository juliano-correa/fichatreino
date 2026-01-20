<?php
// Fichas de Treino - Listagem
$titulo_pagina = 'Fichas de Treino';
$subtitulo_pagina = 'Gestão de Treinos';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Verificar mensagem de sucesso na sessão
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Obter lista de alunos para filtro
try {
    $sql_alunos = "SELECT id, nome FROM students WHERE gym_id = :gym_id ORDER BY nome";
    $stmt_alunos = $pdo->prepare($sql_alunos);
    $stmt_alunos->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_alunos->execute();
    $alunos = $stmt_alunos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alunos = [];
}

// Configuração de filtros
$aluno_filtro = $_GET['aluno'] ?? '';
$status_filtro = $_GET['status'] ?? '';

// Obter fichas
try {
    $sql = "SELECT 
                w.*,
                s.nome as aluno_nome
            FROM workouts w
            LEFT JOIN students s ON w.aluno_id = s.id
            WHERE w.gym_id = :gym_id";
    
    $params = [':gym_id' => getGymId()];
    
    if (!empty($aluno_filtro)) {
        $sql .= " AND w.aluno_id = :aluno_id";
        $params[':aluno_id'] = $aluno_filtro;
    }
    
    if ($status_filtro === 'ativa') {
        $sql .= " AND w.ativa = 1";
    } elseif ($status_filtro === 'inativa') {
        $sql .= " AND w.ativa = 0";
    }
    
    $sql .= " ORDER BY w.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $fichas = [];
    $error = 'Erro ao carregar fichas: ' . $e->getMessage();
}
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

<!-- Cards de Resumo -->
<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-primary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-clipboard-check fs-3 text-primary"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Fichas</p>
                        <h4 class="mb-0 fw-bold"><?= count($fichas) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-person-check fs-3 text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Fichas Ativas</p>
                        <h4 class="mb-0 fw-bold"><?= count(array_filter($fichas, function($f) { return $f['ativa'] == 1; })) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded bg-info bg-opacity-10 p-3 me-3">
                        <i class="bi bi-people fs-3 text-info"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Alunos com Ficha</p>
                        <h4 class="mb-0 fw-bold"><?= count(array_unique(array_column($fichas, 'aluno_id'))) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros e Ações -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h5>
            <a href="novo.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Nova Ficha
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label for="aluno" class="form-label">Aluno</label>
                <select class="form-select" id="aluno" name="aluno">
                    <option value="">Todos os Alunos</option>
                    <?php foreach ($alunos as $aluno): ?>
                        <option value="<?= $aluno['id'] ?>" <?= $aluno_filtro == $aluno['id'] ? 'selected' : '' ?>>
                            <?= sanitizar($aluno['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="" <?= $status_filtro == '' ? 'selected' : '' ?>>Todas</option>
                    <option value="ativa" <?= $status_filtro == 'ativa' ? 'selected' : '' ?>>Ativas</option>
                    <option value="inativa" <?= $status_filtro == 'inativa' ? 'selected' : '' ?>>Inativas</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="index.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-clockwise me-2"></i>Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Fichas -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table me-2"></i>Fichas de Treino</h5>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (count($fichas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Aluno</th>
                            <th>Ficha</th>
                            <th>Objetivo</th>
                            <th>Exercícios</th>
                            <th>Data Criação</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fichas as $ficha): ?>
                            <?php 
                            // Decodificar exercícios do JSON (novo formato com grupos ou antigo)
                            $exercicios_json = json_decode($ficha['exercicios'] ?? '[]', true);
                            $total_exercicios = 0;
                            
                            // Verificar se é o novo formato com grupos ou o antigo
                            if (isset($exercicios_json['grupos']) && is_array($exercicios_json['grupos'])) {
                                // Novo formato com grupos
                                foreach ($exercicios_json['grupos'] as $grupo) {
                                    if (isset($grupo['exercicios']) && is_array($grupo['exercicios'])) {
                                        $total_exercicios += count($grupo['exercicios']);
                                    }
                                }
                            } elseif (is_array($exercicios_json)) {
                                // Formato antigo - array simples
                                $total_exercicios = count($exercicios_json);
                            }
                            ?>
                            <tr>
                                <td><strong><?= sanitizar($ficha['aluno_nome'] ?? 'N/A') ?></strong></td>
                                <td>
                                    <?= sanitizar($ficha['nome']) ?>
                                    <?php if (!empty($ficha['frequencia'])): ?>
                                        <br><small class="text-muted"><?= sanitizar($ficha['frequencia']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $ficha['objetivo'] ? sanitizar($ficha['objetivo']) : '-' ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-list-check me-1"></i>
                                        <?= $total_exercicios ?>
                                    </span>
                                </td>
                                <td><?= $ficha['created_at'] ? date('d/m/Y', strtotime($ficha['created_at'])) : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= $ficha['ativa'] == 1 ? 'success' : 'secondary' ?>">
                                        <?= $ficha['ativa'] == 1 ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="visualizar.php?id=<?= $ficha['id'] ?>" class="btn btn-outline-primary" title="Visualizar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="editar.php?id=<?= $ficha['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="excluir.php?id=<?= $ficha['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir esta ficha?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-clipboard-check fs-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Nenhuma ficha encontrada</h5>
                <p class="text-muted">Não há fichas de treino registradas para os filtros selecionados.</p>
                <a href="novo.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Criar Nova Ficha
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
