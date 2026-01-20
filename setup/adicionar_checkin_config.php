<?php
// Migration: Adicionar configuração de duração máxima de check-in
require_once '../config/conexao.php';

try {
    // Verificar se a coluna já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM gyms LIKE 'checkin_duracao_maxima'");
    $existe = $stmt->fetch();
    
    if (!$existe) {
        $pdo->exec("ALTER TABLE gyms ADD COLUMN checkin_duracao_maxima INT UNSIGNED DEFAULT NULL COMMENT 'Duração máxima do check-in em horas'");
        echo "Coluna 'checkin_duracao_maxima' adicionada com sucesso à tabela gyms!";
    } else {
        echo "A coluna 'checkin_duracao_maxima' já existe.";
    }
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
