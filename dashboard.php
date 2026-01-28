<?php
// Dashboard - Página Principal
$titulo_pagina = 'Dashboard';

require_once 'includes/auth_check.php';
require_once 'config/conexao.php';

if (isAluno()) {
    redirecionar('alunos/view.php?id=' . getAlunoId());
}

// Inicializar variáveis com valores padrão
$total_alunos = 0;
$novos_alunos = 0;
$receita_mes = 0;
$receita_anterior = 0;
$crescimento = 0;
$total_vencidos = 0;
$valor_vencidos = 0;
$checkins_hoje = 0;
$presentes = 0;
$alunos_recentes = [];
$cobrancas_proximas = [];
$checkins_recentes = [];

// Buscar estatísticas
try {
    $gym_id = getGymId();
    
    // Total de alunos ativos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE gym_id = :gym_id AND status = 'ativo'");
    $stmt->execute([':gym_id' => $gym_id]);
    $total_alunos = $stmt->fetch()['total'];
    
    // Novos alunos este mês
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE gym_id = :gym_id AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute([':gym_id' => $gym_id]);
    $novos_alunos = $stmt->fetch()['total'];
    
    // Receitas do mês
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) as total FROM transactions WHERE gym_id = :gym_id AND tipo = 'receita' AND status = 'pago' AND MONTH(data_pagamento) = MONTH(CURDATE()) AND YEAR(data_pagamento) = YEAR(CURDATE())");
    $stmt->execute([':gym_id' => $gym_id]);
    $receita_mes = $stmt->fetch()['total'];
    
    // Receitas do mês anterior
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) as total FROM transactions WHERE gym_id = :gym_id AND tipo = 'receita' AND status = 'pago' AND MONTH(data_pagamento) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(data_pagamento) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $stmt->execute([':gym_id' => $gym_id]);
    $receita_anterior = $stmt->fetch()['total'];
    
    // Cobranças vencidas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor_total FROM transactions WHERE gym_id = :gym_id AND status = 'vencido'");
    $stmt->execute([':gym_id' => $gym_id]);
    $vencimentos = $stmt->fetch();
    $total_vencidos = $vencimentos['total'];
    $valor_vencidos = $vencimentos['valor_total'];
    
    // Calcular crescimento
    $crescimento = $receita_anterior > 0 ? (($receita_mes - $receita_anterior) / $receita_anterior) * 100 : 0;
    
    // Check-ins hoje (tabela sem gym_id)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM checkins WHERE DATE(data_checkin) = CURDATE()");
    $checkins_hoje = $stmt->fetch()['total'];
    
    // Alunos presentes agora (tabela sem hora_saida)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT aluno_id) as total FROM checkins WHERE DATE(data_checkin) = CURDATE()");
    $presentes = $stmt->fetch()['total'];
    
    // Alunos recentes
    $stmt = $pdo->prepare("SELECT s.*, p.nome as plano_nome FROM students s LEFT JOIN plans p ON s.plano_atual_id = p.id WHERE s.gym_id = :gym_id ORDER BY s.created_at DESC LIMIT 5");
    $stmt->execute([':gym_id' => $gym_id]);
    $alunos_recentes = $stmt->fetchAll();
    
    // Cobranças próximas
    $stmt = $pdo->prepare("SELECT t.*, s.nome as aluno_nome FROM transactions t LEFT JOIN students s ON t.aluno_id = s.id WHERE t.gym_id = :gym_id AND t.status = 'pendente' AND t.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY t.data_vencimento ASC LIMIT 5");
    $stmt->execute([':gym_id' => $gym_id]);
    $cobrancas_proximas = $stmt->fetchAll();
    
    // Check-ins recentes (tabela simplificada)
    $stmt = $pdo->query("SELECT c.*, s.nome as aluno_nome FROM checkins c LEFT JOIN students s ON c.aluno_id = s.id WHERE DATE(c.data_checkin) = CURDATE() ORDER BY c.data_checkin DESC LIMIT 5");
    $checkins_recentes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Em caso de erro, usa os valores padrão já inicializados
    error_log("Erro no dashboard: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Total de Alunos</p>
                    <h2 class="stat-value mb-0"><?= number_format($total_alunos, 0, ',', '.') ?></h2>
                    <small class="text-success">
                        <i class="bi bi-arrow-up"></i> <?= $novos_alunos ?> novos este mês
                    </small>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Receita do Mês</p>
                    <h2 class="stat-value mb-0"><?= formatarMoeda($receita_mes) ?></h2>
                    <small class="<?= $crescimento >= 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-<?= $crescimento >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= number_format(abs($crescimento), 1, ',', '.') ?>% vs mês anterior
                    </small>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Cobranças Vencidas</p>
                    <h2 class="stat-value mb-0"><?= $total_vencidos ?></h2>
                    <small class="text-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= formatarMoeda($valor_vencidos) ?> em aberto
                    </small>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Presença Hoje</p>
                    <h2 class="stat-value mb-0"><?= $checkins_hoje ?></h2>
                    <small class="text-muted">
                        <i class="bi bi-person-check"></i> <?= $presentes ?> na academia agora
                    </small>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="bi bi-lightning me-2"></i>Ações Rápidas</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="alunos/create.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Novo Aluno
                    </a>
                    <a href="checkin/index.php" class="btn btn-success">
                        <i class="bi bi-qr-code-scan me-2"></i>Registrar Presença
                    </a>
                    <a href="financeiro/novo.php" class="btn btn-info text-white">
                        <i class="bi bi-cash-stack me-2"></i>Registrar Pagamento
                    </a>
                    <a href="financeiro/" class="btn btn-secondary">
                        <i class="bi bi-graph-up me-2"></i>Ver Relatório
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row g-4">
    <!-- Alunos Recentes -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Alunos Recentes</h5>
                <a href="alunos/" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Plano</th>
                                <th>Status</th>
                                <th>Desde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alunos_recentes)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                        Nenhum aluno cadastrado ainda
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alunos_recentes as $aluno): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                    <i class="bi bi-person text-primary"></i>
                                                </div>
                                                <div>
                                                    <strong><?= sanitizar($aluno['nome']) ?></strong>
                                                    <small class="d-block text-muted"><?= formatarTelefone($aluno['telefone']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= sanitizar($aluno['plano_nome'] ?? 'Não definido') ?></td>
                                        <td><?= getStatusBadge($aluno['status']) ?></td>
                                        <td><?= formatarData($aluno['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cobranças Próximas -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-calendar3 me-2"></i>Cobranças Próximas</h5>
                <a href="financeiro/" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cobrancas_proximas)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-check-circle d-block fs-3 mb-2"></i>
                                        Nenhuma cobrança próxima
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cobrancas_proximas as $cobranca): ?>
                                    <tr>
                                        <td><?= sanitizar($cobranca['aluno_nome'] ?? 'Vários') ?></td>
                                        <td class="fw-bold"><?= formatarMoeda($cobranca['valor']) ?></td>
                                        <td><?= formatarData($cobranca['data_vencimento']) ?></td>
                                        <td><?= getStatusBadge($cobranca['status']) ?></td>
                                        <td>
                                            <?php if (!empty($cobranca['aluno_id'])): ?>
                                                <?php 
                                                $aluno_telefone = '';
                                                $stmt = $pdo->prepare("SELECT telefone FROM students WHERE id = :id");
                                                $stmt->execute([':id' => $cobranca['aluno_id']]);
                                                $aluno_telefone = $stmt->fetch()['telefone'];
                                                
                                                $mensagem = gerarMensagemCobranca(
                                                    $cobranca['aluno_nome'],
                                                    formatarMoeda($cobranca['valor']),
                                                    formatarData($cobranca['data_vencimento']),
                                                    'Titanium Gym'
                                                );
                                                ?>
                                                <a href="<?= gerarLinkWhatsapp($aluno_telefone, $mensagem) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Últimos Check-ins -->
<div class="row g-4 mt-0">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Últimos Check-ins de Hoje</h5>
                <a href="checkin/historico.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Data/Hora</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($checkins_recentes)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                                        Nenhum check-in hoje
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($checkins_recentes as $checkin): ?>
                                    <tr>
                                        <td>
                                            <strong><?= sanitizar($checkin['aluno_nome'] ?? 'Não identificado') ?></strong>
                                        </td>
                                        <td><?= formatarData($checkin['data_checkin']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $checkin['tipo'] == 'entrada' ? 'success' : 'secondary' ?>">
                                                <i class="bi bi-<?= $checkin['tipo'] == 'entrada' ? '-arrow-up' : 'arrow-down' ?>"></i>
                                                <?= ucfirst($checkin['tipo'] ?? 'Entrada') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
