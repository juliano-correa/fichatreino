<?php
// Fichas de Treino - Visualizar com Grupos
$titulo_pagina = 'Detalhes da Ficha';
$subtitulo_pagina = 'Visualizar Ficha de Treino';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Obter ficha
try {
    $sql = "SELECT 
                w.*,
                s.nome as aluno_nome,
                s.telefone,
                s.email,
                u.nome as instructor_name
            FROM workouts w
            LEFT JOIN students s ON w.aluno_id = s.id
            LEFT JOIN users u ON w.instrutor_id = u.id
            WHERE w.id = :id AND w.gym_id = :gym_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':gym_id' => getGymId()]);
    $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ficha) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Erro ao carregar ficha: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Obter grupos e exercícios da ficha (do campo JSON)
$dados_json = json_decode($ficha['exercicios'] ?? '{}', true);
$grupos = [];
$todos_exercicios = [];

// Verificar se é o formato novo (objeto com grupos) ou antigo (array simples)
if (isset($dados_json['grupos']) && is_array($dados_json['grupos'])) {
    // Novo formato com grupos
    $grupos = $dados_json['grupos'];
    // Coletar todos os exercícios para contar
    foreach ($grupos as $grupo) {
        if (isset($grupo['exercicios']) && is_array($grupo['exercicios'])) {
            foreach ($grupo['exercicios'] as $ex) {
                $todos_exercicios[] = $ex;
            }
        }
    }
} elseif (is_array($dados_json)) {
    // Formato antigo - criar um grupo único
    $grupos[] = [
        'nome' => 'Exercícios',
        'exercicios' => $dados_json
    ];
    $todos_exercicios = $dados_json;
}

$total_exercicios = count($todos_exercicios);

// Obter dados da academia
try {
    $sql_gym = "SELECT * FROM gyms WHERE id = :gym_id";
    $stmt_gym = $pdo->prepare($sql_gym);
    $stmt_gym->execute([':gym_id' => getGymId()]);
    $gym = $stmt_gym->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gym = null;
}

// Status badge
$status_badge = $ficha['ativa'] == 1 
    ? '<span class="badge bg-success">Ativo</span>' 
    : '<span class="badge bg-secondary">Inativo</span>';

// Grupo muscular badge
function getMuscleGroupBadge($gm) {
    $cores = [
        'Peito' => 'danger',
        'Costas' => 'primary',
        'Ombros' => 'info',
        'Bíceps' => 'warning',
        'Tríceps' => 'warning',
        'Abdômen' => 'success',
        'Quadríceps' => 'primary',
        'Posterior de Coxa' => 'primary',
        'Glúteos' => 'success',
        'Panturrilha' => 'secondary',
        'Cardio' => 'danger',
        'Full Body' => 'dark'
    ];
    $cor = $cores[$gm] ?? 'secondary';
    return '<span class="badge bg-' . $cor . '">' . sanitizar($gm) . '</span>';
}
?>

<?php include '../includes/header.php'; ?>

<!-- Botão Voltar -->
<div class="mb-3 no-print">
    <a href="<?= base_url('fichas_treino/index.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Voltar para Lista
    </a>
</div>

<!-- Header Simples para Impressão -->
<div class="print-header-simple d-none" id="printHeaderSimple">
    <?php if (!empty($gym['logo_url'])): ?>
        <img src="<?= sanitizar($gym['logo_url']) ?>" alt="Logo" class="gym-logo" style="max-height: 50px;">
    <?php endif; ?>
    <h3><?= sanitizar($gym['nome'] ?? 'Titanium Gym') ?></h3>
    <?php if (!empty($gym['endereco']) || !empty($gym['telefone'])): ?>
        <p>
            <?= sanitizar($gym['endereco'] ?? '') ?>
            <?= !empty($gym['endereco']) && !empty($gym['telefone']) ? ' | ' : '' ?>
            <?= sanitizar($gym['telefone'] ?? '') ?>
        </p>
    <?php endif; ?>
</div>

