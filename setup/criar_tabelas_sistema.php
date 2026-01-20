<?php
/**
 * Script de Setup - Criar Tabelas do Sistema
 * Execute este script para criar as tabelas necessárias no banco de dados
 */

require_once '../config/conexao.php';

echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Titanium Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 40px 20px; }
        .setup-card { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .card-header { background: #0d6efd; color: white; border-radius: 12px 12px 0 0 !important; }
        .success-icon { font-size: 48px; color: #198754; }
        .error-icon { font-size: 48px; color: #dc3545; }
        .step { border-left: 3px solid #0d6efd; padding-left: 15px; margin-bottom: 20px; }
        .step-number { display: inline-block; width: 28px; height: 28px; background: #0d6efd; color: white; border-radius: 50%; text-align: center; line-height: 28px; font-size: 14px; margin-right: 10px; }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="card-header py-3">
        <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Setup do Titanium Gym</h4>
    </div>
    <div class="card-body p-4">
        <p class="text-muted">Este script vai criar as tabelas necessárias para o sistema funcionar corretamente.</p>';

$sucessos = [];
$erros = [];

// Função para executar SQL com tratamento de erros
function executarSQL($pdo, $sql, $descricao) {
    try {
        $pdo->exec($sql);
        return ['status' => true, 'mensagem' => $descricao . ' - Criada com sucesso!'];
    } catch (PDOException $e) {
        return ['status' => false, 'mensagem' => $descricao . ' - Erro: ' . $e->getMessage()];
    }
}

// 1. Criar tabela gyms
echo '<div class="step"><span class="step-number">1</span><strong>Tabela: gyms</strong></div>';
$resultado = executarSQL($pdo, "
    CREATE TABLE IF NOT EXISTS gyms (
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
", 'Tabela gyms');
if ($resultado['status']) $sucessos[] = $resultado['mensagem'];
else $erros[] = $resultado['mensagem'];

// 2. Verificar se existe dados na tabela gyms
echo '<div class="step"><span class="step-number">2</span><strong>Verificar dados</strong></div>';
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM gyms");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        echo '<div class="alert alert-warning">Nenhuma academia encontrada. Você precisa fazer o registro inicial.</div>';
        echo '<a href="../register.php" class="btn btn-primary"><i class="bi bi-pencil me-2"></i>Cadastrar Nova Academia</a>';
    } else {
        $stmt = $pdo->query("SELECT * FROM gyms LIMIT 1");
        $gym = $stmt->fetch();
        echo '<div class="alert alert-success"><strong>Academia encontrada:</strong> ' . sanitizar($gym['nome']) . '</div>';
    }
} catch (PDOException $e) {
    $erros[] = 'Erro ao verificar gyms: ' . $e->getMessage();
}

// 3. Verificar outras tabelas importantes
echo '<div class="step"><span class="step-number">3</span><strong>Verificar outras tabelas</strong></div>';

$tabelas_verificar = [
    'titanium_gym_users' => 'Tabela de usuários',
    'students' => 'Tabela de alunos',
    'modalities' => 'Tabela de modalidades',
    'plans' => 'Tabela de planos'
];

foreach ($tabelas_verificar as $tabela => $descricao) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->fetch()) {
            echo '<div class="text-success"><i class="bi bi-check-circle"></i> ' . $descricao . ' (' . $tabela . ') - OK</div>';
        } else {
            echo '<div class="text-warning"><i class="bi bi-exclamation-triangle"></i> ' . $descricao . ' (' . $tabela . ') - Não encontrada</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="text-danger"><i class="bi bi-x-circle"></i> ' . $descricao . ' - Erro: ' . $e->getMessage() . '</div>';
    }
}

// Resumo
echo '</div><div class="card-footer bg-white p-4">';
if (empty($erros)) {
    echo '<div class="text-center">
            <div class="success-icon mb-3"><i class="bi bi-check-circle"></i></div>
            <h5>Setup Concluído!</h5>
            <p class="text-muted">O banco de dados está configurado corretamente.</p>
            <a href="../login.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i>Ir para Login</a>
          </div>';
} else {
    echo '<div class="text-center">
            <div class="error-icon mb-3"><i class="bi bi-x-circle"></i></div>
            <h5>Erros Encontrados</h5>
            <ul class="text-start text-danger">';
    foreach ($erros as $erro) {
        echo '<li>' . $erro . '</li>';
    }
    echo '</ul></div>';
}
echo '</div></div></body></html>';
