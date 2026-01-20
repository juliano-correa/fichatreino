<?php
/**
 * Script de Verificação e Correção do Módulo de Fichas
 * Executar: setup/fix_fichas.php
 * 
 * ATENÇÃO: Este script usa a estrutura REAL do banco de dados:
 * - Tabela: students (não alunos)
 * - Tabela: workouts (já existe)
 * - Tabela: subscriptions (planos dos alunos)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$output = '';
$sucesso = true;

function add_msg($texto, $tipo = 'sucesso') {
    global $output, $sucesso;
    $cores = [
        'sucesso' => 'green',
        'erro' => 'red',
        'info' => '#667eea',
        'aviso' => 'orange'
    ];
    $cor = $cores[$tipo] ?? 'black';
    if ($tipo == 'erro') $sucesso = false;
    $emoji = $tipo == 'sucesso' ? '✓' : ($tipo == 'erro' ? '✗' : ($tipo == 'aviso' ? '⚠' : 'ℹ'));
    $output .= "<div style='color: $cor; margin: 5px 0;'>$emoji $texto</div>";
}

$output .= "<h2>🔧 Verificação do Módulo de Fichas</h2>";

try {
    // Conexão direta sem depender de includes
    $host = 'sql310.infinityfree.com';
    $dbname = 'if0_40786753_titanium_gym';
    $username = 'if0_40786753';
    $password = 'Jota190876';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    add_msg("Conexão com banco OK!", 'sucesso');
    
    $output .= "<hr>";
    add_msg("1. Verificando tabelas necessárias...", 'info');
    
    // Verificar tabela students
    $stmt = $pdo->query("SHOW TABLES LIKE 'students'");
    if ($stmt->fetch()) {
        add_msg("Tabela 'students' existe", 'sucesso');
        
        // Contar alunos
        $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'ativo'");
        $total = $stmt->fetchColumn();
        add_msg("$total alunos ativos encontrados", 'info');
    } else {
        add_msg("Tabela 'students' não encontrada!", 'erro');
    }
    
    // Verificar tabela workouts
    $stmt = $pdo->query("SHOW TABLES LIKE 'workouts'");
    if ($stmt->fetch()) {
        add_msg("Tabela 'workouts' existe", 'sucesso');
        
        // Contar fichas
        $stmt = $pdo->query("SELECT COUNT(*) FROM workouts");
        $total_fichas = $stmt->fetchColumn();
        add_msg("$total_fichas fichas de treino encontradas", 'info');
    } else {
        add_msg("Tabela 'workouts' não encontrada!", 'erro');
    }
    
    // Verificar/Criar tabela exercícios
    $output .= "<hr>";
    add_msg("2. Verificando tabela de exercícios...", 'info');
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'exercicios'");
    if (!$stmt->fetch()) {
        $sql = "CREATE TABLE `exercicios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `gym_id` int(11) NOT NULL DEFAULT 1,
            `nome` varchar(100) NOT NULL,
            `grupo_muscular` varchar(50) NOT NULL,
            `descricao` text,
            `observacoes` text,
            `ativo` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `gym_id` (`gym_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $pdo->exec($sql);
        add_msg("Tabela 'exercicios' criada!", 'sucesso');
    } else {
        add_msg("Tabela 'exercicios' já existe", 'sucesso');
    }
    
    // Inserir exercícios se estiver vazio
    $stmt = $pdo->query("SELECT COUNT(*) FROM exercicios");
    if ($stmt->fetchColumn() == 0) {
        $exercicios = [
            ['Supino Reto', 'Peito'],
            ['Supino Inclinado', 'Peito'],
            ['Crucifixo', 'Peito'],
            ['Puxada Alta', 'Costas'],
            ['Remada Curvada', 'Costas'],
            ['Pulley Frontal', 'Costas'],
            ['Agachamento Livre', 'Pernas'],
            ['Leg Press', 'Pernas'],
            ['Cadeira Extensora', 'Pernas'],
            ['Rosca Direta', 'Bíceps'],
            ['Rosca Alternada', 'Bíceps'],
            ['Tríceps Pulley', 'Tríceps'],
            ['Testa', 'Tríceps'],
            ['Elevação Lateral', 'Ombros'],
            ['Elevação Frontal', 'Ombros'],
            ['Abdominal Crunch', 'Abdômen'],
            ['Prancha', 'Abdômen'],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO exercicios (gym_id, nome, grupo_muscular) VALUES (1, ?, ?)");
        foreach ($exercicios as $e) {
            $stmt->execute($e);
        }
        add_msg("17 exercícios de exemplo inseridos", 'sucesso');
    } else {
        add_msg("Exercícios já existem no banco", 'aviso');
    }
    
    $output .= "<hr>";
    add_msg("3. Verificando arquivos do módulo...", 'info');
    
    // Verificar arquivos
    $arquivos = [
        'fichas_treino/index.php',
        'fichas_treino/novo.php',
        'fichas_treino/editar.php',
        'fichas_treino/visualizar.php',
    ];
    
    $pasta_base = __DIR__ . '/..';
    foreach ($arquivos as $arquivo) {
        $existe = file_exists($pasta_base . '/' . $arquivo);
        add_msg("$arquivo " . ($existe ? 'encontrado' : 'NÃO encontrado!'), $existe ? 'sucesso' : 'erro');
    }
    
    $output .= "<hr>";
    add_msg("4. Listando fichas existentes...", 'info');
    
    $stmt = $pdo->query("
        SELECT w.id, w.nome, w.status, w.created_at, s.nome as aluno_nome
        FROM workouts w
        LEFT JOIN students s ON w.aluno_id = s.id
        WHERE w.gym_id = 1
        ORDER BY w.created_at DESC
        LIMIT 10
    ");
    $fichas = $stmt->fetchAll();
    
    if (empty($fichas)) {
        add_msg("Nenhuma ficha encontrada", 'aviso');
    } else {
        echo "<table class='table table-striped table-sm mt-2' style='font-size: 0.9em;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Aluno</th><th>Status</th></tr>";
        foreach ($fichas as $f) {
            echo "<tr>";
            echo "<td>" . $f['id'] . "</td>";
            echo "<td>" . htmlspecialchars($f['nome'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($f['aluno_nome'] ?? '-') . "</td>";
            echo "<td>" . $f['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    add_msg("Erro: " . $e->getMessage(), 'erro');
}

$output .= "<hr>";

if ($sucesso) {
    $output .= "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    $output .= "<h4 style='color: #155724; margin-top: 0;'>✅ Sistema OK!</h4>";
    $output .= "<p>Você pode acessar:</p>";
    $output .= "<a href='fichas_treino/index.php' class='btn btn-primary btn-sm' style='margin-right: 10px;'>📋 Lista de Fichas</a>";
    $output .= "<a href='fichas_treino/novo.php' class='btn btn-success btn-sm'>➕ Nova Ficha</a>";
    $output .= "</div>";
} else {
    $output .= "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    $output .= "<h4 style='color: #721c24; margin-top: 0;'>⚠️ Verifique os erros acima</h4>";
    $output .= "</div>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação - Fichas de Treino</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; }
        h2 { color: #333; margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php echo $output; ?>
        </div>
    </div>
</body>
</html>
