<?php
/**
 * Script de Correção - Adicionar coluna gym_id às tabelas existentes
 * Execute este arquivo para corrigir o erro: "Unknown column 'gym_id' in 'where clause'"
 */

require_once '../config/conexao.php';

$success_messages = [];
$error_messages = [];

// Verificar e adicionar coluna gym_id na tabela class_definitions
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM class_definitions LIKE 'gym_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE class_definitions ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
        $success_messages[] = 'Coluna <strong>gym_id</strong> adicionada à tabela class_definitions';
        
        // Adicionar índice para gym_id
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_class_def_gym ON class_definitions(gym_id)");
        $success_messages[] = 'Índice para <strong>gym_id</strong> criado na tabela class_definitions';
    } else {
        $success_messages[] = 'Coluna <strong>gym_id</strong> já existe na tabela class_definitions';
    }
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao adicionar gym_id em class_definitions: ' . $e->getMessage();
}

// Verificar e adicionar coluna gym_id na tabela class_bookings
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM class_bookings LIKE 'gym_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE class_bookings ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
        $success_messages[] = 'Coluna <strong>gym_id</strong> adicionada à tabela class_bookings';
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookings_gym ON class_bookings(gym_id)");
        $success_messages[] = 'Índice para <strong>gym_id</strong> criado na tabela class_bookings';
    } else {
        $success_messages[] = 'Coluna <strong>gym_id</strong> já existe na tabela class_bookings';
    }
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao adicionar gym_id em class_bookings: ' . $e->getMessage();
}

// Verificar e adicionar coluna gym_id na tabela class_attendance
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM class_attendance LIKE 'gym_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE class_attendance ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
        $success_messages[] = 'Coluna <strong>gym_id</strong> adicionada à tabela class_attendance';
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_gym ON class_attendance(gym_id)");
        $success_messages[] = 'Índice para <strong>gym_id</strong> criado na tabela class_attendance';
    } else {
        $success_messages[] = 'Coluna <strong>gym_id</strong> já existe na tabela class_attendance';
    }
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao adicionar gym_id em class_attendance: ' . $e->getMessage();
}

// Verificar e adicionar coluna gym_id na tabela boxes se existir (caixas)
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'boxes'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM boxes LIKE 'gym_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE boxes ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
            $success_messages[] = 'Coluna <strong>gym_id</strong> adicionada à tabela boxes';
        } else {
            $success_messages[] = 'Coluna <strong>gym_id</strong> já existe na tabela boxes';
        }
    }
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Verificar e adicionar coluna gym_id na tabela caixas
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'caixas'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM caixas LIKE 'gym_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE caixas ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
            $success_messages[] = 'Coluna <strong>gym_id</strong> adicionada à tabela caixas';
        } else {
            $success_messages[] = 'Coluna <strong>gym_id</strong> já existe na tabela caixas';
        }
    }
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Verificar e adicionar coluna gym_id na tabela movimentacoes_caixa
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'movimentacoes_caixa'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM movimentacoes_caixa LIKE 'gym_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE movimentacoes_caixa ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
            $success_messages[] = 'Coluna <strong>gym_id</strong> adicionada à tabela movimentacoes_caixa';
        } else {
            $success_messages[] = 'Coluna <strong>gym_id</strong> já existe na tabela movimentacoes_caixa';
        }
    }
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Verificar e adicionar coluna gym_id na tabela financeiro
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'financeiro'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM financeiro LIKE 'gym_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE financeiro ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
            $success_messages[] = 'Coluna <strong>gym_id</strong> adicionada à tabela financeiro';
        } else {
            $success_messages[] = 'Coluna <strong>gym_id</strong> já existe na tabela financeiro';
        }
    }
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Verificar se as tabelas agenda existem
try {
    $tables = ['class_definitions', 'class_bookings', 'class_attendance'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            $error_messages[] = "Tabela <strong>{$table}</strong> não existe! Execute a migração primeiro.";
        } else {
            $success_messages[] = "Tabela <strong>{$table}</strong> encontrada no banco de dados";
        }
    }
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao verificar tabelas: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção de Colunas - Titanium Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .fix-card {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .card-header-custom {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .log-item {
            font-family: monospace;
            font-size: 13px;
            padding: 6px 10px;
            border-radius: 4px;
            margin-bottom: 4px;
        }
        .log-item.success { background: #d1e7dd; color: #0f5132; }
        .log-item.error { background: #f8d7da; color: #842029; }
    </style>
</head>
<body>
    <div class="fix-card">
        <div class="card-header-custom">
            <i class="bi bi-wrench-adjustable-circle fs-1"></i>
            <h4 class="mb-0">Correção de Estrutura</h4>
            <p class="mb-0 opacity-75">Titanium Gym Manager</p>
        </div>
        
        <div class="card-body p-4">
            <?php if (!empty($success_messages)): ?>
                <div class="alert alert-success d-flex align-items-center mb-3">
                    <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                    <div>
                        <strong>Operações Realizadas:</strong>
                        <span class="ms-2">(<?= count($success_messages) ?>)</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_messages)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-3">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                    <div>
                        <strong>Erros:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($error_messages as $msg): ?>
                                <li><?= $msg ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <h5 class="mb-3"><i class="bi bi-list me-2"></i>Log de Execução</h5>
            <div class="bg-light p-3 rounded mb-4" style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($success_messages as $msg): ?>
                    <div class="log-item success">
                        <i class="bi bi-check me-1"></i><?= $msg ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($error_messages as $msg): ?>
                    <div class="log-item error">
                        <i class="bi bi-x me-1"></i><?= $msg ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($error_messages)): ?>
                <div class="alert alert-info d-flex align-items-center mb-4">
                    <i class="bi bi-info-circle-fill fs-4 me-2"></i>
                    <div>
                        <strong>Correção Concluída!</strong><br>
                        As colunas gym_id foram adicionadas/verificadas nas tabelas do sistema.
                    </div>
                </div>
                
                <div class="d-flex gap-3 justify-content-center">
                    <a href="../agenda/turmas.php" class="btn btn-primary">
                        <i class="bi bi-people-group me-2"></i>Acessar Turmas
                    </a>
                    <a href="../agenda/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-calendar-event me-2"></i>Agenda
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="migracao_completa.php" class="btn btn-warning">
                        <i class="bi bi-database-gear me-2"></i>Executar Migração Completa
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
