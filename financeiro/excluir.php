<?php
/**
 * Financeiro - Excluir Transação
 * Script para remover uma transação do banco de dados
 */

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

if (isAluno()) {
    redirecionar('index.php');
}

// Verificar ID da transação
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Transação não especificada.';
    redirecionar('index.php');
}

$transacao_id = (int)$_GET['id'];

// Verificar se a transação existe e pertence ao gym logado
try {
    $stmt = $pdo->prepare("SELECT id, descricao, tipo, valor FROM transactions WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $transacao_id, ':gym_id' => getGymId()]);
    $transacao = $stmt->fetch();
    
    if (!$transacao) {
        $_SESSION['error'] = 'Transação não encontrada ou você não tem permissão para excluí-la.';
        redirecionar('index.php');
    }
    
    // Excluir a transação
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $transacao_id, ':gym_id' => getGymId()]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Transação '" . sanitizar($transacao['descricao']) . "' ({$transacao['tipo']}: R$ " . number_format($transacao['valor'], 2, ',', '.') . ") foi excluída com sucesso.";
    } else {
        $_SESSION['error'] = 'Erro ao excluir a transação.';
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erro ao excluir transação: ' . $e->getMessage();
}

redirecionar('index.php');
