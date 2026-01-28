<?php
/**
 * Titanium Gym Manager - Header com Sistema de Navegação Mobile
 * Implementação com Bootstrap 5 Offcanvas para mobile
 */
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Titanium Gym Manager - <?= $titulo_pagina ?? 'Dashboard' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --sidebar-width: 280px;
            --sidebar-bg: #293552;
            --sidebar-hover: #354263;
            --sidebar-active: #1E2638;
            --accent-color: #FFB950;
            --text-muted: #A8B2C3;
            --border-color: #354263;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            position: relative !important;
            height: auto !important;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            color: #fff;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar-brand i {
            font-size: 1.75rem;
            color: var(--accent-color);
        }
        
        .sidebar-brand img {
            max-height: 45px;
            max-width: 80px;
            object-fit: contain;
        }
        
        .sidebar-brand h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.25rem;
            color: #fff;
        }
        
        .sidebar-brand span {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: block;
        }
        
        /* Accordion Styles - Custom sem Bootstrap JS */
        .accordion-button {
            background: transparent !important;
            color: #fff !important;
            padding: 0.875rem 1.25rem;
            border: none !important;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            width: 100%;
            text-align: left;
        }
        
        .accordion-button::after {
            content: '';
            display: none;
        }
        
        .accordion-button:hover {
            background: var(--sidebar-hover) !important;
        }
        
        .accordion-button:not(.collapsed) {
            background: var(--sidebar-active) !important;
            color: var(--accent-color) !important;
        }
        
        .accordion-button .chevron {
            margin-left: auto;
            transition: transform 0.3s ease;
        }
        
        .accordion-button:not(.collapsed) .chevron {
            transform: rotate(180deg);
        }
        
        .accordion-item {
            background: transparent;
            border: none;
        }
        
        /* Accordion Content */
        .accordion-collapse {
            display: none;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .accordion-collapse.show {
            display: block;
        }
        
        .accordion-content {
            background: rgba(0, 0, 0, 0.15);
        }
        
        /* Submenu Styles */
        .accordion-content .nav-link {
            color: var(--text-muted) !important;
            padding: 0.75rem 1.25rem 0.75rem 3rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            min-height: 48px;
        }
        
        .accordion-content .nav-link:hover {
            color: #fff !important;
            background: var(--sidebar-hover);
        }
        
        .accordion-content .nav-link.active {
            color: var(--accent-color) !important;
            background: rgba(255, 185, 80, 0.15);
            border-left: 3px solid var(--accent-color);
        }
        
        .accordion-content .nav-link i {
            width: 1.25rem;
            text-align: center;
            font-size: 1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            min-height: 100vh;
            display: block;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 1rem 1.5rem;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #212529;
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: 300px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .sidebar .sidebar-close-btn {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-toggle {
                display: flex !important;
            }
            
            .user-dropdown {
                position: static;
            }
            
            .custom-dropdown-menu {
                position: fixed;
                top: auto;
                right: 0;
                bottom: 0;
                left: 0;
                width: 100%;
                max-width: 100%;
                margin: 0;
                border-radius: 16px 16px 0 0;
                z-index: 1050;
            }
        }
        
        .sidebar-close-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            z-index: 10;
        }
        
        .sidebar-close-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .mobile-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .mobile-toggle:hover {
            background: #e9ecef;
        }
        
        .mobile-toggle i {
            font-size: 1.5rem;
            color: #212529;
        }
        
        /* Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: var(--sidebar-bg);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* Custom Dropdown Styles */
        .custom-dropdown {
            position: relative;
        }
        
        .custom-dropdown-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            color: #212529;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            min-height: 44px;
        }
        
        .custom-dropdown-toggle:hover {
            background: #e9ecef;
        }
        
        .custom-dropdown-toggle .chevron {
            transition: transform 0.2s ease;
        }
        
        .custom-dropdown-toggle.show .chevron {
            transform: rotate(180deg);
        }
        
        .custom-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            z-index: 1000;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
            display: none;
        }
        
        .custom-dropdown-menu.show {
            display: block;
        }
        
        .custom-dropdown-menu a {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            color: #212529;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .custom-dropdown-menu a:hover {
            background: #f8f9fa;
            color: #0d6efd;
        }
        
        .custom-dropdown-menu a.text-danger:hover {
            color: #dc3545;
            background: #fff5f5;
        }
        
        .custom-dropdown-menu a i {
            margin-right: 0.5rem;
            width: 1rem;
            text-align: center;
        }
        
        .custom-dropdown-divider {
            height: 1px;
            margin: 0.5rem 0;
            background: #e9ecef;
            border: none;
        }
        
        /* Overlay para fechar dropdown */
        .dropdown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 999;
            display: none;
        }
        
        .dropdown-overlay.show {
            display: block;
        }
        
        /* Sidebar overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>

<?php
/**
 * Sistema de Navegação do Titanium Gym Manager
 */

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role = $_SESSION['user_role'] ?? '';
$user_name = $_SESSION['user_nome'] ?? 'Usuário';
$user_email = $_SESSION['user_email'] ?? '';

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

