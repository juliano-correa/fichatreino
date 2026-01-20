<?php
/**
 * Migração para adicionar colunas ausentes na tabela transactions
 * Corrige: Column not found 'observacoes' e outras colunas faltantes
 */

require_once '../config/conexao.php';

$msg = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['executar'])) {
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Verificar se a coluna observacoes já existe
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'observacoes'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN observacoes TEXT DEFAULT NULL AFTER forma_pagamento");
            $msg .= "✓ Coluna 'observacoes' adicionada com sucesso.<br>";
        } else {
            $msg .= "✓ Coluna 'observacoes' já existe.<br>";
        }
        
        // Verificar e adicionar coluna inscricao_id
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'inscricao_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN inscricao_id INT DEFAULT NULL AFTER aluno_id");
            $msg .= "✓ Coluna 'inscricao_id' adicionada com sucesso.<br>";
        } else {
            $msg .= "✓ Coluna 'inscricao_id' já existe.<br>";
        }
        
        // Verificar e adicionar coluna metodo_pagamento (pode ser que seja forma_pagamento)
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'metodo_pagamento'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN metodo_pagamento VARCHAR(50) DEFAULT NULL AFTER status");
            $msg .= "✓ Coluna 'metodo_pagamento' adicionada com sucesso.<br>";
        } else {
            $msg .= "✓ Coluna 'metodo_pagamento' já existe.<br>";
        }
        
        // Verificar e adicionar coluna enviado_whatsapp
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'enviado_whatsapp'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN enviado_whatsapp TINYINT(1) DEFAULT FALSE AFTER observacoes");
            $msg .= "✓ Coluna 'enviado_whatsapp' adicionada com sucesso.<br>";
        } else {
            $msg .= "✓ Coluna 'enviado_whatsapp' já existe.<br>";
        }
        
        // Verificar e adicionar coluna data_envio_whatsapp
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'data_envio_whatsapp'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN data_envio_whatsapp TIMESTAMP NULL DEFAULT NULL AFTER enviado_whatsapp");
            $msg .= "✓ Coluna 'data_envio_whatsapp' adicionada com sucesso.<br>";
        } else {
            $msg .= "✓ Coluna 'data_envio_whatsapp' já existe.<br>";
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $sucesso = true;
        $msg = "Migração executada com sucesso!<br>" . $msg;
        
    } catch (PDOException $e) {
        $msg = 'Erro na migração: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração - Corrigir Tabela Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .card {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .card-header-custom {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 16px 16px 0 0;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header-custom">
            <i class="bi bi-database-gear fs-1"></i>
            <h4 class="mb-0 mt-2">Migração - Tabela Transactions</h4>
            <p class="mb-0 opacity-75">Adicionar colunas ausentes</p>
        </div>
        <div class="card-body p-4">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $sucesso ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?= $sucesso ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= $msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Sobre esta Migração</h6>
                <p class="text-muted">
                    Esta migração adiciona as seguintes colunas que estão faltando na tabela <code>transactions</code>:
                </p>
                <ul class="text-muted">
                    <li><code>observacoes</code> - Para informações adicionais sobre a transação</li>
                    <li><code>inscricao_id</code> - Para vincular transação a uma inscrição</li>
                    <li><code>metodo_pagamento</code> - Método de pagamento utilizado</li>
                    <li><code>enviado_whatsapp</code> - Status do envio de notificação</li>
                    <li><code>data_envio_whatsapp</code> - Data do envio da notificação</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" class="btn btn-primary w-100 btn-lg" onclick="return confirm('Esta migração adicionará colunas ausentes na tabela transactions. Continuar?');">
                    <i class="bi bi-play-circle me-2"></i>Executar Migração
                </button>
            </form>
            
            <div class="mt-4 text-center">
                <a href="../index.php" class="text-muted">
                    <i class="bi bi-house me-1"></i>Voltar ao Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
