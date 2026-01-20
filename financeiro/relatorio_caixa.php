<?php
/**
 * Relatório de Caixas em PDF
 */

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem gerar relatórios de caixas.';
    redirecionar('../index.php');
}

$error = '';

// Obter lista de caixas para o select
try {
    $stmt = $pdo->prepare("SELECT id, nome, tipo, status FROM cash_registers WHERE gym_id = :gym_id ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $caixas = $stmt->fetchAll();
} catch (PDOException $e) {
    $caixas = [];
}

// Obter dados da academia
$stmt_gym = $pdo->prepare("SELECT nome, cidade, estado FROM gyms WHERE id = :gym_id");
$stmt_gym->execute([':gym_id' => getGymId()]);
$gym = $stmt_gym->fetch();

// Processar geração do relatório
if (isset($_GET['gerar']) && $_GET['gerar'] === '1') {
    $caixa_id = $_GET['caixa_id'] ?? 'todos';
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-t');
    
    if (empty($data_inicio)) $data_inicio = date('Y-m-01');
    if (empty($data_fim)) $data_fim = date('Y-m-t');
    
    // Query para buscar movimentações
    $sql = "SELECT 
                m.*,
                c.nome as caixa_nome,
                c.tipo as caixa_tipo,
                u.nome as usuario_nome,
                cd.nome as caixa_destino_nome
            FROM cash_movements m
            LEFT JOIN cash_registers c ON m.caixa_id = c.id
            LEFT JOIN users u ON m.usuario_id = u.id
            LEFT JOIN cash_registers cd ON m.caixa_destino_id = cd.id
            WHERE m.gym_id = :gym_id";
    
    $params = [':gym_id' => getGymId()];
    
    if ($caixa_id !== 'todos') {
        $sql .= " AND m.caixa_id = :caixa_id";
        $params[':caixa_id'] = $caixa_id;
    }
    
    $sql .= " AND DATE(m.created_at) BETWEEN :data_inicio AND :data_fim";
    $params[':data_inicio'] = $data_inicio;
    $params[':data_fim'] = $data_fim;
    
    $sql .= " ORDER BY m.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais por tipo
    $totais = [
        'entradas' => 0,
        'saidas' => 0,
        'transferencias_entrada' => 0,
        'transferencias_saida' => 0
    ];
    
    foreach ($movimentacoes as $m) {
        if (in_array($m['tipo'], ['entrada', 'suprimento'])) {
            $totais['entradas'] += (float)$m['valor'];
        } elseif (in_array($m['tipo'], ['saida', 'sangria'])) {
            $totais['saidas'] += (float)$m['valor'];
        } elseif ($m['tipo'] === 'transferencia_entrada') {
            $totais['transferencias_entrada'] += (float)$m['valor'];
        } elseif ($m['tipo'] === 'transferencia_saida') {
            $totais['transferencias_saida'] += (float)$m['valor'];
        }
    }
    
    // Labels para tipos
    $tipo_labels = [
        'entrada' => ['label' => 'Entrada', 'class' => 'text-success', 'icon' => '↓'],
        'saida' => ['label' => 'Saída', 'class' => 'text-danger', 'icon' => '↑'],
        'transferencia_entrada' => ['label' => 'Transf. Entrada', 'class' => 'text-info', 'icon' => '↔'],
        'transferencia_saida' => ['label' => 'Transf. Saída', 'class' => 'text-warning', 'icon' => '↔'],
        'sangria' => ['label' => 'Sangria', 'class' => 'text-danger', 'icon' => '↑'],
        'suprimento' => ['label' => 'Suprimento', 'class' => 'text-success', 'icon' => '↓'],
        'ajuste' => ['label' => 'Ajuste', 'class' => 'text-secondary', 'icon' => '~']
    ];
    
    // Formatar datas
    $data_inicio_fmt = date('d/m/Y', strtotime($data_inicio));
    $data_fim_fmt = date('d/m/Y', strtotime($data_fim));
    $data_geracao = date('d/m/Y H:i');
    
    // Nome do caixa selecionado
    $caixa_selecionado_nome = 'Todos os Caixas';
    if ($caixa_id !== 'todos') {
        foreach ($caixas as $c) {
            if ($c['id'] == $caixa_id) {
                $caixa_selecionado_nome = $c['nome'];
                break;
            }
        }
    }
    
    // Gerar HTML do PDF
    $html = '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Caixas - ' . sanitizar($gym['nome'] ?? 'Titanium Gym') . '</title>
        <style>
            @page {
                size: A4;
                margin: 1.5cm;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                line-height: 1.4;
                color: #333;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .header h1 {
                font-size: 20px;
                margin: 0 0 5px 0;
                color: #0d6efd;
            }
            .header .subtitle {
                font-size: 12px;
                color: #666;
            }
            .info-box {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
                font-size: 10px;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 3px;
            }
            .kpi-container {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                gap: 10px;
            }
            .kpi-box {
                flex: 1;
                padding: 10px;
                border-radius: 5px;
                text-align: center;
                border: 1px solid #ddd;
            }
            .kpi-box.entradas { background: #d1e7dd; border-color: #a3cfbb; }
            .kpi-box.saidas { background: #f8d7da; border-color: #f1aeb5; }
            .kpi-box.transfers { background: #cfe2ff; border-color: #b6d4fe; }
            .kpi-box.saldo { background: #fff3cd; border-color: #ffecb5; }
            .kpi-box h3 {
                font-size: 18px;
                margin: 0;
            }
            .kpi-box.entradas h3 { color: #198754; }
            .kpi-box.saidas h3 { color: #dc3545; }
            .kpi-box.transfers h3 { color: #0d6efd; }
            .kpi-box.saldo h3 { color: #856404; }
            .kpi-box p {
                margin: 3px 0 0 0;
                font-size: 9px;
                color: #666;
            }
            .section {
                margin-bottom: 20px;
            }
            .section h2 {
                font-size: 14px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 8px;
                margin-bottom: 10px;
                color: #333;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
                font-size: 9px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 5px 6px;
                text-align: left;
            }
            th {
                background: #f8f9fa;
                font-weight: bold;
                font-size: 9px;
            }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .text-success { color: #198754; }
            .text-danger { color: #dc3545; }
            .text-info { color: #0d6efd; }
            .text-warning { color: #fd7e14; }
            .text-secondary { color: #6c757d; }
            .footer {
                margin-top: 30px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 9px;
                color: #666;
            }
            .assinatura {
                margin-top: 40px;
                display: flex;
                justify-content: space-around;
            }
            .assinatura-box {
                text-align: center;
                width: 40%;
            }
            .linha-assinatura {
                border-top: 1px solid #333;
                margin-top: 40px;
                padding-top: 5px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . sanitizar($gym['nome'] ?? 'Titanium Gym') . '</h1>
            <div class="subtitle">
                Relatório de Fluxo de Caixa<br>
                Período: ' . $data_inicio_fmt . ' a ' . $data_fim_fmt . '
            </div>
        </div>
        
        <div class="info-box">
            <div class="info-row">
                <span><strong>Caixa:</strong> ' . sanitizar($caixa_selecionado_nome) . '</span>
            </div>
            <div class="info-row">
                <span><strong>Data de Geração:</strong> ' . $data_geracao . '</span>
                <span><strong>Cidade:</strong> ' . sanitizar($gym['cidade'] ?? '-') . ' - ' . sanitizar($gym['estado'] ?? '') . '</span>
            </div>
        </div>
        
        <div class="kpi-container">
            <div class="kpi-box entradas">
                <h3>R$ ' . number_format($totais['entradas'], 2, ',', '.') . '</h3>
                <p>Entradas</p>
            </div>
            <div class="kpi-box saidas">
                <h3>R$ ' . number_format($totais['saidas'], 2, ',', '.') . '</h3>
                <p>Saídas</p>
            </div>
            <div class="kpi-box transfers">
                <h3>R$ ' . number_format($totais['transferencias_entrada'] + $totais['transferencias_saida'], 2, ',', '.') . '</h3>
                <p>Transferências</p>
            </div>
            <div class="kpi-box saldo">
                <h3>R$ ' . number_format($totais['entradas'] + $totais['transferencias_entrada'] - $totais['saidas'] - $totais['transferencias_saida'], 2, ',', '.') . '</h3>
                <p>Saldo do Período</p>
            </div>
        </div>
        
        <div class="section">
            <h2>Movimentações Detalhadas (' . count($movimentacoes) . ' registros)</h2>
            <table>
                <thead>
                    <tr>
                        <th width="12%">Data/Hora</th>
                        <th width="18%">Caixa</th>
                        <th width="30%">Descrição</th>
                        <th width="12%">Tipo</th>
                        <th width="14%" class="text-end">Valor</th>
                        <th width="14%" class="text-end">Usuário</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (count($movimentacoes) > 0) {
        foreach ($movimentacoes as $m) {
            $tipo_info = $tipo_labels[$m['tipo']] ?? ['label' => $m['tipo'], 'class' => 'text-secondary', 'icon' => '?'];
            $valor_prefix = in_array($m['tipo'], ['entrada', 'transferencia_entrada', 'suprimento']) ? '+' : '-';
            $valor_class = in_array($m['tipo'], ['entrada', 'transferencia_entrada', 'suprimento']) ? 'text-success' : 'text-danger';
            
            $destino = '';
            if (!empty($m['caixa_destino_nome'])) {
                $destino = ' → ' . sanitizar($m['caixa_destino_nome']);
            }
            
            $html .= '
                    <tr>
                        <td>' . date('d/m/Y H:i', strtotime($m['created_at'])) . '</td>
                        <td>' . sanitizar($m['caixa_nome']) . '</td>
                        <td>' . sanitizar($m['observacoes']) . $destino . '</td>
                        <td class="' . $tipo_info['class'] . '">' . $tipo_info['label'] . '</td>
                        <td class="text-end ' . $valor_class . '"><strong>' . $valor_prefix . 'R$ ' . number_format($m['valor'], 2, ',', '.') . '</strong></td>
                        <td class="text-end">' . sanitizar(substr($m['usuario_nome'], 0, 15)) . '</td>
                    </tr>';
        }
    } else {
        $html .= '
                    <tr>
                        <td colspan="6" class="text-center">Nenhuma movimentação encontrada no período.</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="assinatura">
            <div class="assinatura-box">
                <div class="linha-assinatura">Responsável</div>
            </div>
            <div class="assinatura-box">
                <div class="linha-assinatura">Administrador</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Relatório gerado pelo Titanium Gym Manager - Sistema de Gestão para Academias</p>
            <p>Gerado em ' . $data_geracao . '</p>
        </div>
    </body>
    </html>';
    
    // Verificar se dompdf está disponível
    $dompdf_disponivel = class_exists('Dompdf\\Dompdf');
    
    // Gerar PDF
    if ($dompdf_disponivel) {
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'relatorio_caixa_' . date('Y-m-d') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
    } else {
        // Usar impressão do navegador como alternativa
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        echo '
        <script>
            window.onload = function() {
                window.print();
            }
        </script>';
    }
    exit;
}
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Financeiro</a></li>
        <li class="breadcrumb-item"><a href="caixas/index.php">Caixas</a></li>
        <li class="breadcrumb-item active">Relatório PDF</li>
    </ol>
</nav>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Formulário de Filtros -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Gerar Relatório de Caixas
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="relatorio_caixa.php">
                    <input type="hidden" name="gerar" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="caixa_id" class="form-label fw-bold">Caixa</label>
                            <select class="form-select" id="caixa_id" name="caixa_id">
                                <option value="todos">Todos os Caixas</option>
                                <?php foreach ($caixas as $caixa): ?>
                                    <option value="<?= $caixa['id'] ?>">
                                        <?= sanitizar($caixa['nome']) ?> 
                                        (<?= $caixa['status'] === 'aberto' ? 'Aberto' : 'Fechado' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="data_inicio" class="form-label fw-bold">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="data_fim" class="form-label fw-bold">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Gerar PDF
                        </button>
                        <a href="caixas/index.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left me-2"></i>Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Informações -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Informações
                </h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li class="mb-2">O relatório exibirá todas as movimentações do período selecionado</li>
                    <li class="mb-2">As transferências entre caixas são contabilizadas separadamente</li>
                    <li class="mb-2">O PDF pode ser impresso diretamente ou salvo no computador</li>
                    <?php if (empty($caixas)): ?>
                        <li class="text-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Nenhum caixa cadastrado. Crie um caixa primeiro para gerar relatórios.
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Barra Lateral -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightning me-2"></i>Ações Rápidas
                </h5>
            </div>
            <div class="card-body">
                <a href="caixas/index.php" class="btn btn-outline-primary w-100 mb-2">
                    <i class="bi bi-safe2 me-2"></i>Ver Caixas
                </a>
                <a href="caixas/novo.php" class="btn btn-outline-success w-100 mb-2">
                    <i class="bi bi-plus-lg me-2"></i>Novo Caixa
                </a>
                <a href="caixas/historico.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-clock-history me-2"></i>Histórico
                </a>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="bi bi-bar-chart me-2"></i>Caixas Cadastrados
                </h6>
            </div>
            <div class="card-body">
                <?php if (count($caixas) > 0): ?>
                    <ul class="mb-0 list-unstyled">
                        <?php foreach ($caixas as $caixa): ?>
                            <li class="mb-2 d-flex justify-content-between align-items-center">
                                <span><?= sanitizar($caixa['nome']) ?></span>
                                <span class="badge bg-<?= $caixa['status'] === 'aberto' ? 'success' : 'secondary' ?>">
                                    <?= $caixa['status'] === 'aberto' ? 'Aberto' : 'Fechado' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">Nenhum caixa cadastrado</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