$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$current_file = basename($script_path);
$is_dashboard = $current_file === 'dashboard.php';
$is_alunos = strpos($script_path, '/alunos/') !== false;
$is_modalidades = strpos($script_path, '/modalidades/') !== false;
$is_planos = strpos($script_path, '/planos/') !== false;
$is_financeiro = strpos($script_path, '/financeiro/') !== false;
$is_caixas = strpos($script_path, '/financeiro/caixas/') !== false;
$is_relatorios = strpos($script_path, '/relatorios/') !== false;
$is_avaliacoes = strpos($script_path, '/avaliacoes/') !== false;
$is_fichas_treino = strpos($script_path, '/fichas_treino/') !== false;
$is_agenda = strpos($script_path, '/agenda/') !== false;
$is_turmas = strpos($script_path, '/agenda/turmas.php') !== false;
$is_turma_alunos = strpos($script_path, '/agenda/turma_alunos.php') !== false;
$is_presenca = strpos($script_path, '/agenda/presenca.php') !== false;
$is_checkin = strpos($script_path, '/checkin/') !== false;
$is_configuracoes = strpos($script_path, '/configuracoes/') !== false;
$is_admin_users = strpos($script_path, '/admin/users/') !== false;

$canAccess = function($permission) use ($user_role, $logged_in) {
    if (!$logged_in) return false;
    if ($user_role === 'admin') return true;
    
    $permissions = [
        'recepcao' => ['dashboard', 'alunos', 'modalidades', 'financeiro', 'agenda', 'fichas_treino', 'checkin', 'relatorios'],
        'instrutor' => ['dashboard', 'alunos', 'modalidades', 'avaliacoes', 'fichas_treino', 'agenda'],
        'aluno' => ['dashboard', 'fichas_treino', 'avaliacoes']
    ];
    
    return in_array($permission, $permissions[$user_role] ?? []);
};
?>

