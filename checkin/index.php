<?php
// Check-in - Página Principal
$titulo_pagina = 'Check-in';
$subtitulo_pagina = 'Registro de Presença';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';
$aluno_encontrado = null;

// Obter configuração de duração máxima
$stmt = $pdo->prepare("SELECT checkin_duracao_maxima FROM gyms WHERE id = :gym_id");
$stmt->execute([':gym_id' => getGymId()]);
$gym_config = $stmt->fetch();
$duracao_maxima = $gym_config['checkin_duracao_maxima'] ?? null;

// Processar busca rápida por telefone ou nome
if (isset($_GET['buscar'])) {
    $busca = preg_replace('/\D/', '', $_GET['buscar']);
    if (strlen($busca) >= 8) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE gym_id = :gym_id AND (telefone LIKE :telefone OR REPLACE(telefone, '0', '') LIKE :telefone2) AND status = 'ativo' LIMIT 1");
            $stmt->execute([
                ':gym_id' => getGymId(),
                ':telefone' => "%$busca%",
                ':telefone2' => "%$busca%"
            ]);
            $aluno_encontrado = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Erro na busca: ' . $e->getMessage();
        }
    }
}

// Processar check-in manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $aluno_id = $_POST['aluno_id'] ?? '';
    $tipo = $_POST['tipo'] ?? 'academia';
    $modalidade = $_POST['modalidade'] ?? '';
    
    if (empty($aluno_id)) {
        $error = 'Selecione um aluno para a operação.';
    } else {
        try {
            // Verificar se já existe check-in hoje sem saída
            $stmt = $pdo->prepare("SELECT id, hora_checkin FROM checkins WHERE gym_id = :gym_id AND aluno_id = :aluno_id AND DATE(data_checkin) = CURDATE() AND hora_saida IS NULL");
            $stmt->execute([':gym_id' => getGymId(), ':aluno_id' => $aluno_id]);
            $checkin_aberto = $stmt->fetch();
            
            if ($checkin_aberto && $_POST['acao'] === 'checkout') {
                // Fazer check-out manual
                $stmt = $pdo->prepare("UPDATE checkins SET hora_saida = NOW(), tipo = :tipo, modalidade = :modalidade WHERE id = :id");
                $stmt->execute([':id' => $checkin_aberto['id'], ':tipo' => $tipo, ':modalidade' => $modalidade]);
                $success = 'Check-out realizado com sucesso!';
            } elseif (!$checkin_aberto && $_POST['acao'] === 'checkin') {
                // Fazer check-in
                $stmt = $pdo->prepare("INSERT INTO checkins (gym_id, aluno_id, data_checkin, hora_checkin, tipo, modalidade, fonte) VALUES (:gym_id, :aluno_id, CURDATE(), NOW(), :tipo, :modalidade, 'manual')");
                $stmt->execute([
                    ':gym_id' => getGymId(),
                    ':aluno_id' => $aluno_id,
                    ':tipo' => $tipo,
                    ':modalidade' => $modalidade
                ]);
                $success = 'Check-in realizado com sucesso!';
            } else {
                $error = 'Operação inválida.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao registrar: ' . $e->getMessage();
        }
    }
}

// Verificar check-ins que excederam a duração máxima e fazer auto-checkout
if ($duracao_maxima) {
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM checkins 
            WHERE gym_id = :gym_id 
            AND DATE(data_checkin) = CURDATE() 
            AND hora_saida IS NULL
            AND TIMESTAMPDIFF(HOUR, CONCAT(data_checkin, ' ', hora_checkin), NOW()) >= :duracao
        ");
        $stmt->execute([':gym_id' => getGymId(), ':duracao' => $duracao_maxima]);
        $checkins_excedidos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($checkins_excedidos)) {
            $placeholders = implode(',', array_fill(0, count($checkins_excedidos), '?'));
            $stmt = $pdo->prepare("UPDATE checkins SET hora_saida = NOW() WHERE id IN ($placeholders)");
            $stmt->execute($checkins_excedidos);
        }
    } catch (PDOException $e) {
        // Ignorar erros no auto-checkout
    }
}

