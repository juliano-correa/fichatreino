<?php
// Alunos - Excluir
require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirecionar('index.php');
}

$aluno_id = (int)$_GET['id'];

// Verificar se o aluno existe
try {
    $stmt = $pdo->prepare("SELECT nome FROM students WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $aluno_id, ':gym_id' => getGymId()]);
    $aluno = $stmt->fetch();
    
    if (!$aluno) {
        $_SESSION['error'] = 'Aluno não encontrado.';
        redirecionar('index.php');
    }
    
    // Excluir (soft delete - alterar status)
    $stmt = $pdo->prepare("UPDATE students SET status = 'inativo' WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $aluno_id, ':gym_id' => getGymId()]);
    
    $_SESSION['success'] = 'Aluno inativado com sucesso!';
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erro ao inativar aluno: ' . $e->getMessage();
}

redirecionar('index.php');
