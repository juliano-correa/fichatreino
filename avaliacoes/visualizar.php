<?php
// Avaliação Física - Visualizar
$titulo_pagina = 'Detalhes da Avaliação';
$subtitulo_pagina = 'Visualizar Avaliação Física';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Obter avaliação atual
try {
    $sql = "SELECT 
                a.*,
                s.nome as aluno_nome,
                s.data_nascimento
            FROM assessments a
            LEFT JOIN students s ON a.aluno_id = s.id
            WHERE a.id = :id AND s.gym_id = :gym_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':gym_id' => getGymId()]);
    $avaliacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$avaliacao) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

// Obter avaliação anterior para comparação
try {
    $sql_anterior = "SELECT * FROM assessments 
                     WHERE aluno_id = :aluno_id AND id < :id 
                     ORDER BY data_avaliacao DESC LIMIT 1";
    $stmt_anterior = $pdo->prepare($sql_anterior);
    $stmt_anterior->execute([':aluno_id' => $avaliacao['aluno_id'], ':id' => $id]);
    $avaliacao_anterior = $stmt_anterior->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avaliacao_anterior = null;
}

// Obter histórico completo para gráfico
try {
    $sql_historico = "SELECT id, data_avaliacao, peso, imc, percentual_gordura, massa_magra 
                      FROM assessments 
                      WHERE aluno_id = :aluno_id 
                      ORDER BY data_avaliacao ASC";
    $stmt_historico = $pdo->prepare($sql_historico);
    $stmt_historico->execute([':aluno_id' => $avaliacao['aluno_id']]);
    $historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historico = [];
}

// Calcular diferenças
function calcularDiferenca($atual, $anterior) {
    if ($anterior === null || $anterior == 0) return ['valor' => '-', 'classe' => 'text-muted', 'icone' => ''];
    $diff = $atual - $anterior;
    if ($diff > 0) {
        return ['valor' => '+' . number_format($diff, 2, ',', '.'), 'classe' => 'text-danger', 'icone' => 'bi-arrow-up'];
    } elseif ($diff < 0) {
        return ['valor' => number_format($diff, 2, ',', '.'), 'classe' => 'text-success', 'icone' => 'bi-arrow-down'];
    } else {
        return ['valor' => '0', 'classe' => 'text-muted', 'icone' => 'bi-dash'];
    }
}

// Classificação do IMC
function classificarIMC($imc) {
    if ($imc < 18.5) return ['texto' => 'Abaixo do peso', 'classe' => 'text-warning'];
    if ($imc < 25) return ['texto' => 'Peso normal', 'classe' => 'text-success'];
    if ($imc < 30) return ['texto' => 'Sobrepeso', 'classe' => 'text-warning'];
    if ($imc < 35) return ['texto' => 'Obesidade Grau I', 'classe' => 'text-danger'];
    if ($imc < 40) return ['texto' => 'Obesidade Grau II', 'classe' => 'text-danger'];
    return ['texto' => 'Obesidade Grau III', 'classe' => 'text-danger'];
}

$imc_class = classificarIMC($avaliacao['imc']);
?>

<?php include '../includes/header.php'; ?>

<!-- Botão Voltar -->
<div class="mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Voltar para Lista
    </a>
</div>

<!-- Header do Aluno -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1">
                    <i class="bi bi-person-circle me-2 text-primary"></i>
                    <?= sanitizar($avaliacao['aluno_nome']) ?>
                </h4>
                <p class="text-muted mb-0">
                    Avaliação de <?= date('d/m/Y', strtotime($avaliacao['data_avaliacao'])) ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="editar.php?id=<?= $avaliacao['id'] ?>" class="btn btn-primary me-2">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
                <a href="excluir.php?id=<?= $avaliacao['id'] ?>" class="btn btn-outline-danger" onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir esta avaliação?');">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Comparativo com Avaliação Anterior -->
