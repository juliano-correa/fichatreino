<?php
/**
 * Agenda - Controle de Presença
 * Permite marcar presença dos alunos em cada aula
 */
$titulo_pagina = 'Controle de Presença';
$subtitulo_pagina = 'Registro de Presença nas Aulas';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';
require_once '../config/functions.php';

$error = '';
$success = '';

// Obter turmas ativas
try {
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
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar turmas: ' . $e->getMessage();
    $classes = [];
}

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

// Obter alunos vinculados às turmas
$class_students = [];
if (!empty($classes)) {
    $class_ids = array_column($classes, 'id');
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT cb.*, s.id as student_id, s.nome as student_name, s.telefone
            FROM class_bookings cb
            JOIN students s ON cb.student_id = s.id
            WHERE cb.class_definition_id IN ($placeholders)
            AND cb.status = 'confirmed'
            ORDER BY s.nome
        ");
        $stmt->execute($class_ids);
        $bookings = $stmt->fetchAll();
        
        foreach ($bookings as $booking) {
            $class_students[$booking['class_definition_id']][] = $booking;
        }
    }
}

// Obter presenças já registradas
$attendances = [];
if (!empty($classes)) {
    $class_ids = array_column($classes, 'id');
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT ca.*, s.nome as student_name
            FROM class_attendance ca
            JOIN students s ON ca.student_id = s.id
            WHERE ca.class_definition_id IN ($placeholders)
            ORDER BY ca.attendance_date DESC, ca.checked_in_at DESC
        ");
        $stmt->execute($class_ids);
        $all_attendances = $stmt->fetchAll();
        
        foreach ($all_attendances as $att) {
            $key = $att['class_definition_id'] . '_' . $att['attendance_date'];
            $attendances[$key][$att['student_id']] = $att;
        }
    }
}

// Processar formulário de presença
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_presenca') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $presents = $_POST['presents'] ?? [];
    $absents = $_POST['absents'] ?? [];
    
    if (empty($class_id)) {
        $error = 'Selecione uma turma.';
    } elseif (empty($attendance_date)) {
        $error = 'Selecione uma data.';
    } else {
        try {
            $salvos = 0;
            $present_students = [];
            
            // Marcar presenças
            foreach ($presents as $student_id) {
                $student_id = (int)$student_id;
                $present_students[] = $student_id;
                
                // Verificar se já existe registro
                $stmt = $pdo->prepare("
                    SELECT id FROM class_attendance 
                    WHERE class_definition_id = ? AND student_id = ? AND attendance_date = ?
                ");
                $stmt->execute([$class_id, $student_id, $attendance_date]);
                
                if (!$stmt->fetch()) {
                    // Criar ou atualizar registro de presença
                    $stmt = $pdo->prepare("
                        INSERT INTO class_attendance 
                        (gym_id, class_definition_id, student_id, attendance_date, present, checked_in_at, checked_by_user_id)
                        VALUES (?, ?, ?, ?, 1, NOW(), ?)
                        ON DUPLICATE KEY UPDATE present = 1, checked_in_at = NOW()
                    ");
                    $stmt->execute([getGymId(), $class_id, $student_id, $attendance_date, $_SESSION['user_id']]);
                    $salvos++;
                } else {
                    // Atualizar presença existente
                    $stmt = $pdo->prepare("
                        UPDATE class_attendance 
                        SET present = 1, checked_in_at = NOW(), checked_by_user_id = ?
                        WHERE class_definition_id = ? AND student_id = ? AND attendance_date = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $class_id, $student_id, $attendance_date]);
                    $salvos++;
                }
            }
            
            // Marcar ausências
            $all_students = $class_students[$class_id] ?? [];
            foreach ($all_students as $booking) {
                $student_id = (int)$booking['student_id'];
                
                if (!in_array($student_id, $present_students)) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM class_attendance 
                        WHERE class_definition_id = ? AND student_id = ? AND attendance_date = ?
                    ");
                    $stmt->execute([$class_id, $student_id, $attendance_date]);
                    
                    if (!$stmt->fetch()) {
                        // Criar registro de ausência
                        $stmt = $pdo->prepare("
                            INSERT INTO class_attendance 
                            (gym_id, class_definition_id, student_id, attendance_date, present, checked_by_user_id)
                            VALUES (?, ?, ?, ?, 0, ?)
                        ");
                        $stmt->execute([getGymId(), $class_id, $student_id, $attendance_date, $_SESSION['user_id']]);
                    } else {
                        // Atualizar ausência
                        $stmt = $pdo->prepare("
                            UPDATE class_attendance 
                            SET present = 0, checked_by_user_id = ?
                            WHERE class_definition_id = ? AND student_id = ? AND attendance_date = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $class_id, $student_id, $attendance_date]);
                    }
                }
            }
            
            $success = "Presença salva com sucesso! ($salvos registros)";
            
            // Recarregar página
            header("Location: presenca.php?success=" . urlencode($success) . "&turma=$class_id&data=$attendance_date");
            exit;
            
        } catch (PDOException $e) {
            $error = 'Erro ao salvar presença: ' . $e->getMessage();
        }
    }
}

