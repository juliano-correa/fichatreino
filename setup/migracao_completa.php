<?php
/**
 * Migration Completa - Titanium Gym Manager
 * Cria todas as tabelas necessárias para o sistema funcionar corretamente
 * Execute este arquivo no navegador: setup/migracao_completa.php
 */

require_once '../config/conexao.php';

$success_messages = [];
$error_messages = [];

// Função auxiliar para criar índices
function criarIndice($pdo, $nome_tabela, $nome_indice, $colunas) {
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS {$nome_indice} ON {$nome_tabela} ({$colunas})");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

try {
    // ==========================================
    // Tabela 1: financeiro (Financeiro Principal)
    // ==========================================
    $sql = "
    CREATE TABLE IF NOT EXISTS financeiro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        tipo ENUM('receita', 'despesa') NOT NULL,
        data DATE NOT NULL,
        modalidade_id INT NULL,
        aluno_id INT NULL,
        status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pago',
        forma_pagamento VARCHAR(50) DEFAULT NULL,
        observacoes TEXT NULL,
        caixa_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gym_id (gym_id),
        INDEX idx_data (data),
        INDEX idx_tipo (tipo),
        INDEX idx_status (status),
        INDEX idx_modalidade (modalidade_id),
        INDEX idx_aluno (aluno_id),
        INDEX idx_caixa (caixa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    $success_messages[] = 'Tabela <strong>financeiro</strong> criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar tabela financeiro: ' . $e->getMessage();
}

try {
    // ==========================================
    // Tabela 2: caixas (Gestão de Caixas)
    // ==========================================
    $sql = "
    CREATE TABLE IF NOT EXISTS caixas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        tipo ENUM('principal', 'secundario', 'pix', 'dinheiro', 'cartao') DEFAULT 'principal',
        status ENUM('aberto', 'fechado') DEFAULT 'fechado',
        saldo_inicial DECIMAL(10,2) DEFAULT 0.00,
        saldo_atual DECIMAL(10,2) DEFAULT 0.00,
        usuario_abertura INT DEFAULT NULL,
        usuario_fechamento INT DEFAULT NULL,
        data_abertura DATETIME DEFAULT NULL,
        data_fechamento DATETIME DEFAULT NULL,
        observacoes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gym_id (gym_id),
        INDEX idx_status (status),
        INDEX idx_tipo (tipo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    $success_messages[] = 'Tabela <strong>caixas</strong> criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar tabela caixas: ' . $e->getMessage();
}

try {
    // ==========================================
    // Tabela 3: movimentacoes_caixa (Movimentações do Caixa)
    // ==========================================
    $sql = "
    CREATE TABLE IF NOT EXISTS movimentacoes_caixa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        caixa_id INT NOT NULL,
        tipo ENUM('entrada', 'saida') NOT NULL,
        categoria VARCHAR(50) NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        forma_pagamento VARCHAR(50) DEFAULT NULL,
        referencia_id INT DEFAULT NULL,
        referencia_tipo VARCHAR(50) DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_gym_id (gym_id),
        INDEX idx_caixa (caixa_id),
        INDEX idx_tipo (tipo),
        INDEX idx_categoria (categoria),
        INDEX idx_data (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    $success_messages[] = 'Tabela <strong>movimentacoes_caixa</strong> criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar tabela movimentacoes_caixa: ' . $e->getMessage();
}

try {
    // ==========================================
    // Tabela 4: class_definitions (Definição das Turmas)
    // ==========================================
    $sql = "
    CREATE TABLE IF NOT EXISTS class_definitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        modality_id INT NULL,
        instructor_id INT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        day_of_week TINYINT NOT NULL COMMENT '0=Domingo, 1=Segunda, ..., 6=Sábado',
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        max_capacity INT DEFAULT 20,
        color_hex VARCHAR(7) DEFAULT '#0d6efd',
        active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gym_id (gym_id),
        INDEX idx_modality_id (modality_id),
        INDEX idx_instructor_id (instructor_id),
        INDEX idx_day_time (day_of_week, start_time, end_time),
        INDEX idx_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    $success_messages[] = 'Tabela <strong>class_definitions</strong> (Turmas) criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar tabela class_definitions: ' . $e->getMessage();
}

try {
    // ==========================================
    // Tabela 5: class_bookings (Agendamentos dos Alunos)
    // ==========================================
    $sql = "
    CREATE TABLE IF NOT EXISTS class_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        class_definition_id INT NOT NULL,
        student_id INT NOT NULL,
        booking_date DATE NOT NULL,
        status ENUM('confirmed', 'canceled', 'waitlist') DEFAULT 'confirmed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gym_id (gym_id),
        INDEX idx_class_def (class_definition_id),
        INDEX idx_student (student_id),
        INDEX idx_booking_date (booking_date),
        INDEX idx_status (status),
        UNIQUE KEY uk_booking (class_definition_id, student_id, booking_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    $success_messages[] = 'Tabela <strong>class_bookings</strong> (Agendamentos) criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar tabela class_bookings: ' . $e->getMessage();
}

try {
    // ==========================================
    // Tabela 6: class_attendance (Registro de Presença)
    // ==========================================
    $sql = "
    CREATE TABLE IF NOT EXISTS class_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        booking_id INT NOT NULL,
        class_definition_id INT NOT NULL,
        student_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        present TINYINT DEFAULT 0 COMMENT '0=Ausente, 1=Presente',
        checked_in_at DATETIME NULL,
        checked_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_gym_id (gym_id),
        INDEX idx_booking (booking_id),
        INDEX idx_student (student_id),
        INDEX idx_attendance_date (attendance_date),
        UNIQUE KEY uk_attendance (booking_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    $success_messages[] = 'Tabela <strong>class_attendance</strong> (Presenças) criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar tabela class_attendance: ' . $e->getMessage();
}

// Adicionar Foreign Keys (com tratamento de erros para quando já existem)
try {
    $pdo->exec("ALTER TABLE class_definitions 
        ADD CONSTRAINT fk_class_def_modality FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE SET NULL,
        ADD CONSTRAINT fk_class_def_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL");
    $success_messages[] = 'Foreign Keys de <strong>class_definitions</strong> adicionadas!';
} catch (PDOException $e) {
    $success_messages[] = 'Foreign Keys de class_definitions: ' . $e->getMessage();
}

try {
    $pdo->exec("ALTER TABLE class_bookings 
        ADD CONSTRAINT fk_booking_class_def FOREIGN KEY (class_definition_id) REFERENCES class_definitions(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_booking_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE");
    $success_messages[] = 'Foreign Keys de <strong>class_bookings</strong> adicionadas!';
} catch (PDOException $e) {
    $success_messages[] = 'Foreign Keys de class_bookings: ' . $e->getMessage();
}

try {
    $pdo->exec("ALTER TABLE class_attendance 
        ADD CONSTRAINT fk_attendance_booking FOREIGN KEY (booking_id) REFERENCES class_bookings(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_attendance_user FOREIGN KEY (checked_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
    $success_messages[] = 'Foreign Keys de <strong>class_attendance</strong> adicionadas!';
} catch (PDOException $e) {
    $success_messages[] = 'Foreign Keys de class_attendance: ' . $e->getMessage();
}

try {
    $pdo->exec("ALTER TABLE movimentacoes_caixa 
        ADD CONSTRAINT fk_mov_caixa FOREIGN KEY (caixa_id) REFERENCES caixas(id) ON DELETE CASCADE");
    $success_messages[] = 'Foreign Key de <strong>movimentacoes_caixa</strong> adicionada!';
} catch (PDOException $e) {
    $success_messages[] = 'Foreign Key de movimentacoes_caixa: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Completa - Titanium Gym Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
        }
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .migration-card {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .card-header-custom i {
            font-size: 64px;
            margin-bottom: 15px;
        }
        .table-check {
            font-family: monospace;
            font-size: 13px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table-check i.success { color: var(--success-color); }
        .table-check i.error { color: var(--danger-color); }
        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="migration-card">
        <div class="card-header-custom">
            <i class="bi bi-database-gear"></i>
            <h3 class="mb-2">Migration Completa</h3>
            <p class="mb-0 opacity-75">Titanium Gym Manager - Tabelas do Sistema</p>
        </div>
        
        <div class="card-body p-4">
            <?php if (!empty($success_messages)): ?>
                <div class="alert alert-success d-flex align-items-center mb-4">
                    <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                    <div>
                        <strong>Migração executada com sucesso!</strong>
                        <span class="ms-2">(<?= count($success_messages) ?> operações)</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_messages)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                    <div>
                        <strong>Erros encontrados:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($error_messages as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <h5 class="mb-3"><i class="bi bi-table me-2"></i>Tabelas Criadas/Verificadas</h5>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="table-check">
                        <i class="bi bi-check-circle-fill success"></i>
                        <span><strong>financeiro</strong> - Transações financeiras</span>
                    </div>
                    <div class="table-check">
                        <i class="bi bi-check-circle-fill success"></i>
                        <span><strong>caixas</strong> - Gestão de caixas</span>
                    </div>
                    <div class="table-check">
                        <i class="bi bi-check-circle-fill success"></i>
                        <span><strong>movimentacoes_caixa</strong> - Movimentações</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-check">
                        <i class="bi bi-check-circle-fill success"></i>
                        <span><strong>class_definitions</strong> - Turmas/Aulas</span>
                    </div>
                    <div class="table-check">
                        <i class="bi bi-check-circle-fill success"></i>
                        <span><strong>class_bookings</strong> - Agendamentos</span>
                    </div>
                    <div class="table-check">
                        <i class="bi bi-check-circle-fill success"></i>
                        <span><strong>class_attendance</strong> - Presenças</span>
                    </div>
                </div>
            </div>
            
            <h5 class="mb-3"><i class="bi bi-list-check me-2"></i>Log de Operações</h5>
            <div class="bg-light p-3 rounded mb-4" style="max-height: 200px; overflow-y: auto;">
                <ul class="mb-0 list-unstyled">
                    <?php foreach ($success_messages as $msg): ?>
                        <li class="mb-1"><i class="bi bi-check text-success me-2"></i><?= $msg ?></li>
                    <?php endforeach; ?>
                    <?php foreach ($error_messages as $msg): ?>
                        <li class="mb-1"><i class="bi bi-x text-danger me-2"></i><?= $msg ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php if (empty($error_messages)): ?>
                <div class="alert alert-info d-flex align-items-center mb-4">
                    <i class="bi bi-info-circle-fill fs-4 me-2"></i>
                    <div>
                        <strong>Próximos Passos:</strong>
                        <ol class="mb-0 mt-1">
                            <li>Acesse o módulo Agenda no menu lateral</li>
                            <li>Cadastre suas turmas em "Gerenciar Turmas"</li>
                            <li>Comece a utilizar o sistema de agendamento</li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="../index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-house me-2"></i>Dashboard
                </a>
                <a href="../agenda/index.php" class="btn btn-primary">
                    <i class="bi bi-calendar-event me-2"></i>Acessar Agenda
                </a>
                <a href="../financeiro/index.php" class="btn btn-success">
                    <i class="bi bi-currency-dollar me-2"></i>Financeiro
                </a>
            </div>
        </div>
    </div>
</body>
</html>
