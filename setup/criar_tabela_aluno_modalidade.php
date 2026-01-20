<?php
/**
 * Script de Criação da Tabela de Relacionamento Aluno-Modalidade
 * Permite que um aluno tenha múltiplas modalidades
 */

require_once '../config/conexao.php';

echo '<h1>Criação da Tabela Aluno-Modalidade</h1>';
echo '<p>Este script cria a tabela para vincular alunos a múltiplas modalidades.</p>';

try {
    // Desabilitar foreign key check temporariamente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    echo '<h3>1. Verificando se a tabela já existe</h3>';
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'aluno_modalidade'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo '<p style="color: orange;">A tabela aluno_modalidade já existe!</p>';
    } else {
        echo '<p>Criando tabela...</p>';
        
        $pdo->exec("
            CREATE TABLE aluno_modalidade (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aluno_id INT NOT NULL,
                modalidade_id INT NOT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE NULL,
                ativo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_aluno (aluno_id),
                INDEX idx_modalidade (modalidade_id),
                UNIQUE KEY unique_aluno_modalidade (aluno_id, modalidade_id, ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        echo '<p style="color: green;">✓ Tabela aluno_modalidade criada com sucesso!</p>';
    }
    
    echo '<h3>2. Verificando se a coluna modalidade_id existe em students</h3>';
    
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'modalidade_id'");
    $has_col = $stmt->fetch();
    
    if (!$has_col) {
        echo '<p>Adicionando coluna modalidade_id na tabela students...</p>';
        $pdo->exec("ALTER TABLE students ADD COLUMN modalidade_id INT NULL AFTER plano_atual_id");
        echo '<p style="color: green;">✓ Coluna modalidade_id adicionada!</p>';
    } else {
        echo '<p style="color: orange;">A coluna modalidade_id já existe.</p>';
    }
    
    // Reabilitar foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo '<hr>';
    echo '<h2 style="color: green;">✓ Tabela Criada!</h2>';
    echo '<p>Agora vou atualizar os formulários para permitir seleção de modalidades.</p>';
    echo '<p><a href="../dashboard.php" class="btn btn-primary">Ir para o Dashboard</a></p>';
    
} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo '<p style="color: red;">Erro: ' . $e->getMessage() . '</p>';
}
?>
