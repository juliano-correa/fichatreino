<?php
/**
 * CORREÇÃO URGENTE - Adicionar coluna 'ativo' na tabela users
 * Para uso no InfinityFree
 * Acesse: https://fichaonline.gt.tc/setup/corrigir_coluna_ativo.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção - Titanium Gym Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .card { background: white; border-radius: 15px; max-width: 600px; margin: 50px auto; padding: 30px; }
        .success-icon { font-size: 4rem; color: #198754; }
        .error-icon { font-size: 4rem; color: #dc3545; }
        .log-output { background: #1a1a2e; color: #00ff00; padding: 15px; border-radius: 8px; font-family: monospace; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2 class="text-center mb-4"><i class="bi bi-dumbbell"></i> Titanium Gym Manager</h2>
        <h4 class="text-center mb-4">Correção da Tabela Users</h4>';

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
    
    // ============================================
    // VERIFICAR E CORRIGIR TABELAS
    // ============================================
    
    // Verificar estrutura da tabela users
    echo "<strong>1. Verificando tabela users...</strong><br>";
    $stmt = $pdo->query("DESCRIBE users");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colunas encontradas: " . implode(', ', $colunas) . "<br>";
    
    // Verificar se a coluna 'ativo' existe
    if (in_array('ativo', $colunas)) {
        echo "✓ Coluna 'ativo' já existe<br>";
    } else {
        echo "✗ Coluna 'ativo' NÃO existe - ADICIONANDO...<br>";
        $pdo->exec("ALTER TABLE users ADD COLUMN ativo ENUM('ativo', 'inativo') DEFAULT 'ativo' AFTER telefone");
        echo "✓ Coluna 'ativo' adicionada com sucesso!<br>";
    }
    
    // Verificar se a coluna 'status' existe
    if (in_array('status', $colunas)) {
        echo "✓ Coluna 'status' existe<br>";
        // Remover a coluna status se ela existir (o sistema usa 'ativo')
        echo "⚠ Removendo coluna 'status' (usaremos 'ativo')...<br>";
        $pdo->exec("ALTER TABLE users DROP COLUMN status");
        echo "✓ Coluna 'status' removida<br>";
    } else {
        echo "✓ Coluna 'status' não existe (ok)<br>";
    }
    
    echo "<hr>";
    
    // Verificar estrutura da tabela gyms
    echo "<strong>2. Verificando tabela gyms...</strong><br>";
    $stmt = $pdo->query("DESCRIBE gyms");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colunas encontradas: " . implode(', ', $colunas) . "<br>";
    
    if (in_array('logo_texto', $colunas)) {
        echo "✓ Coluna 'logo_texto' existe<br>";
    } else {
        echo "✗ Adicionando coluna 'logo_texto'...<br>";
        $pdo->exec("ALTER TABLE gyms ADD COLUMN logo_texto VARCHAR(100) AFTER logo_url");
        echo "✓ Coluna 'logo_texto' adicionada<br>";
    }
    
    echo "<hr>";
    
    // ============================================
    // ATUALIZAR DADOS
    // ============================================
    
    echo "<strong>3. Atualizando dados...</strong><br>";
    
    // Atualizar coluna ativo para todos os usuários
    $pdo->exec("UPDATE users SET ativo = 'ativo' WHERE ativo IS NULL OR ativo = ''");
    echo "✓ Todos os usuários marcados como 'ativo'<br>";
    
    echo "</div>";
    
    echo '<div class="alert alert-success text-center">
        <i class="bi bi-check-circle success-icon d-block mb-3"></i>
        <h4>CORREÇÃO CONCLUÍDA!</h4>
        <p>As colunas foram corrigidas com sucesso.</p>
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
    
    echo '<div class="log-output">';
    echo "ERRO: " . $e->getMessage() . "<br>";
    echo "</div>";
}

echo '
    </div>
</div>
</body>
</html>';
