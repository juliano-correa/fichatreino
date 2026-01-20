<?php
/**
 * Script de criação da tabela financeiro
 * Execute este arquivo uma vez para criar a tabela no banco de dados
 */

require_once '../config/conexao.php';

echo "Criando tabela 'financeiro'...<br>";

$sql = "CREATE TABLE IF NOT EXISTS financeiro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gym_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    tipo ENUM('receita', 'despesa') NOT NULL,
    data DATE NOT NULL,
    modalidade_id INT NULL,
    aluno_id INT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gym_id (gym_id),
    INDEX idx_data (data),
    INDEX idx_tipo (tipo),
    INDEX idx_modalidade (modalidade_id),
    INDEX idx_aluno (aluno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    echo "Tabela 'financeiro' criada com sucesso!<br>";
    
    // Verificar se a tabela foi criada
    $stmt = $pdo->query("SHOW TABLES LIKE 'financeiro'");
    if ($stmt->rowCount() > 0) {
        echo "Verificação: Tabela existe no banco de dados.";
    } else {
        echo "ERRO: A tabela não foi criada!";
    }
} catch (PDOException $e) {
    echo "Erro ao criar tabela: " . $e->getMessage();
}
