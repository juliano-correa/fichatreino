<?php
/**
 * Logout - Titanium Gym Manager
 * Encerra a sessão do usuário
 */

session_start();

// Incluir funções necessárias
require_once 'config/functions.php';

// Limpar todas as variáveis de sessão
$_SESSION = [];

// Destruir a sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirecionar para login
redirecionar('index.php');
