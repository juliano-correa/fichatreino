<?php
/**
 * Diagnóstico Completo - Erro ao criar turma
 * Este script identifica a causa exata do erro
 */

require_once '../config/conexao.php';

$erros = [];
$sucessos = [];

// ============================================
// 1. Verificar estrutura da tabela
// ============================================
echo "<h4>1. Verificando estrutura da tabela class_definitions:</h4>";

try {
    $stmt = $pdo->query("DESCRIBE class_definitions");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $colunas_encontradas = [];
    echo "<table class='table table-striped table-bordered' style='font-size: 12px;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Padrão</th></tr>";
    
    foreach ($colunas as $col) {
        $colunas_encontradas[] = $col['Field'];
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . ($col['Default'] ?? '<em>NULL</em>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar colunas obrigatórias
    $colunas_obrigatorias = ['gym_id', 'name', 'day_of_week', 'start_time', 'end_time', 'max_capacity', 'color_hex', 'active'];
    foreach ($colunas_obrigatorias as $col) {
        if (in_array($col, $colunas_encontradas)) {
            $sucessos[] = "✅ Coluna '$col' encontrada";
        } else {
            $erros[] = "❌ Coluna '$col' NÃO encontrada!";
        }
    }
    
} catch (PDOException $e) {
    $erros[] = "Erro ao verificar estrutura: " . $e->getMessage();
}

// ============================================
// 2. Testar INSERT com os parâmetros exatos
// ============================================
echo "<hr><h4>2. Testando INSERT com parâmetros:</h4>";

try {
    // Primeiro, ver os dados que serão enviados
    $dados_teste = [
        'gym_id' => 1,
        'modality_id' => null,
        'instructor_id' => null,
        'name' => 'TESTE_DIAGNOSTICO',
        'description' => 'Teste para diagnóstico',
        'day_of_week' => 1,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'max_capacity' => 20,
        'color_hex' => '#0d6efd'
    ];
    
    echo "<p><strong>Dados que serão inseridos:</strong></p>";
    echo "<ul>";
    foreach ($dados_teste as $key => $value) {
        echo "<li><code>$key</code>: " . ($value === null ? 'NULL' : "'$value'") . "</li>";
    }
    echo "</ul>";
    
    // Verificar quantas colunas a tabela tem vs. quantos dados estamos enviando
    $stmt = $pdo->query("DESCRIBE class_definitions");
    $total_colunas = count($stmt->fetchAll());
    
    echo "<p><strong>Análise:</strong></p>";
    echo "<ul>";
    echo "<li>Colunas na tabela: $total_colunas</li>";
    echo "<li>Colunas no INSERT: " . count($dados_teste) . "</li>";
    echo "</ul>";
    
    // Testar INSERT
    $sql = "
        INSERT INTO class_definitions (
            gym_id, modality_id, instructor_id, name, description,
            day_of_week, start_time, end_time, max_capacity, color_hex
        ) VALUES (
            :gym_id, :modality_id, :instructor_id, :name, :description,
            :day_of_week, :start_time, :end_time, :max_capacity, :color_hex
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute($dados_teste);
    
    if ($resultado) {
        $novo_id = $pdo->lastInsertId();
        $sucessos[] = "✅ INSERT funcionou! ID criado: $novo_id";
        
        // Remover registro de teste
        $pdo->exec("DELETE FROM class_definitions WHERE id = $novo_id");
        echo "<div class='alert alert-success'>✅ SUCESSO! O INSERT funciona corretamente.</div>";
    } else {
        $erros[] = "❌ INSERT falhou!";
        echo "<pre>";
        print_r($stmt->errorInfo());
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    $erros[] = "❌ Erro no INSERT: " . $e->getMessage();
    echo "<div class='alert alert-danger'>";
    echo "<strong>Erro detalhado:</strong><br>";
    echo $e->getMessage();
    echo "</div>";
}

// ============================================
// 3. Verificar função getGymId()
// ============================================
echo "<hr><h4>3. Verificando função getGymId():</h4>";

try {
    $gym_id = getGymId();
    echo "<div class='alert alert-info'>getGymId() retorna: <strong>$gym_id</strong></div>";
    
    if (empty($gym_id) || $gym_id === null) {
        $erros[] = "⚠️ getGymId() está retornando valor vazio!";
    } else {
        $sucessos[] = "✅ getGymId() retorna valor válido: $gym_id";
    }
} catch (Exception $e) {
    $erros[] = "❌ Erro ao chamar getGymId(): " . $e->getMessage();
}

// ============================================
// Resumo
// ============================================
echo "<hr>";
echo "<h4>RESUMO:</h4>";

if (!empty($erros)) {
    echo "<div class='alert alert-danger'>";
    echo "<strong>❌ Problemas encontrados:</strong>";
    echo "<ul>";
    foreach ($erros as $erro) {
        echo "<li>$erro</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($sucessos)) {
    echo "<div class='alert alert-success'>";
    echo "<strong>✅ Verificações OK:</strong>";
    echo "<ul>";
    foreach ($sucessos as $sucesso) {
        echo "<li>$sucesso</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Completo - Criação de Turmas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        h4 { color: #0d6efd; margin-top: 20px; }
        table { margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="bi bi-bug me-2"></i>Diagnóstico: Erro ao Criar Turma</h3>
            </div>
            <div class="card-body">
                <?= date('d/m/Y H:i:s') ?>
                
                <?php if (empty($erros) && !empty($sucessos)): ?>
                    <div class="alert alert-success d-flex align-items-center mt-4">
                        <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                        <div>
                            <strong>Diagnóstico concluído!</strong><br>
                            O INSERT funcionou corretamente no banco de dados.<br>
                            O problema pode estar no código PHP da página turmas.php.
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <a href="../agenda/turmas.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-arrow-repeat me-2"></i>Tentar Criar Turma Novamente
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning d-flex align-items-center mt-4">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                        <div>Verifique os erros acima e execute as correções sugeridas.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
