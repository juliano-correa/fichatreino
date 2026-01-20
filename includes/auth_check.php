<?php
/**
 * Verificação de Autenticação - Titanium Gym Manager
 * Incluir este arquivo no início de páginas protegidas
 */

session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Timeout de sessão (30 minutos)
$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

// Variáveis globais para uso nos arquivos
$user_id = $_SESSION['user_id'] ?? 0;
$user_nome = $_SESSION['user_nome'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$student_id = $_SESSION['student_id'] ?? null;

// Incluir conexão com banco de dados
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/functions.php';

// Verificar se usuário ainda existe e está ativo
try {
    $stmt = $pdo->prepare("SELECT id, nome, email, role, ativo FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['ativo'] != 1) {
        // Usuário não encontrado ou inativo, fazer logout
        session_destroy();
        header('Location: ../login.php?error=inactive');
        exit;
    }
    
    // Atualizar sessão com dados mais recentes
    $_SESSION['user_nome'] = $user['nome'];
    $_SESSION['user_role'] = $user['role'];
    
} catch (PDOException $e) {
    error_log("Erro ao verificar autenticação: " . $e->getMessage());
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Carregar sistema de permissões
require_once __DIR__ . '/permissions.php';