<?php if ($avaliacao_anterior): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Comparativo com Avaliação Anterior</h5>
    </div>
    <div class="card-body">
        <div class="row text-center g-3">
            <div class="col-3">
                <div class="p-3 border rounded">
                    <h6 class="text-muted mb-1">Peso</h6>
                    <h4 class="mb-0"><?= number_format($avaliacao['peso'], 2, ',', '.') ?> <small class="text-muted">kg</small></h4>
                    <small class="<?= calcularDiferenca($avaliacao['peso'], $avaliacao_anterior['peso'])['classe'] ?>">
                        <i class="bi <?= calcularDiferenca($avaliacao['peso'], $avaliacao_anterior['peso'])['icone'] ?>"></i>
                        <?= calcularDiferenca($avaliacao['peso'], $avaliacao_anterior['peso'])['valor'] ?> kg
                    </small>
                </div>
            </div>
            <div class="col-3">
                <div class="p-3 border rounded">
                    <h6 class="text-muted mb-1">IMC</h6>
                    <h4 class="mb-0"><?= number_format($avaliacao['imc'], 2, ',', '.') ?></h4>
                    <small class="<?= calcularDiferenca($avaliacao['imc'], $avaliacao_anterior['imc'])['classe'] ?>">
                        <i class="bi <?= calcularDiferenca($avaliacao['imc'], $avaliacao_anterior['imc'])['icone'] ?>"></i>
                        <?= calcularDiferenca($avaliacao['imc'], $avaliacao_anterior['imc'])['valor'] ?>
                    </small>
                </div>
            </div>
            <div class="col-3">
                <div class="p-3 border rounded">
                    <h6 class="text-muted mb-1">% Gordura</h6>
                    <h4 class="mb-0"><?= $avaliacao['percentual_gordura'] ? number_format($avaliacao['percentual_gordura'], 1, ',', '.') . '%' : '-' ?></h4>
                    <?php if ($avaliacao_anterior['percentual_gordura']): ?>
                        <small class="<?= calcularDiferenca($avaliacao['percentual_gordura'], $avaliacao_anterior['percentual_gordura'])['classe'] ?>">
                            <i class="bi <?= calcularDiferenca($avaliacao['percentual_gordura'], $avaliacao_anterior['percentual_gordura'])['icone'] ?>"></i>
                            <?= calcularDiferenca($avaliacao['percentual_gordura'], $avaliacao_anterior['percentual_gordura'])['valor'] ?>%
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-3">
                <div class="p-3 border rounded">
                    <h6 class="text-muted mb-1">Massa Magra</h6>
                    <h4 class="mb-0"><?= $avaliacao['massa_magra'] ? number_format($avaliacao['massa_magra'], 2, ',', '.') . ' kg' : '-' ?></h4>
                    <?php if ($avaliacao_anterior['massa_magra']): ?>
                        <small class="<?= calcularDiferenca($avaliacao['massa_magra'], $avaliacao_anterior['massa_magra'])['classe'] ?>">
                            <i class="bi <?= calcularDiferenca($avaliacao['massa_magra'], $avaliacao_anterior['massa_magra'])['icone'] ?>"></i>
                            <?= calcularDiferenca($avaliacao['massa_magra'], $avaliacao_anterior['massa_magra'])['valor'] ?> kg
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Dados da Avaliação -->
<div class="row g-4 mb-4">
    <!-- Dados Básicos -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-person-vcard me-2"></i>Dados Básicos</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small">Peso</label>
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0 me-2"><?= number_format($avaliacao['peso'], 2, ',', '.') ?></h4>
                        <span class="text-muted">kg</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Altura</label>
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0 me-2"><?= number_format($avaliacao['altura'], 2, ',', '.') ?></h4>
                        <span class="text-muted">m</span>
                    </div>
                </div>
                <div>
                    <label class="text-muted small">IMC (<?= $imc_class['texto'] ?>)</label>
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0 me-2 <?= $imc_class['classe'] ?>"><?= number_format($avaliacao['imc'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Composição Corporal -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Composição Corporal</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small">% Gordura</label>
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0 me-2"><?= $avaliacao['percentual_gordura'] ? number_format($avaliacao['percentual_gordura'], 1, ',', '.') . '%' : '-' ?></h4>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Massa Magra</label>
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0 me-2"><?= $avaliacao['massa_magra'] ? number_format($avaliacao['massa_magra'], 2, ',', '.') : '-' ?></h4>
                        <span class="text-muted">kg</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Perímetros -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-rulers me-2"></i>Perímetros (cm)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="text-muted small">Ombro</label>
                        <div><?= $avaliacao['ombro'] ? number_format($avaliacao['ombro'], 1, ',', '.') : '-' ?></div>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="text-muted small">Tórax</label>
                        <div><?= $avaliacao['torax'] ? number_format($avaliacao['torax'], 1, ',', '.') : '-' ?></div>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="text-muted small">Cintura</label>
                        <div><?= $avaliacao['cintura'] ? number_format($avaliacao['cintura'], 1, ',', '.') : '-' ?></div>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="text-muted small">Abdômen</label>
                        <div><?= $avaliacao['abdomen'] ? number_format($avaliacao['abdomen'], 1, ',', '.') : '-' ?></div>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="text-muted small">Quadril</label>
                        <div><?= $avaliacao['quadril'] ? number_format($avaliacao['quadril'], 1, ',', '.') : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Perímetros Detalhados -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-rulers me-2"></i>Perímetros Detalhados</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="text-muted small">Braço Direito</label>
                <div><?= $avaliacao['braco_direito'] ? number_format($avaliacao['braco_direito'], 1, ',', '.') . ' cm' : '-' ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Braço Esquerdo</label>
                <div><?= $avaliacao['braco_esquerdo'] ? number_format($avaliacao['braco_esquerdo'], 1, ',', '.') . ' cm' : '-' ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Coxa Direita</label>
                <div><?= $avaliacao['coxa_direita'] ? number_format($avaliacao['coxa_direita'], 1, ',', '.') . ' cm' : '-' ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Coxa Esquerda</label>
                <div><?= $avaliacao['coxa_esquerda'] ? number_format($avaliacao['coxa_esquerda'], 1, ',', '.') . ' cm' : '-' ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Panturrilha Direita</label>
                <div><?= $avaliacao['panturrilha_direita'] ? number_format($avaliacao['panturrilha_direita'], 1, ',', '.') . ' cm' : '-' ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Panturrilha Esquerda</label>
                <div><?= $avaliacao['panturrilha_esquerda'] ? number_format($avaliacao['panturrilha_esquerda'], 1, ',', '.') . ' cm' : '-' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Testes Físicos -->
