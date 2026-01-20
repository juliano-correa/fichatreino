<?php
/**
 * Migration: Criar tabelas do módulo de Agenda
 * Execute este arquivo no navegador: setup/migrar_agenda.php
 */

require_once '../config/conexao.php';

$success_messages = [];
$error_messages = [];

// Função para criar índice
function criarIndice($pdo, $nome_tabela, $nome_indice, $colunas) {
    try {
        $sql = "CREATE INDEX IF NOT EXISTS {$nome_indice} ON {$nome_tabela} ({$colunas})";
        $pdo->exec($sql);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

try {
    // Tabela 1: class_definitions (Definição das Turmas)
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
    $success_messages[] = 'Tabela class_definitions criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar class_definitions: ' . $e->getMessage();
}

try {
    // Tabela 2: class_bookings (Agendamentos dos Alunos)
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
    $success_messages[] = 'Tabela class_bookings criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar class_bookings: ' . $e->getMessage();
}

try {
    // Tabela 3: class_attendance (Registro de Presença)
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
    $success_messages[] = 'Tabela class_attendance criada com sucesso!';
} catch (PDOException $e) {
    $error_messages[] = 'Erro ao criar class_attendance: ' . $e->getMessage();
}

try {
    // Adicionar colunas foreign keys para class_definitions
    $pdo->exec("ALTER TABLE class_definitions 
        ADD CONSTRAINT fk_class_modality FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE SET NULL,
        ADD CONSTRAINT fk_class_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL");
    $success_messages[] = 'Foreign keys da class_definitions adicionadas!';
} catch (PDOException $e) {
    // Se as constraints já existirem, ignora
    $success_messages[] = 'FK de class_definitions (pode já existir): ' . $e->getMessage();
}

try {
    // Adicionar colunas foreign keys para class_bookings
    $pdo->exec("ALTER TABLE class_bookings 
        ADD CONSTRAINT fk_booking_class FOREIGN KEY (class_definition_id) REFERENCES class_definitions(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_booking_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE");
    $success_messages[] = 'Foreign keys da class_bookings adicionadas!';
} catch (PDOException $e) {
    $success_messages[] = 'FK de class_bookings (pode já existir): ' . $e->getMessage();
}

try {
    // Adicionar colunas foreign keys para class_attendance
    $pdo->exec("ALTER TABLE class_attendance 
        ADD CONSTRAINT fk_attendance_booking FOREIGN KEY (booking_id) REFERENCES class_bookings(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_attendance_user FOREIGN KEY (checked_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
    $success_messages[] = 'Foreign keys da class_attendance adicionadas!';
} catch (PDOException $e) {
    $success_messages[] = 'FK de class_attendance (pode já existir): ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - Módulo de Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-calendar-check me-2"></i>Migration - Módulo de Agenda
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_messages)): ?>
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle me-2"></i>Sucesso!</h5>
                                <ul class="mb-0">
                                    <?php foreach ($success_messages as $msg): ?>
                                        <li><?= $msg ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_messages)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="bi bi-x-circle me-2"></i>Erros!</h5>
                                <ul class="mb-0">
                                    <?php foreach ($error_messages as $msg): ?>
                                        <li><?= $msg ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($success_messages) && empty($error_messages)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Nenhuma operação foi executada. Acesse este arquivo para criar as tabelas do módulo de Agenda.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5>Tabelas Criadas:</h5>
                                <ul class="mb-0">
                                    <li><strong>class_definitions</strong> - Definição das turmas/aulas recorrentes</li>
                                    <li><strong>class_bookings</strong> - Agendamentos dos alunos</li>
                                    <li><strong>class_attendance</strong> - Registro de presença</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
                            </a>
                            <a href="teste_avancado_financeiro.php" class="btn btn-outline-primary">
                                <i class="bi bi-database me-2"></i>Verificar Banco
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
