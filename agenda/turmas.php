<?php
/**
 * Agenda - Listagem de Turmas (NOVA VERSÃO)
 * Suporta múltiplos dias da semana para cada turma
 */
$titulo_pagina = 'Turmas';
$subtitulo_pagina = 'Gerenciamento de Turmas e Aulas';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';
require_once '../config/functions.php';

$error = '';
$success = '';

// Obter modalidades para selects
try {
    $stmt = $pdo->prepare("SELECT id, nome FROM modalities WHERE gym_id = :gym_id ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $modalities = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT id, nome, email FROM users WHERE gym_id = :gym_id AND role IN ('admin', 'instrutor') ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $instructors = $stmt->fetchAll();
    
    // Obter turmas com seus horários
    $stmt = $pdo->prepare("
        SELECT c.*, m.nome as modalidade_nome, u.nome as instrutor_nome
        FROM class_definitions c
        LEFT JOIN modalities m ON c.modality_id = m.id
        LEFT JOIN users u ON c.instructor_id = u.id
        WHERE c.gym_id = :gym_id AND c.active = 1
        ORDER BY c.name
    ");
    $stmt->execute([':gym_id' => getGymId()]);
    $classes = $stmt->fetchAll();
    
    // Obter horários de cada turma
    $schedules = [];
    if (!empty($classes)) {
        $class_ids = array_column($classes, 'id');
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT * FROM class_schedules 
            WHERE class_definition_id IN ($placeholders)
            ORDER BY day_of_week, start_time
        ");
        $stmt->execute($class_ids);
        $all_schedules = $stmt->fetchAll();
        
        foreach ($all_schedules as $schedule) {
            $schedules[$schedule['class_definition_id']][] = $schedule;
        }
    }
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    $modalities = [];
    $instructors = [];
    $classes = [];
    $schedules = [];
}

// Processar formulário de nova turma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $name = trim($_POST['name'] ?? '');
    $modality_id_raw = $_POST['modality_id'] ?? '';
    $modality_id = (is_numeric($modality_id_raw) && $modality_id_raw > 0) ? (int)$modality_id_raw : null;
    $instructor_id_raw = $_POST['instructor_id'] ?? '';
    $instructor_id = (is_numeric($instructor_id_raw) && $instructor_id_raw > 0) ? (int)$instructor_id_raw : null;
    $max_capacity = (int)($_POST['max_capacity'] ?? 20);
    $color_hex = trim($_POST['color_hex'] ?? '#0d6efd');
    $description = trim($_POST['description'] ?? '');
    
    // Obter dias selecionados (agora é array)
    $days_of_week = $_POST['days_of_week'] ?? [];
    
    // Validações
    if (empty($name)) {
        $error = 'O nome da turma é obrigatório.';
    } elseif (empty($days_of_week)) {
        $error = 'Selecione pelo menos um dia da semana.';
    } else {
        // Obter horários do POST
        $start_times = $_POST['start_time'] ?? [];
        $end_times = $_POST['end_time'] ?? [];
        
        // Validar que há horário para cada dia
        $has_valid_schedule = false;
        $valid_days = [];
        $valid_start_times = [];
        $valid_end_times = [];
        
        foreach ($days_of_week as $day) {
            $day = (int)$day;
            $start = $start_times[$day] ?? '';
            $end = $end_times[$day] ?? '';
            
            if (!empty($start) && !empty($end) && $start < $end) {
                $valid_days[] = $day;
                $valid_start_times[$day] = $start;
                $valid_end_times[$day] = $end;
                $has_valid_schedule = true;
            }
        }
        
        if (!$has_valid_schedule) {
            $error = 'Informe o horário de início e fim para pelo menos um dia.';
        } else {
            try {
                // INSERT na tabela class_definitions
                $sql = "INSERT INTO class_definitions (
                            gym_id, modality_id, instructor_id, name, description,
                            max_capacity, color_hex, active
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    getGymId(),
                    $modality_id,
                    $instructor_id,
                    $name,
                    !empty($description) ? $description : null,
                    $max_capacity,
                    $color_hex
                ]);
                
                $class_id = $pdo->lastInsertId();
                
                // INSERT em class_schedules para cada dia
                foreach ($valid_days as $day) {
                    $stmt = $pdo->prepare("
                        INSERT INTO class_schedules (gym_id, class_definition_id, day_of_week, start_time, end_time)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        getGymId(),
                        $class_id,
                        $day,
                        $valid_start_times[$day],
                        $valid_end_times[$day]
                    ]);
                }
                
                $success = 'Turma criada com sucesso!';
                
                // Recarregar dados
                header("Location: turmas.php?success=1");
                exit;
                
            } catch (PDOException $e) {
                $error = 'Erro ao criar turma: ' . $e->getMessage();
            }
        }
    }
}

