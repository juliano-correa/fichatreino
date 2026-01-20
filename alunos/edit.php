<?php
// Alunos - Editar
$titulo_pagina = 'Editar Aluno';
$subtitulo_pagina = 'Alterar Dados';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirecionar('index.php');
}

$aluno_id = (int)$_GET['id'];

// Buscar aluno
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND gym_id = :gym_id");
    $stmt->execute([':id' => $aluno_id, ':gym_id' => getGymId()]);
    $aluno = $stmt->fetch();
    
    if (!$aluno) {
        redirecionar('index.php');
    }
    
    // Planos disponíveis
    $stmt = $pdo->prepare("SELECT p.*, m.nome as modalidade_nome FROM plans p LEFT JOIN modalities m ON p.modalidade_id = m.id WHERE p.gym_id = :gym_id AND p.ativo = 1 ORDER BY m.nome, p.nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $planos_disponiveis = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM modalities WHERE gym_id = :gym_id AND ativa = 1 ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $modalidades = $stmt->fetchAll();
    
    // Verificar se existe a tabela de relacionamento
    $stmt = $pdo->query("SHOW TABLES LIKE 'aluno_modalidade'");
    $tabela_relacionamento_existe = $stmt->fetch() !== false;
    
    // Verificar se a tabela subscriptions existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'subscriptions'");
    $tabela_subscriptions_existe = $stmt->fetch() !== false;
    
    // Buscar modalidades do aluno
    $modalidades_aluno = [];
    if ($tabela_relacionamento_existe) {
        $stmt = $pdo->prepare("SELECT modalidade_id FROM aluno_modalidade WHERE aluno_id = :aluno_id AND ativo = 1");
        $stmt->execute([':aluno_id' => $aluno_id]);
        $modalidades_aluno = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Buscar inscrições do aluno
    $inscricoes = [];
    if ($tabela_subscriptions_existe) {
        $stmt = $pdo->prepare("
            SELECT s.*, p.nome as plano_nome, p.preco, m.nome as modalidade_nome 
            FROM subscriptions s 
            LEFT JOIN plans p ON s.plano_id = p.id 
            LEFT JOIN modalities m ON p.modalidade_id = m.id
            WHERE s.aluno_id = :aluno_id 
            ORDER BY s.data_inicio DESC
        ");
        $stmt->execute([':aluno_id' => $aluno_id]);
        $inscricoes = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
    $telefone2 = preg_replace('/\D/', '', $_POST['telefone2'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $genero = $_POST['genero'] ?? '';
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $contato_emergencia = trim($_POST['contato_emergencia'] ?? '');
    $telefone_emergencia = preg_replace('/\D/', '', $_POST['telefone_emergencia'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $objetivo = $_POST['objetivo'] ?? '';
    $nivel = $_POST['nivel'] ?? '';
    $status = $_POST['status'] ?? 'ativo';
    $modalidades_selecionadas = $_POST['modalidades'] ?? [];
    $novos_planos = $_POST['novos_planos'] ?? [];
    $inscricoes_cancelar = $_POST['inscricoes_cancelar'] ?? [];
    
    if (empty($nome)) {
        $error = 'O nome é obrigatório.';
    } elseif (!empty($cpf) && !validarCPF($cpf)) {
        $error = 'CPF inválido. Por favor, verifique o número digitado.';
    } elseif (!empty($cpf)) {
        // Verificar se CPF já existe (exceto o próprio aluno)
        try {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE gym_id = :gym_id AND cpf = :cpf AND id != :id");
            $stmt_check->execute([
                ':gym_id' => getGymId(),
                ':cpf' => $cpf,
                ':id' => $aluno_id
            ]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = 'Este CPF já está cadastrado para outro aluno nesta academia.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao verificar CPF: ' . $e->getMessage();
        }
    }
    
    // Se não houver erro, prosseguir com a atualização
    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            
            // Atualizar dados do aluno
            $stmt = $pdo->prepare("UPDATE students SET 
                nome = :nome, cpf = :cpf, email = :email, telefone = :telefone, telefone2 = :telefone2,
                data_nascimento = :data_nascimento, genero = :genero, endereco = :endereco, cidade = :cidade,
                contato_emergencia = :contato_emergencia, telefone_emergencia = :telefone_emergencia,
                observacoes = :observacoes, objetivo = :objetivo, nivel = :nivel, status = :status
            WHERE id = :id AND gym_id = :gym_id");
            
            $stmt->execute([
                ':id' => $aluno_id,
                ':gym_id' => getGymId(),
                ':nome' => $nome,
                ':cpf' => $cpf,
                ':email' => $email,
                ':telefone' => $telefone,
                ':telefone2' => $telefone2,
                ':data_nascimento' => !empty($data_nascimento) ? $data_nascimento : null,
                ':genero' => $genero,
                ':endereco' => $endereco,
                ':cidade' => $cidade,
                ':contato_emergencia' => $contato_emergencia,
                ':telefone_emergencia' => $telefone_emergencia,
                ':observacoes' => $observacoes,
                ':objetivo' => $objetivo,
                ':nivel' => $nivel,
                ':status' => $status
            ]);
            
            // Cancelar inscrições selecionadas
            if (!empty($inscricoes_cancelar) && $tabela_subscriptions_existe) {
                $stmt_cancelar = $pdo->prepare("UPDATE subscriptions SET status = 'cancelado' WHERE id = :id AND aluno_id = :aluno_id");
                foreach ($inscricoes_cancelar as $insc_id) {
                    $stmt_cancelar->execute([':id' => $insc_id, ':aluno_id' => $aluno_id]);
                }
            }
            
            // Criar novas inscrições
            if (!empty($novos_planos) && $tabela_subscriptions_existe) {
                foreach ($novos_planos as $plano_id) {
                    if (in_array($plano_id, array_column($inscricoes, 'plano_id'))) {
                        continue; // Já possui este plano
                    }
                    
                    // Buscar dados do plano
                    $stmt_plano = $pdo->prepare("SELECT * FROM plans WHERE id = :id");
                    $stmt_plano->execute([':id' => $plano_id]);
                    $plano = $stmt_plano->fetch();
                    
                    if ($plano) {
                        $data_inicio = date('Y-m-d');
                        $data_fim = !empty($plano['duracao_dias']) 
                            ? date('Y-m-d', strtotime("+{$plano['duracao_dias']} days"))
                            : date('Y-m-d', strtotime('+1 month'));
                        
                        // Criar inscrição
                        $stmt_sub = $pdo->prepare("INSERT INTO subscriptions (
                            gym_id, aluno_id, plano_id, data_inicio, data_fim, preco_pago, status
                        ) VALUES (
                            :gym_id, :aluno_id, :plano_id, :data_inicio, :data_fim, :preco, 'ativo'
                        )");
                        
                        $stmt_sub->execute([
                            ':gym_id' => getGymId(),
                            ':aluno_id' => $aluno_id,
                            ':plano_id' => $plano_id,
                            ':data_inicio' => $data_inicio,
                            ':data_fim' => $data_fim,
                            ':preco' => $plano['preco']
                        ]);
                        
                        $inscricao_id = $pdo->lastInsertId();
                        
                        // Criar transação
                        if (!empty($plano['preco'])) {
                            $stmt_trans = $pdo->prepare("INSERT INTO transactions (
                                gym_id, aluno_id, inscricao_id, tipo, categoria, descricao, valor, data_vencimento, status
                            ) VALUES (
                                :gym_id, :aluno_id, :inscricao_id, 'receita', 'mensalidade', :descricao, :valor, :data_vencimento, 'pendente'
                            )");
                            
                            $stmt_trans->execute([
                                ':gym_id' => getGymId(),
                                ':aluno_id' => $aluno_id,
                                ':inscricao_id' => $inscricao_id,
                                ':descricao' => "Mensalidade - {$plano['nome']}",
                                ':valor' => $plano['preco'],
                                ':data_vencimento' => date('Y-m-d', strtotime('+1 month'))
                            ]);
                        }
                    }
                }
            }
            
            // Atualizar modalidades
            if ($tabela_relacionamento_existe) {
                $stmt_desativar = $pdo->prepare("UPDATE aluno_modalidade SET ativo = 0 WHERE aluno_id = :aluno_id");
                $stmt_desativar->execute([':aluno_id' => $aluno_id]);
                
                if (!empty($modalidades_selecionadas)) {
                    $stmt_mod = $pdo->prepare("INSERT INTO aluno_modalidade (aluno_id, modalidade_id, data_inicio, ativo) 
                        VALUES (:aluno_id, :modalidade_id, CURDATE(), 1)
                        ON DUPLICATE KEY UPDATE ativo = 1, data_inicio = CURDATE()");
                    foreach ($modalidades_selecionadas as $mod_id) {
                        try {
                            $stmt_mod->execute([':aluno_id' => $aluno_id, ':modalidade_id' => $mod_id]);
                        } catch (PDOException $e) {}
                    }
                }
            }
            
            // Atualizar plano_atual_id (primeira inscrição ativa)
            $stmt_plano_atual = $pdo->prepare("
                SELECT plano_id FROM subscriptions 
                WHERE aluno_id = :aluno_id AND status = 'ativo' 
                ORDER BY data_inicio DESC LIMIT 1
            ");
            $stmt_plano_atual->execute([':aluno_id' => $aluno_id]);
            $novo_plano_atual = $stmt_plano_atual->fetchColumn();
            
            $stmt_update_plano = $pdo->prepare("UPDATE students SET plano_atual_id = :plano_id WHERE id = :id");
            $stmt_update_plano->execute([':plano_id' => $novo_plano_atual ?: null, ':id' => $aluno_id]);
            
            $pdo->commit();
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND gym_id = :gym_id");
            $stmt->execute([':id' => $aluno_id, ':gym_id' => getGymId()]);
            $aluno = $stmt->fetch();
            
            // Recarregar inscrições
            if ($tabela_subscriptions_existe) {
                $stmt = $pdo->prepare("
                    SELECT s.*, p.nome as plano_nome, p.preco, m.nome as modalidade_nome 
                    FROM subscriptions s 
                    LEFT JOIN plans p ON s.plano_id = p.id 
                    LEFT JOIN modalities m ON p.modalidade_id = m.id
                    WHERE s.aluno_id = :aluno_id 
                    ORDER BY s.data_inicio DESC
                ");
                $stmt->execute([':aluno_id' => $aluno_id]);
                $inscricoes = $stmt->fetchAll();
            }
            
            $success = 'Aluno atualizado com sucesso!';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Alunos</a></li>
        <li class="breadcrumb-item active">Editar: <?= sanitizar($aluno['nome']) ?></li>
    </ol>
</nav>

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

<form method="POST" id="formAluno">
    <div class="row g-4">
        <!-- Dados Pessoais -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person me-2"></i>Dados Pessoais
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label fw-bold">Nome Completo *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= sanitizar($aluno['nome']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control cpf-input" id="cpf" name="cpf" value="<?= formatarCPF($aluno['cpf']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= sanitizar($aluno['email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefone" class="form-label fw-bold">Telefone/WhatsApp *</label>
                            <input type="text" class="form-control phone-input" id="telefone" name="telefone" value="<?= formatarTelefone($aluno['telefone']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="telefone2" class="form-label">Telefone Alternativo</label>
                            <input type="text" class="form-control phone-input" id="telefone2" name="telefone2" value="<?= formatarTelefone($aluno['telefone2']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                            <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="<?= formatarDataInput($aluno['data_nascimento']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="genero" class="form-label">Gênero</label>
                            <select class="form-select" id="genero" name="genero">
                                <option value="">Selecione...</option>
                                <option value="M" <?= $aluno['genero'] === 'M' ? 'selected' : '' ?>>Masculino</option>
                                <option value="F" <?= $aluno['genero'] === 'F' ? 'selected' : '' ?>>Feminino</option>
                                <option value="O" <?= $aluno['genero'] === 'O' ? 'selected' : '' ?>>Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="cidade" class="form-label">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" value="<?= sanitizar($aluno['cidade']) ?>">
                        </div>
                        <div class="col-12">
                            <label for="endereco" class="form-label">Endereço</label>
                            <input type="text" class="form-control" id="endereco" name="endereco" value="<?= sanitizar($aluno['endereco']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contato de Emergência -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>Contato de Emergência
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="contato_emergencia" class="form-label">Nome do Contato</label>
                            <input type="text" class="form-control" id="contato_emergencia" name="contato_emergencia" value="<?= sanitizar($aluno['contato_emergencia']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefone_emergencia" class="form-label">Telefone</label>
                            <input type="text" class="form-control phone-input" id="telefone_emergencia" name="telefone_emergencia" value="<?= formatarTelefone($aluno['telefone_emergencia']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Observações -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-sticky me-2"></i>Observações
                    </h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?= sanitizar($aluno['observacoes']) ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Barra Lateral -->
        <div class="col-lg-4">
            <!-- Planos Ativos -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-credit-card me-2"></i>Planos Ativos
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!$tabela_subscriptions_existe): ?>
                        <div class="alert alert-warning py-2 mb-0">
                            <small>A tabela de inscrições não existe.</small>
                        </div>
                    <?php elseif (empty($inscricoes)): ?>
                        <div class="alert alert-info py-2 mb-0">
                            <small>Este aluno não possui nenhum plano ativo.</small>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($inscricoes as $insc): ?>
                                <?php if ($insc['status'] === 'ativo'): ?>
                                    <div class="list-group-item bg-light rounded mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= sanitizar($insc['plano_nome']) ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?= sanitizar($insc['modalidade_nome']) ?> - 
                                                    <?= formatarMoeda($insc['preco']) ?>
                                                </small>
                                                <br>
                                                <small class="text-success">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    Vence: <?= formatarData($insc['data_fim']) ?>
                                                </small>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="inscricoes_cancelar[]" 
                                                       value="<?= $insc['id'] ?>" 
                                                       id="cancelar_<?= $insc['id'] ?>">
                                                <label class="form-check-label text-danger small" for="cancelar_<?= $insc['id'] ?>">
                                                    Cancelar
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Adicionar Novo Plano -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus-circle me-2"></i>Adicionar Novo Plano
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($planos_disponiveis)): ?>
                        <div class="alert alert-info py-2 mb-0">
                            <small>Nenhum plano disponível.</small>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Selecione um plano:</label>
                            <select class="form-select" name="novos_planos[]" id="novo_plano">
                                <option value="">Selecione um plano...</option>
                                <?php 
                                // Exibir apenas planos que o aluno não possui
                                $planos_possuidos = array_column($inscricoes, 'plano_id');
                                foreach ($planos_disponiveis as $plano): 
                                    if (!in_array($plano['id'], $planos_possuidos)):
                                ?>
                                    <option value="<?= $plano['id'] ?>">
                                        <?= sanitizar($plano['nome']) ?> - <?= sanitizar($plano['modalidade_nome']) ?> (<?= formatarMoeda($plano['preco']) ?>)
                                    </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                            <small class="text-muted">Selecione um plano que o aluno ainda não possui.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Configurações -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>Configurações
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ativo" <?= $aluno['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= $aluno['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="suspenso" <?= $aluno['status'] === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="objetivo" class="form-label">Objetivo</label>
                        <select class="form-select" id="objetivo" name="objetivo">
                            <option value="">Selecione...</option>
                            <option value="emagrecimento" <?= $aluno['objetivo'] === 'emagrecimento' ? 'selected' : '' ?>>Emagrecimento</option>
                            <option value="hipertrofia" <?= $aluno['objetivo'] === 'hipertrofia' ? 'selected' : '' ?>>Hipertrofia</option>
                            <option value="condicionamento" <?= $aluno['objetivo'] === 'condicionamento' ? 'selected' : '' ?>>Condicionamento</option>
                            <option value="saude" <?= $aluno['objetivo'] === 'saude' ? 'selected' : '' ?>>Saúde</option>
                            <option value="competicao" <?= $aluno['objetivo'] === 'competicao' ? 'selected' : '' ?>>Competição</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nível</label>
                        <select class="form-select" id="nivel" name="nivel">
                            <option value="">Selecione...</option>
                            <option value="iniciante" <?= $aluno['nivel'] === 'iniciante' ? 'selected' : '' ?>>Iniciante</option>
                            <option value="intermediario" <?= $aluno['nivel'] === 'intermediario' ? 'selected' : '' ?>>Intermediário</option>
                            <option value="avancado" <?= $aluno['nivel'] === 'avancado' ? 'selected' : '' ?>>Avançado</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Modalidades -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-check me-2"></i>Modalidades
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!$tabela_relacionamento_existe): ?>
                        <div class="alert alert-warning py-2 mb-0">
                            <small>A tabela de relacionamento ainda não foi criada.</small>
                        </div>
                    <?php elseif (empty($modalidades)): ?>
                        <div class="alert alert-info py-2 mb-0">
                            <small>Nenhuma modalidade ativa encontrada.</small>
                        </div>
                    <?php else: ?>
                        <div class="modalidades-checkboxes" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($modalidades as $modalidade): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="modalidades[]" 
                                           id="modalidade_<?= $modalidade['id'] ?>" 
                                           value="<?= $modalidade['id'] ?>"
                                           <?= in_array($modalidade['id'], $modalidades_aluno) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modalidade_<?= $modalidade['id'] ?>">
                                        <?= sanitizar($modalidade['nome']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Selecione uma ou mais modalidades para o aluno.</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Ações -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Salvar Alterações
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Confirmação de cancelamento de plano
document.querySelectorAll('input[name="inscricoes_cancelar[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            if (!confirm('Tem certeza que deseja CANCELAR este plano? Esta ação não pode ser desfeita.')) {
                this.checked = false;
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