<!-- Header do Aluno -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1 print-student-name">
                    <?= sanitizar($ficha['aluno_nome']) ?>
                </h4>
                <p class="text-muted mb-0 print-student-info">
                    <strong>Ficha:</strong> <?= sanitizar($ficha['nome']) ?>
                    <?php if (!empty($ficha['objetivo'])): ?>
                        | <strong>Objetivo:</strong> <?= sanitizar($ficha['objetivo']) ?>
                    <?php endif; ?>
                    <?php if (!empty($ficha['frequencia'])): ?>
                        | <strong>Frequência:</strong> <?= sanitizar($ficha['frequencia']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0 no-print">
                <a href="editar.php?id=<?= $ficha['id'] ?>" class="btn btn-primary me-2">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
                <button class="btn btn-outline-primary me-2" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
                <a href="excluir.php?id=<?= $ficha['id'] ?>" class="btn btn-outline-danger" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir esta ficha?');">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Barra de Informações Compacta -->
<div class="card border-0 shadow-sm mb-3 print-info-bar">
    <div class="card-body py-2">
        <div class="row text-center g-0">
            <div class="col">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <span class="print-info-label">Criação:</span>
                    <strong><?= date('d/m/Y', strtotime($ficha['created_at'])) ?></strong>
                </div>
            </div>
            <?php if (!empty($ficha['objetivo'])): ?>
            <div class="col-auto text-muted print-separator">|</div>
            <div class="col">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <span class="print-info-label">Objetivo:</span>
                    <strong><?= sanitizar($ficha['objetivo']) ?></strong>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($ficha['frequencia'])): ?>
            <div class="col-auto text-muted print-separator">|</div>
            <div class="col">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <span class="print-info-label">Frequência:</span>
                    <strong><?= sanitizar($ficha['frequencia']) ?></strong>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-auto text-muted print-separator">|</div>
            <div class="col">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <span class="print-info-label">Status:</span>
                    <?= $status_badge ?>
                </div>
            </div>
            <?php if (!empty($ficha['instructor_name'])): ?>
            <div class="col-auto text-muted print-separator">|</div>
            <div class="col">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <span class="print-info-label">Professor:</span>
                    <strong><?= sanitizar($ficha['instructor_name']) ?></strong>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Observações -->
<?php if (!empty($ficha['descricao'])): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-2">
        <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Observações Gerais</h6>
    </div>
    <div class="card-body py-2">
        <small><?= nl2br(sanitizar($ficha['descricao'])) ?></small>
    </div>
</div>
<?php endif; ?>

