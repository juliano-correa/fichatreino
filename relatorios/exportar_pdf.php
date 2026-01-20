<?php
/**
 * Exportação de Relatório Financeiro em PDF
 */

// Importar classes do dompdf no início do arquivo (escopo global)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

// Validar parâmetros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

if (empty($data_inicio)) $data_inicio = date('Y-m-01');
if (empty($data_fim)) $data_fim = date('Y-m-t');

// Obter dados da academia
$stmt_gym = $pdo->prepare("SELECT nome, cidade, estado FROM gyms WHERE id = :gym_id");
$stmt_gym->execute([':gym_id' => getGymId()]);
$gym = $stmt_gym->fetch();

// Obter dados resumidos
$sql_resumo = "SELECT 
    COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) as total_receitas,
    COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) as total_despesas
FROM financeiro 
WHERE gym_id = :gym_id AND data BETWEEN :data_inicio AND :data_fim";

$stmt_resumo = $pdo->prepare($sql_resumo);
$stmt_resumo->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
$stmt_resumo->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
$stmt_resumo->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
$stmt_resumo->execute();
$resumo = $stmt_resumo->fetch(PDO::FETCH_ASSOC);

$saldo = $resumo['total_receitas'] - $resumo['total_despesas'];

// Obter detalhamento de transações
$sql_transacoes = "SELECT 
    f.*,
    m.nome as modalidade_nome,
    s.nome as aluno_nome
FROM financeiro f
LEFT JOIN modalities m ON f.modalidade_id = m.id AND m.gym_id = f.gym_id
LEFT JOIN students s ON f.aluno_id = s.id AND s.gym_id = f.gym_id
WHERE f.gym_id = :gym_id AND f.data BETWEEN :data_inicio AND :data_fim
ORDER BY f.data DESC, f.created_at DESC";

$stmt_transacoes = $pdo->prepare($sql_transacoes);
$stmt_transacoes->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
$stmt_transacoes->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
$stmt_transacoes->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
$stmt_transacoes->execute();
$transacoes = $stmt_transacoes->fetchAll(PDO::FETCH_ASSOC);

// Obter resumo por modalidade
$sql_modalidades = "SELECT 
    COALESCE(m.nome, 'Outros') as modalidade,
    COALESCE(SUM(CASE WHEN f.tipo = 'receita' THEN f.valor ELSE 0 END), 0) as receitas,
    COALESCE(SUM(CASE WHEN f.tipo = 'despesa' THEN f.valor ELSE 0 END), 0) as despesas
FROM financeiro f
LEFT JOIN modalities m ON f.modalidade_id = m.id AND m.gym_id = f.gym_id
WHERE f.gym_id = :gym_id AND f.data BETWEEN :data_inicio AND :data_fim
GROUP BY f.modalidade_id
ORDER BY receitas DESC";

$stmt_modalidades = $pdo->prepare($sql_modalidades);
$stmt_modalidades->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
$stmt_modalidades->bindValue(':data_inicio', $data_inicio, PDO::PARAM_STR);
$stmt_modalidades->bindValue(':data_fim', $data_fim, PDO::PARAM_STR);
$stmt_modalidades->execute();
$modalidades = $stmt_modalidades->fetchAll(PDO::FETCH_ASSOC);

// Formatar datas para exibição
$data_inicio_fmt = date('d/m/Y', strtotime($data_inicio));
$data_fim_fmt = date('d/m/Y', strtotime($data_fim));
$data_geracao = date('d/m/Y H:i');

