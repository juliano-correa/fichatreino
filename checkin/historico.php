<?php
// Check-in - Histórico Completo
$titulo_pagina = 'Histórico de Check-ins';
$subtitulo_pagina = 'Registro de Presenças';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Configuração de filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$aluno_id = $_GET['aluno_id'] ?? '';
$tipo_filtro = $_GET['tipo'] ?? '';

// Buscar alunos para filtro
try {
    $stmt = $pdo->prepare("SELECT id, nome, telefone FROM students WHERE gym_id = :gym_id AND status = 'ativo' ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $alunos = $stmt->fetchAll();
} catch (PDOException $e) {
    $alunos = [];
}

// Buscar check-ins com filtros
try {
    $sql = "
        SELECT c.*, s.nome as aluno_nome, s.telefone as aluno_telefone, s.cpf as aluno_cpf
        FROM checkins c
        LEFT JOIN students s ON c.aluno_id = s.id
        WHERE c.gym_id = :gym_id 
        AND DATE(c.data_checkin) BETWEEN :data_inicio AND :data_fim
    ";
    
    $params = [
        ':gym_id' => getGymId(),
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ];
    
    if (!empty($aluno_id)) {
        $sql .= " AND c.aluno_id = :aluno_id";
        $params[':aluno_id'] = $aluno_id;
    }
    
    if (!empty($tipo_filtro)) {
        $sql .= " AND c.tipo = :tipo";
        $params[':tipo'] = $tipo_filtro;
    }
    
    $sql .= " ORDER BY c.data_checkin DESC, c.hora_checkin DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $checkins = $stmt->fetchAll();
    
    // Estatísticas do período
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_acessos,
            COUNT(DISTINCT aluno_id) as alunos_unicos,
            COUNT(CASE WHEN hora_saida IS NULL THEN 1 END) as acessos_abertos
        FROM checkins c
        WHERE c.gym_id = :gym_id 
        AND DATE(c.data_checkin) BETWEEN :data_inicio AND :data_fim
    ");
    $stmt_stats->execute([
        ':gym_id' => getGymId(),
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $estatisticas = $stmt_stats->fetch();
    
} catch (PDOException $e) {
    $error = 'Erro ao buscar dados: ' . $e->getMessage();
    $checkins = [];
    $estatisticas = ['total_acessos' => 0, 'alunos_unicos' => 0, 'acessos_abertos' => 0];
}

// Calcular tempo médio
$tempo_medio = '0m';
if (!empty($checkins)) {
    $total_minutos = 0;
    $count_completo = 0;
    foreach ($checkins as $c) {
        if (!empty($c['hora_saida'])) {
            $entrada = new DateTime($c['hora_checkin']);
            $saida = new DateTime($c['hora_saida']);
            $total_minutos += $entrada->diff($saida)->i + ($entrada->diff($saida)->h * 60);
            $count_completo++;
        }
    }
    if ($count_completo > 0) {
        $media = (int)($total_minutos / $count_completo);
        $tempo_medio = $media >= 60 ? ($media / 60) . 'h ' . ($media % 60) . 'm' : $media . 'm';
    }
}
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Check-in</a></li>
        <li class="breadcrumb-item active">Histórico</li>
    </ol>
</nav>

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

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">
            <i class="bi bi-funnel me-2"></i>Filtros
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= sanitizar($data_inicio) ?>">
            </div>
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= sanitizar($data_fim) ?>">
            </div>
            <div class="col-md-3">
                <label for="aluno_id" class="form-label">Aluno</label>
                <select class="form-select" id="aluno_id" name="aluno_id">
                    <option value="">Todos os alunos</option>
                    <?php foreach ($alunos as $aluno): ?>
                        <option value="<?= $aluno['id'] ?>" <?= $aluno_id == $aluno['id'] ? 'selected' : '' ?>>
                            <?= sanitizar($aluno['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="tipo" class="form-label">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <option value="academia" <?= $tipo_filtro === 'academia' ? 'selected' : '' ?>>Academia</option>
                    <option value="aula" <?= $tipo_filtro === 'aula' ? 'selected' : '' ?>>Aula</option>
                    <option value="personal" <?= $tipo_filtro === 'personal' ? 'selected' : '' ?>>Personal</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estatísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-people fs-1 text-primary mb-2"></i>
                <h4 class="mb-0"><?= $estatisticas['total_acessos'] ?></h4>
                <small class="text-muted">Total de Acessos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-person-check fs-1 text-success mb-2"></i>
                <h4 class="mb-0"><?= $estatisticas['alunos_unicos'] ?></h4>
                <small class="text-muted">Alunos Únicos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-clock fs-1 text-warning mb-2"></i>
                <h4 class="mb-0"><?= $tempo_medio ?></h4>
                <small class="text-muted">Tempo Médio</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-door-open fs-1 text-info mb-2"></i>
                <h4 class="mb-0"><?= $estatisticas['acessos_abertos'] ?></h4>
                <small class="text-muted">Acessos Abertos</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Check-ins -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-table me-2"></i>Registros de Presença
        </h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print();">
                <i class="bi bi-printer me-1"></i>Imprimir
            </button>
            <button class="btn btn-sm btn-outline-success" onclick="exportarCSV();">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($checkins)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
                <p class="text-muted mb-0">Nenhum registro encontrado para o período selecionado</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabela_checkins">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Aluno</th>
                            <th>Telefone</th>
                            <th>Entrada</th>
                            <th>Saída</th>
                            <th>Duração</th>
                            <th>Tipo</th>
                            <th>Fonte</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checkins as $check): ?>
                            <?php
                            $hora_entrada = new DateTime($check['hora_checkin']);
                            $hora_saida = !empty($check['hora_saida']) ? new DateTime($check['hora_saida']) : null;
                            $duracao = $hora_saida ? $hora_entrada->diff($hora_saida) : null;
                            
                            if ($duracao) {
                                if ($duracao->h > 0) {
                                    $tempo_str = $duracao->h . 'h ' . $duracao->i . 'm';
                                } else {
                                    $tempo_str = $duracao->i . 'm';
                                }
                            } else {
                                $tempo_str = '<span class="text-success"><i class="bi bi-circle-fill animation-pulse"></i>ativo</span>';
                            }
                            ?>
                            <tr>
                                <td><?= $hora_entrada->format('d/m/Y') ?></td>
                                <td>
                                    <a href="../alunos/visualizar.php?id=<?= $check['aluno_id'] ?>" class="text-decoration-none">
                                        <strong><?= sanitizar($check['aluno_nome']) ?></strong>
                                    </a>
                                </td>
                                <td><?= formatarTelefone($check['aluno_telefone']) ?></td>
                                <td><?= $hora_entrada->format('H:i:s') ?></td>
                                <td><?= $hora_saida ? $hora_saida->format('H:i:s') : '-' ?></td>
                                <td><?= $tempo_str ?></td>
                                <td>
                                    <?php
                                    $tipos = [
                                        'academia' => ['class' => 'bg-primary', 'label' => 'Academia'],
                                        'aula' => ['class' => 'bg-success', 'label' => 'Aula'],
                                        'personal' => ['class' => 'bg-info', 'label' => 'Personal']
                                    ];
                                    $t = $tipos[$check['tipo']] ?? $tipos['academia'];
                                    ?>
                                    <span class="badge <?= $t['class'] ?>"><?= $t['label'] ?></span>
                                </td>
                                <td>
                                    <?php
                                    $fontes = [
                                        'manual' => ['icon' => 'hand-index', 'label' => 'Manual'],
                                        'qrcode' => ['icon' => 'qr-code', 'label' => 'QR Code'],
                                        'catraca' => ['icon' => 'door-open', 'label' => 'Catraca'],
                                        'biometria' => ['icon' => 'fingerprint', 'label' => 'Biometria']
                                    ];
                                    $f = $fontes[$check['fonte']] ?? $fontes['manual'];
                                    ?>
                                    <span class="text-muted" title="<?= $f['label'] ?>">
                                        <i class="bi bi-<?= $f['icon'] ?>"></i>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resumo por Aluno -->
<?php if (!empty($checkins) && empty($aluno_id)): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="bi bi-person-badge me-2"></i>Frequência por Aluno
            </h5>
        </div>
        <div class="card-body p-0">
            <?php
            // Agrupar por aluno
            $alunos_agrupados = [];
            foreach ($checkins as $c) {
                $id = $c['aluno_id'];
                if (!isset($alunos_agrupados[$id])) {
                    $alunos_agrupados[$id] = [
                        'nome' => $c['aluno_nome'],
                        'telefone' => $c['aluno_telefone'],
                        'total' => 0,
                        'dias' => []
                    ];
                }
                $alunos_agrupados[$id]['total']++;
                $alunos_agrupados[$id]['dias'][$c['data_checkin']] = true;
            }
            
            // Ordenar por frequência
            uasort($alunos_agrupados, function($a, $b) {
                return $b['total'] - $a['total'];
            });
            ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Aluno</th>
                            <th>Telefone</th>
                            <th>Total Acessos</th>
                            <th>Dias Presente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($alunos_agrupados, 0, 10) as $id => $dados): ?>
                            <tr>
                                <td>
                                    <a href="?aluno_id=<?= $id ?>&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" class="text-decoration-none">
                                        <strong><?= sanitizar($dados['nome']) ?></strong>
                                    </a>
                                </td>
                                <td><?= formatarTelefone($dados['telefone']) ?></td>
                                <td><span class="badge bg-primary"><?= $dados['total'] ?></span></td>
                                <td><?= count($dados['dias']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.animation-pulse {
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.4; }
    100% { opacity: 1; }
}
@media print {
    .card-header button { display: none !important; }
    .breadcrumb { display: none !important; }
}
</style>

<script>
function exportarCSV() {
    let csv = [];
    const rows = document.querySelectorAll('#tabela_checkins tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        let rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    downloadLink.download = 'historico_checkins_<?= date('Y-m-d') ?>.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php include '../includes/footer.php'; ?>
