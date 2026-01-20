<?php
// Avaliações Físicas - Listagem
$titulo_pagina = 'Avaliações Físicas';
$subtitulo_pagina = 'Histórico de Avaliações';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

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
$ano_filtro = $_GET['ano'] ?? date('Y');

// Obter avaliações
try {
    $sql = "SELECT 
                a.*,
                s.nome as aluno_nome
            FROM assessments a
            LEFT JOIN students s ON a.aluno_id = s.id
            WHERE s.gym_id = :gym_id";
    
    $params = [':gym_id' => getGymId()];
    
    if (!empty($aluno_filtro)) {
        $sql .= " AND a.aluno_id = :aluno_id";
        $params[':aluno_id'] = $aluno_filtro;
    }
    
    if (!empty($ano_filtro)) {
        $sql .= " AND YEAR(a.data_avaliacao) = :ano";
        $params[':ano'] = $ano_filtro;
    }
    
    $sql .= " ORDER BY a.data_avaliacao DESC, a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $avaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avaliacoes = [];
    $error = 'Erro ao carregar avaliações: ' . $e->getMessage();
}

// Anos para filtro
$anos = range(date('Y') - 5, date('Y') + 1);
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
                        <i class="bi bi-clipboard-pulse fs-3 text-primary"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Total de Avaliações</p>
                        <h4 class="mb-0 fw-bold"><?= count($avaliacoes) ?></h4>
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
                        <i class="bi bi-people fs-3 text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Alunos Avaliados</p>
                        <h4 class="mb-0 fw-bold"><?= count(array_unique(array_column($avaliacoes, 'aluno_id'))) ?></h4>
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
                        <i class="bi bi-calendar-check fs-3 text-info"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 small">Este Mês</p>
                        <h4 class="mb-0 fw-bold"><?= count(array_filter($avaliacoes, function($a) {
                            return substr($a['data_avaliacao'], 0, 7) === date('Y-m');
                        })) ?></h4>
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
                <i class="bi bi-plus-lg me-2"></i>Nova Avaliação
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
                <label for="ano" class="form-label">Ano</label>
                <select class="form-select" id="ano" name="ano">
                    <?php foreach ($anos as $ano): ?>
                        <option value="<?= $ano ?>" <?= $ano_filtro == $ano ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endforeach; ?>
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

<!-- Tabela de Avaliações -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table me-2"></i>Avaliações</h5>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (count($avaliacoes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Aluno</th>
                            <th>Peso</th>
                            <th>IMC</th>
                            <th>Gordura</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avaliacoes as $av): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($av['data_avaliacao'])) ?></td>
                                <td><strong><?= sanitizar($av['aluno_nome']) ?></strong></td>
                                <td><?= number_format($av['peso'], 2, ',', '.') ?> kg</td>
                                <td>
                                    <?php
                                    $imc_class = '';
                                    if ($av['imc'] < 18.5) $imc_class = 'text-warning';
                                    elseif ($av['imc'] < 25) $imc_class = 'text-success';
                                    elseif ($av['imc'] < 30) $imc_class = 'text-warning';
                                    else $imc_class = 'text-danger';
                                    ?>
                                    <span class="<?= $imc_class ?>"><?= number_format($av['imc'], 2, ',', '.') ?></span>
                                </td>
                                <td><?= $av['percentual_gordura'] ? number_format($av['percentual_gordura'], 1, ',', '.') . '%' : '-' ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="visualizar.php?id=<?= $av['id'] ?>" class="btn btn-outline-primary" title="Visualizar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="editar.php?id=<?= $av['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="excluir.php?id=<?= $av['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir esta avaliação?');">
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
                <i class="bi bi-clipboard-pulse fs-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Nenhuma avaliação encontrada</h5>
                <p class="text-muted">Não há avaliações registradas para os filtros selecionados.</p>
                <a href="novo.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Nova Avaliação
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
