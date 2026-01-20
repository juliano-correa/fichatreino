<?php
/**
 * Script de Emergência - Corrigir Tabelas Corrompidas
 * Execute este script para corrigir tabelas com problemas
 */

require_once '../config/conexao.php';

$msg = '';
$tipo_msg = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Lista de tabelas que podem estar corrompidas
        $tabelas_problematicas = ['gyms', 'students', 'modalities', 'plans', 'titanium_gym_users'];
        
        foreach ($tabelas_problematicas as $tabela) {
            // Tentar dropar a tabela se existir (mesmo que corrompida)
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$tabela`");
            } catch (PDOException $e) {
                // Pode falhar, mas continuamos
            }
        }
        
        // Criar tabela gyms primeiro (é a mais importante)
        $pdo->exec("
            CREATE TABLE gyms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                cnpj VARCHAR(20) DEFAULT NULL,
                telefone VARCHAR(20) DEFAULT NULL,
                whatsapp VARCHAR(20) DEFAULT NULL,
                email VARCHAR(100) DEFAULT NULL,
                endereco VARCHAR(255) DEFAULT NULL,
                cidade VARCHAR(100) DEFAULT NULL,
                estado VARCHAR(2) DEFAULT NULL,
                status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Criar tabela de usuários
        $pdo->exec("
            CREATE TABLE titanium_gym_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                senha VARCHAR(255) NOT NULL,
                role ENUM('admin', 'instrutor', 'recepcao', 'aluno') DEFAULT 'aluno',
                student_id INT DEFAULT NULL,
                ativo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Criar tabela de alunos
        $pdo->exec("
            CREATE TABLE students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                gym_id INT NOT NULL,
                nome VARCHAR(100) NOT NULL,
                cpf VARCHAR(14) DEFAULT NULL,
                email VARCHAR(100) DEFAULT NULL,
                telefone VARCHAR(20) DEFAULT NULL,
                telefone2 VARCHAR(20) DEFAULT NULL,
                data_nascimento DATE DEFAULT NULL,
                genero ENUM('M', 'F', 'O') DEFAULT NULL,
                endereco VARCHAR(255) DEFAULT NULL,
                cidade VARCHAR(100) DEFAULT NULL,
                contato_emergencia VARCHAR(100) DEFAULT NULL,
                telefone_emergencia VARCHAR(20) DEFAULT NULL,
                observacoes TEXT DEFAULT NULL,
                objetivo ENUM('emagrecimento', 'hipertrofia', 'condicionamento', 'saude', 'competicao') DEFAULT NULL,
                nivel ENUM('iniciante', 'intermediario', 'avancado') DEFAULT NULL,
                status ENUM('ativo', 'inativo', 'suspenso') DEFAULT 'ativo',
                plano_atual_id INT DEFAULT NULL,
                plano_validade DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Criar tabela de modalidades
        $pdo->exec("
            CREATE TABLE modalities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                gym_id INT NOT NULL,
                nome VARCHAR(50) NOT NULL,
                descricao TEXT DEFAULT NULL,
                cor VARCHAR(7) DEFAULT '#0d6efd',
                icone VARCHAR(50) DEFAULT 'activity',
                ativa TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Criar tabela de planos
        $pdo->exec("
            CREATE TABLE plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                gym_id INT NOT NULL,
                modalidade_id INT DEFAULT NULL,
                nome VARCHAR(50) NOT NULL,
                descricao TEXT DEFAULT NULL,
                preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                duracao_dias INT NOT NULL DEFAULT 30,
                ativo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Criar tabela de relacionamento aluno-modalidade
        $pdo->exec("
            CREATE TABLE aluno_modalidade (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aluno_id INT NOT NULL,
                modalidade_id INT NOT NULL,
                data_inicio DATE NOT NULL,
                ativo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_vinculo (aluno_id, modalidade_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Criar tabela de inscrições
        $pdo->exec("
            CREATE TABLE subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                gym_id INT NOT NULL,
                aluno_id INT NOT NULL,
                plano_id INT NOT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE NOT NULL,
                preco_pago DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                status ENUM('ativo', 'cancelado', 'encerrado') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Criar tabela de transações
        $pdo->exec("
            CREATE TABLE transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                gym_id INT NOT NULL,
                aluno_id INT DEFAULT NULL,
                tipo ENUM('receita', 'despesa') NOT NULL,
                categoria VARCHAR(50) NOT NULL,
                descricao TEXT DEFAULT NULL,
                valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                data_vencimento DATE NOT NULL,
                data_pagamento DATE DEFAULT NULL,
                status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
                forma_pagamento VARCHAR(50) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $msg = 'Todas as tabelas foram recriadas com sucesso!';
        $tipo_msg = 'success';
        
    } catch (PDOException $e) {
        $msg = 'Erro: ' . $e->getMessage();
        $tipo_msg = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção de Tabelas - Titanium Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .setup-card {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header-custom {
            background: #dc3545;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .card-header-custom.success {
            background: #0d6efd;
        }
        .card-body {
            padding: 30px;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: #0d6efd;
            color: white;
            border-radius: 50%;
            font-weight: bold;
            flex-shrink: 0;
        }
        .step-content h6 {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="card-header-custom <?= $tipo_msg == 'success' ? 'success' : '' ?>">
            <i class="bi <?= $tipo_msg == 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle' ?> fs-1"></i>
            <h3 class="mb-0 mt-2">Correção de Tabelas</h3>
            <p class="mb-0 opacity-75">Titanium Gym Manager</p>
        </div>
        
        <div class="card-body">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $tipo_msg ?> alert-dismissible fade show" role="alert">
                    <i class="bi <?= $tipo_msg == 'success' ? 'bi-check-circle' : 'bi-x-circle' ?> me-2"></i>
                    <?= $msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="warning-box">
                <h5><i class="bi bi-exclamation-triangle me-2"></i>Atenção!</h5>
                <p class="mb-0">Este script vai:</p>
                <ul class="mb-0 mt-2">
                    <li>Remover tabelas corrompidas (se existirem)</li>
                    <li>Recriar todas as tabelas do sistema</li>
                    <li><strong>APAGAR TODOS OS DADOS</strong> do banco atual</li>
                </ul>
                <p class="mb-0 mt-2"><strong>Execute apenas se o sistema estiver apresentando erros de tabela!</strong></p>
            </div>
            
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h6>Tabelas que serão recriadas:</h6>
                    <ul class="mb-0 text-muted">
                        <li>gyms (academias)</li>
                        <li>titanium_gym_users (usuários)</li>
                        <li>students (alunos)</li>
                        <li>modalities (modalidades)</li>
                        <li>plans (planos)</li>
                        <li>aluno_modalidade (vínculos)</li>
                        <li>subscriptions (inscrições)</li>
                        <li>transactions (financeiro)</li>
                    </ul>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h6>Após executar:</h6>
                    <p class="mb-0">Você precisará fazer o cadastro inicial da academia novamente através do formulário de registro.</p>
                </div>
            </div>
            
            <hr class="my-4">
            
            <form method="POST">
                <button type="submit" class="btn btn-danger w-100 mb-3" onclick="return confirm('Tem certeza? Isso vai apagar todos os dados do banco!');">
                    <i class="bi bi-trash3 me-2"></i>Recriar Tabelas (APAGA DADOS)
                </button>
            </form>
            
            <div class="d-flex gap-2">
                <a href="../register.php" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-pencil-square me-2"></i>Cadastrar Academia
                </a>
                <a href="../login.php" class="btn btn-outline-secondary flex-grow-1">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
