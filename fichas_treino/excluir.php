<?php
// Fichas de Treino - Excluir
require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

if (isAluno()) {
    redirecionar('index.php');
}

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Verificar se a ficha existe
try {
    $sql = "SELECT w.*, s.nome as aluno_nome 
            FROM workouts w
            LEFT JOIN students s ON w.aluno_id = s.id
            WHERE w.id = :id AND w.gym_id = :gym_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':gym_id' => getGymId()]);
    $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ficha) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Erro ao verificar ficha: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        $sql = "DELETE FROM workouts WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $_SESSION['success'] = 'Ficha excluída com sucesso!';
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Erro ao excluir ficha: ' . $e->getMessage();
    }
}

// Status label
$status_label = $ficha['ativa'] == 1 ? 'Ativo' : 'Inativo';
?>

<?php include '../includes/header.php'; ?>

<!-- Mensagem de Erro -->
<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Card de Confirmação -->
<div class="row justify-content-center mt-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-danger fs-4 me-2"></i>
                    <h5 class="mb-0">Confirmar Exclusão</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <p class="mb-3">Tem certeza que deseja excluir esta ficha de treino?</p>
                    
                    <div class="alert alert-light border-start border-4 border-danger">
                        <div class="mb-2"><strong>Aluno:</strong> <?= sanitizar($ficha['aluno_nome']) ?></div>
                        <div class="mb-2"><strong>Ficha:</strong> <?= sanitizar($ficha['nome']) ?></div>
                        <div class="mb-2"><strong>Status:</strong> <?= $status_label ?></div>
                        <?php if (!empty($ficha['objetivo'])): ?>
                            <div><strong>Objetivo:</strong> <?= sanitizar($ficha['objetivo']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong>Atenção:</strong> Esta ação não poderá ser desfeita!<br>
                        Todos os exercícios desta ficha serão excluídos.
                    </div>
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

<?php include '../includes/footer.php'; ?>
