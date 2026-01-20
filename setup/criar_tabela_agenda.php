<?php
// Titanium Gym Manager - Script de Criação das Tabelas de Agenda
// Execution: http://localhost/titanium-gym-php/setup/criar_tabela_agenda.php

require_once '../config/conexao.php';

echo '<h1>Setup - Tabelas de Agenda</h1>';
echo '<p>Este script criará as tabelas necessárias para o módulo de Agenda/Calendário.</p>';

try {
    // Tabela de Tipos de Aula
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS titanium_gym_tipos_aula (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            capacidade_maxima INT DEFAULT 20,
            duracao_minutos INT DEFAULT 60,
            cor_calendario VARCHAR(20) DEFAULT '#007bff',
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo '<p style="color: green;">✓ Tabela titanium_gym_tipos_aula criada com sucesso!</p>';
    
    // Tabela de Horários de Aulas (grade semanal)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS titanium_gym_horarios_aulas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo_aula_id INT NOT NULL,
            dia_semana TINYINT(1) NOT NULL COMMENT '1=Segunda, 2=Terça, ..., 7=Domingo',
            horario TIME NOT NULL,
            duracao_minutos INT DEFAULT 60,
            capacidade INT DEFAULT 20,
            instrutor_id INT DEFAULT NULL,
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tipo_aula_id) REFERENCES titanium_gym_tipos_aula(id) ON DELETE CASCADE,
            FOREIGN KEY (instrutor_id) REFERENCES titanium_gym_users(id) ON DELETE SET NULL,
            INDEX idx_dia_horario (dia_semana, horario),
            INDEX idx_ativo (ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo '<p style="color: green;">✓ Tabela titanium_gym_horarios_aulas criada com sucesso!</p>';
    
    // Tabela de Agendamentos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS titanium_gym_agendamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            horario_aula_id INT DEFAULT NULL COMMENT 'Se for aula coletiva',
            tipo_aula_id INT DEFAULT NULL COMMENT 'Se for aula avulsa ou treino',
            instrutor_id INT DEFAULT NULL,
            data_aula DATE NOT NULL,
            horario_inicio TIME NOT NULL,
            horario_fim TIME NOT NULL,
            tipo_agendamento ENUM('aula_coletiva', 'treino_personalizado', 'avaliacao') DEFAULT 'treino_personalizado',
            status ENUM('agendado', 'confirmado', 'realizado', 'cancelado', 'falta') DEFAULT 'agendado',
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (aluno_id) REFERENCES titanium_gym_users(id) ON DELETE CASCADE,
            FOREIGN KEY (horario_aula_id) REFERENCES titanium_gym_horarios_aulas(id) ON DELETE SET NULL,
            FOREIGN KEY (tipo_aula_id) REFERENCES titanium_gym_tipos_aula(id) ON DELETE SET NULL,
            FOREIGN KEY (instrutor_id) REFERENCES titanium_gym_users(id) ON DELETE SET NULL,
            INDEX idx_data_aula (data_aula),
            INDEX idx_aluno (aluno_id),
            INDEX idx_status (status),
            INDEX idx_instrutor (instrutor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo '<p style="color: green;">✓ Tabela titanium_gym_agendamentos criada com sucesso!</p>';
    
    // Tabela de Presenças
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS titanium_gym_presencas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agendamento_id INT NOT NULL,
            aluno_id INT NOT NULL,
            presente TINYINT(1) DEFAULT 0,
            horario_chegada TIME DEFAULT NULL,
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agendamento_id) REFERENCES titanium_gym_agendamentos(id) ON DELETE CASCADE,
            FOREIGN KEY (aluno_id) REFERENCES titanium_gym_users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_agendamento_aluno (agendamento_id, aluno_id),
            INDEX idx_presente (presente)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo '<p style="color: green;">✓ Tabela titanium_gym_presencas criada com sucesso!</p>';
    
    // Inserir tipos de aula padrão
    $tiposAula = [
        ['Musculação', 'Aula de musculação orientada', 30, 60, '#28a745'],
        ['Spinning', 'Aula de bike spinning', 15, 45, '#dc3545'],
        ['Funcional', 'Treino funcional em grupo', 20, 45, '#fd7e14'],
        ['Yoga', 'Aula de yoga e alongamento', 15, 60, '#20c997'],
        ['HIT', 'Treino intervalado de alta intensidade', 15, 40, '#6f42c1'],
        ['Boxe', 'Aula deboxing fitness', 12, 60, '#e83e8c'],
        ['Pilates', 'Aula de pilates', 10, 60, '#17a2b8'],
        ['Avaliação Física', 'Avaliação física personalizada', 5, 30, '#ffc107']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO titanium_gym_tipos_aula (nome, descricao, capacidade_maxima, duracao_minutos, cor_calendario) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($tiposAula as $aula) {
        try {
            $stmt->execute($aula);
        } catch (PDOException $e) {
            // Ignorar duplicatas
        }
    }
    echo '<p style="color: green;">✓ Tipos de aula padrão inseridos com sucesso!</p>';
    
    echo '<hr>';
    echo '<h2 style="color: green;">✓ Todas as tabelas de Agenda foram criadas com sucesso!</h2>';
    echo '<p><a href="../admin/index.php">Voltar ao Painel</a></p>';
    
} catch (PDOException $e) {
    echo '<p style="color: red;">Erro: ' . $e->getMessage() . '</p>';
    echo '<p>Código do erro: ' . $e->getCode() . '</p>';
}
?>
