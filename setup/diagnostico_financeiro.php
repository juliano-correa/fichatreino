<?php
/**
 * Script de diagnóstico para verificar os dados do financeiro
 */

require_once '../config/conexao.php';

echo "<h2>Diagnóstico - Tabela Financeiro</h2>";

$gym_id = $_SESSION['gym_id'] ?? 1;

echo "<p><strong>Gym ID:</strong> {$gym_id}</p>";

// Verificar estrutura da tabela
echo "<h3>1. Estrutura da Tabela</h3>";
try {
    $stmt = $pdo->query("DESCRIBE financeiro");
    $estrutura = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Padrão</th></tr>";
    foreach ($estrutura as $campo) {
        echo "<tr>";
        echo "<td>{$campo['Field']}</td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro ao obter estrutura: " . $e->getMessage() . "</p>";
}

echo "<h3>2. Todas as Transações</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM financeiro WHERE gym_id = :gym_id ORDER BY data DESC LIMIT 20");
    $stmt->execute([':gym_id' => $gym_id]);
    $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Total de registros encontrados: " . count($transacoes) . "</p>";
    
    if (count($transacoes) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Data</th><th>Tipo</th><th>Descrição</th><th>Valor</th><th>Gym ID</th></tr>";
        foreach ($transacoes as $t) {
            echo "<tr>";
            echo "<td>{$t['id']}</td>";
            echo "<td>{$t['data']}</td>";
            echo "<td>{$t['tipo']}</td>";
            echo "<td>{$t['descricao']}</td>";
            echo "<td>{$t['valor']} (tipo: " . gettype($t['valor']) . ")</td>";
            echo "<td>{$t['gym_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>Nenhuma transação encontrada para este gym.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}

echo "<h3>3. Resumo por Tipo (Query do Sistema)</h3>";
try {
    $mes = date('m');
    $ano = date('Y');
    
    $sql = "SELECT 
        SUM(CASE WHEN tipo = 'receita' AND MONTH(data) = :mes AND YEAR(data) = :ano THEN valor ELSE 0 END) as total_receitas,
        SUM(CASE WHEN tipo = 'despesa' AND MONTH(data) = :mes AND YEAR(data) = :ano THEN valor ELSE 0 END) as total_despesas
    FROM financeiro 
    WHERE gym_id = :gym_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':gym_id' => $gym_id, ':mes' => $mes, ':ano' => $ano]);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($resumo);
    echo "</pre>";
    
    echo "<p>Receitas: R$ " . number_format($resumo['total_receitas'], 2, ',', '.') . "</p>";
    echo "<p>Despesas: R$ " . number_format($resumo['total_despesas'], 2, ',', '.') . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}

echo "<h3>4. Transações do Mês Atual</h3>";
try {
    $mes = date('m');
    $ano = date('Y');
    
    $stmt = $pdo->prepare("SELECT * FROM financeiro WHERE gym_id = :gym_id AND MONTH(data) = :mes AND YEAR(data) = :ano ORDER BY data DESC");
    $stmt->execute([':gym_id' => $gym_id, ':mes' => $mes, ':ano' => $ano]);
    $transacoes_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Total: " . count($transacoes_mes) . " transações</p>";
    
    if (count($transacoes_mes) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Data</th><th>Tipo</th><th>Descrição</th><th>Valor</th></tr>";
        foreach ($transacoes_mes as $t) {
            echo "<tr>";
            echo "<td>{$t['id']}</td>";
            echo "<td>{$t['data']}</td>";
            echo "<td>{$t['tipo']}</td>";
            echo "<td>{$t['descricao']}</td>";
            echo "<td>{$t['valor']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
