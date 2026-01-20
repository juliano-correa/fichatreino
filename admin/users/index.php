<?php
// Usuários - Listagem
$titulo_pagina = 'Usuários';
$subtitulo_pagina = 'Gerenciar Usuários do Sistema';

require_once '../../includes/auth_check.php';
require_once '../../includes/permissions.php';

requirePermission('usuarios.view');

require_once '../../config/conexao.php';

$error = '';
$success = '';

// Processar ações em lote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    $ids = $_POST['user_ids'] ?? [];
    
    if (empty($ids)) {
        $error = 'Selecione pelo menos um usuário.';
    } elseif ($acao === 'ativar') {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE users SET ativo = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $success = count($ids) . ' usuário(s) ativado(s) com sucesso.';
        } catch (PDOException $e) {
            $error = 'Erro ao ativar usuários: ' . $e->getMessage();
        }
    } elseif ($acao === 'desativar') {
        try {
            // Não desativar o próprio usuário
            $ids = array_filter($ids, fn($id) => $id != $user_id);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("UPDATE users SET ativo = 0 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $success = count($ids) . ' usuário(s) desativado(s) com sucesso.';
            } else {
                $error = 'Você não pode se desativar.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao desativar usuários: ' . $e->getMessage();
        }
    } elseif ($acao === 'excluir') {
        try {
            // Não excluir o próprio usuário
            $ids = array_filter($ids, fn($id) => $id != $user_id);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $success = count($ids) . ' usuário(s) excluído(s) com sucesso.';
            } else {
                $error = 'Você não pode se excluir.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao excluir usuários: ' . $e->getMessage();
        }
    }
}

// Listar usuários
try {
    $sql = "SELECT u.*, s.nome as aluno_nome 
            FROM users u 
            LEFT JOIN students s ON u.student_id = s.id 
            ORDER BY u.nome";
    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios = [];
    $error = 'Erro ao carregar usuários: ' . $e->getMessage();
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

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Novo Usuário
        </a>
    </div>
    <div class="text-muted">
        Total: <?= count($usuarios) ?> usuário(s)
    </div>
</div>

<!-- Formulário de Ações em Lote -->
<form method="POST" id="formLote">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll" 
                                       onclick="toggleSelectAll()">
                            </th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Vinculado a</th>
                            <th>Status</th>
                            <th style="width: 120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-people mb-2 d-block" style="font-size: 32px;"></i>
                                    Nenhum usuário cadastrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input user-checkbox" 
                                               name="user_ids[]" value="<?= $u['id'] ?>">
                                    </td>
                                    <td>
                                        <strong><?= sanitizar($u['nome']) ?></strong>
                                        <?php if ($u['id'] == $user_id): ?>
                                            <span class="badge bg-info ms-1">Você</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= sanitizar($u['email']) ?></td>
                                    <td>
                                        <span class="badge <?= getRoleBadgeClass($u['role']) ?>">
                                            <?= getRoleLabel($u['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'aluno'): ?>
                                            <?php if ($u['aluno_nome']): ?>
                                                <a href="../../students/visualizar.php?id=<?= $u['student_id'] ?>">
                                                    <?= sanitizar($u['aluno_nome']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Não vinculado</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['ativo']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($u['id'] != $user_id): ?>
                                            <a href="delete.php?id=<?= $u['id'] ?>" 
                                               class="btn btn-outline-danger btn-sm" 
                                               onclick="return confirmarExclusao(event, 'Tem certeza que deseja excluir este usuário?');"
                                               title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Ações em Lote -->
    <?php if (!empty($usuarios)): ?>
        <div class="d-flex gap-2 align-items-center no-print">
            <span class="text-muted">Ações em lote:</span>
            <select class="form-select form-select-sm" style="width: auto;" name="acao" required>
                <option value="">Selecione...</option>
                <option value="ativar">Ativar selecionados</option>
                <option value="desativar">Desativar selecionados</option>
                <option value="excluir">Excluir selecionados</option>
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm"
                    onclick="return confirm('Tem certeza que deseja executar esta ação nos usuários selecionados?');">
                <i class="bi bi-check-lg"></i> Aplicar
            </button>
        </div>
    <?php endif; ?>
</form>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}
</script>

<?php include '../../includes/footer.php'; ?>
