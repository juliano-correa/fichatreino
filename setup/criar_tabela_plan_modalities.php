<?php
/**
 * Script para criar a tabela de relacionamento Planos x Modalidades
 *钛Gym Manager
 */

require_once '../config/conexao.php';

try {
    // Criar a tabela de relacionamento plan_modalities
    $sql = "CREATE TABLE IF NOT EXISTS plan_modalities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plan_id INT NOT NULL,
        modalidade_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
        FOREIGN KEY (modalidade_id) REFERENCES modalities(id) ON DELETE CASCADE,
        UNIQUE KEY unique_plan_modalidade (plan_id, modalidade_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    
    echo "Tabela 'plan_modalities' criada com sucesso!<br>";
    
    // Migrar dados existentes da coluna modalidade_id para a nova tabela
    $stmt = $pdo->query("SELECT id, modalidade_id FROM plans WHERE modalidade_id IS NOT NULL");
    $planosComModalidade = $stmt->fetchAll();
    
    $migrados = 0;
    foreach ($planosComModalidade as $plano) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO plan_modalities (plan_id, modalidade_id) VALUES (?, ?)");
        $stmt->execute([$plano['id'], $plano['modalidade_id']]);
        $migrados++;
    }
    
    echo "{$migrados} registros migrados para a nova tabela.<br>";
    echo "Operação concluída com sucesso!";
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
