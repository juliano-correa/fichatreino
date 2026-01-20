<?php
// Usuários - Editar
$titulo_pagina = 'Editar Usuário';
$subtitulo_pagina = 'Atualizar Dados do Usuário';

require_once '../../includes/auth_check.php';
require_once '../../includes/permissions.php';

requirePermission('usuarios.edit');

require_once '../../config/conexao.php';

$id = $_GET['id'] ?? 0;
$error = '';

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Obter usuário existente
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

// Obter lista de alunos para vinculação
try {
    $sql_alunos = "SELECT id, nome FROM students WHERE gym_id = :gym_id ORDER BY nome";
    $stmt_alunos = $pdo->prepare($sql_alunos);
    $stmt_alunos->execute([':gym_id' => getGymId()]);
    $alunos = $stmt_alunos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alunos = [];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $role = $_POST['role'] ?? 'aluno';
    $student_id = ($role === 'aluno') ? ($_POST['student_id'] ?? null) : null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    // Validações
    if (empty($nome)) {
        $error = 'Por favor, informe o nome.';
    } elseif (empty($email)) {
        $error = 'Por favor, informe o e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, informe um e-mail válido.';
    } elseif (!empty($senha) && strlen($senha) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $error = 'As senhas não conferem.';
    } elseif ($role === 'aluno' && empty($student_id)) {
        $error = 'Para perfil de Aluno, é obrigatório vincular a um aluno existente.';
    } else {
        try {
            // Verificar se e-mail já existe (outro usuário)
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt_check->execute([':email' => $email, ':id' => $id]);
            if ($stmt_check->fetch()) {
                $error = 'Este e-mail já está em uso por outro usuário.';
            } else {
                // Atualizar usuário
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
                    $sql = "UPDATE users SET 
                                nome = :nome, 
                                email = :email, 
                                senha = :senha,
                                role = :role, 
                                student_id = :student_id, 
                                ativo = :ativo
                            WHERE id = :id";
                    $params = [
                        ':nome' => $nome,
                        ':email' => $email,
                        ':senha' => $senha_hash,
                        ':role' => $role,
                        ':student_id' => $student_id,
                        ':ativo' => $ativo,
                        ':id' => $id
                    ];
                } else {
                    $sql = "UPDATE users SET 
                                nome = :nome, 
                                email = :email, 
                                role = :role, 
                                student_id = :student_id, 
                                ativo = :ativo
                            WHERE id = :id";
                    $params = [
                        ':nome' => $nome,
                        ':email' => $email,
                        ':role' => $role,
                        ':student_id' => $student_id,
                        ':ativo' => $ativo,
                        ':id' => $id
                    ];
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Se alterou o próprio usuário, atualizar sessão
                if ($id == $user_id) {
                    $_SESSION['user_nome'] = $nome;
                    $_SESSION['user_role'] = $role;
                }
                
                $_SESSION['success'] = 'Usuário atualizado com sucesso!';
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar usuário: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Formulário -->
<form method="POST">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>Editar Usuário</h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome Completo *</label>
                    <input type="text" class="form-control" id="nome" name="nome" 
                           value="<?= sanitizar($usuario['nome']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= sanitizar($usuario['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="senha" class="form-label">Nova Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha">
                    <small class="text-muted">Preencha apenas se quiser alterar a senha</small>
                </div>
                <div class="col-md-6">
                    <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                    <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha">
                </div>
                <div class="col-md-4">
                    <label for="role" class="form-label">Perfil *</label>
                    <select class="form-select" id="role" name="role" required onchange="toggleAlunoField()">
                        <option value="admin" <?= $usuario['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="instrutor" <?= $usuario['role'] === 'instrutor' ? 'selected' : '' ?>>Instrutor</option>
                        <option value="recepcao" <?= $usuario['role'] === 'recepcao' ? 'selected' : '' ?>>Recepção</option>
                        <option value="aluno" <?= $usuario['role'] === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                    </select>
                </div>
                <div class="col-md-4" id="alunoField">
                    <label for="student_id" class="form-label">Vincular ao Aluno *</label>
                    <select class="form-select" id="student_id" name="student_id">
                        <option value="">Selecione um aluno...</option>
                        <?php foreach ($alunos as $aluno): ?>
                            <option value="<?= $aluno['id'] ?>" <?= $usuario['student_id'] == $aluno['id'] ? 'selected' : '' ?>>
                                <?= sanitizar($aluno['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                               <?= $usuario['ativo'] ? 'checked' : '' ?>
                               <?= $id == $user_id ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="ativo">
                            Usuário ativo
                            <?php if ($id == $user_id): ?>
                                <small class="text-muted">(Você não pode se desativar)</small>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botões -->
    <div class="d-flex justify-content-end gap-2">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Salvar Alterações
        </button>
    </div>
</form>

<script>
function toggleAlunoField() {
    const role = document.getElementById('role').value;
    const alunoField = document.getElementById('alunoField');
    const studentId = document.getElementById('student_id');
    
    if (role === 'aluno') {
        alunoField.style.display = 'block';
        studentId.setAttribute('required', 'required');
    } else {
        alunoField.style.display = 'none';
        studentId.removeAttribute('required');
        studentId.value = '';
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', toggleAlunoField);
</script>

<?php include '../../includes/footer.php'; ?>
