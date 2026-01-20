<?php
/**
 * Teste Debug - Identificar o problema no INSERT
 */

require_once '../config/conexao.php';
require_once '../config/functions.php';

echo "<h2>Debug - Criação de Turma</h2>";
echo "<hr>";

// Simular dados do POST
$_POST = [
    'acao' => 'criar',
    'name' => 'TESTE DEBUG',
    'modality_id' => '',
    'instructor_id' => '',
    'day_of_week' => '1',
    'start_time' => '08:00',
    'end_time' => '09:00',
    'max_capacity' => '20',
    'color_hex' => '#0d6efd',
    'description' => 'Teste'
];

// Processar exatamente como no turmas.php
$name = trim($_POST['name'] ?? '');
$modality_id = $_POST['modality_id'] ?? null;
$instructor_id = $_POST['instructor_id'] ?? null;
$day_of_week = $_POST['day_of_week'] ?? 0;
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$max_capacity = (int)($_POST['max_capacity'] ?? 20);
$color_hex = $_POST['color_hex'] ?? '#0d6efd';
$description = trim($_POST['description'] ?? '');

echo "<h4>1. Valores das variáveis:</h4>";
echo "<table class='table table-bordered' style='font-size: 14px;'>";
echo "<tr><th>Variável</th><th>Valor</th><th>Tipo</th><th>Empty?</th></tr>";

$vars = [
    'name' => $name,
    'modality_id' => $modality_id,
    'instructor_id' => $instructor_id,
    'day_of_week' => $day_of_week,
    'start_time' => $start_time,
    'end_time' => $end_time,
    'max_capacity' => $max_capacity,
    'color_hex' => $color_hex,
    'description' => $description,
    'gym_id' => getGymId()
];

foreach ($vars as $key => $value) {
    echo "<tr>";
    echo "<td><code>\$$key</code></td>";
    echo "<td>" . var_export($value, true) . "</td>";
    echo "<td>" . gettype($value) . "</td>";
    echo "<td>" . (empty($value) ? 'SIM' : 'NÃO') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h4>2. Array de parâmetros:</h4>";
$params = [
    ':gym_id' => getGymId(),
    ':modality_id' => !empty($modality_id) ? $modality_id : null,
    ':instructor_id' => !empty($instructor_id) ? $instructor_id : null,
    ':name' => $name,
    ':description' => !empty($description) ? $description : null,
    ':day_of_week' => $day_of_week,
    ':start_time' => $start_time,
    ':end_time' => $end_time,
    ':max_capacity' => $max_capacity,
    ':color_hex' => $color_hex
];

echo "<pre>";
print_r($params);
echo "</pre>";

echo "<h4>3. SQL e Contagem:</h4>";
$sql = "
    INSERT INTO class_definitions (
        gym_id, modality_id, instructor_id, name, description,
        day_of_week, start_time, end_time, max_capacity, color_hex
    ) VALUES (
        :gym_id, :modality_id, :instructor_id, :name, :description,
        :day_of_week, :start_time, :end_time, :max_capacity, :color_hex
    )
";

$placeholders = [];
preg_match_all('/:(\w+)/', $sql, $placeholders);
$param_count = count($placeholders[1]);
$execute_count = count($params);

echo "<ul>";
echo "<li>Placeholders no SQL: $param_count</li>";
echo "<li>Parâmetros no execute: $execute_count</li>";
echo "<li>Placeholders: " . implode(', ', $placeholders[1]) . "</li>";
echo "<li>Keys do array: " . implode(', ', array_keys($params)) . "</li>";
echo "</ul>";

if ($param_count !== $execute_count) {
    echo "<div class='alert alert-danger'>❌ CONTAGEM DIFERENTE! ISSO CAUSA O ERRO!</div>";
} else {
    echo "<div class='alert alert-success'>✅ Contagem OK!</div>";
}

echo "<h4>4. Tentando INSERT:</h4>";
try {
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        $novo_id = $pdo->lastInsertId();
        echo "<div class='alert alert-success'>✅ SUCESSO! ID: $novo_id</div>";
        
        // Remover registro de teste
        $pdo->exec("DELETE FROM class_definitions WHERE id = $novo_id");
    } else {
        echo "<div class='alert alert-danger'>❌ FALHA! ErroInfo: ";
        print_r($stmt->errorInfo());
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>❌ EXCEPTION: " . $e->getMessage() . "</div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Criação de Turma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-bug me-2"></i>Debug - Criação de Turma</h4>
            </div>
            <div class="card-body">
                <a href="../agenda/turmas.php" class="btn btn-secondary">Voltar para Turmas</a>
            </div>
        </div>
    </div>
</body>
</html>
