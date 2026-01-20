<?php
// Agenda - Calendário Semanal
$titulo_pagina = 'Agenda';
$subtitulo_pagina = 'Calendário de Aulas';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';
$selected_class = isset($_GET['turma']) ? (int)$_GET['turma'] : null;

// Obter turmas ativas para o select
try {
    $stmt = $pdo->prepare("
        SELECT c.*, m.nome as modalidade_nome 
        FROM class_definitions c
        LEFT JOIN modalities m ON c.modality_id = m.id
        WHERE c.gym_id = :gym_id AND c.active = 1
        ORDER BY c.name
    ");
    $stmt->execute([':gym_id' => getGymId()]);
    $classes = $stmt->fetchAll();
    
    // Obter alunos ativos para autocomplete
    $stmt = $pdo->prepare("SELECT id, nome, telefone FROM students WHERE gym_id = :gym_id AND status = 'ativo' ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    $classes = [];
    $students = [];
}

// Labels para dias
$day_labels = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
?>

<?php include '../includes/header.php'; ?>

<!-- FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet'>

<style>
.fc .fc-timegrid-slot {
    height: 50px !important;
}
.fc-event {
    cursor: pointer;
    border-radius: 4px;
    border: none;
    padding: 2px 4px;
    font-size: 0.8rem;
}
.fc-event-main {
    padding: 2px;
}
.capacity-badge {
    font-size: 0.7rem;
    opacity: 0.9;
}
.class-details-modal .modal-header {
    border-bottom: 2px solid var(--class-color);
}
.attendance-toggle .btn-check:checked + .btn-success {
    background-color: #198754;
    border-color: #198754;
}
.attendance-toggle .btn-check:checked + .btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item active">Agenda</li>
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

<div class="row g-4">
    <div class="col-lg-12">
        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Turma</label>
                        <select class="form-select" id="classFilter" onchange="filterCalendar()">
                            <option value="all">Todas as Turmas</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $selected_class == $class['id'] ? 'selected' : '' ?>>
                                    <?= sanitizar($class['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100" onclick="goToToday()">
                            <i class="bi bi-calendar-check me-2"></i>Hoje
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="turmas.php" class="btn btn-outline-secondary">
                            <i class="bi bi-gear me-2"></i>Gerenciar Turmas
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Calendário -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div id='calendar' style="min-height: 600px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalhes da Aula -->
<div class="modal fade class-details-modal" id="classModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="modalHeader" style="--class-color: #0d6efd;">
                <h5 class="modal-title" id="modalTitle">
                    <i class="bi bi-calendar-event me-2"></i>
                    <span id="classTitle">Nome da Aula</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Info da Aula -->
                <div class="row g-3 mb-4" id="classInfo">
                    <div class="col-md-3">
                        <label class="text-muted small">Data</label>
                        <p class="mb-0 fw-bold" id="classDate">-</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Horário</label>
                        <p class="mb-0 fw-bold" id="classTime">-</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Instrutor</label>
                        <p class="mb-0 fw-bold" id="classInstructor">-</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Lotação</label>
                        <p class="mb-0 fw-bold" id="classCapacity">-</p>
                    </div>
                </div>
                
                <!-- Barra de Progresso -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small">Lotação da Aula</span>
                        <span class="small" id="capacityPercent">0%</span>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" id="capacityBar" role="progressbar" style="width: 0%">0/0</div>
                    </div>
                </div>
                
                <!-- Lista de Alunos -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-people me-2"></i>Alunos Agendados
                        </h6>
                        <button class="btn btn-sm btn-outline-primary" onclick="openAddStudentModal()">
                            <i class="bi bi-plus-lg me-1"></i>Adicionar
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Aluno</th>
                                        <th>Telefone</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Presença</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsList">
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            Nenhum aluno agendado ainda.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Adicionar Aluno -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Adicionar Aluno
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="addStudentClassId" value="">
                <input type="hidden" id="addStudentClassDate" value="">
                
                <label class="form-label">Buscar Aluno</label>
                <input type="text" class="form-control mb-3" id="studentSearch" placeholder="Digite o nome do aluno..." autocomplete="off">
                
                <div id="studentResults" class="list-group" style="max-height: 300px; overflow-y: auto;">
                    <!-- Resultados da busca aparecerão aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data for calendar -->
<script>
var calendar;
var classesData = <?= json_encode(array_map(function($c) {
    return [
        'id' => $c['id'],
        'name' => $c['name'],
        'modality' => $c['modalidade_nome'],
        'day_of_week' => $c['day_of_week'],
        'start_time' => substr($c['start_time'], 0, 5),
        'end_time' => substr($c['end_time'], 0, 5),
        'max_capacity' => $c['max_capacity'],
        'color' => $c['color_hex'],
        'instructor_id' => $c['instructor_id']
    ];
}, $classes)) ?>;

var studentsData = <?= json_encode(array_map(function($s) {
    return [
        'id' => $s['id'],
        'nome' => $s['nome'],
        'telefone' => $s['telefone']
    ];
}, $students)) ?>;
</script>

<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridWeek,dayGridMonth'
        },
        locale: 'pt-br',
        slotMinTime: '05:00:00',
        slotMaxTime: '23:00:00',
        allDaySlot: false,
        nowIndicator: true,
        weekends: true,
        height: 'auto',
        expandRows: true,
        stickyHeaderDates: true,
        
        events: function(info, successCallback, failureCallback) {
            var classFilter = document.getElementById('classFilter').value;
            var events = [];
            
            // Gerar eventos recorrentes baseados na definição das turmas
            classesData.forEach(function(c) {
                if (classFilter !== 'all' && c.id != classFilter) return;
                
                var startDate = info.start;
                var endDate = info.end;
                
                // Gerar eventos para cada dia no período visível
                var current = new Date(startDate);
                while (current < endDate) {
                    if (current.getDay() === c.day_of_week) {
                        var eventDate = current.toISOString().split('T')[0];
                        var startDateTime = eventDate + 'T' + c.start_time + ':00';
                        var endDateTime = eventDate + 'T' + c.end_time + ':00';
                        
                        events.push({
                            id: c.id + '_' + eventDate,
                            classId: c.id,
                            title: c.name,
                            start: startDateTime,
                            end: endDateTime,
                            backgroundColor: c.color,
                            borderColor: c.color,
                            extendedProps: {
                                name: c.name,
                                modality: c.modality,
                                date: eventDate,
                                startTime: c.start_time,
                                endTime: c.end_time,
                                maxCapacity: c.max_capacity,
                                color: c.color
                            }
                        });
                    }
                    current.setDate(current.getDate() + 1);
                }
            });
            
            successCallback(events);
        },
        
        eventClick: function(info) {
            openClassModal(info.event);
        },
        
        eventContent: function(arg) {
            var html = '<div class="fc-content">';
            html += '<b>' + arg.event.title + '</b>';
            html += '<div class="capacity-badge">' + arg.event.extendedProps.startTime + '</div>';
            html += '</div>';
            return { html: html };
        }
    });
    
    calendar.render();
    
    // Inicializar modal
    window.classModal = new bootstrap.Modal(document.getElementById('classModal'));
    window.addStudentModal = new bootstrap.Modal(document.getElementById('addStudentModal'));
});

