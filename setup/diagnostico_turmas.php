<?php
/**
 * Diagnóstico e Correção da Tabela class_definitions
 */

require_once '../config/conexao.php';

echo "<h2>Diagnóstico - Tabela class_definitions</h2>";
echo "<hr>";

// Verificar estrutura da tabela
echo "<h4>1. Estrutura Atual da Tabela:</h4>";
try {
    $stmt = $pdo->query("DESCRIBE class_definitions");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table class='table table-bordered' style='font-size: 14px;'>";
    echo "<tr><th>Coluna</th><th>Tipo</th><th>Nulo</th><th>Padrão</th></tr>";
    foreach ($colunas as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar se coluna active existe
    $has_active = false;
    $has_gym_id = false;
    foreach ($colunas as $col) {
        if ($col['Field'] === 'active') $has_active = true;
        if ($col['Field'] === 'gym_id') $has_gym_id = true;
    }
    
    if (!$has_active) {
        echo "<div class='alert alert-danger'>❌ Coluna 'active' NÃO existe! Adicionando...</div>";
        $pdo->exec("ALTER TABLE class_definitions ADD COLUMN active TINYINT DEFAULT 1 AFTER color_hex");
        echo "<div class='alert alert-success'>✅ Coluna 'active' adicionada!</div>";
    }
    
    if (!$has_gym_id) {
        echo "<div class='alert alert-danger'>❌ Coluna 'gym_id' NÃO existe! Adicionando...</div>";
        $pdo->exec("ALTER TABLE class_definitions ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
        echo "<div class='alert alert-success'>✅ Coluna 'gym_id' adicionada!</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
}

echo "<hr>";

// Testar INSERT
echo "<h4>2. Testando INSERT:</h4>";
try {
    $stmt = $pdo->prepare("
        INSERT INTO class_definitions (
            gym_id, modality_id, instructor_id, name, description,
            day_of_week, start_time, end_time, max_capacity, color_hex, active
        ) VALUES (
            :gym_id, :modality_id, :instructor_id, :name, :description,
            :day_of_week, :start_time, :end_time, :max_capacity, :color_hex, :active
        )
    ");
    
    $params = [
        ':gym_id' => 1,
        ':modality_id' => null,
        ':instructor_id' => null,
        ':name' => 'TESTE - Delete-me',
        ':description' => 'Teste de diagnóstico',
        ':day_of_week' => 1,
        ':start_time' => '08:00:00',
        ':end_time' => '09:00:00',
        ':max_capacity' => 20,
        ':color_hex' => '#ff0000',
        ':active' => 0
    ];
    
    $stmt->execute($params);
    $last_id = $pdo->lastInsertId();
    
    echo "<div class='alert alert-success'>✅ INSERT funcionou! ID criado: $last_id</div>";
    
    // Limpar registro de teste
    $pdo->exec("DELETE FROM class_definitions WHERE id = $last_id");
    echo "<div class='alert alert-info'>🗑️ Registro de teste removido (ID: $last_id)</div>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>❌ Erro no INSERT: " . $e->getMessage() . "</div>";
    echo "<br>Parâmetros esperados vs. enviados - Verifique a estrutura da tabela!";
}

echo "<hr>";
echo "<a href='../agenda/turmas.php' class='btn btn-primary btn-lg'>Ir para Turmas</a>";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Titanium Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Diagnóstico de Tabela</h4>
                    </div>
                    <div class="card-body">
                        <?= date('d/m/Y H:i:s') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
