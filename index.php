<?php
/**
 * Titanium Gym Manager - Redirecionamento
 * Redireciona para o sistema de login ou dashboard
 */

session_start();

// Se já estiver logado, ir para dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Redirecionar para o novo sistema de login
header('Location: login.php');
exit;