<?php if ($logged_in): ?>
<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
    <button class="sidebar-close-btn" onclick="closeSidebar()">
        <i class="bi bi-x-lg"></i>
    </button>
    
    <div class="sidebar-brand">
        <?php if (!empty($gym_logo)): ?>
            <img src="<?= $gym_logo ?>" alt="<?= sanitizar($gym_name) ?>" style="max-height: 40px; max-width: 60px; object-fit: contain;">
        <?php else: ?>
            <i class="bi bi-dumbbell"></i>
        <?php endif; ?>
        <div>
            <h4 style="font-size: 1.1rem;"><?= sanitizar($gym_name) ?></h4>
            <span><?= getRoleLabel($user_role) ?></span>
        </div>
    </div>
    
    <div class="accordion" id="sidebarAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" onclick="toggleAccordion(this)">
                    <i class="bi bi-house-door me-2"></i>
                    Principal
                    <i class="bi bi-chevron-down chevron ms-auto"></i>
                </button>
            </h2>
            <div id="collapsePrincipal" class="accordion-collapse collapse">
                <div class="accordion-content">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= $is_dashboard ? 'active' : '' ?>" href="<?= base_url('dashboard.php') ?>">
                                <i class="bi bi-speedometer2"></i>
                                Dashboard
                            </a>
                        </li>
                        <?php if ($canAccess('alunos')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_alunos ? 'active' : '' ?>" href="<?= base_url('alunos/index.php') ?>">
                                <i class="bi bi-people"></i>
                                Alunos
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($canAccess('modalidades')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_modalidades ? 'active' : '' ?>" href="<?= base_url('modalidades/index.php') ?>">
                                <i class="bi bi-activity"></i>
                                Modalidades
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($user_role !== 'aluno'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_planos ? 'active' : '' ?>" href="<?= base_url('planos/index.php') ?>">
                                <i class="bi bi-credit-card"></i>
                                Planos
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($canAccess('financeiro')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_financeiro ? 'active' : '' ?>" href="<?= base_url('financeiro/index.php') ?>">
                                <i class="bi bi-currency-dollar"></i>
                                Financeiro
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_caixas ? 'active' : '' ?>" href="<?= base_url('financeiro/caixas/index.php') ?>">
                                <i class="bi bi-safe2"></i>
                                Caixas
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" onclick="toggleAccordion(this)">
                    <i class="bi bi-person-arms-left me-2"></i>
                    Treinamento
                    <i class="bi bi-chevron-down chevron ms-auto"></i>
                </button>
            </h2>
            <div id="collapseTreinamento" class="accordion-collapse collapse">
                <div class="accordion-content">
                    <ul class="nav flex-column">
                        <?php if ($canAccess('avaliacoes')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_avaliacoes ? 'active' : '' ?>" href="<?= base_url('avaliacoes/index.php') ?>">
                                <i class="bi bi-clipboard-pulse"></i>
                                Avaliações Físicas
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link <?= $is_fichas_treino ? 'active' : '' ?>" href="<?= base_url('fichas_treino/index.php') ?>">
                                <i class="bi bi-clipboard-check"></i>
                                Fichas de Treino
                            </a>
                        </li>
                        
                        <?php if ($canAccess('agenda')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_agenda && !$is_turmas && !$is_turma_alunos && !$is_presenca ? 'active' : '' ?>" href="<?= base_url('agenda/index.php') ?>">
                                <i class="bi bi-calendar-event"></i>
                                Agenda
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_turmas ? 'active' : '' ?>" href="<?= base_url('agenda/turmas.php') ?>">
                                <i class="bi bi-collection"></i>
                                Turmas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_turma_alunos ? 'active' : '' ?>" href="<?= base_url('agenda/turma_alunos.php') ?>">
                                <i class="bi bi-person-plus"></i>
                                Alunos nas Turmas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_presenca ? 'active' : '' ?>" href="<?= base_url('agenda/presenca.php') ?>">
                                <i class="bi bi-clipboard-check"></i>
                                Controle de Presença
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php if ($user_role !== 'aluno'): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" onclick="toggleAccordion(this)">
                    <i class="bi bi-gear me-2"></i>
                    Administrativo
                    <i class="bi bi-chevron-down chevron ms-auto"></i>
                </button>
            </h2>
            <div id="collapseAdmin" class="accordion-collapse collapse">
                <div class="accordion-content">
                    <ul class="nav flex-column">
                        <?php if ($canAccess('relatorios')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_relatorios ? 'active' : '' ?>" href="<?= base_url('relatorios/index.php') ?>">
                                <i class="bi bi-bar-chart-line"></i>
                                Relatórios
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link <?= $is_checkin ? 'active' : '' ?>" href="<?= base_url('checkin/index.php') ?>">
                                <i class="bi bi-qr-code-scan"></i>
                                Check-in
                            </a>
                        </li>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_configuracoes ? 'active' : '' ?>" href="<?= base_url('configuracoes/index.php') ?>">
                                <i class="bi bi-sliders"></i>
                                Configurações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_admin_users ? 'active' : '' ?>" href="<?= base_url('admin/users/index.php') ?>">
                                <i class="bi bi-people-gear"></i>
                                Usuários
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="p-3 border-top" style="border-color: var(--border-color) !important;">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="bi bi-person-circle fs-4"></i>
            </div>
            <div class="flex-grow-1 ms-2 overflow-hidden">
                <div class="fw-medium text-truncate"><?= sanitizar($user_name) ?></div>
                <small class="text-muted text-truncate d-block"><?= sanitizar($user_email) ?></small>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Main Content -->
<main class="main-content" style="<?= !$logged_in ? 'margin-left: 0;' : '' ?>">
    <?php if ($logged_in): ?>
    <header class="page-header">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-toggle" id="sidebarToggle" type="button" aria-label="Abrir menu">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h1 class="page-title"><?= $titulo_pagina ?? 'Dashboard' ?></h1>
                <?php if (isset($subtitulo_pagina)): ?>
                    <nav aria-label="breadcrumb" class="d-none d-md-block">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= base_url('dashboard.php') ?>" class="text-decoration-none">Home</a></li>
                            <li class="breadcrumb-item active"><?= $subtitulo_pagina ?></li>
                        </ol>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="custom-dropdown user-dropdown" id="userDropdown">
                <button class="custom-dropdown-toggle" type="button" onclick="toggleUserDropdown(event)" aria-label="Menu do usuário">
                    <i class="bi bi-person-circle fs-4"></i>
                    <span class="d-none d-md-inline"><?= sanitizar($user_name) ?></span>
                    <i class="bi bi-chevron-down chevron small"></i>
                </button>
                <ul class="custom-dropdown-menu">
                    <li><a href="<?= base_url('configuracoes/index.php') ?>"><i class="bi bi-person"></i>Perfil</a></li>
                    <li><a href="<?= base_url('configuracoes/index.php') ?>"><i class="bi bi-gear"></i>Configurações</a></li>
                    <li><hr class="custom-dropdown-divider"></li>
                    <li><a class="text-danger" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i>Sair</a></li>
                </ul>
            </div>
        </div>
        
        <div class="dropdown-overlay" id="dropdownOverlay" onclick="closeAllDropdowns()"></div>
    </header>
    <?php endif; ?>
    
    <div class="fade-in">
