<?php
// Login - Titanium Gym Manager

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

$titulo_pagina = 'Login';

if (session_status() === PHP_SESSION_NONE) {
    // Configurações de segurança para cookies de sessão
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Se estiver em HTTPS, habilitar Secure e SameSite=None para compatibilidade cross-browser
    $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    session_start();
}

$base_path = dirname(__FILE__);
$config_path = $base_path . '/config/conexao.php';
$functions_path = $base_path . '/config/functions.php';

if (!file_exists($config_path)) {
    die("Erro: Arquivo de configuração não encontrado em: $config_path");
}

try {
    require_once $config_path;
    require_once $functions_path;
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = isset($_GET['logout']) ? 'Logout realizado com sucesso.' : '';

$gym_name = 'Titanium Gym';
$gym_logo = null;

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM gyms LIKE 'logo_path'");
    $has_logo_column = $stmt->fetch() !== false;
    
    if ($has_logo_column) {
        $stmt = $pdo->query("SELECT nome, logo_path, logo_texto FROM gyms ORDER BY id LIMIT 1");
        $gym = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($gym) {
            $gym_name = $gym['logo_texto'] ?: $gym['nome'];
            $gym_logo = $gym['logo_path'];
        }
    } else {
        $stmt = $pdo->query("SELECT nome FROM gyms ORDER BY id LIMIT 1");
        $gym = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($gym) {
            $gym_name = $gym['nome'];
        }
    }
} catch (PDOException $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $error = 'Por favor, preencha e-mail e senha.';
    } else {
        try {
            $sql = "SELECT * FROM users WHERE email = :email AND ativo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($senha, $user['senha'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['student_id'] = $user['student_id'] ?? null;
                $_SESSION['gym_id'] = $user['gym_id'] ?? 1;
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'E-mail ou senha inválidos.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao tentar fazer login: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            position: relative;
        }
        
        .login-wrapper {
            width: 100%;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 420px;
            width: 100%;
            padding: 40px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo img {
            max-width: 250px;
            max-height: 100px;
            object-fit: contain;
        }
        
        .login-logo .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin: 10px 0 0;
        }
        
        .login-logo .logo-subtext {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .login-logo .default-icon {
            font-size: 64px;
            color: #0d6efd;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            min-height: 48px;
            font-size: 16px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .input-group-text {
            min-height: 48px;
            padding: 0 15px;
        }
        
        .btn-primary {
            padding: 14px;
            border-radius: 8px;
            font-weight: 500;
            min-height: 48px;
            font-size: 16px;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        /* Mobile Styles */
        @media (max-width: 575.98px) {
            body {
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                padding: 1rem;
            }
            
            .login-wrapper {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 2rem;
            }
            
            .login-card {
                padding: 1.5rem;
                border-radius: 1rem;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            }
            
            .login-logo {
                margin-bottom: 1.5rem;
            }
            
            .login-logo img {
                max-width: 180px;
                max-height: 80px;
            }
            
            .login-logo .logo-text {
                font-size: 1.25rem;
            }
            
            .login-logo .default-icon {
                font-size: 48px;
            }
            
            .form-control {
                padding: 14px 16px;
                font-size: 16px;
            }
            
            .btn-primary {
                padding: 16px;
                font-size: 1rem;
            }
            
            .input-group {
                border-radius: 8px;
                overflow: hidden;
            }
            
            .input-group-text {
                font-size: 1.1rem;
            }
            
            .mb-3 {
                margin-bottom: 1.25rem !important;
            }
            
            .form-footer {
                margin-top: 1.5rem;
            }
        }
        
        /* Tablet Styles */
        @media (min-width: 576px) and (max-width: 991.98px) {
            .login-card {
                padding: 2.5rem;
            }
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            animation: fadeInUp 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <?php if (!empty($gym_logo)): ?>
                    <img src="<?= $gym_logo ?>" alt="<?= sanitizar($gym_name) ?>">
                <?php else: ?>
                    <i class="bi bi-building default-icon"></i>
                <?php endif; ?>
                <h2 class="logo-text"><?= sanitizar($gym_name) ?></h2>
                <p class="logo-subtext">Sistema de Gestão</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" required autocomplete="email" inputmode="email">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Sua senha" required autocomplete="current-password">
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                    </button>
                </div>
            </form>
            
            <div class="form-footer">
                <small class="text-muted">© 2024 <?= sanitizar($gym_name) ?></small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile-specific improvements
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on email field
            const emailField = document.getElementById('email');
            if (emailField) emailField.focus();
            
            // Prevent zoom on input focus (iOS)
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    if (window.innerWidth < 576) {
                        const card = document.querySelector('.login-card');
                        if (card) card.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
            
            console.log('Titanium Gym Login - Mobile Optimized');
        });
    </script>
</body>
</html>