<!-- Grupos de Exercícios -->
<?php if (count($grupos) > 0): ?>
    <?php foreach ($grupos as $grupo_index => $grupo): ?>
        <?php 
        $exercicios_grupo = $grupo['exercicios'] ?? [];
        $nome_grupo = !empty($grupo['nome']) ? $grupo['nome'] : 'Grupo ' . ($grupo_index + 1);
        ?>
        <div class="card border-0 shadow-sm mb-3 grupo-exercicios-card">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 print-grupo-title">
                        <i class="bi bi-collection me-2"></i>
                        <?= sanitizar($nome_grupo) ?>
                    </h5>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($exercicios_grupo) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>Exercício</th>
                                <th>Grupo</th>
                                <th class="text-center">Séries</th>
                                <th class="text-center">Reps</th>
                                <th class="text-center">Carga</th>
                                <th class="text-center">Descanso</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exercicios_grupo as $index => $ex): ?>
                            <tr>
                                <td class="text-center text-muted">
                                    <strong><?= $index + 1 ?></strong>
                                </td>
                                <td>
                                    <strong><?= sanitizar($ex['nome'] ?? 'Exercício') ?></strong>
                                </td>
                                <td><?= getMuscleGroupBadge($ex['grupo_muscular'] ?? null) ?></td>
                                <td class="text-center"><?= $ex['series'] ?? '-' ?></td>
                                <td class="text-center"><?= sanitizar($ex['repeticoes'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?= !empty($ex['carga']) ? number_format($ex['carga'], 2, ',', '.') . ' kg' : '-' ?>
                                </td>
                                <td class="text-center">
                                    <?= !empty($ex['descanso']) ? $ex['descanso'] . 's' : '-' ?>
                                </td>
                                <td class="text-muted">
                                    <?= !empty($ex['observacoes']) ? sanitizar($ex['observacoes']) : '-' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-clipboard-x fs-3 text-muted"></i>
                    <p class="mb-0 mt-2 text-muted">Nenhum exercício neste grupo</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body text-center py-5">
        <i class="bi bi-clipboard-x fs-1 text-muted"></i>
        <h5 class="mt-3 text-muted">Nenhum exercício nesta ficha</h5>
        <a href="editar.php?id=<?= $ficha['id'] ?>" class="btn btn-primary mt-2">
            <i class="bi bi-plus-lg me-1"></i>Adicionar Exercícios
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Footer da Academia (Visível apenas na impressão) -->
<div class="print-footer d-none mt-4" id="printFooter">
    <hr class="my-2">
    <div class="text-center">
        <small class="text-muted">
            <?= sanitizar($gym['nome'] ?? 'Titanium Gym') ?>
            <?php if (!empty($gym['endereco'])): ?>
                | <?= sanitizar($gym['endereco']) ?>
            <?php endif; ?>
            <?php if (!empty($gym['telefone'])): ?>
                | Tel: <?= sanitizar($gym['telefone']) ?>
            <?php endif; ?>
        </small>
    </div>
</div>

<!-- Imprimir CSS -->
<style media="print">
    @page {
        size: A4;
        margin: 0.8cm;
    }
    /* Ocultar elementos de interface */
    .sidebar, .no-print, .accordion-button::after {
        display: none !important;
    }
    /* Ocultar breadcrumbs e navegação */
    .breadcrumb, .breadcrumb-item, .nav-breadcrumb, .page-header-desc {
        display: none !important;
    }
    /* Ocultar ícones Bootstrap Icons exceto os essenciais para impressão */
    .bi {
        display: none !important;
    }
    /* Mostrar header simples da academia */
    .print-header-simple {
        display: block !important;
        text-align: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #333;
    }
    .print-header-simple h3 {
        margin: 10px 0 5px;
        font-size: 18px;
    }
    .print-header-simple p {
        margin: 0;
        font-size: 11px;
        color: #666;
    }
    .print-header-simple img {
        max-height: 50px;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        margin-bottom: 8px !important;
        page-break-inside: avoid;
    }
    .card-body {
        padding: 8px !important;
    }
    .card-header {
        padding: 8px 12px !important;
        background: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body {
        background: white !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }
    .row {
        margin: 0 !important;
    }
    [class*="col-"] {
        padding: 3px 5px !important;
    }
    table {
        font-size: 10px;
    }
    table th, table td {
        padding: 3px 6px !important;
    }
    .badge {
        font-size: 9px;
        padding: 1px 4px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    h4 {
        font-size: 15px;
        margin-bottom: 3px !important;
    }
    .text-muted {
        color: #666 !important;
    }
    .print-separator {
        display: inline !important;
    }
    .print-info-label {
        font-size: 10px;
        color: #666;
    }
    .print-student-name {
        font-size: 16px;
    }
    .print-student-info {
        font-size: 11px;
    }
    .print-info-bar .d-flex {
        gap: 4px !important;
    }
    /* Título do grupo na impressão */
    .print-grupo-title {
        font-size: 14px;
    }
    .print-grupo-title .bi {
        display: inline !important;
        margin-right: 5px !important;
    }
    .grupo-exercicios-card {
        page-break-inside: avoid;
    }
</style>

<script>
// Ativar header de impressão e footer
document.addEventListener('DOMContentLoaded', function() {
    const printHeader = document.getElementById('printHeaderSimple');
    if (printHeader) {
        printHeader.classList.remove('d-none');
    }
    
    const printFooter = document.getElementById('printFooter');
    if (printFooter) {
        printFooter.classList.remove('d-none');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
