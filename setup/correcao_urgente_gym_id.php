<?php
/**
 * Correção Urgente - Adicionar coluna gym_id às tabelas de Agenda
 * Execute este arquivo para corrigir o erro imediatamente
 */

require_once '../config/conexao.php';

echo "<h2>Correção de Estrutura - Tabelas de Agenda</h2>";
echo "<hr>";

// Lista de correções a serem aplicadas
$correcoes = [];

try {
    // Verificar se tabela class_definitions existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'class_definitions'");
    if ($stmt->rowCount() > 0) {
        // Verificar se coluna gym_id existe
        $stmt = $pdo->query("SHOW COLUMNS FROM class_definitions LIKE 'gym_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE class_definitions ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
            $correcoes[] = "✅ Coluna 'gym_id' adicionada à tabela 'class_definitions'";
        } else {
            $correcoes[] = "ℹ️ Coluna 'gym_id' já existe na tabela 'class_definitions'";
        }
    } else {
        $correcoes[] = "❌ Tabela 'class_definitions' não existe! Execute a migração primeiro.";
    }
} catch (PDOException $e) {
    $correcoes[] = "❌ Erro em class_definitions: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'class_bookings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM class_bookings LIKE 'gym_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE class_bookings ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
            $correcoes[] = "✅ Coluna 'gym_id' adicionada à tabela 'class_bookings'";
        } else {
            $correcoes[] = "ℹ️ Coluna 'gym_id' já existe na tabela 'class_bookings'";
        }
    }
} catch (PDOException $e) {
    $correcoes[] = "❌ Erro em class_bookings: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'class_attendance'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM class_attendance LIKE 'gym_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE class_attendance ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
            $correcoes[] = "✅ Coluna 'gym_id' adicionada à tabela 'class_attendance'";
        } else {
            $correcoes[] = "ℹ️ Coluna 'gym_id' já existe na tabela 'class_attendance'";
        }
    }
} catch (PDOException $e) {
    $correcoes[] = "❌ Erro em class_attendance: " . $e->getMessage();
}

// Verificar tabelas de users e modalities (para garantir)
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'gym_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
        $correcoes[] = "✅ Coluna 'gym_id' adicionada à tabela 'users'";
    } else {
        $correcoes[] = "ℹ️ Coluna 'gym_id' já existe na tabela 'users'";
    }
} catch (PDOException $e) {
    $correcoes[] = "⚠️ Erro em users: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM modalities LIKE 'gym_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE modalities ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
        $correcoes[] = "✅ Coluna 'gym_id' adicionada à tabela 'modalities'";
    } else {
        $correcoes[] = "ℹ️ Coluna 'gym_id' já existe na tabela 'modalities'";
    }
} catch (PDOException $e) {
    $correcoes[] = "⚠️ Erro em modalities: " . $e->getMessage();
}

// Mostrar resultados
echo "<div style='font-family: monospace; font-size: 14px;'>";
foreach ($correcoes as $msg) {
    echo "<div style='padding: 8px; margin: 5px 0; background: #f8f9fa; border-radius: 4px;'>$msg</div>";
}
echo "</div>";

echo "<hr>";
echo "<h4>Status:</h4>";
echo "<a href='../agenda/turmas.php' class='btn btn-primary btn-lg'>Clique aqui para testar a página de Turmas</a>";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção de Banco - Titanium Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-wrench me-2"></i>Correção de Banco de Dados</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($correcoes)): ?>
                            <div class="alert <?= strpos(implode('', $correcoes), '❌') !== false ? 'alert-danger' : 'alert-success' ?>">
                                <strong>Correções Aplicadas:</strong>
                            </div>
                            <ul class="list-group mb-4">
                                <?php foreach ($correcoes as $msg): ?>
                                    <li class="list-group-item"><?= $msg ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="../agenda/turmas.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-repeat me-2"></i>Recarregar Página de Turmas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
