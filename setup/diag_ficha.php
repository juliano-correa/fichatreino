<?php
/**
 * Script de Diagnóstico - Fichas de Treino
 * Execute: setup/diag_ficha.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico - Fichas de Treino</h2>";

$pdo = null;

try {
    // Conexão com banco
    $host = 'sql310.infinityfree.com';
    $dbname = 'if0_40786753_titanium_gym';
    $username = 'if0_40786753';
    $password = 'Jota190876';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>✓ Conexão OK!</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>";
    echo "✗ Erro de conexão: " . $e->getMessage();
    echo "</div>";
    exit;
}

echo "<h4>1. Verificando tabelas...</h4>";

// Tabela students
$stmt = $pdo->query("SHOW TABLES LIKE 'students'");
if ($stmt->fetch()) {
    echo "<span style='color: green;'>✓ Tabela 'students' existe</span><br>";
} else {
    echo "<span style='color: red;'>✗ Tabela 'students' NÃO existe!</span><br>";
}

// Tabela workouts
$stmt = $pdo->query("SHOW TABLES LIKE 'workouts'");
if ($stmt->fetch()) {
    echo "<span style='color: green;'>✓ Tabela 'workouts' existe</span><br>";
} else {
    echo "<span style='color: red;'>✗ Tabela 'workouts' NÃO existe!</span><br>";
}

// Tabela exercicios
$stmt = $pdo->query("SHOW TABLES LIKE 'exercicios'");
if ($stmt->fetch()) {
    echo "<span style='color: green;'>✓ Tabela 'exercicios' existe</span><br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM exercicios");
    $total = $stmt->fetchColumn();
    echo "  → $total exercícios cadastrados<br>";
} else {
    echo "<span style='color: red;'>✗ Tabela 'exercicios' NÃO existe!</span><br>";
}

echo "<h4>2. Verificando estrutura workouts...</h4>";
try {
    $stmt = $pdo->query("DESCRIBE workouts");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $necessarias = ['id', 'gym_id', 'aluno_id', 'nome', 'ativa', 'exercicios'];
    $faltando = array_diff($necessarias, $colunas);
    
    if (empty($faltando)) {
        echo "<span style='color: green;'>✓ Todas as colunas necessárias existem</span><br>";
    } else {
        echo "<span style='color: red;'>✗ Colunas faltando: " . implode(', ', $faltando) . "</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Erro: " . $e->getMessage() . "</span><br>";
}

echo "<h4>3. Verificando arquivos...</h4>";

$arquivos_verificar = [
    '../fichas_treino/index.php',
    '../fichas_treino/novo.php',
    '../fichas_treino/editar.php',
    '../fichas_treino/visualizar.php',
    '../includes/menu.php',
    '../config/db.php',
];

$pasta_base = __DIR__ . '/..';

foreach ($arquivos_verificar as $arquivo) {
    $caminho = $pasta_base . '/' . str_replace('../', '', $arquivo);
    
    if (file_exists($caminho)) {
        echo "✓ $arquivo existe<br>";
        
        // Verificar se usa tabela students (não alunos)
        $conteudo = file_get_contents($caminho);
        
        if (strpos($conteudo, "'alunos'") !== false && strpos($conteudo, "'students'") === false) {
            echo "  ⚠️ ATENÇÃO: Este arquivo ainda usa a tabela 'alunos' (deve ser 'students')<br>";
        }
    } else {
        echo "<span style='color: red;'>✗ $arquivo NÃO existe!</span><br>";
    }
}

echo "<h4>4. Testando incluir menu...</h4>";
try {
    ob_start();
    include $pasta_base . '/includes/menu.php';
    $menu_output = ob_get_clean();
    echo "<span style='color: green;'>✓ Menu incluído sem erros</span><br>";
} catch (Exception $e) {
    ob_end_clean();
    echo "<span style='color: red;'>✗ Erro no menu: " . $e->getMessage() . "</span><br>";
}

echo "<h4>5. Listando fichas existentes...</h4>";
try {
    $stmt = $pdo->query("
        SELECT w.id, w.nome, w.status, s.nome as aluno
        FROM workouts w 
        LEFT JOIN students s ON w.aluno_id = s.id 
        WHERE w.gym_id = 1
        ORDER BY w.created_at DESC
        LIMIT 5
    ");
    $fichas = $stmt->fetchAll();
    
    if (empty($fichas)) {
        echo "Nenhuma ficha encontrada<br>";
    } else {
        foreach ($fichas as $f) {
            echo "ID {$f['id']}: " . htmlspecialchars($f['nome']) . " - " . htmlspecialchars($f['aluno'] ?? 'N/A') . "<br>";
        }
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Erro: " . $e->getMessage() . "</span><br>";
}

echo "<hr>";
echo "<h4>Links de Teste:</h4>";
echo "<a href='fichas_treino/index.php' class='btn btn-primary btn-sm' style='margin: 5px;'>Index</a>";
echo "<a href='fichas_treino/novo.php' class='btn btn-success btn-sm' style='margin: 5px;'>Novo</a>";
echo "<a href='dashboard.php' class='btn btn-secondary btn-sm' style='margin: 5px;'>Dashboard</a>";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Fichas</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h2 { color: #333; margin-top: 0; }
        h4 { color: #667eea; margin-top: 20px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Diagnóstico de Fichas de Treino</h2>
        <?php echo $output ?? ''; ?>
    </div>
</body>
</html>
