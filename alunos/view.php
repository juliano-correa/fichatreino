<?php
// Alunos - Visualizar Detalhes
$titulo_pagina = 'Perfil do Aluno';
$subtitulo_pagina = 'Detalhes do Aluno';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirecionar('index.php');
}

$aluno_id = (int)$_GET['id'];

// Buscar dados do aluno
try {
    $stmt = $pdo->prepare("SELECT s.*, m.nome as modalidade_nome FROM students s LEFT JOIN plans p ON s.plano_atual_id = p.id LEFT JOIN modalities m ON p.modalidade_id = m.id WHERE s.id = :id AND s.gym_id = :gym_id");
    $stmt->execute([':id' => $aluno_id, ':gym_id' => getGymId()]);
    $aluno = $stmt->fetch();
    
    if (!$aluno) {
        redirecionar('index.php');
    }

    // Segurança adicional para perfil aluno
    if (isAluno() && $aluno['id'] != getAlunoId()) {
        redirecionar('../dashboard.php');
    }
    
    // Últimas transações
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE gym_id = :gym_id AND aluno_id = :aluno_id ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([':gym_id' => getGymId(), ':aluno_id' => $aluno_id]);
    $transacoes = $stmt->fetchAll();
    
    // Últimos check-ins
    $stmt = $pdo->prepare("SELECT * FROM checkins WHERE gym_id = :gym_id AND aluno_id = :aluno_id ORDER BY data_checkin DESC, hora_checkin DESC LIMIT 10");
    $stmt->execute([':gym_id' => getGymId(), ':aluno_id' => $aluno_id]);
    $checkins = $stmt->fetchAll();
    
    // Avaliações
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE gym_id = :gym_id AND aluno_id = :aluno_id ORDER BY data_avaliacao DESC LIMIT 5");
    $stmt->execute([':gym_id' => getGymId(), ':aluno_id' => $aluno_id]);
    $avaliacoes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
}

// Calcular idade
$idade = calcularIdade($aluno['data_nascimento']);
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('alunos/index.php') ?>">Alunos</a></li>
        <li class="breadcrumb-item active"><?= sanitizar($aluno['nome']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- Perfil do Aluno -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="bg-primary bg-opacity-10 rounded-circle p-4 d-inline-flex mb-3" style="width: 120px; height: 120px; align-items: center; justify-content: center;">
                    <i class="bi bi-person fs-1 text-primary"></i>
                </div>
                <h4 class="mb-1"><?= sanitizar($aluno['nome']) ?></h4>
                <div class="mb-3"><?= getStatusBadge($aluno['status']) ?></div>
                
                <hr>
                
                <div class="text-start">
                    <p class="mb-2">
                        <i class="bi bi-phone me-2 text-muted"></i>
                        <?= formatarTelefone($aluno['telefone']) ?>
                        <?php if (!empty($aluno['telefone2'])): ?>
                            <small class="text-muted d-block">(Alt: <?= formatarTelefone($aluno['telefone2']) ?>)</small>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($aluno['email'])): ?>
                        <p class="mb-2">
                            <i class="bi bi-envelope me-2 text-muted"></i>
                            <?= sanitizar($aluno['email']) ?>
                        </p>
                    <?php endif; ?>
                    <p class="mb-2">
                        <i class="bi bi-calendar me-2 text-muted"></i>
                        <?= $idade ?>
                    </p>
                    <p class="mb-2">
                        <i class="bi bi-geo-alt me-2 text-muted"></i>
                        <?= sanitizar($aluno['cidade'] ?? 'Não informada') ?>
                    </p>
                </div>
            </div>
            <div class="card-footer bg-white">
                <div class="d-grid gap-2">
                    <?php if (!isAluno()): ?>
                    <a href="<?= base_url('alunos/edit.php?id=' . $aluno_id) ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-2"></i>Editar Dados
                    </a>
                    <?php endif; ?>
                    <a href="https://wa.me/<?= preg_replace('/\D/', '', $aluno['telefone']) ?>" target="_blank" class="btn btn-success">
                        <i class="bi bi-whatsapp me-2"></i>Enviar WhatsApp
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Informações do Treino -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-activity me-2"></i>Informações do Treino
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Objetivo</small>
                    <strong><?= ucfirst($aluno['objetivo'] ?? 'Não definido') ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Nível</small>
                    <strong><?= ucfirst($aluno['nivel'] ?? 'Não definido') ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Modalidade Atual</small>
                    <strong><?= sanitizar($aluno['modalidade_nome'] ?? 'Não definida') ?></strong>
                </div>
                <?php if ($aluno['plano_validade']): ?>
                    <div>
                        <small class="text-muted d-block">Validade do Plano</small>
                        <strong class="<?= strtotime($aluno['plano_validade']) < time() ? 'text-danger' : '' ?>">
                            <?= formatarData($aluno['plano_validade']) ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Histórico e Atividades -->
    <div class="col-lg-8">
        <!-- Últimas Transações -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-currency-dollar me-2"></i>Últimas Transações
                </h5>
                <a href="<?= base_url('financeiro/index.php?aluno=' . $aluno_id) ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transacoes)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Nenhuma transação encontrada
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transacoes as $transacao): ?>
                                    <tr>
                                        <td><?= sanitizar($transacao['descricao']) ?></td>
                                        <td class="fw-bold <?= $transacao['tipo'] === 'receita' ? 'text-success' : 'text-danger' ?>">
                                            <?= $transacao['tipo'] === 'despesa' ? '-' : '' ?><?= formatarMoeda($transacao['valor']) ?>
                                        </td>
                                        <td><?= formatarData($transacao['data_vencimento']) ?></td>
                                        <td><?= getStatusBadge($transacao['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Check-ins Recentes -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-check me-2"></i>Presença Recente
                </h5>
                <a href="<?= base_url('checkin/index.php?aluno=' . $aluno_id) ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Modalidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($checkins)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Nenhum check-in encontrado
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($checkins as $checkin): ?>
                                    <tr>
                                        <td><?= formatarData($checkin['data_checkin']) ?></td>
                                        <td><?= substr($checkin['hora_checkin'], 0, 5) ?></td>
                                        <td><?= $checkin['hora_saida'] ? substr($checkin['hora_saida'], 0, 5) : '<span class="text-success">Na academia</span>' ?></td>
                                        <td><?= sanitizar($checkin['modalidade'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Avaliações Físicas -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>Avaliações Físicas
                </h5>
                <?php if (!isAluno()): ?>
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Nova Avaliação
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($avaliacoes)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clipboard d-block fs-1 mb-2"></i>
                        Nenhuma avaliação registrada
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Peso</th>
                                    <th>IMC</th>
                                    <th>% Gordura</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($avaliacoes as $avaliacao): ?>
                                    <tr>
                                        <td><?= formatarData($avaliacao['data_avaliacao']) ?></td>
                                        <td><?= $avaliacao['peso'] ? $avaliacao['peso'] . ' kg' : '-' ?></td>
                                        <td><?= $avaliacao['imc'] ?: '-' ?></td>
                                        <td><?= $avaliacao['percentual_gordura'] ? $avaliacao['percentual_gordura'] . '%' : '-' ?></td>
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

<?php include '../includes/footer.php'; ?>
