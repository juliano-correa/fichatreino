<?php
/**
 * CORREÇÃO COMPLETA DAS TABELAS - Titanium Gym Manager
 * Para uso no InfinityFree
 * Acesse: https://fichaonline.gt.tc/setup/correcao_completa.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção Completa - Titanium Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .card { background: white; border-radius: 15px; max-width: 700px; margin: 50px auto; padding: 30px; }
        .success-icon { font-size: 4rem; color: #198754; }
        .error-icon { font-size: 4rem; color: #dc3545; }
        .log-output { background: #1a1a2e; color: #00ff00; padding: 15px; border-radius: 8px; font-family: monospace; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2 class="text-center mb-4"><i class="bi bi-dumbbell"></i> Titanium Gym Manager</h2>
        <h4 class="text-center mb-4">Correção Completa das Tabelas</h4>';

try {
    // Conectar ao banco
    $pdo = new PDO(
        'mysql:host=sql310.infinityfree.com;dbname=if0_40786753_titanium_gym;charset=utf8mb4',
        'if0_40786753',
        'Jota190876',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<div class="log-output mb-4">';
    echo "✓ Conectado ao banco de dados<br><hr>";
    
    $correcoes = 0;
    
    // ============================================
    // TABELA: gyms
    // ============================================
    echo "<strong>[gyms]</strong><br>";
    $stmt = $pdo->query("DESCRIBE gyms");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('logo_texto', $colunas)) {
        $pdo->exec("ALTER TABLE gyms ADD COLUMN logo_texto VARCHAR(100) AFTER logo_url");
        echo "  + logo_texto adicionada<br>";
        $correcoes++;
    }
    echo "  ✓ gyms OK<br><hr>";
    
    // ============================================
    // TABELA: users
    // ============================================
    echo "<strong>[users]</strong><br>";
    $stmt = $pdo->query("DESCRIBE users");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Adicionar coluna 'ativo' se não existir
    if (!in_array('ativo', $colunas)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN ativo ENUM('ativo', 'inativo') DEFAULT 'ativo' AFTER telefone");
        echo "  + coluna 'ativo' adicionada<br>";
        $correcoes++;
    }
    
    // Remover coluna 'status' se existir (conflito com 'ativo')
    if (in_array('status', $colunas)) {
        $pdo->exec("ALTER TABLE users DROP COLUMN status");
        echo "  - coluna 'status' removida (usando 'ativo')<br>";
        $correcoes++;
    }
    
    // Adicionar coluna 'ultimo_login' se não existir
    if (!in_array('ultimo_login', $colunas)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN ultimo_login TIMESTAMP NULL AFTER ativo");
        echo "  + coluna 'ultimo_login' adicionada<br>";
        $correcoes++;
    }
    
    echo "  ✓ users OK<br><hr>";
    
    // ============================================
    // TABELA: modalities
    // ============================================
    echo "<strong>[modalities]</strong><br>";
    $stmt = $pdo->query("DESCRIBE modalities");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('ativa', $colunas)) {
        $pdo->exec("ALTER TABLE modalities ADD COLUMN ativa BOOLEAN DEFAULT TRUE AFTER icone");
        echo "  + coluna 'ativa' adicionada<br>";
        $correcoes++;
    }
    echo "  ✓ modalities OK<br><hr>";
    
    // ============================================
    // TABELA: plans
    // ============================================
    echo "<strong>[plans]</strong><br>";
    $stmt = $pdo->query("DESCRIBE plans");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('ativo', $colunas)) {
        $pdo->exec("ALTER TABLE plans ADD COLUMN ativo BOOLEAN DEFAULT TRUE AFTER duracao_dias");
        echo "  + coluna 'ativo' adicionada<br>";
        $correcoes++;
    }
    echo "  ✓ plans OK<br><hr>";
    
    // ============================================
    // TABELA: students
    // ============================================
    echo "<strong>[students]</strong><br>";
    $stmt = $pdo->query("DESCRIBE students");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('status', $colunas)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN status ENUM('ativo', 'inativo', 'suspenso', 'cancelado') DEFAULT 'ativo' AFTER nivel");
        echo "  + coluna 'status' adicionada<br>";
        $correcoes++;
    }
    echo "  ✓ students OK<br><hr>";
    
    // ============================================
    // TABELA: subscriptions
    // ============================================
    echo "<strong>[subscriptions]</strong><br>";
    $stmt = $pdo->query("DESCRIBE subscriptions");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('status', $colunas)) {
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN status ENUM('ativo', 'pausado', 'cancelado', 'expirado') DEFAULT 'ativo' AFTER preco_pago");
        echo "  + coluna 'status' adicionada<br>";
        $correcoes++;
    }
    echo "  ✓ subscriptions OK<br><hr>";
    
    // ============================================
    // TABELA: transactions
    // ============================================
    echo "<strong>[transactions]</strong><br>";
    $stmt = $pdo->query("DESCRIBE transactions");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('status', $colunas)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN status ENUM('pendente', 'pago', 'cancelado', 'vencido') DEFAULT 'pendente' AFTER data_pagamento");
        echo "  + coluna 'status' adicionada<br>";
        $correcoes++;
    }
    
    if (!in_array('data_vencimento', $colunas)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN data_vencimento DATE NOT NULL AFTER valor");
        echo "  + coluna 'data_vencimento' adicionada<br>";
        $correcoes++;
    }
    echo "  ✓ transactions OK<br><hr>";
    
    // ============================================
    // TABELA: presences
    // ============================================
    echo "<strong>[presences]</strong><br>";
    $stmt = $pdo->query("DESCRIBE presences");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('fonte', $colunas)) {
        $pdo->exec("ALTER TABLE presences ADD COLUMN fonte ENUM('manual', 'qrcode', 'catraca', 'biometria') DEFAULT 'manual' AFTER tipo");
        echo "  + coluna 'fonte' adicionada<br>";
        $correcoes++;
    }
    echo "  ✓ presences OK<br><hr>";
    
    // ============================================
    // ATUALIZAR DADOS
    // ============================================
    echo "<strong>[ATUALIZANDO DADOS]</strong><br>";
    
    $pdo->exec("UPDATE users SET ativo = 'ativo' WHERE ativo IS NULL");
    echo "  ✓ Usuários atualizados<br>";
    
    $pdo->exec("UPDATE modalities SET ativa = 1 WHERE ativa IS NULL");
    echo "  ✓ Modalidades atualizadas<br>";
    
    $pdo->exec("UPDATE plans SET ativo = 1 WHERE ativo IS NULL");
    echo "  ✓ Planos atualizados<br>";
    
    echo "</div>";
    
    echo '<div class="alert alert-success text-center">
        <i class="bi bi-check-circle success-icon d-block mb-3"></i>
        <h4>CORREÇÃO CONCLUÍDA!</h4>
        <p>Total de correções realizadas: <strong>' . $correcoes . '</strong></p>
        <hr>
        <a href="../login.php" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right"></i> FAZER LOGIN
        </a>
    </div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">
        <i class="bi bi-x-circle error-icon d-block mb-3"></i>
        <h4>ERRO</h4>
        <p>' . $e->getMessage() . '</p>
    </div>';
}

echo '
    </div>
</div>
</body>
</html>';