// Obter parâmetros da URL
$selected_class_id = isset($_GET['turma']) ? (int)$_GET['turma'] : (isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0);
$selected_date = isset($_GET['data']) ? $_GET['data'] : (isset($_POST['attendance_date']) ? $_POST['attendance_date'] : date('Y-m-d'));

// Obter presenças do dia selecionado
$day_attendances = [];
if ($selected_class_id && $selected_date) {
    $key = $selected_class_id . '_' . $selected_date;
    $day_attendances = $attendances[$key] ?? [];
}

$day_labels = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$day_short_labels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item active">Agenda</li>
        <li class="breadcrumb-item active">Controle de Presença</li>
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
        <i class="bi bi-check-circle me-2"></i><?= $success ?: $_GET['success'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Formulário de Presença -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm sticky-top" style="top: 100px;">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-check me-2"></i>Registro de Presença
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formPresenca">
                    <input type="hidden" name="acao" value="salvar_presenca">
                    
                    <div class="mb-3">
                        <label for="class_id" class="form-label fw-bold">Selecionar Turma *</label>
                        <select class="form-select" id="class_id" name="class_id" required onchange="loadStudentsAndSchedule()">
                            <option value="">Selecione uma turma...</option>
                            <?php foreach ($classes as $class): ?>
                                <?php 
                                $class_schedules = $schedules[$class['id']] ?? [];
                                $horarios = [];
                                foreach ($class_schedules as $sch) {
                                    $horarios[] = $day_short_labels[$sch['day_of_week']] . ' ' . substr($sch['start_time'], 0, 5);
                                }
                                $horario_str = implode(', ', $horarios);
                                $student_count = count($class_students[$class['id']] ?? []);
                                ?>
                                <option value="<?= $class['id'] ?>" <?= $selected_class_id == $class['id'] ? 'selected' : '' ?>>
                                    <?= sanitizar($class['name']) ?> (<?= $student_count ?> alunos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attendance_date" class="form-label fw-bold">Data da Aula *</label>
                        <input type="date" class="form-control" id="attendance_date" name="attendance_date" 
                               value="<?= $selected_date ?>" required
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <?php if ($selected_class_id): ?>
                        <?php 
                        $selected_class = null;
                        foreach ($classes as $c) {
                            if ($c['id'] == $selected_class_id) {
                                $selected_class = $c;
                                break;
                            }
                        }
                        $students = $class_students[$selected_class_id] ?? [];
                        ?>
                        
                        <?php if ($selected_class && !empty($students)): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold mb-0">Alunos (<?= count($students) ?>)</label>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-success" onclick="markAllPresent()">Todos Presentes</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="markAllAbsent()">Todos Ausentes</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="invertAll()">Inverter</button>
                                    </div>
                                </div>
                                
                                <div class="students-list" style="max-height: 350px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                                    <?php foreach ($students as $booking): ?>
                                        <?php 
                                        $student_id = $booking['student_id'];
                                        $is_present = isset($day_attendances[$student_id]) && $day_attendances[$student_id]['present'] == 1;
                                        ?>
                                        <div class="student-row p-2 border-bottom d-flex align-items-center justify-content-between <?= $is_present ? 'bg-success-subtle' : '' ?>" id="row_<?= $student_id ?>">
                                            <div class="form-check">
                                                <input class="form-check-input present-checkbox" type="checkbox" 
                                                       name="presents[]" value="<?= $student_id ?>" 
                                                       id="present_<?= $student_id ?>"
                                                       <?= $is_present ? 'checked' : '' ?>
                                                       onchange="updateRowColor(<?= $student_id ?>)">
                                                <label class="form-check-label fw-medium" for="present_<?= $student_id ?>">
                                                    <?= sanitizar($booking['student_name']) ?>
                                                </label>
                                            </div>
                                            <div class="status-badge">
                                                <?php if ($is_present): ?>
                                                    <span class="badge bg-success">Presente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Ausente</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-2 d-flex justify-content-between text-muted small">
                                    <span id="present_count">Presentes: <?= array_sum(array_column($day_attendances, 'present')) ?></span>
                                    <span id="absent_count">Ausentes: <?= count($students) - array_sum(array_column($day_attendances, 'present')) ?></span>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg me-2"></i>Salvar Presença
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Esta turma não tem alunos vinculados. 
                                <a href="turma_alunos.php" target="_blank">Vincular alunos</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Selecione uma turma para ver os alunos.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Histórico de Presenças -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-check me-2"></i>Histórico de Presenças
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($attendances)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                        <p class="mb-0">Nenhum registro de presença ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Turma</th>
                                    <th>Presentes</th>
                                    <th>Ausentes</th>
                                    <th>% Presença</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Agrupar por turma e data
                                $history = [];
                                foreach ($attendances as $key => $atts) {
                                    list($class_id_key, $date_key) = explode('_', $key);
                                    $presents = 0;
                                    $absents = 0;
                                    foreach ($atts as $att) {
                                        if ($att['present'] == 1) $presents++;
                                        else $absents++;
                                    }
                                    $total = $presents + $absents;
                                    $percent = $total > 0 ? round(($presents / $total) * 100) : 0;
                                    
                                    // Buscar nome da turma
                                    $class_name = '';
                                    foreach ($classes as $c) {
                                        if ($c['id'] == $class_id_key) {
                                            $class_name = $c['name'];
                                            break;
                                        }
                                    }
                                    
                                    $history[$date_key][$class_id_key] = [
                                        'class_name' => $class_name,
                                        'presents' => $presents,
                                        'absents' => $absents,
                                        'percent' => $percent
                                    ];
                                }
                                krsort($history);
                                ?>
                                <?php foreach ($history as $date => $turmas): ?>
                                    <?php foreach ($turmas as $data): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($date)) ?></td>
                                            <td><?= sanitizar($data['class_name']) ?></td>
                                            <td>
                                                <span class="badge bg-success"><?= $data['presents'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?= $data['absents'] ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 8px; width: 80px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $data['percent'] ?>%"></div>
                                                    </div>
                                                    <small><?= $data['percent'] ?>%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Estatísticas Rápidas -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart-line me-2"></i>Estatísticas por Aluno
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Calcular estatísticas por aluno
                $student_stats = [];
                foreach ($attendances as $key => $atts) {
                    foreach ($atts as $att) {
                        $student_id = $att['student_id'];
                        $student_name = $att['student_name'];
                        
                        if (!isset($student_stats[$student_id])) {
                            $student_stats[$student_id] = [
                                'nome' => $student_name,
                                'presents' => 0,
                                'absents' => 0,
                                'total' => 0
                            ];
                        }
                        $student_stats[$student_id]['total']++;
                        if ($att['present'] == 1) {
                            $student_stats[$student_id]['presents']++;
                        } else {
                            $student_stats[$student_id]['absents']++;
                        }
                    }
                }
                
                // Ordenar por percentual de presença
                usort($student_stats, function($a, $b) {
                    $percent_a = $a['total'] > 0 ? ($a['presents'] / $a['total']) : 0;
                    $percent_b = $b['total'] > 0 ? ($b['presents'] / $b['total']) : 0;
                    return $percent_b <=> $percent_a;
                });
                ?>
                
                <?php if (empty($student_stats)): ?>
                    <div class="text-center text-muted py-3">
                        Sem dados suficientes para estatísticas
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Aluno</th>
                                    <th class="text-center">Pres.</th>
                                    <th class="text-center">Aus.</th>
                                    <th class="text-center">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($student_stats as $stat): ?>
                                    <?php $percent = $stat['total'] > 0 ? round(($stat['presents'] / $stat['total']) * 100) : 0; ?>
                                    <tr>
                                        <td><?= sanitizar(substr($stat['nome'], 0, 25)) ?></td>
                                        <td class="text-center"><span class="badge bg-success-subtle text-success"><?= $stat['presents'] ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger-subtle text-danger"><?= $stat['absents'] ?></span></td>
                                        <td class="text-center">
                                            <span class="badge <?= $percent >= 80 ? 'bg-success' : ($percent >= 50 ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= $percent ?>%
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
    </div>
</div>

<script>
function updateRowColor(studentId) {
    const checkbox = document.getElementById('present_' + studentId);
    const row = document.getElementById('row_' + studentId);
    const badge = row.querySelector('.status-badge');
    
    if (checkbox.checked) {
        row.classList.add('bg-success-subtle');
        badge.innerHTML = '<span class="badge bg-success">Presente</span>';
    } else {
        row.classList.remove('bg-success-subtle');
        badge.innerHTML = '<span class="badge bg-secondary">Ausente</span>';
    }
    
    updateCounts();
}

function markAllPresent() {
    document.querySelectorAll('.present-checkbox').forEach(cb => {
        cb.checked = true;
        updateRowColor(cb.value);
    });
}

function markAllAbsent() {
    document.querySelectorAll('.present-checkbox').forEach(cb => {
        cb.checked = false;
        updateRowColor(cb.value);
    });
}

function invertAll() {
    document.querySelectorAll('.present-checkbox').forEach(cb => {
        cb.checked = !cb.checked;
        updateRowColor(cb.value);
    });
}

function updateCounts() {
    const checkboxes = document.querySelectorAll('.present-checkbox');
    let present = 0;
    let absent = 0;
    
    checkboxes.forEach(cb => {
        if (cb.checked) present++;
        else absent++;
    });
    
    document.getElementById('present_count').textContent = 'Presentes: ' + present;
    document.getElementById('absent_count').textContent = 'Ausentes: ' + absent;
}

// Inicializar contadores
document.addEventListener('DOMContentLoaded', function() {
    updateCounts();
});

// Carregar alunos e horário quando mudar a turma
function loadStudentsAndSchedule() {
    const classId = document.getElementById('class_id').value;
    const date = document.getElementById('attendance_date').value;
    
    if (classId && date) {
        window.location.href = 'presenca.php?turma=' + classId + '&data=' + date;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
