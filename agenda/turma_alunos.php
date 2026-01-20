<?php
/**
 * Agenda - Vincular Alunos a Turmas
 * Permite gerenciar quais alunos participam de cada turma
 */
$titulo_pagina = 'Alunos nas Turmas';
$subtitulo_pagina = 'Gerenciamento de Alunos por Turma';

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
    
    // Obter todos os alunos ativos
    $stmt = $pdo->prepare("
        SELECT id, nome, telefone 
        FROM students 
        WHERE gym_id = :gym_id AND status = 'ativo'
        ORDER BY nome
    ");
    $stmt->execute([':gym_id' => getGymId()]);
    $all_students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    $classes = [];
    $all_students = [];
}

// Obter turmas com seus horários
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

// Obter alunos já vinculados a cada turma
$class_students = [];
if (!empty($classes)) {
    $class_ids = array_column($classes, 'id');
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT cb.*, s.nome as student_name, s.telefone,
                   c.name as class_name
            FROM class_bookings cb
            JOIN students s ON cb.student_id = s.id
            JOIN class_definitions c ON cb.class_definition_id = c.id
            WHERE cb.class_definition_id IN ($placeholders)
            AND cb.status = 'confirmed'
            ORDER BY c.name, s.nome
        ");
        $stmt->execute($class_ids);
        $bookings = $stmt->fetchAll();
        
        foreach ($bookings as $booking) {
            $class_students[$booking['class_definition_id']][] = $booking;
        }
    }
}

// Processar formulário de vínculo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'vincular') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $student_ids = $_POST['student_ids'] ?? [];
    
    if (empty($class_id)) {
        $error = 'Selecione uma turma.';
    } elseif (empty($student_ids)) {
        $error = 'Selecione pelo menos um aluno.';
    } else {
        try {
            $vinculados = 0;
            foreach ($student_ids as $student_id) {
                $student_id = (int)$student_id;
                
                // Verificar se já está vinculado
                $stmt = $pdo->prepare("
                    SELECT id FROM class_bookings 
                    WHERE class_definition_id = ? AND student_id = ? AND status = 'confirmed'
                ");
                $stmt->execute([$class_id, $student_id]);
                
                if (!$stmt->fetch()) {
                    // Vincular aluno à turma
                    $stmt = $pdo->prepare("
                        INSERT INTO class_bookings (gym_id, class_definition_id, student_id, booking_date, status)
                        VALUES (?, ?, ?, CURDATE(), 'confirmed')
                    ");
                    $stmt->execute([getGymId(), $class_id, $student_id]);
                    $vinculados++;
                }
            }
            
            $success = "$vinculados aluno(s) vinculado(s) à turma com sucesso!";
            
            // Recarregar página para atualizar lista
            header("Location: turma_alunos.php?success=" . urlencode($success));
            exit;
            
        } catch (PDOException $e) {
            $error = 'Erro ao vincular alunos: ' . $e->getMessage();
        }
    }
}

// Processar desvinculação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'desvincular' && isset($_POST['booking_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE class_bookings SET status = 'canceled' WHERE id = ? AND gym_id = ?");
        $stmt->execute([$_POST['booking_id'], getGymId()]);
        $success = 'Aluno desvinculado da turma com sucesso!';
        
        header("Location: turma_alunos.php?success=" . urlencode($success));
        exit;
    } catch (PDOException $e) {
        $error = 'Erro ao desvincular: ' . $e->getMessage();
    }
}

// Selecionar turma para visualização
$selected_class_id = isset($_GET['turma']) ? (int)$_GET['turma'] : (isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0);

