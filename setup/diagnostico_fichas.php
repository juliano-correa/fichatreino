<?php
/**
 * Script de Diagnóstico e Correção do Módulo de Fichas de Treino
 * Execute via navegador: setup/diagnostico_fichas.php
 */

// Variáveis para acumular saída
$output = '';
$sucesso = true;

function msg($texto, $tipo = 'sucesso') {
    global $output, $sucesso;
    $cores = [
        'sucesso' => 'green',
        'erro' => 'red',
        'info' => 'blue',
        'aviso' => 'orange'
    ];
    $cor = $cores[$tipo] ?? 'black';
    if ($tipo == 'erro') $sucesso = false;
    $output .= "<p style='color: $cor; margin: 5px 0;'>$texto</p>";
}

function msg_hr() {
    global $output;
    $output .= "<hr style='margin: 15px 0;'>";
}

$output .= "<h2>🔧 Diagnóstico e Correção - Módulo de Fichas</h2>";

$output .= "<ul>";

// 1. Testar conexão com banco de dados
msg("1. Testando conexão com banco de dados...", 'info');

try {
    $host = 'sql310.infinityfree.com';
    $dbname = 'if0_40786753_titanium_gym';
    $username = 'if0_40786753';
    $password = 'Jota190876';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    msg("✓ Conexão com banco de dados OK!", 'sucesso');
} catch (PDOException $e) {
    msg("✗ Erro na conexão: " . $e->getMessage(), 'erro');
    $sucesso = false;
    goto mostra_resultado;
}

msg_hr();
msg("2. Verificando tabelas necessárias...", 'info');

// 2. Verificar se tabela workouts existe
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'workouts'");
    $stmt->execute();
    if ($stmt->fetch()) {
        msg("✓ Tabela 'workouts' existe", 'sucesso');
    } else {
        msg("✗ Tabela 'workouts' não encontrada!", 'erro');
    }
} catch (PDOException $e) {
    msg("✗ Erro ao verificar tabela workouts: " . $e->getMessage(), 'erro');
}

msg_hr();
msg("3. Criando/Verificando tabela de exercícios...", 'info');

// 3. Criar tabela de exercícios
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `exercicios` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `gym_id` int(11) NOT NULL DEFAULT 1,
        `nome` varchar(100) NOT NULL,
        `grupo_muscular` varchar(50) NOT NULL,
        `descricao` text,
        `observacoes` text,
        `ativo` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `gym_id` (`gym_id`),
        KEY `grupo_muscular` (`grupo_muscular`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    msg("✓ Tabela 'exercicios' criada/verificada com sucesso!", 'sucesso');
} catch (PDOException $e) {
    msg("✗ Erro ao criar tabela: " . $e->getMessage(), 'erro');
}

// 4. Inserir exercícios de exemplo
msg_hr();
msg("4. Inserindo exercícios de exemplo...", 'info');

try {
    // Verificar se já existem exercícios
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exercicios");
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    if ($total > 0) {
        msg("Já existem $total exercícios cadastrados", 'aviso');
    } else {
        $exercicios_exemplo = [
            ['nome' => 'Supino Reto', 'grupo_muscular' => 'Peito'],
            ['nome' => 'Supino Inclinado', 'grupo_muscular' => 'Peito'],
            ['nome' => 'Crucifixo', 'grupo_muscular' => 'Peito'],
            ['nome' => 'Puxada Alta', 'grupo_muscular' => 'Costas'],
            ['nome' => 'Remada Curvada', 'grupo_muscular' => 'Costas'],
            ['nome' => 'Pulley Frontal', 'grupo_muscular' => 'Costas'],
            ['nome' => 'Agachamento Livre', 'grupo_muscular' => 'Pernas'],
            ['nome' => 'Leg Press', 'grupo_muscular' => 'Pernas'],
            ['nome' => 'Cadeira Extensora', 'grupo_muscular' => 'Pernas'],
            ['nome' => 'Rosca Direta', 'grupo_muscular' => 'Bíceps'],
            ['nome' => 'Rosca Alternada', 'grupo_muscular' => 'Bíceps'],
            ['nome' => 'Tríceps Pulley', 'grupo_muscular' => 'Tríceps'],
            ['nome' => 'Testa', 'grupo_muscular' => 'Tríceps'],
            ['nome' => 'Elevação Lateral', 'grupo_muscular' => 'Ombros'],
            ['nome' => 'Elevação Frontal', 'grupo_muscular' => 'Ombros'],
            ['nome' => 'Abdominal Crunch', 'grupo_muscular' => 'Abdômen'],
            ['nome' => 'Prancha', 'grupo_muscular' => 'Abdômen'],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO exercicios (gym_id, nome, grupo_muscular) VALUES (1, ?, ?)");
        
        foreach ($exercicios_exemplo as $exerc) {
            $stmt->execute([$exerc['nome'], $exerc['grupo_muscular']]);
        }
        
        msg(count($exercicios_exemplo) . " exercícios de exemplo inseridos!", 'sucesso');
    }
} catch (PDOException $e) {
    msg("✗ Erro ao inserir exercícios: " . $e->getMessage(), 'erro');
}

// 5. Verificar alunos
msg_hr();
msg("5. Verificando alunos cadastrados...", 'info');

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE ativo = 1");
    $stmt->execute();
    $total_alunos = $stmt->fetchColumn();
    
    if ($total_alunos > 0) {
        msg("✓ $total_alunos alunos encontrados", 'sucesso');
    } else {
        msg("⚠ Nenhum aluno encontrado. Cadastre alunos antes de criar fichas!", 'aviso');
    }
} catch (PDOException $e) {
    msg("✗ Erro ao verificar alunos: " . $e->getMessage(), 'erro');
}

// 6. Verificar arquivos PHP
msg_hr();
msg("6. Verificando arquivos do módulo...", 'info');

$arquivos_necessarios = [
    '../fichas_treino/novo.php',
    '../fichas_treino/editar.php',
    '../fichas_treino/index.php',
    '../fichas_treino/visualizar.php',
];

foreach ($arquivos_necessarios as $arquivo) {
    $caminho_completo = __DIR__ . '/' . str_replace('..', '.', $arquivo);
    if (file_exists($caminho_completo)) {
        msg("✓ $arquivo existe", 'sucesso');
    } else {
        msg("✗ $arquivo NÃO encontrado!", 'erro');
    }
}

mostra_resultado:

$output .= "</ul>";

msg_hr();

if ($sucesso) {
    $output .= "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
    $output .= "<h4 style='color: #155724; margin-top: 0;'>🎉 Diagnóstico Concluído com Sucesso!</h4>";
    $output .= "<p>Todas as correções foram aplicadas. Agora você pode:</p>";
    $output .= "<p><a href='../fichas_treino/index.php' class='btn btn-primary' style='margin-right: 10px;'>Ver Fichas de Treino</a>";
    $output .= "<a href='../fichas_treino/novo.php' class='btn btn-success'>Criar Nova Ficha</a></p>";
    $output .= "</div>";
} else {
    $output .= "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
    $output .= "<h4 style='color: #721c24; margin-top: 0;'>⚠️ Problemas Encontrados</h4>";
    $output .= "<p>Verifique os erros acima e tente novamente.</p>";
    $output .= "<p>Se o problema persistir, entre em contato com o suporte.</p>";
    $output .= "</div>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Módulo de Fichas</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
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
