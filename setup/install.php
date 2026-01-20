<?php
/**
 * INSTALADOR DO BANCO DE DADOS - Titanium Gym Manager
 * Para uso no InfinityFree
 * Acesse: https://fichaonline.gt.tc/setup/install.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Titanium Gym Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .install-card { background: white; border-radius: 15px; max-width: 600px; margin: 50px auto; padding: 30px; }
        .success-icon { font-size: 4rem; color: #198754; }
        .error-icon { font-size: 4rem; color: #dc3545; }
        .log-output { background: #1a1a2e; color: #00ff00; padding: 15px; border-radius: 8px; font-family: monospace; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="install-card">
        <h2 class="text-center mb-4"><i class="bi bi-dumbbell"></i> Titanium Gym Manager</h2>
        <h4 class="text-center mb-4">Instalação do Banco de Dados</h4>';

try {
    // Conectar ao banco
    $pdo = new PDO(
        'mysql:host=sql310.infinityfree.com;dbname=if0_40786753_titanium_gym;charset=utf8mb4',
        'if0_40786753',
        'Jota190876',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<div class="log-output mb-4">';
    echo "✓ Conectado ao banco de dados<br>";
    
    // ============================================
    // CRIAR TABELAS
    // ============================================
    
    // Tabela gyms
    echo "Criando tabela gyms...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS gyms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cnpj VARCHAR(20),
        telefone VARCHAR(20),
        whatsapp VARCHAR(20),
        email VARCHAR(100),
        endereco VARCHAR(255),
        cidade VARCHAR(100),
        estado VARCHAR(2),
        logo_url VARCHAR(255),
        logo_texto VARCHAR(100),
        pix_chave VARCHAR(100),
        pix_tipo VARCHAR(20) DEFAULT 'CPF',
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela gyms criada<br>";
    
    // Tabela users
    echo "Criando tabela users...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        senha VARCHAR(255) NOT NULL,
        papel ENUM('admin', 'instrutor', 'recepcionista') DEFAULT 'recepcionista',
        telefone VARCHAR(20),
        foto_url VARCHAR(255),
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        ultimo_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
        UNIQUE KEY unique_email_gym (email, gym_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela users criada<br>";
    
    // Tabela modalities
    echo "Criando tabela modalities...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS modalities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        nome VARCHAR(50) NOT NULL,
        descricao TEXT,
        cor VARCHAR(7) DEFAULT '#0d6efd',
        icone VARCHAR(50) DEFAULT 'dumbbell',
        ativa BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
        UNIQUE KEY unique_nome_gym (nome, gym_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela modalities criada<br>";
    
    // Tabela plans
    echo "Criando tabela plans...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        modalidade_id INT,
        nome VARCHAR(50) NOT NULL,
        descricao TEXT,
        preco DECIMAL(10, 2) NOT NULL,
        duracao_dias INT NOT NULL DEFAULT 30,
        ativo BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
        FOREIGN KEY (modalidade_id) REFERENCES modalities(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela plans criada<br>";
    
    // Tabela students
    echo "Criando tabela students...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        cpf VARCHAR(14),
        email VARCHAR(100),
        telefone VARCHAR(20) NOT NULL,
        telefone2 VARCHAR(20),
        data_nascimento DATE,
        genero ENUM('M', 'F', 'O'),
        endereco VARCHAR(255),
        cidade VARCHAR(100),
        contato_emergencia VARCHAR(100),
        telefone_emergencia VARCHAR(20),
        observacoes TEXT,
        foto_url VARCHAR(255),
        objetivo VARCHAR(50),
        nivel ENUM('iniciante', 'intermediario', 'avancado'),
        status ENUM('ativo', 'inativo', 'suspenso', 'cancelado') DEFAULT 'ativo',
        plano_atual_id INT,
        plano_validade DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
        FOREIGN KEY (plano_atual_id) REFERENCES plans(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela students criada<br>";
    
    // Tabela subscriptions
    echo "Criando tabela subscriptions...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        aluno_id INT NOT NULL,
        plano_id INT NOT NULL,
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        preco_pago DECIMAL(10, 2) NOT NULL,
        status ENUM('ativo', 'pausado', 'cancelado', 'expirado') DEFAULT 'ativo',
        renovacao_automatica BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
        FOREIGN KEY (aluno_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (plano_id) REFERENCES plans(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela subscriptions criada<br>";
    
    // Tabela transactions
    echo "Criando tabela transactions...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        aluno_id INT,
        inscricao_id INT,
        tipo ENUM('receita', 'despesa') NOT NULL,
        categoria VARCHAR(50),
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10, 2) NOT NULL,
        data_vencimento DATE NOT NULL,
        data_pagamento DATE,
        status ENUM('pendente', 'pago', 'cancelado', 'vencido') DEFAULT 'pendente',
        metodo_pagamento VARCHAR(50),
        observacoes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela transactions criada<br>";
    
    // Tabela presences
    echo "Criando tabela presences...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS presences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gym_id INT NOT NULL,
        aluno_id INT NOT NULL,
        data_presenca DATE NOT NULL,
        hora_checkin TIME NOT NULL,
        hora_saida TIME,
        modalidade VARCHAR(50),
        tipo ENUM('academia', 'aula', 'personal') DEFAULT 'academia',
        fonte ENUM('manual', 'qrcode', 'catraca', 'biometria') DEFAULT 'manual',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
        FOREIGN KEY (aluno_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela presences criada<br>";
    
    echo "</div>";
    
    // ============================================
    // INSERIR DADOS INICIAIS
    // ============================================
    
    echo '<div class="alert alert-info">Inserindo dados iniciais...</div>';
    
    // Inserir academia
    $pdo->exec("INSERT INTO gyms (id, nome, status) VALUES (1, 'Titanium Gym', 'ativo') ON DUPLICATE KEY UPDATE nome = 'Titanium Gym'");
    echo "✓ Academia criada<br>";
    
    // Inserir usuário admin
    $senha_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (id, gym_id, nome, email, senha, papel, status) VALUES (1, 1, 'Administrador', 'admin@titanium.com', '{$senha_hash}', 'admin', 'ativo') ON DUPLICATE KEY UPDATE nome = 'Administrador'");
    echo "✓ Usuário admin criado<br>";
    
    // Inserir modalidades
    $pdo->exec("INSERT INTO modalities (gym_id, nome, descricao, cor) VALUES 
        (1, 'Musculação', 'Treino com pesos', '#0d6efd'),
        (1, 'CrossFit', 'Treino funcional', '#dc3545'),
        (1, 'Spinning', 'Ciclismo indoor', '#198754'),
        (1, 'Yoga', 'Flexibilidade', '#6f42c1')
        ON DUPLICATE KEY UPDATE nome = VALUES(nome)");
    echo "✓ Modalidades criadas<br>";
    
    // Inserir planos
    $pdo->exec("INSERT INTO plans (gym_id, modalidade_id, nome, preco, duracao_dias) VALUES 
        (1, 1, 'Mensal Musculação', 99.90, 30),
        (1, 1, 'Trimestral Musculação', 269.70, 90),
        (1, 1, 'Anual Musculação', 958.80, 365),
        (1, 2, 'Mensal CrossFit', 149.90, 30)
        ON DUPLICATE KEY UPDATE nome = VALUES(nome)");
    echo "✓ Planos criados<br>";
    
    echo '<div class="alert alert-success text-center">
        <i class="bi bi-check-circle success-icon d-block mb-3"></i>
        <h4>INSTALAÇÃO CONCLUÍDA COM SUCESSO!</h4>
        <p>O banco de dados foi configurado corretamente.</p>
        <hr>
        <h5>Credenciais de Acesso:</h5>
        <table class="table table-bordered mb-3">
            <tr><td><strong>Email:</strong></td><td>admin@titanium.com</td></tr>
            <tr><td><strong>Senha:</strong></td><td>admin123</td></tr>
        </table>
        <a href="../dashboard.php" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right"></i> ACESSAR O SISTEMA
        </a>
    </div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">
        <i class="bi bi-x-circle error-icon d-block mb-3"></i>
        <h4>ERRO NA INSTALAÇÃO</h4>
        <p>' . $e->getMessage() . '</p>
    </div>';
    
    echo '<div class="log-output">';
    echo "ERRO: " . $e->getMessage() . "<br>";
    echo trace($e);
    echo "</div>";
}

echo '
    </div>
</div>
</body>
</html>';
