-- ======================================================
-- SCRIPT DE MIGRAÇÃO - TABELAS FALTANTES
-- Titanium Gym Manager
-- ======================================================
-- Execute este script no phpMyAdmin do InfinityFree
-- ATENÇÃO: Este script APENAS CRIA tabelas, NÃO apaga dados!
-- ======================================================

-- Definir o banco de dados (descomente se necessário)
-- USE titanium_gym;

-- ======================================================
-- 1. TABELA DE AVALIAÇÕES FÍSICAS (assessments)
-- ======================================================

CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    instructor_id INT DEFAULT NULL,
    assessment_date DATE NOT NULL,
    weight DECIMAL(5,2) DEFAULT NULL COMMENT 'Peso em kg',
    height DECIMAL(5,2) DEFAULT NULL COMMENT 'Altura em cm',
    imc DECIMAL(5,2) DEFAULT NULL,
    body_fat DECIMAL(5,2) DEFAULT NULL,
    lean_mass DECIMAL(5,2) DEFAULT NULL,
    chest DECIMAL(5,2) DEFAULT NULL,
    waist DECIMAL(5,2) DEFAULT NULL,
    hips DECIMAL(5,2) DEFAULT NULL,
    right_thigh DECIMAL(5,2) DEFAULT NULL,
    left_thigh DECIMAL(5,2) DEFAULT NULL,
    right_bicep DECIMAL(5,2) DEFAULT NULL,
    left_bicep DECIMAL(5,2) DEFAULT NULL,
    right_calf DECIMAL(5,2) DEFAULT NULL,
    left_calf DECIMAL(5,2) DEFAULT NULL,
    objectives TEXT DEFAULT NULL,
    observations TEXT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- 2. TABELA DE CATEGORIAS DE DESPESA (expense_categories)
-- ======================================================

CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gym_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('fixed', 'variable') DEFAULT 'variable',
    description TEXT DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir categorias de despesa padrao (apenas se a tabela estiver vazia)
INSERT IGNORE INTO expense_categories (id, gym_id, name, type, description) VALUES
(1, 1, 'Aluguel', 'fixed', 'Custos com aluguel do espaco'),
(2, 1, 'Agua', 'fixed', 'Consumo de agua'),
(3, 1, 'Luz', 'fixed', 'Consumo de energia eletrica'),
(4, 1, 'Internet', 'fixed', 'Servicos de internet'),
(5, 1, 'Salarios', 'fixed', 'Pagamento de funcionarios'),
(6, 1, 'Equipamentos', 'variable', 'Manutencao e compra de equipamentos'),
(7, 1, 'Marketing', 'variable', 'Gastos com divulgacao'),
(8, 1, 'Outros', 'variable', 'Outras despesas diversas');

-- ======================================================
-- 3. TABELA DE ASSINATURAS (subscriptions)
-- ======================================================

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    plan_id INT DEFAULT NULL,
    modality_id INT DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'pix', 'check', 'transfer', 'other') DEFAULT NULL,
    status ENUM('active', 'inactive', 'pending', 'cancelled', 'expired') DEFAULT 'active',
    auto_renew TINYINT(1) DEFAULT 0,
    observations TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL,
    FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- 4. TABELA DE FICHAS DE TREINO (workouts)
-- ======================================================

CREATE TABLE IF NOT EXISTS workouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    instructor_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    objective VARCHAR(100) DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    observations TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- 5. TABELA DE EXERCÍCIOS NAS FICHAS (workout_exercises)
-- ======================================================

CREATE TABLE IF NOT EXISTS workout_exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workout_id INT NOT NULL,
    exercise VARCHAR(100) NOT NULL,
    muscle_group VARCHAR(50) DEFAULT NULL,
    sets INT DEFAULT 3,
    repetitions VARCHAR(20) DEFAULT NULL,
    `load` DECIMAL(5,2) DEFAULT NULL COMMENT 'Carga em kg',
    rest_seconds INT DEFAULT 60,
    observations TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- 6. TABELA DE PRESENÇA NAS AULAS (attendance)
-- ======================================================

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    class_date DATE NOT NULL,
    status ENUM('present', 'absent', 'justified') NOT NULL,
    arrival_time TIME DEFAULT NULL,
    departure_time TIME DEFAULT NULL,
    observations TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES class_enrollments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (enrollment_id, class_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================
-- ÍNDICES PARA OTIMIZAÇÃO
-- ======================================================

CREATE INDEX idx_assessments_student ON assessments(student_id);
CREATE INDEX idx_assessments_date ON assessments(assessment_date);
CREATE INDEX idx_expense_categories_gym ON expense_categories(gym_id);
CREATE INDEX idx_subscriptions_student ON subscriptions(student_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
CREATE INDEX idx_workouts_student ON workouts(student_id);
CREATE INDEX idx_workout_exercises_workout ON workout_exercises(workout_id);
CREATE INDEX idx_attendance_enrollment ON attendance(enrollment_id);

-- ======================================================
-- FIM DO SCRIPT DE MIGRAÇÃO
-- ======================================================

-- Verificação: Liste as novas tabelas
-- SHOW TABLES LIKE 'a%';
-- SHOW TABLES LIKE 'e%';
-- SHOW TABLES LIKE 's%';
-- SHOW TABLES LIKE 'w%';
