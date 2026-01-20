<?php
/**
 * Caixas - Excluir Caixa
 * Script para remover um caixa do banco de dados
 */

require_once '../../includes/auth_check.php';
require_once '../../config/conexao.php';

// Verificar permissão de admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Apenas administradores podem excluir caixas.';
    redirecionar('../../index.php');
}

// Verificar ID do caixa
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Caixa não especificado.';
    redirecionar('index.php');
}

$caixa_id = (int)$_GET['id'];

// Verificar se o caixa existe e pertence ao gym logado
try {
    $stmt = $pdo->prepare("SELECT id, nome, status FROM cash_registers WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $caixa_id, ':gym_id' => getGymId()]);
    $caixa = $stmt->fetch();
    
    if (!$caixa) {
        $_SESSION['error'] = 'Caixa não encontrado ou você não tem permissão para excluí-lo.';
        redirecionar('index.php');
    }
    
    // Verificar se o caixa está aberto
    if ($caixa['status'] === 'aberto') {
        $_SESSION['error'] = 'Não é possível excluir um caixa aberto. Feche-o primeiro.';
        redirecionar('index.php');
    }
    
    // Excluir o caixa
    $stmt = $pdo->prepare("DELETE FROM cash_registers WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $caixa_id, ':gym_id' => getGymId()]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Caixa '" . sanitizar($caixa['nome']) . "' foi excluído com sucesso.";
    } else {
        $_SESSION['error'] = 'Erro ao excluir o caixa.';
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erro ao excluir caixa: ' . $e->getMessage();
}

redirecionar('index.php');
