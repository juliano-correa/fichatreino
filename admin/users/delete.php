<?php
// Usuários - Excluir
require_once '../../includes/auth_check.php';
require_once '../../includes/permissions.php';

requirePermission('usuarios.delete');

require_once '../../config/conexao.php';

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Não permitir excluir o próprio usuário
if ($id == $user_id) {
    $_SESSION['error'] = 'Você não pode excluir seu próprio usuário.';
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT nome FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Location: index.php');
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    $_SESSION['success'] = 'Usuário "' . $usuario['nome'] . '" excluído com sucesso!';
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erro ao excluir usuário: ' . $e->getMessage();
}

header('Location: index.php');
exit;
