<?php
// Avaliação Física - Excluir
require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Verificar se a avaliação existe
try {
    $sql = "SELECT a.*, s.name as student_name 
            FROM assessments a
            LEFT JOIN students s ON a.student_id = s.id
            WHERE a.id = :id AND s.gym_id = :gym_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':gym_id' => getGymId()]);
    $avaliacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$avaliacao) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        $sql = "DELETE FROM assessments WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $_SESSION['success'] = 'Avaliação excluída com sucesso!';
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Erro ao excluir avaliação: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Avaliação - Titanium Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .alert-danger {
            background: #f8d7da;
            border: none;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-exclamation-triangle text-danger fs-1"></i>
                            <h4 class="mt-3">Confirmar Exclusão</h4>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger mb-4">
                                <i class="bi bi-x-circle me-2"></i><?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mb-4">
                            <p class="mb-1">Tem certeza que deseja excluir esta avaliação?</p>
                            <div class="alert alert-light border mt-3">
                                <strong>Aluno:</strong> <?= sanitizar($avaliacao['student_name']) ?><br>
                                <strong>Data:</strong> <?= date('d/m/Y', strtotime($avaliacao['assessment_date'])) ?><br>
                                <strong>Peso:</strong> <?= number_format($avaliacao['peso'], 2, ',', '.') ?> kg<br>
                                <strong>IMC:</strong> <?= number_format($avaliacao['imc'], 2, ',', '.') ?>
                            </div>
                            <p class="text-danger small mb-0">
                                <i class="bi bi-exclamation-circle me-1"></i>
                                Esta ação não poderá ser desfeita!
                            </p>
                        </div>
                        
                        <form method="POST">
                            <div class="d-flex justify-content-center gap-3">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i>Cancelar
                                </a>
                                <button type="submit" name="confirmar" value="1" class="btn btn-danger">
                                    <i class="bi bi-trash me-1"></i>Sim, Excluir
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
