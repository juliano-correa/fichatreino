<?php
/**
 * Script para criar a tabela de exercícios
 * Execute via navegador: setup/criar_tabela_exercicios.php
 */

require_once '../config/db.php';
require_once '../config/sessao.php';

$gym_id = $_SESSION['gym_id'] ?? 1;

echo "<h2>Criação da Tabela de Exercícios</h2>";

try {
    // Verificar se a tabela já existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'exercicios'");
    $stmt->execute();
    $tabela_existe = $stmt->fetch();
    
    if ($tabela_existe) {
        echo "<p style='color: orange;'>A tabela 'exercicios' já existe!</p>";
    } else {
        // Criar a tabela de exercícios
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
        echo "<p style='color: green;'>Tabela 'exercicios' criada com sucesso!</p>";
        
        // Inserir exercícios de exemplo
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
        
        $stmt = $pdo->prepare("INSERT INTO exercicios (gym_id, nome, grupo_muscular) VALUES (?, ?, ?)");
        
        foreach ($exercicios_exemplo as $exerc) {
            $stmt->execute([$gym_id, $exerc['nome'], $exerc['grupo_muscular']]);
        }
        
        echo "<p style='color: green;'>" . count($exercicios_exemplo) . " exercícios de exemplo inseridos!</p>";
    }
    
    echo "<hr>";
    echo "<h3>Status do Banco de Dados:</h3>";
    
    // Verificar tabelas existentes
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($tabelas as $tabela) {
        echo "<li>" . htmlspecialchars($tabela) . "</li>";
    }
    echo "</ul>";
    
    echo "<p><a href='../fichas_treino/novo.php' class='btn btn-primary'>Ir para Nova Ficha de Treino</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Tabela de Exercícios</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-body">
                <?php echo $output ?? ''; ?>
            </div>
        </div>
    </div>
</body>
</html>