<?php if ($avaliacao['flexibilidade'] || $avaliacao['flexoes'] || $avaliacao['abdominal']): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Testes Físicos</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="text-muted small">Flexibilidade</label>
                <div><?= $avaliacao['flexibilidade'] ? $avaliacao['flexibilidade'] . ' cm' : '-' ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Flexões</label>
                <div><?= $avaliacao['flexoes'] ? $avaliacao['flexoes'] . ' repetições' : '-' ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Abdominal</label>
                <div><?= $avaliacao['abdominal'] ? $avaliacao['abdominal'] . ' repetições' : '-' ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Observações -->
<?php if (!empty($avaliacao['observacoes'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Observações</h6>
    </div>
    <div class="card-body">
        <?= nl2br(sanitizar($avaliacao['observacoes'])) ?>
    </div>
</div>
<?php endif; ?>

<!-- Gráfico de Evolução -->
<?php if (count($historico) > 1): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Evolução do Aluno</h6>
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" id="graficoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="peso-tab" data-bs-toggle="tab" data-bs-target="#peso-pane" type="button" role="tab">
                    Peso e IMC
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="gordura-tab" data-bs-toggle="tab" data-bs-target="#gordura-pane" type="button" role="tab">
                    Gordura
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="musculo-tab" data-bs-toggle="tab" data-bs-target="#musculo-pane" type="button" role="tab">
                    Massa Magra
                </button>
            </li>
        </ul>
        <div class="tab-content" id="graficoTabsContent">
            <div class="tab-pane fade show active" id="peso-pane" role="tabpanel">
                <canvas id="graficoPeso" height="100"></canvas>
            </div>
            <div class="tab-pane fade" id="gordura-pane" role="tabpanel">
                <canvas id="graficoGordura" height="100"></canvas>
            </div>
            <div class="tab-pane fade" id="musculo-pane" role="tabpanel">
                <canvas id="graficoMusculo" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Script do Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (count($historico) > 1): ?>
const historico = <?= json_encode($historico) ?>;
const labels = historico.map(h => new Date(h.data_avaliacao).toLocaleDateString('pt-BR'));

// Dados para gráfico de Peso e IMC
const dadosPeso = {
    labels: labels,
    datasets: [
        {
            label: 'Peso (kg)',
            data: historico.map(h => parseFloat(h.peso)),
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            yAxisID: 'y',
            tension: 0.3,
            fill: true
        },
        {
            label: 'IMC',
            data: historico.map(h => parseFloat(h.imc)),
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            yAxisID: 'y1',
            tension: 0.3,
            fill: false
        }
    ]
};

// Dados para gráfico de Gordura
const dadosGordura = {
    labels: labels,
    datasets: [{
        label: '% Gordura',
        data: historico.map(h => h.percentual_gordura ? parseFloat(h.percentual_gordura) : null),
        borderColor: '#dc3545',
        backgroundColor: 'rgba(220, 53, 69, 0.1)',
        tension: 0.3,
        fill: true
    }]
};

// Dados para gráfico de Massa Magra
const dadosMusculo = {
    labels: labels,
    datasets: [{
        label: 'Massa Magra (kg)',
        data: historico.map(h => h.massa_magra ? parseFloat(h.massa_magra) : null),
        borderColor: '#6f42c1',
        backgroundColor: 'rgba(111, 66, 193, 0.1)',
        tension: 0.3,
        fill: true
    }]
};

// Configuração comum
const configComum = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom'
        }
    }
};

// Criar gráficos
new Chart(document.getElementById('graficoPeso'), {
    type: 'line',
    data: dadosPeso,
    options: {
        ...configComum,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: { display: true, text: 'Peso (kg)' }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: { display: true, text: 'IMC' },
                grid: { drawOnChartArea: false }
            }
        }
    }
});

new Chart(document.getElementById('graficoGordura'), {
    type: 'line',
    data: dadosGordura,
    options: configComum
});

new Chart(document.getElementById('graficoMusculo'), {
    type: 'line',
    data: dadosMusculo,
    options: configComum
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
