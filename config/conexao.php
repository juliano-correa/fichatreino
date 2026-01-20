<?php
/**
 * Configuração do Banco de Dados - Titanium Gym Manager
 * Configurado para InfinityFree
 */

// ======================================================
// CONFIGURAÇÃO DO BANCO DE DADOS - INFINITEFREE
// ======================================================

$db_host = 'sql310.infinityfree.com';
$db_name = 'if0_40786753_titanium_gym';
$db_user = 'if0_40786753';
$db_pass = 'Jota190876';

// ======================================================
// CÓDIGO DE CONEXÃO
// ======================================================

// Opções do PDO
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, $options);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Definir timezone padrão
date_default_timezone_set('America/Sao_Paulo');
