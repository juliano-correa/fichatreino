<?php
/**
 * Script de Setup - Criar Tabela de Usuários
 * Execute este arquivo uma vez para criar a tabela de usuários no banco de dados
 */

require_once '../config/conexao.php';

echo "<h2>Setup - Tabela de Usuários</h2>";

try {
    // Primeiro, verificar se a tabela já existe
    $check_table = $pdo->query("SHOW TABLES LIKE 'titanium_gym_users'")->fetch();
    
    if ($check_table) {
        echo "<p style='color: orange;'>! A tabela 'titanium_gym_users' já existe.</p>";
    } else {
        // Criar tabela de usuários SEM chave estrangeira (para evitar problemas de compatibilidade)
        $sql = "CREATE TABLE `titanium_gym_users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `senha` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'instrutor', 'recepcao', 'aluno') NOT NULL DEFAULT 'aluno',
            `student_id` INT(11) NULL DEFAULT NULL COMMENT 'Link para tabela students se role=aluno',
            `ativo` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_student_id` (`student_id`),
            KEY `idx_email` (`email`),
            KEY `idx_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Tabela 'titanium_gym_users' criada com sucesso!</p>";
    }
    
    // Verificar se já existe um admin
    $check_admin = $pdo->query("SELECT COUNT(*) FROM titanium_gym_users WHERE role = 'admin'")->fetchColumn();
    
    if ($check_admin == 0) {
        // Criar usuário admin padrão
        $admin_password = password_hash('admin123', PASSWORD_BCRYPT);
        $sql_admin = "INSERT INTO titanium_gym_users (nome, email, senha, role, ativo) 
                      VALUES ('Administrador', 'admin@titanium.com', :senha, 'admin', 1)";
        $stmt_admin = $pdo->prepare($sql_admin);
        $stmt_admin->execute([':senha' => $admin_password]);
        echo "<p style='color: blue;'>✓ Usuário admin criado com sucesso!</p>";
        echo "<p><strong>E-mail:</strong> admin@titanium.com</p>";
        echo "<p><strong>Senha:</strong> admin123</p>";
    } else {
        echo "<p style='color: green;'>✓ Já existe um administrador no sistema.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Resumo:</h3>";
    echo "<ul>";
    echo "<li>Tabela: titanium_gym_users</li>";
    echo "<li>Perfis disponíveis: admin, instrutor, recepcao, aluno</li>";
    echo "<li>Perfil 'aluno' pode ser vinculado a um registro da tabela students</li>";
    echo "</ul>";
    
    echo "<div class='mt-4'>";
    echo "<a href='../login.php' class='btn btn-primary me-2'>";
    echo "<i class='bi bi-box-arrow-in-right'></i> Ir para Login";
    echo "</a>";
    echo "<a href='index.php' class='btn btn-outline-secondary'>";
    echo "<i class='bi bi-arrow-left'></i> Voltar ao Setup";
    echo "</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>Erro:</h4>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    
    // Sugestões de solução
    echo "<div class='alert alert-info mt-3'>";
    echo "<h5>Sugestões:</h5>";
    echo "<ul>";
    echo "<li>Verifique se o banco de dados 'titanium_gym' existe</li>";
    echo "<li>Verifique se as tabelas 'students' e outras já foram criadas</li>";
    echo "<li>Entre em contato com o administrador do sistema se o erro persistir</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Tabela de Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            padding: 40px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php // O conteúdo PHP acima será executado e exibido aqui ?>
    </div>
</body>
</html>