function filterCalendar() {
    calendar.refetchEvents();
}

function goToToday() {
    calendar.today();
    calendar.gotoDate(new Date());
}

function openClassModal(event) {
    var props = event.extendedProps;
    var classId = props.classId;
    var classDate = props.date;
    
    // Configurar modal header
    document.getElementById('modalHeader').style.setProperty('--class-color', props.color);
    document.getElementById('classTitle').textContent = props.name;
    document.getElementById('classDate').textContent = formatDateBR(classDate);
    document.getElementById('classTime').textContent = props.startTime + ' - ' + props.endTime;
    document.getElementById('classInstructor').textContent = props.modality || 'Sem modalidade';
    
    // Carregar alunos agendados
    loadClassBookings(classId, classDate);
    
    classModal.show();
}

function loadClassBookings(classId, classDate) {
    // Simular carregamento (em produção, faria uma requisição AJAX)
    document.getElementById('studentsList').innerHTML = '<tr><td colspan="5" class="text-center py-4">Carregando...</td></tr>';
    
    // Aqui você faria uma requisição para buscar os agendamentos
    // Por enquanto, vamos mostrar uma mensagem
    setTimeout(function() {
        document.getElementById('studentsList').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-2"></i>Clique em "Adicionar" para incluir alunos nesta aula.</td></tr>';
        document.getElementById('capacityPercent').textContent = '0%';
        document.getElementById('capacityBar').style.width = '0%';
        document.getElementById('capacityBar').textContent = '0/' + document.querySelector('#classCapacity').textContent.split('/')[1] || '20';
        document.getElementById('classCapacity').textContent = '0/' + (document.querySelector('#classCapacity').textContent.split('/')[1] || '20');
    }, 500);
    
    // Atualizar campos
    document.getElementById('classCapacity').textContent = '0/' + (props.maxCapacity || 20);
    document.getElementById('capacityPercent').textContent = '0%';
    document.getElementById('capacityBar').style.width = '0%';
    document.getElementById('capacityBar').textContent = '0/' + (props.maxCapacity || 20);
}

function formatDateBR(dateStr) {
    var date = new Date(dateStr + 'T00:00:00');
    var options = { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' };
    return date.toLocaleDateString('pt-BR', options);
}

function openAddStudentModal() {
    var props = document.querySelector('#classModal .modal-title span').textContent;
    document.getElementById('addStudentClassId').value = '';
    document.getElementById('addStudentClassDate').value = '';
    document.getElementById('studentSearch').value = '';
    document.getElementById('studentResults').innerHTML = '';
    addStudentModal.show();
}

// Busca de alunos
document.getElementById('studentSearch').addEventListener('input', function(e) {
    var search = e.target.value.toLowerCase();
    var results = document.getElementById('studentResults');
    
    if (search.length < 2) {
        results.innerHTML = '';
        return;
    }
    
    var filtered = studentsData.filter(function(s) {
        return s.nome.toLowerCase().includes(search);
    });
    
    if (filtered.length === 0) {
        results.innerHTML = '<div class="list-group-item text-muted">Nenhum aluno encontrado</div>';
    } else {
        results.innerHTML = filtered.map(function(s) {
            return '<button type="button" class="list-group-item list-group-item-action" onclick="selectStudent(' + s.id + ', \'' + s.nome.replace(/'/g, "\\'") + '\')">' +
                '<i class="bi bi-person me-2"></i>' + s.nome +
                '<small class="text-muted ms-2">' + formatPhone(s.telefone) + '</small>' +
                '</button>';
        }).join('');
    }
});

function selectStudent(id, nome) {
    alert('Aluno selecionado: ' + nome + '\n\nEsta funcionalidade requer implementação da lógica de agendamento no backend.');
    addStudentModal.hide();
}

function formatPhone(phone) {
    if (!phone) return '';
    phone = phone.replace(/\D/g, '');
    if (phone.length === 11) {
        return '(' + phone.substring(0, 2) + ') ' + phone.substring(2, 7) + '-' + phone.substring(7);
    }
    return phone;
}
</script>

<?php include '../includes/footer.php'; ?>
