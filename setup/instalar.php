<?php
/**
 * Titanium Gym Manager - Script de Instalação Completa
 * Cria todas as tabelas e configurações iniciais do sistema
 */

require_once '../config/conexao.php';

$msg = '';
$tipo_msg = 'info';
$instalado = false;

// Verificar se já existe academia cadastrada
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'gyms'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM gyms");
        if ($stmt->fetchColumn() > 0) {
            $instalado = true;
        }
    }
} catch (PDOException $e) {
    // Tabela não existe, ok para instalação
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'instalar') {
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Drop todas as tabelas existentes (se houver)
        $tabelas_existentes = ['aluno_modalidade', 'workout_exercises', 'workouts', 'checkins', 'assessments', 'expense_categories', 'transactions', 'subscriptions', 'plans', 'modalities', 'students', 'users', 'gyms'];
        foreach ($tabelas_existentes as $tabela) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$tabela`");
            } catch (PDOException $e) {
                // Pode falhar se não existir
            }
        }
        
        // ==================== CRIAR TABELAS ====================
        
        // 1. Tabela gyms (ACADEMIAS)
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
        
        // 2. Tabela users (USUÁRIOS DO SISTEMA)
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                senha VARCHAR(255) NOT NULL,
                role ENUM('admin', 'instrutor', 'recepcao', 'aluno') DEFAULT 'aluno',
                student_id INT DEFAULT NULL,
                ativo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 3. Tabela students (ALUNOS)
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_gym_id (gym_id),
                INDEX idx_nome (nome),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 4. Tabela modalities (MODALIDADES)
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_gym_id (gym_id),
                INDEX idx_ativa (ativa)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 5. Tabela plans (PLANOS)
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_gym_id (gym_id),
                INDEX idx_modalidade (modalidade_id),
                INDEX idx_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 6. Tabela subscriptions (INSCRIÇÕES)
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_gym_id (gym_id),
                INDEX idx_aluno (aluno_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 7. Tabela transactions (FINANCEIRO)
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_gym_id (gym_id),
                INDEX idx_aluno (aluno_id),
                INDEX idx_status (status),
                INDEX idx_data_vencimento (data_vencimento)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 8. Tabela assessments (AVALIAÇÕES FÍSICAS)
        $pdo->exec("
            CREATE TABLE assessments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aluno_id INT NOT NULL,
                data_avaliacao DATE NOT NULL,
                peso DECIMAL(5,2) DEFAULT NULL,
                altura DECIMAL(5,2) DEFAULT NULL,
                imc DECIMAL(5,2) DEFAULT NULL,
                percentual_gordura DECIMAL(5,2) DEFAULT NULL,
                massa_magra DECIMAL(5,2) DEFAULT NULL,
                torax DECIMAL(5,2) DEFAULT NULL,
                cintura DECIMAL(5,2) DEFAULT NULL,
                quadril DECIMAL(5,2) DEFAULT NULL,
                braco_direito DECIMAL(5,2) DEFAULT NULL,
                braco_esquerdo DECIMAL(5,2) DEFAULT NULL,
                coxa_direita DECIMAL(5,2) DEFAULT NULL,
                coxa_esquerda DECIMAL(5,2) DEFAULT NULL,
                observacoes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_aluno (aluno_id),
                INDEX idx_data (data_avaliacao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 9. Tabela checkins (REGISTRO DE ENTRADA)
        $pdo->exec("
            CREATE TABLE checkins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aluno_id INT NOT NULL,
                data_checkin DATETIME NOT NULL,
                tipo ENUM('entrada', 'saida') DEFAULT 'entrada',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_aluno (aluno_id),
                INDEX idx_data (data_checkin)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 10. Tabela workouts (FICHAS DE TREINO)
        $pdo->exec("
            CREATE TABLE workouts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aluno_id INT NOT NULL,
                nome VARCHAR(100) DEFAULT NULL,
                descricao TEXT DEFAULT NULL,
                data_inicio DATE DEFAULT NULL,
                data_fim DATE DEFAULT NULL,
                status ENUM('ativo', 'inativo', 'concluido') DEFAULT 'ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_aluno (aluno_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 11. Tabela workout_exercises (EXERCÍCIOS DA FICHA)
        $pdo->exec("
            CREATE TABLE workout_exercises (
                id INT AUTO_INCREMENT PRIMARY KEY,
                workout_id INT NOT NULL,
                exercicio VARCHAR(100) NOT NULL,
                serie INT DEFAULT NULL,
                repeticao VARCHAR(20) DEFAULT NULL,
                carga DECIMAL(5,2) DEFAULT NULL,
                descanso_segundos INT DEFAULT NULL,
                observacao TEXT DEFAULT NULL,
                ordem INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_workout (workout_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 12. Tabela expense_categories (CATEGORIAS DE DESPESA)
        $pdo->exec("
            CREATE TABLE expense_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(50) NOT NULL,
                tipo ENUM('fixa', 'variavel') DEFAULT 'variavel',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 13. Tabela aluno_modalidade (VÍNCULO ALUNO-MODALIDADE)
        $pdo->exec("
            CREATE TABLE aluno_modalidade (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aluno_id INT NOT NULL,
                modalidade_id INT NOT NULL,
                data_inicio DATE NOT NULL,
                ativo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_vinculo (aluno_id, modalidade_id),
                INDEX idx_aluno (aluno_id),
                INDEX idx_modalidade (modalidade_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $msg = 'Banco de dados instalado com sucesso! Agora cadastre sua academia.';
        $tipo_msg = 'success';
        
    } catch (PDOException $e) {
        $msg = 'Erro na instalação: ' . $e->getMessage();
        $tipo_msg = 'danger';
    }
}

// Processar cadastro da academia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_academia') {
    $gym_name = trim($_POST['gym_name'] ?? '');
    $admin_name = trim($_POST['admin_name'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha_confirm = $_POST['senha_confirm'] ?? '';
    
    if (empty($gym_name) || empty($admin_name) || empty($email) || empty($telefone) || empty($senha)) {
        $msg = 'Por favor, preencha todos os campos.';
        $tipo_msg = 'danger';
    } elseif ($senha !== $senha_confirm) {
        $msg = 'As senhas não coincidem.';
        $tipo_msg = 'danger';
    } elseif (strlen($senha) < 6) {
        $msg = 'A senha deve ter pelo menos 6 caracteres.';
        $tipo_msg = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Criar academia
            $stmt = $pdo->prepare("INSERT INTO gyms (nome, telefone, whatsapp, email, status) VALUES (:nome, :telefone, :whatsapp, :email, 'ativo')");
            $stmt->execute([
                ':nome' => $gym_name,
                ':telefone' => $telefone,
                ':whatsapp' => $telefone,
                ':email' => $email
            ]);
            $gym_id = $pdo->lastInsertId();
            
            // Criar usuário admin
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nome, email, senha, role, ativo) VALUES (:nome, :email, :senha, 'admin', 1)");
            $stmt->execute([
                ':nome' => $admin_name,
                ':email' => $email,
                ':senha' => $senha_hash
            ]);
            
            // Criar modalidades padrão
            $modalidades = [
                ['nome' => 'Musculação', 'cor' => '#0d6efd', 'icone' => 'dumbbell'],
                ['nome' => 'Crossfit', 'cor' => '#dc2626', 'icone' => 'activity'],
                ['nome' => 'Zumba', 'cor' => '#ec4899', 'icone' => 'music'],
                ['nome' => 'Luta', 'cor' => '#ca8a04', 'icone' => 'zap']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO modalities (gym_id, nome, cor, icone, ativa) VALUES (:gym_id, :nome, :cor, :icone, 1)");
            foreach ($modalidades as $mod) {
                $stmt->execute([
                    ':gym_id' => $gym_id,
                    ':nome' => $mod['nome'],
                    ':cor' => $mod['cor'],
                    ':icone' => $mod['icone']
                ]);
            }
            
            // Criar categorias de despesa padrão
            $categorias = [
                ['nome' => 'Aluguel', 'tipo' => 'fixa'],
                ['nome' => 'Salários', 'tipo' => 'fixa'],
                ['nome' => 'Água', 'tipo' => 'fixa'],
                ['nome' => 'Luz', 'tipo' => 'fixa'],
                ['nome' => 'Internet', 'tipo' => 'fixa'],
                ['nome' => 'Equipamentos', 'tipo' => 'variavel'],
                ['nome' => 'Material de Limpeza', 'tipo' => 'variavel'],
                ['nome' => 'Outros', 'tipo' => 'variavel']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO expense_categories (nome, tipo) VALUES (:nome, :tipo)");
            foreach ($categorias as $cat) {
                $stmt->execute([':nome' => $cat['nome'], ':tipo' => $cat['tipo']]);
            }
            
            $pdo->commit();
            
            $msg = '<strong>Parabéns!</strong> Sua academia foi cadastrada com sucesso!<br>
                    <strong>Login:</strong> ' . $email . '<br>
                    <strong>Senha:</strong> A senha que você criou';
            $tipo_msg = 'success';
            $instalado = true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Erro ao cadastrar: ' . $e->getMessage();
            $tipo_msg = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Titanium Gym Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .install-card {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .card-header-custom i {
            font-size: 72px;
            margin-bottom: 15px;
        }
        .card-body {
            padding: 30px;
        }
        .tabela-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 6px;
            font-family: monospace;
            font-size: 14px;
        }
        .tabela-item i {
            color: #198754;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #0d6efd;
            color: white;
            border-radius: 50%;
            font-size: 14px;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="install-card">
        <div class="card-header-custom">
            <i class="bi bi-dumbbell"></i>
            <h2 class="mb-2">Titanium Gym Manager</h2>
            <p class="mb-0 opacity-75">Sistema de Gestão para Academias</p>
        </div>
        
        <div class="card-body">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $tipo_msg ?> alert-dismissible fade show" role="alert">
                    <?php if ($tipo_msg == 'success'): ?>
                        <i class="bi bi-check-circle me-2"></i>
                    <?php else: ?>
                        <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php endif; ?>
                    <?= $msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!$instalado): ?>
                <!-- Etapa 1: Instalar Banco -->
                <div class="mb-4">
                    <h5 class="mb-3"><span class="step-number">1</span>Instalar Banco de Dados</h5>
                    <p class="text-muted">Cria todas as tabelas necessárias para o sistema funcionar.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="acao" value="instalar">
                        <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Isso vai criar todas as tabelas do sistema. Continuar?');">
                            <i class="bi bi-database me-2"></i>Instalar Banco de Dados
                        </button>
                    </form>
                </div>
                
                <hr class="my-4">
                
                <!-- Etapa 2: Cadastrar Academia -->
                <div class="mb-4">
                    <h5 class="mb-3"><span class="step-number">2</span>Cadastrar Academia</h5>
                    <p class="text-muted">Preencha os dados abaixo para criar sua academia e o usuário administrador.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="acao" value="cadastrar_academia">
                        
                        <div class="mb-3">
                            <label for="gym_name" class="form-label fw-bold">Nome da Academia *</label>
                            <input type="text" class="form-control" id="gym_name" name="gym_name" placeholder="Ex: Titanium Gym Centro" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefone" class="form-label fw-bold">WhatsApp da Academia *</label>
                            <input type="text" class="form-control phone-input" id="telefone" name="telefone" placeholder="(11) 99999-9999" required>
                        </div>
                        
                        <hr class="my-3">
                        <h6 class="mb-3"><i class="bi bi-person-badge me-2"></i>Dados do Responsável</h6>
                        
                        <div class="mb-3">
                            <label for="admin_name" class="form-label fw-bold">Nome Completo *</label>
                            <input type="text" class="form-control" id="admin_name" name="admin_name" placeholder="Seu nome completo" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">E-mail *</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" required>
                            <small class="text-muted">Este e-mail será usado para login</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="senha" class="form-label fw-bold">Senha *</label>
                                <input type="password" class="form-control" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required minlength="6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="senha_confirm" class="form-label fw-bold">Confirmar Senha *</label>
                                <input type="password" class="form-control" id="senha_confirm" name="senha_confirm" placeholder="Digite a senha novamente" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Cadastrar Minha Academia
                        </button>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- Sistema Instalado -->
                <div class="text-center py-4">
                    <div class="success-icon mb-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 80px;"></i>
                    </div>
                    <h4 class="mb-3">Sistema Instalado com Sucesso!</h4>
                    <p class="text-muted mb-4">O Titanium Gym Manager está pronto para uso.</p>
                    
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Fazer Login
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-house me-2"></i>Ir para Home
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tabelas que serão criadas -->
            <hr class="my-4">
            <h6 class="mb-3"><i class="bi bi-table me-2"></i>Tabelas do Sistema</h6>
            <div class="row">
                <?php
                $tabelas = [
                    'gyms' => 'Academias',
                    'users' => 'Usuários',
                    'students' => 'Alunos',
                    'modalities' => 'Modalidades',
                    'plans' => 'Planos',
                    'subscriptions' => 'Inscrições',
                    'transactions' => 'Financeiro',
                    'assessments' => 'Avaliações',
                    'checkins' => 'Check-ins',
                    'workouts' => 'Fichas de Treino',
                    'workout_exercises' => 'Exercícios',
                    'expense_categories' => 'Categorias',
                    'aluno_modalidade' => 'Vínculos'
                ];
                ?>
                <div class="col-md-6">
                    <?php foreach (array_slice($tabelas, 0, 7) as $tab => $desc): ?>
                        <div class="tabela-item">
                            <i class="bi bi-check-circle"></i>
                            <span><?= $desc ?> (<?= $tab ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <?php foreach (array_slice($tabelas, 7) as $tab => $desc): ?>
                        <div class="tabela-item">
                            <i class="bi bi-check-circle"></i>
                            <span><?= $desc ?> (<?= $tab ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone mask
        document.querySelectorAll('.phone-input').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = this.value.replace(/\D/g, '');
                if (value.length > 11) value = value.substring(0, 11);
                if (value.length > 6) {
                    this.value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7)}`;
                } else if (value.length > 2) {
                    this.value = `(${value.substring(0, 2)}) ${value.substring(2)}`;
                } else {
                    this.value = value;
                }
            });
        });
    </script>
</body>
</html>