$day_labels = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$day_short_labels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item active">Agenda</li>
        <li class="breadcrumb-item active">Alunos nas Turmas</li>
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
    <!-- Formulário de Vínculo -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm sticky-top" style="top: 100px;">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-person-plus me-2"></i>Vincular Alunos
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formVincular">
                    <input type="hidden" name="acao" value="vincular">
                    
                    <div class="mb-3">
                        <label for="class_id" class="form-label fw-bold">Selecionar Turma *</label>
                        <select class="form-select" id="class_id" name="class_id" required onchange="updateStudentsList()">
                            <option value="">Selecione uma turma...</option>
                            <?php foreach ($classes as $class): ?>
                                <?php 
                                $class_schedules = $schedules[$class['id']] ?? [];
                                $horarios = [];
                                foreach ($class_schedules as $sch) {
                                    $horarios[] = $day_short_labels[$sch['day_of_week']] . ' ' . substr($sch['start_time'], 0, 5);
                                }
                                $horario_str = implode(', ', $horarios);
                                ?>
                                <option value="<?= $class['id'] ?>" <?= $selected_class_id == $class['id'] ? 'selected' : '' ?>>
                                    <?= sanitizar($class['name']) ?> (<?= $horario_str ?: 'Sem horários' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Selecionar Alunos *</label>
                        <div class="form-text mb-2">Marque os alunos que participam desta turma</div>
                        
                        <div class="students-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                            <?php foreach ($all_students as $student): ?>
                                <div class="form-check">
                                    <input class="form-check-input student-checkbox" type="checkbox" 
                                           name="student_ids[]" value="<?= $student['id'] ?>" 
                                           id="student_<?= $student['id'] ?>"
                                           data-class="<?= $selected_class_id ?>">
                                    <label class="form-check-label" for="student_<?= $student['id'] ?>">
                                        <?= sanitizar($student['nome']) ?>
                                        <small class="text-muted">(<?= sanitizar($student['telefone'] ?? 'Sem telefone') ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($all_students)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-people fs-4 d-block mb-2"></i>
                                    Nenhum aluno cadastrado
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-link-45deg me-2"></i>Vincular Alunos Selecionados
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Lista de Vínculos -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-people-fill me-2"></i>Alunos por Turma
                </h5>
                <div class="btn-group">
                    <?php foreach (array_slice($classes, 0, 5) as $class): ?>
                        <a href="?turma=<?= $class['id'] ?>" 
                           class="btn btn-sm <?= $selected_class_id == $class['id'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                            <?= substr($class['name'], 0, 10) ?>...
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($classes)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                        <p class="mb-0">Nenhuma turma cadastrada ainda.</p>
                    </div>
                <?php elseif (empty($selected_class_id)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-hand-index fs-1 d-block mb-3"></i>
                        <p class="mb-0">Selecione uma turma para ver os alunos vinculados.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $selected_class = null;
                    foreach ($classes as $c) {
                        if ($c['id'] == $selected_class_id) {
                            $selected_class = $c;
                            break;
                        }
                    }
                    $vinculados = $class_students[$selected_class_id] ?? [];
                    ?>
                    
                    <?php if ($selected_class): ?>
                        <div class="p-3 bg-light border-bottom">
                            <h6 class="mb-1">
                                <span class="badge" style="background-color: <?= $selected_class['color_hex'] ?>">
                                    <?= sanitizar($selected_class['name']) ?>
                                </span>
                            </h6>
                            <small class="text-muted">
                                <?php 
                                $class_schedules = $schedules[$selected_class['id']] ?? [];
                                $horarios = [];
                                foreach ($class_schedules as $sch) {
                                    $horarios[] = $day_labels[$sch['day_of_week']] . ' (' . substr($sch['start_time'], 0, 5) . '-' . substr($sch['end_time'], 0, 5) . ')';
                                }
                                echo implode(', ', $horarios) ?: 'Sem horários definidos';
                                ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($vinculados)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-3"></i>
                            <p class="mb-0">Nenhum aluno vinculado a esta turma ainda.</p>
                            <p class="small">Selecione alunos no formulário ao lado e clique em Vincular.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Aluno</th>
                                        <th>Contato</th>
                                        <th>Data do Vínculo</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vinculados as $booking): ?>
                                        <tr>
                                            <td>
                                                <i class="bi bi-person-fill me-2 text-muted"></i>
                                                <?= sanitizar($booking['student_name']) ?>
                                            </td>
                                            <td><?= sanitizar($booking['telefone'] ?? '-') ?></td>
                                            <td><?= date('d/m/Y', strtotime($booking['created_at'])) ?></td>
                                            <td class="text-center">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="acao" value="desvincular">
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            title="Desvincular" 
                                                            onclick="return confirm('Tem certeza que deseja desvincular este aluno da turma?');">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Resumo -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart me-2"></i>Resumo de Vínculos
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($classes as $class): ?>
                        <?php $count = count($class_students[$class['id']] ?? []); ?>
                        <div class="col-md-4">
                            <div class="p-3 border rounded text-center">
                                <h6 class="mb-2"><?= sanitizar(substr($class['name'], 0, 20)) ?></h6>
                                <div class="fs-4 fw-bold <?= $count > 0 ? 'text-success' : 'text-muted' ?>">
                                    <?= $count ?>
                                </div>
                                <small class="text-muted">alunos vinculados</small>
                                <a href="?turma=<?= $class['id'] ?>" class="btn btn-sm btn-outline-primary mt-2 w-100">
                                    Ver/Alterar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Função para atualizar lista de alunos (futuro: marcar já vinculados)
function updateStudentsList() {
    const classId = document.getElementById('class_id').value;
    const checkboxes = document.querySelectorAll('.student-checkbox');
    
    // Resetar todos os checkboxes
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
}

// Mostrar/esconder lista de alunos baseado na turma selecionada
document.getElementById('class_id').addEventListener('change', function() {
    // Pode ser usado para filtrar alunos já vinculados
});
</script>

<?php include '../includes/footer.php'; ?>