// Gerar HTML para impressão/PDF
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro - ' . sanitizar($gym['nome'] ?? 'Titanium Gym') . '</title>
    <style>
        @page {
            size: A4;
            margin: 1.5cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .transacoes-table {
            font-size: 9px;
        }
        .transacoes-table th,
        .transacoes-table td {
            padding: 4px 6px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
            color: #0d6efd;
        }
        .header .subtitle {
            font-size: 14px;
            color: #666;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .kpi-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 15px;
        }
        .kpi-box {
            flex: 1;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .kpi-box.receitas { background: #d1e7dd; border-color: #a3cfbb; }
        .kpi-box.despesas { background: #f8d7da; border-color: #f1aeb5; }
        .kpi-box.saldo { background: #cfe2ff; border-color: #b6d4fe; }
        .kpi-box h3 {
            font-size: 24px;
            margin: 0;
        }
        .kpi-box.receitas h3 { color: #198754; }
        .kpi-box.despesas h3 { color: #dc3545; }
        .kpi-box.saldo h3 { color: #0d6efd; }
        .kpi-box p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #666;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .text-success { color: #198754; }
        .text-danger { color: #dc3545; }
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
        }
        .badge-success { background: #d1e7dd; color: #198754; }
        .badge-danger { background: #f8d7da; color: #dc3545; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .modalidade-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .modalidade-item {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . sanitizar($gym['nome'] ?? 'Titanium Gym') . '</h1>
        <div class="subtitle">
            Relatório Financeiro<br>
            Período: ' . $data_inicio_fmt . ' a ' . $data_fim_fmt . '
        </div>
    </div>
    
    <div class="info-box">
        <div class="info-row">
            <span><strong>Data de Geração:</strong> ' . $data_geracao . '</span>
            <span><strong>Cidade:</strong> ' . sanitizar($gym['cidade'] ?? '-') . ' - ' . sanitizar($gym['estado'] ?? '') . '</span>
        </div>
    </div>
    
    <div class="kpi-container">
        <div class="kpi-box receitas">
            <h3>R$ ' . number_format($resumo['total_receitas'], 2, ',', '.') . '</h3>
            <p>Total de Receitas</p>
        </div>
        <div class="kpi-box despesas">
            <h3>R$ ' . number_format($resumo['total_despesas'], 2, ',', '.') . '</h3>
            <p>Total de Despesas</p>
        </div>
        <div class="kpi-box saldo">
            <h3>R$ ' . number_format($saldo, 2, ',', '.') . '</h3>
            <p>Saldo do Período</p>
        </div>
    </div>
    
    <div class="section">
        <h2>Resumo por Modalidade</h2>
        <table>
            <thead>
                <tr>
                    <th>Modalidade</th>
                    <th class="text-end">Receitas</th>
                    <th class="text-end">Despesas</th>
                    <th class="text-end">Resultado</th>
                </tr>
            </thead>
            <tbody>';
            
foreach ($modalidades as $mod) {
    $resultado = $mod['receitas'] - $mod['despesas'];
    $html .= '
                <tr>
                    <td>' . sanitizar($mod['modalidade']) . '</td>
                    <td class="text-end text-success">R$ ' . number_format($mod['receitas'], 2, ',', '.') . '</td>
                    <td class="text-end text-danger">R$ ' . number_format($mod['despesas'], 2, ',', '.') . '</td>
                    <td class="text-end ' . ($resultado >= 0 ? 'text-success' : 'text-danger') . '">
                        <strong>R$ ' . number_format($resultado, 2, ',', '.') . '</strong>
                    </td>
                </tr>';
}

$html .= '
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h2>Detalhamento de Transações (' . count($transacoes) . ' registros)</h2>
        <table class="transacoes-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Modalidade</th>
                    <th>Aluno</th>
                    <th>Tipo</th>
                    <th class="text-end">Valor</th>
                </tr>
            </thead>
            <tbody>';

if (count($transacoes) > 0) {
    foreach ($transacoes as $trans) {
        $modalidade = !empty($trans['modalidade_nome']) ? $trans['modalidade_nome'] : '-';
        $aluno = !empty($trans['aluno_nome']) ? $trans['aluno_nome'] : '-';
        $tipo_class = $trans['tipo'] == 'receita' ? 'badge-success' : 'badge-danger';
        $valor_class = $trans['tipo'] == 'receita' ? 'text-success' : 'text-danger';
        $valor_prefix = $trans['tipo'] == 'receita' ? '+' : '-';
        
        $html .= '
                <tr>
                    <td>' . date('d/m/Y', strtotime($trans['data'])) . '</td>
                    <td>' . sanitizar($trans['descricao']) . '</td>
                    <td>' . sanitizar($modalidade) . '</td>
                    <td>' . sanitizar($aluno) . '</td>
                    <td><span class="badge ' . $tipo_class . '">' . ($trans['tipo'] == 'receita' ? 'Receita' : 'Despesa') . '</span></td>
                    <td class="text-end ' . $valor_class . '"><strong>' . $valor_prefix . 'R$ ' . number_format($trans['valor'], 2, ',', '.') . '</strong></td>
                </tr>';
    }
} else {
    $html .= '
                <tr>
                    <td colspan="6" class="text-center">Nenhuma transação encontrada no período.</td>
                </tr>';
}

$html .= '
            </tbody>
        </table>
    </div>
    
    <div class="footer">
        <p>Relatório gerado pelo Titanium Gym Manager - Sistema de Gestão para Academias</p>
        <p>Página gerada em ' . $data_geracao . '</p>
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
    
    $filename = 'relatorio_financeiro_' . date('Y-m-d') . '.pdf';
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