// Processar desativação de turma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'desativar' && isset($_POST['class_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE class_definitions SET active = 0 WHERE id = ? AND gym_id = ?");
        $stmt->execute([$_POST['class_id'], getGymId()]);
        $success = 'Turma desativada com sucesso!';
        
        // Recarregar dados
        header("Location: turmas.php");
        exit;
    } catch (PDOException $e) {
        $error = 'Erro ao desativar turma: ' . $e->getMessage();
    }
}

// Labels para dias da semana
$day_labels = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$day_short_labels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item active">Agenda</li>
        <li class="breadcrumb-item active">Turmas</li>
    </ol>
</nav>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success || isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= $success ?: 'Turma criada com sucesso!' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Lista de Turmas -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list me-2"></i>Turmas Cadastradas
                </h5>
                <span class="badge bg-secondary"><?= count($classes) ?> turmas ativas</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($classes)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                        <p class="mb-0">Nenhuma turma cadastrada ainda.</p>
                        <p class="small">Crie sua primeira turma usando o formulário ao lado.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Turma</th>
                                    <th>Modalidade</th>
                                    <th>Dias e Horários</th>
                                    <th>Instrutor</th>
                                    <th>Vagas</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: <?= $class['color_hex'] ?>">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                                <?= sanitizar($class['name']) ?>
                                            </span>
                                        </td>
                                        <td><?= sanitizar($class['modalidade_nome'] ?? '-') ?></td>
                                        <td>
                                            <?php 
                                            $class_schedules = $schedules[$class['id']] ?? [];
                                            if (empty($class_schedules)) {
                                                echo '<span class="text-muted">Sem horários</span>';
                                            } else {
                                                $horarios_formatados = [];
                                                foreach ($class_schedules as $sch) {
                                                    $horarios_formatados[] = $day_short_labels[$sch['day_of_week']] . ' ' . substr($sch['start_time'], 0, 5);
                                                }
                                                echo '<span class="badge bg-light text-dark">' . implode(', ', $horarios_formatados) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?= sanitizar($class['instrutor_nome'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= $class['max_capacity'] ?> máx</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="agenda.php?turma=<?= $class['id'] ?>" class="btn btn-outline-primary" title="Ver no Calendário">
                                                    <i class="bi bi-calendar-week"></i>
                                                </a>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="acao" value="desativar">
                                                    <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Desativar" onclick="return confirm('Tem certeza que deseja desativar esta turma?');">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Grade Semanal Completa -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-grid-3x3 me-2"></i>Grade Semanal Completa
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Organizar turmas por dia e horário
                $grade = [];
                foreach ($classes as $class) {
                    $class_schedules = $schedules[$class['id']] ?? [];
                    foreach ($class_schedules as $sch) {
                        $key = substr($sch['start_time'], 0, 5);
                        if (!isset($grade[$key])) {
                            $grade[$key] = [0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];
                        }
                        $grade[$key][$sch['day_of_week']][] = $class;
                    }
                }
                ksort($grade);
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" style="font-size: 0.875rem;">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Horário</th>
                                <?php for ($i = 0; $i < 7; $i++): ?>
                                    <th class="text-center"><?= $day_short_labels[$i] ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grade as $hora => $dias): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $hora ?></td>
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                        <?php if (!empty($dias[$i])): ?>
                                            <td class="text-center" style="background-color: <?= $dias[$i][0]['color_hex'] ?>20;">
                                                <?php foreach ($dias[$i] as $turma): ?>
                                                    <span class="badge d-block mb-1" style="background-color: <?= $turma['color_hex'] ?>">
                                                        <?= sanitizar(substr($turma['name'], 0, 12)) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                        <?php else: ?>
                                            <td class="text-center text-muted">-</td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulário -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top: 100px;">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-2"></i>Nova Turma
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formTurma">
                    <input type="hidden" name="acao" value="criar">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Nome da Turma *</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Ex: Musculação Manhã" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modality_id" class="form-label">Modalidade</label>
                        <select class="form-select" id="modality_id" name="modality_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($modalities as $mod): ?>
                                <option value="<?= $mod['id'] ?>"><?= sanitizar($mod['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="instructor_id" class="form-label">Instrutor</label>
                        <select class="form-select" id="instructor_id" name="instructor_id">
                            <option value="">Nenhum</option>
                            <?php foreach ($instructors as $inst): ?>
                                <option value="<?= $inst['id'] ?>"><?= sanitizar($inst['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dias da Semana *</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php for ($i = 0; $i < 7; $i++): ?>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" 
                                           name="days_of_week[]" value="<?= $i ?>" 
                                           id="day_<?= $i ?>" onchange="toggleTimeInputs(<?= $i ?>)">
                                    <label class="form-check-label small" for="day_<?= $i ?>">
                                        <?= $day_short_labels[$i] ?>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Container de Horários -->
                    <div id="time_inputs_container">
                        <?php for ($i = 0; $i < 7; $i++): ?>
                            <div class="time-inputs mb-2" id="time_inputs_<?= $i ?>" style="display: none;">
                                <div class="card bg-light">
                                    <div class="card-body py-2 px-3">
                                        <label class="form-label small fw-bold"><?= $day_labels[$i] ?></label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <input type="time" class="form-control form-control-sm" 
                                                       name="start_time[<?= $i ?>]" id="start_<?= $i ?>" placeholder="Início">
                                            </div>
                                            <div class="col-6">
                                                <input type="time" class="form-control form-control-sm" 
                                                       name="end_time[<?= $i ?>]" id="end_<?= $i ?>" placeholder="Fim">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_capacity" class="form-label">Capacidade Máxima</label>
                        <input type="number" class="form-control" id="max_capacity" name="max_capacity" value="20" min="1" max="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="color_hex" class="form-label">Cor</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="color_hex" name="color_hex" value="#0d6efd">
                            <span class="input-group-text">Cor</span>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" id="btnSalvar">
                            <i class="bi bi-check-lg me-2"></i>Salvar Turma
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTimeInputs(day) {
    const checkbox = document.getElementById('day_' + day);
    const timeInputs = document.getElementById('time_inputs_' + day);
    
    if (checkbox.checked) {
        timeInputs.style.display = 'block';
        document.getElementById('start_' + day).required = true;
        document.getElementById('end_' + day).required = true;
    } else {
        timeInputs.style.display = 'none';
        document.getElementById('start_' + day).required = false;
        document.getElementById('end_' + day).required = false;
        document.getElementById('start_' + day).value = '';
        document.getElementById('end_' + day).value = '';
    }
}

// Validação do formulário
document.getElementById('formTurma').addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('.day-checkbox:checked');
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Selecione pelo menos um dia da semana!');
        return;
    }
    
    // Verificar se pelo menos um dia tem horário preenchido
    let hasTime = false;
    checkboxes.forEach(function(cb) {
        const day = cb.value;
        const start = document.getElementById('start_' + day).value;
        const end = document.getElementById('end_' + day).value;
        if (start && end) {
            if (start >= end) {
                e.preventDefault();
                alert('O horário de fim deve ser maior que o horário de início!');
            } else {
                hasTime = true;
            }
        }
    });
    
    if (!hasTime && !e.defaultPrevented) {
        e.preventDefault();
        alert('Preencha o horário de início e fim para pelo menos um dia!');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