// Buscar modalidades ativas
try {
    $stmt = $pdo->prepare("SELECT id, nome, cor FROM modalities WHERE gym_id = :gym_id AND ativa = 1 ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $modalidades = $stmt->fetchAll();
} catch (PDOException $e) {
    $modalidades = [];
}

// Buscar check-ins do dia
try {
    $stmt = $pdo->prepare("
        SELECT c.*, s.name as student_name, s.telefone as student_phone, m.nome as modalidade_nome
        FROM checkins c
        LEFT JOIN students s ON c.student_id = s.id
        LEFT JOIN plans p ON s.plano_atual_id = p.id
        LEFT JOIN modalities m ON p.modalidade_id = m.id OR c.modalidade = m.nome
        WHERE c.gym_id = :gym_id AND DATE(c.data_checkin) = CURDATE()
        ORDER BY c.id DESC
    ");
    $stmt->execute([':gym_id' => getGymId()]);
    $checkins_hoje = $stmt->fetchAll();
    
    // Contadores
    $total_checkins = count($checkins_hoje);
    $checkins_ativos = count(array_filter($checkins_hoje, function($c) { return empty($c['hora_saida']); }));
    
    // Verificar alunos que estão a ponto de exceder o tempo
    $checkins_proximo_limite = [];
    if ($duracao_maxima) {
        foreach ($checkins_hoje as $check) {
            if (empty($check['hora_saida'])) {
                $hora_entrada = new DateTime($check['hora_checkin']);
                $agora = new DateTime();
                $diff = $hora_entrada->diff($agora);
                $horas_passadas = $diff->h + ($diff->i / 60);
                
                if ($horas_passadas >= ($duracao_maxima - 0.5) && $horas_passadas < $duracao_maxima) {
                    $checkins_proximo_limite[] = $check['id'];
                }
            }
        }
    }
    
} catch (PDOException $e) {
    $checkins_hoje = [];
    $total_checkins = 0;
    $checkins_ativos = 0;
    $checkins_proximo_limite = [];
}

// Buscar alunos ativos para select
try {
    $stmt = $pdo->prepare("SELECT id, name, telefone FROM students WHERE gym_id = :gym_id AND status = 'ativo' ORDER BY name LIMIT 100");
    $stmt->execute([':gym_id' => getGymId()]);
    $alunos = $stmt->fetchAll();
} catch (PDOException $e) {
    $alunos = [];
}

// Verificar se há check-in aberto para o aluno encontrado
$checkin_aberto_encontrado = false;
if ($aluno_encontrado) {
    $stmt = $pdo->prepare("SELECT id FROM checkins WHERE gym_id = :gym_id AND aluno_id = :aluno_id AND DATE(data_checkin) = CURDATE() AND hora_saida IS NULL");
    $stmt->execute([':gym_id' => getGymId(), ':aluno_id' => $aluno_encontrado['id']]);
    $checkin_aberto_encontrado = $stmt->fetch() ? true : false;
}
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item active">Check-in</li>
    </ol>
</nav>

<!-- Alerta de tempo limite -->
<?php if (!empty($checkins_proximo_limite)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-clock-history me-2"></i>
        <strong>Atenção!</strong> Alguns alunos estão prestes a atingir o tempo máximo de permanência.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

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
    <!-- Coluna Esquerda - Check-in -->
    <div class="col-lg-5">
        <!-- Card de Check-in/Check-out -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-<?= $checkin_aberto_encontrado ? 'warning' : 'primary' ?> text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-<?= $checkin_aberto_encontrado ? 'door-open' : 'qr-code-scan' ?> me-2"></i>
                    <?= $checkin_aberto_encontrado ? 'Check-out' : 'Check-in' ?> Rápido
                </h5>
            </div>
            <div class="card-body">
                <!-- Busca por telefone -->
                <form method="GET" class="mb-4">
                    <label class="form-label fw-bold">Buscar Aluno</label>
                    <div class="input-group">
                        <input type="text" class="form-control phone-input" name="buscar" placeholder="(11) 99999-9999" value="<?= sanitizar($_GET['buscar'] ?? '') ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <small class="text-muted">Digite o telefone para buscar rapidamente</small>
                </form>
                
                <!-- Aluno Encontrado -->
                <?php if ($aluno_encontrado): ?>
                    <div class="alert alert-<?= $checkin_aberto_encontrado ? 'warning' : 'success' ?> d-flex align-items-center mb-4">
                        <i class="bi bi-person-check fs-4 me-2"></i>
                        <div>
                            <strong><?= sanitizar($aluno_encontrado['name']) ?></strong><br>
                            <small><?= formatarTelefone($aluno_encontrado['telefone']) ?></small>
                            <?php if ($checkin_aberto_encontrado): ?>
                                <br><small class="text-danger"><i class="bi bi-clock me-1"></i>Já está na academia</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Formulário de Check-in/Check-out -->
                <form method="POST">
                    <input type="hidden" name="acao" value="<?= $checkin_aberto_encontrado ? 'checkout' : 'checkin' ?>">
                    <input type="hidden" name="aluno_id" id="aluno_id_selecionado" value="<?= $aluno_encontrado['id'] ?? '' ?>">
                    
                    <div class="mb-3">
                        <label for="aluno_select" class="form-label fw-bold">Selecionar Aluno *</label>
                        <select class="form-select" id="aluno_select" onchange="verificarCheckin(this.value)">
                            <option value="">Escolha um aluno...</option>
                            <?php foreach ($alunos as $aluno): ?>
                                <?php
                                // Verificar se este aluno tem check-in aberto
                                $stmt = $pdo->prepare("SELECT id, hora_checkin FROM checkins WHERE gym_id = :gym_id AND aluno_id = :aluno_id AND DATE(data_checkin) = CURDATE() AND hora_saida IS NULL");
                                $stmt->execute([':gym_id' => getGymId(), ':aluno_id' => $aluno['id']]);
                                $tem_checkin = $stmt->fetch();
                                ?>
                                <option value="<?= $aluno['id'] ?>" data-checkin="<?= $tem_checkin ? 'true' : 'false' ?>" <?= ($aluno_encontrado['id'] ?? '') == $aluno['id'] ? 'selected' : '' ?>>
                                    <?= sanitizar($aluno['name']) ?> (<?= formatarTelefone($aluno['telefone']) ?>)<?= $tem_checkin ? ' - ✓ Na academia' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo_checkin" class="form-label">Tipo de Acesso</label>
                        <select class="form-select" id="tipo_checkin" name="tipo">
                            <option value="academia">Academia Livre</option>
                            <option value="aula">Aula Coletiva</option>
                            <option value="personal">Personal Trainer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modalidade_checkin" class="form-label">Modalidade</label>
                        <select class="form-select" id="modalidade_checkin" name="modalidade">
                            <option value="">Não especificada</option>
                            <?php foreach ($modalidades as $mod): ?>
                                <option value="<?= sanitizar($mod['nome']) ?>"><?= sanitizar($mod['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-<?= $checkin_aberto_encontrado ? 'warning' : 'success' ?> w-100 btn-lg">
                        <i class="bi bi-<?= $checkin_aberto_encontrado ? 'door-open' : 'check-circle' ?> me-2"></i>
                        Confirmar <?= $checkin_aberto_encontrado ? 'Check-out' : 'Check-in' ?>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Resumo do Dia -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="bi bi-graph-up me-2"></i>Resumo de Hoje
                </h6>
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-primary mb-0"><?= $total_checkins ?></h3>
                        <small class="text-muted">Total de Acessos</small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success mb-0"><?= $checkins_ativos ?></h3>
                        <small class="text-muted">Agora na Academia</small>
                    </div>
                </div>
                <?php if ($duracao_maxima): ?>
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        <i class="bi bi-clock-history me-1"></i>
                        Tempo máximo: <?= $duracao_maxima ?> hora<?= $duracao_maxima > 1 ? 's' : '' ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Links Rápidos -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="historico.php" class="btn btn-outline-primary">
                        <i class="bi bi-clock-history me-2"></i>Ver Histórico Completo
                    </a>
                    <a href="../configuracoes/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-2"></i>Configurar Duração Máxima
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Coluna Direita - Lista de Check-ins -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check me-2"></i>Movimentação de Hoje
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($checkins_hoje)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
                        <p class="text-muted mb-0">Nenhum registro hoje</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Aluno</th>
                                    <th>Entrada</th>
                                    <th>Saída</th>
                                    <th>Tipo</th>
                                    <th>Tempo</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checkins_hoje as $check): ?>
                                    <?php
                                    $hora_entrada = new DateTime($check['hora_checkin']);
                                    $hora_saida = !empty($check['hora_saida']) ? new DateTime($check['hora_saida']) : null;
                                    $tempo = $hora_saida ? $hora_entrada->diff($hora_saida)->format('%H:%i') : '-';
                                    $status = $hora_saida ? 'text-muted' : 'text-success';
                                    $tempo_decimal = 0;
                                    
                                    if (!$hora_saida && $duracao_maxima) {
                                        $diff = $hora_entrada->diff(new DateTime());
                                        $tempo_decimal = $diff->h + ($diff->i / 60);
                                        if ($tempo_decimal >= $duracao_maxima) {
                                            $status = 'text-danger';
                                            $tempo = '<span class="text-danger fw-bold">Excedido</span>';
                                        }
                                    }
                                    
                                    $esta_na_academia = !$hora_saida;
                                    ?>
                                    <tr class="<?= in_array($check['id'], $checkins_proximo_limite) ? 'table-warning' : '' ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                    <i class="bi bi-person text-primary"></i>
                                                </div>
                                                <div>
                                                    <strong><?= sanitizar($check['student_name']) ?></strong><br>
                                                    <small class="text-muted"><?= formatarTelefone($check['student_phone']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $hora_entrada->format('H:i') ?>
                                            <small class="text-muted d-block"><?= $hora_entrada->format('d/m') ?></small>
                                        </td>
                                        <td class="<?= $status ?>">
                                            <?= $hora_saida ? $hora_saida->format('H:i') : '<i class="bi bi-circle-fill animation-pulse"></i>' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $tipos = [
                                                'academia' => ['icon' => 'dumbbell', 'label' => 'Academia'],
                                                'aula' => ['icon' => 'people', 'label' => 'Aula'],
                                                'personal' => ['icon' => 'person', 'label' => 'Personal']
                                            ];
                                            $t = $tipos[$check['tipo']] ?? $tipos['academia'];
                                            ?>
                                            <i class="bi bi-<?= $t['icon'] ?> me-1"></i><?= $t['label'] ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= $tempo ?></span>
                                        </td>
                                        <td>
                                            <?php if ($esta_na_academia): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="acao" value="checkout">
                                                    <input type="hidden" name="aluno_id" value="<?= $check['aluno_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Check-out manual">
                                                        <i class="bi bi-door-open"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
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

<style>
.animation-pulse {
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.4; }
    100% { opacity: 1; }
}
.table-warning {
    background-color: #fff3cd !important;
}
</style>

<script>
function verificarCheckin(alunoId) {
    const select = document.getElementById('aluno_select');
    const option = select.options[select.selectedIndex];
    const temCheckin = option ? option.dataset.checkin === 'true' : false;
    const form = select.closest('form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const header = form.closest('.card').querySelector('.card-header');
    
    document.getElementById('aluno_id_selecionado').value = alunoId;
    
    if (temCheckin) {
        submitBtn.innerHTML = '<i class="bi bi-door-open me-2"></i>Confirmar Check-out';
        submitBtn.className = 'btn btn-warning w-100 btn-lg';
        form.querySelector('input[name="acao"]').value = 'checkout';
        header.className = 'card-header bg-warning text-white';
        header.querySelector('h5 i').className = 'bi bi-door-open me-2';
        header.querySelector('h5').childNodes[2].textContent = ' Check-out Rápido';
    } else {
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Confirmar Check-in';
        submitBtn.className = 'btn btn-success w-100 btn-lg';
        form.querySelector('input[name="acao"]').value = 'checkin';
        header.className = 'card-header bg-primary text-white';
        header.querySelector('h5 i').className = 'bi bi-qr-code-scan me-2';
        header.querySelector('h5').childNodes[2].textContent = ' Check-in Rápido';
    }
}

// Verificar se há parâmetro de busca para auto-selecionar
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('aluno_select');
    if (select.value) {
        verificarCheckin(select.value);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
