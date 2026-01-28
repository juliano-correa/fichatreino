<?php
// Alunos - Novo
$titulo_pagina = 'Novo Aluno';
$subtitulo_pagina = 'Cadastrar Aluno';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Buscar planos ativos
try {
    $stmt = $pdo->prepare("SELECT p.* FROM plans p WHERE p.gym_id = :gym_id AND p.ativo = 1 ORDER BY p.nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $planos = $stmt->fetchAll();
    
    // Buscar modalidades para cada plano se a tabela plan_modalities existir
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'plan_modalities'");
    $tem_plan_modalities = $stmt_check->fetch() !== false;
    
    foreach ($planos as &$plano) {
        $modalidades_nomes = [];
        if ($tem_plan_modalities) {
            $stmt_mod = $pdo->prepare("
                SELECT m.nome 
                FROM modalities m 
                JOIN plan_modalities pm ON m.id = pm.modalidade_id 
                WHERE pm.plan_id = :plan_id
            ");
            $stmt_mod->execute([':plan_id' => $plano['id']]);
            $modalidades_nomes = $stmt_mod->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Fallback para modalidade_id direta
        if (empty($modalidades_nomes) && !empty($plano['modalidade_id'])) {
            $stmt_mod_dir = $pdo->prepare("SELECT nome FROM modalities WHERE id = ?");
            $stmt_mod_dir->execute([$plano['modalidade_id']]);
            $mod_nome = $stmt_mod_dir->fetchColumn();
            if ($mod_nome) $modalidades_nomes[] = $mod_nome;
        }
        
        $plano['modalidade_nome'] = !empty($modalidades_nomes) ? implode(', ', $modalidades_nomes) : 'Geral';
    }
    unset($plano);
} catch (PDOException $e) {
    $error = 'Erro ao carregar planos: ' . $e->getMessage();
    $planos = [];
}

// Buscar modalidades
try {
    $stmt = $pdo->prepare("SELECT * FROM modalities WHERE gym_id = :gym_id AND ativa = 1 ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $modalidades = $stmt->fetchAll();
} catch (PDOException $e) {
    $modalidades = [];
}

// Verificar se existe a tabela de relacionamento
$stmt = $pdo->query("SHOW TABLES LIKE 'aluno_modalidade'");
$tabela_relacionamento_existe = $stmt->fetch() !== false;

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
    $objetivo = trim($_POST['objetivo'] ?? '');
    $nivel = $_POST['nivel'] ?? '';
    $status = $_POST['status'] ?? 'ativo';
    
    $planos_selecionados = $_POST['planos'] ?? [];
    $modalidades_selecionadas = $_POST['modalidades'] ?? [];

    if (empty($nome)) {
        $error = 'O nome do aluno é obrigatório.';
    } elseif (empty($planos_selecionados) || empty($planos_selecionados[0])) {
        $error = 'Selecione pelo menos um plano para o aluno.';
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            
            // Inserir aluno
            $stmt = $pdo->prepare("INSERT INTO students (
                gym_id, nome, cpf, email, telefone, telefone2, data_nascimento, genero, 
                endereco, cidade, contato_emergencia, telefone_emergencia, observacoes, 
                objetivo, nivel, status, data_cadastro
            ) VALUES (
                :gym_id, :nome, :cpf, :email, :telefone, :telefone2, :data_nascimento, :genero, 
                :endereco, :cidade, :contato_emergencia, :telefone_emergencia, :observacoes, 
                :objetivo, :nivel, :status, CURDATE()
            )");
            
            $stmt->execute([
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
            
            $aluno_id = $pdo->lastInsertId();
            
            // Criar inscrições (subscriptions) e transações para cada plano
            $primeiro_plano_id = null;
            $primeira_validade = null;
            
            foreach ($planos_selecionados as $plano_id) {
                if (empty($plano_id)) continue;
                
                // Buscar dados do plano
                $stmt_plano = $pdo->prepare("SELECT * FROM plans WHERE id = :id");
                $stmt_plano->execute([':id' => $plano_id]);
                $plano_info = $stmt_plano->fetch();
                
                if ($plano_info) {
                    $data_inicio = date('Y-m-d');
                    $data_fim = !empty($plano_info['duracao_dias']) 
                        ? date('Y-m-d', strtotime("+{$plano_info['duracao_dias']} days"))
                        : date('Y-m-d', strtotime('+1 month'));
                    
                    if ($primeiro_plano_id === null) {
                        $primeiro_plano_id = $plano_id;
                        $primeira_validade = $data_fim;
                    }

                    // Inserir assinatura
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
                        ':preco' => $plano_info['preco']
                    ]);
                    
                    $inscricao_id = $pdo->lastInsertId();
                    
                    // Criar transação inicial
                    if (!empty($plano_info['preco'])) {
                        $stmt_trans = $pdo->prepare("INSERT INTO transactions (
                            gym_id, aluno_id, tipo, categoria, descricao, valor, data_vencimento, status
                        ) VALUES (
                            :gym_id, :aluno_id, 'entrada', 'mensalidade', :descricao, :valor, :data_vencimento, 'pendente'
                        )");
                        
                        $stmt_trans->execute([
                            ':gym_id' => getGymId(),
                            ':aluno_id' => $aluno_id,
                            ':descricao' => "Mensalidade - {$plano_info['nome']}",
                            ':valor' => $plano_info['preco'],
                            ':data_vencimento' => date('Y-m-d')
                        ]);
                    }
                }
            }
            
            // Atualizar o plano principal no cadastro do aluno
            if ($primeiro_plano_id) {
                $stmt_upd = $pdo->prepare("UPDATE students SET plano_atual_id = :plano_id, plano_validade = :validade WHERE id = :id");
                $stmt_upd->execute([
                    ':plano_id' => $primeiro_plano_id,
                    ':validade' => $primeira_validade,
                    ':id' => $aluno_id
                ]);
            }
            
            // Inserir modalidades
            if ($tabela_relacionamento_existe && !empty($modalidades_selecionadas)) {
                $stmt_mod = $pdo->prepare("INSERT INTO aluno_modalidade (aluno_id, modalidade_id, data_inicio, ativo) VALUES (:aluno_id, :modalidade_id, CURDATE(), 1)");
                foreach ($modalidades_selecionadas as $mod_id) {
                    $stmt_mod->execute([
                        ':aluno_id' => $aluno_id,
                        ':modalidade_id' => $mod_id
                    ]);
                }
            }
            
            $pdo->commit();
            $success = 'Aluno cadastrado com sucesso!';
            
            // Limpar formulário
            $_POST = [];
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao cadastrar aluno: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php">Alunos</a></li>
                <li class="breadcrumb-item active">Novo Aluno</li>
            </ol>
        </nav>
        <h4 class="mb-0"><?= $titulo_pagina ?></h4>
    </div>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

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

<form method="POST" id="formAluno" class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Dados Pessoais</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nome Completo *</label>
                        <input type="text" name="nome" class="form-control" value="<?= sanitizar($_POST['nome'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CPF</label>
                        <input type="text" name="cpf" class="form-control mask-cpf" value="<?= sanitizar($_POST['cpf'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?= sanitizar($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone/WhatsApp *</label>
                        <input type="text" name="telefone" class="form-control mask-phone" value="<?= sanitizar($_POST['telefone'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone Alternativo</label>
                        <input type="text" name="telefone2" class="form-control mask-phone" value="<?= sanitizar($_POST['telefone2'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data de Nascimento</label>
                        <input type="date" name="data_nascimento" class="form-control" value="<?= $_POST['data_nascimento'] ?? '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gênero</label>
                        <select name="genero" class="form-select">
                            <option value="">Selecione...</option>
                            <option value="Masculino" <?= ($_POST['genero'] ?? '') === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="Feminino" <?= ($_POST['genero'] ?? '') === 'Feminino' ? 'selected' : '' ?>>Feminino</option>
                            <option value="Outro" <?= ($_POST['genero'] ?? '') === 'Outro' ? 'selected' : '' ?>>Outro</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" class="form-control" value="<?= sanitizar($_POST['cidade'] ?? 'Alpercata/MG') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" class="form-control" value="<?= sanitizar($_POST['endereco'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações Adicionais</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Contato de Emergência</label>
                        <input type="text" name="contato_emergencia" class="form-control" value="<?= sanitizar($_POST['contato_emergencia'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone de Emergência</label>
                        <input type="text" name="telefone_emergencia" class="form-control mask-phone" value="<?= sanitizar($_POST['telefone_emergencia'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Objetivo</label>
                        <input type="text" name="objetivo" class="form-control" value="<?= sanitizar($_POST['objetivo'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nível</label>
                        <select name="nivel" class="form-select">
                            <option value="Iniciante" <?= ($_POST['nivel'] ?? '') === 'Iniciante' ? 'selected' : '' ?>>Iniciante</option>
                            <option value="Intermediário" <?= ($_POST['nivel'] ?? '') === 'Intermediário' ? 'selected' : '' ?>>Intermediário</option>
                            <option value="Avançado" <?= ($_POST['nivel'] ?? '') === 'Avançado' ? 'selected' : '' ?>>Avançado</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observações Médicas/Gerais</label>
                        <textarea name="observacoes" class="form-control" rows="3"><?= sanitizar($_POST['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-card-list me-2"></i>Planos</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarPlano()">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body">
                <div id="container-planos">
                    <div class="plano-item mb-3 p-3 border rounded bg-light position-relative">
                        <label class="form-label fw-bold">Plano 1</label>
                        <select class="form-select plano-select" name="planos[]" required onchange="atualizarPrecoPlano(this)">
                            <option value="">Selecione um plano...</option>
                            <?php foreach ($planos as $plano): ?>
                                <option value="<?= $plano['id'] ?>" data-preco="<?= $plano['preco'] ?>" data-dias="<?= $plano['duracao_dias'] ?>">
                                    <?= sanitizar($plano['nome']) ?> (<?= sanitizar($plano['modalidade_nome']) ?>) - <?= formatarMoeda($plano['preco']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="plano-info mt-2 text-muted small" style="display: none;">
                            <i class="bi bi-calendar3 me-1"></i>
                            <span class="plano-duracao"></span>
                            <span class="plano-valor ms-2" style="display: none;">
                                <i class="bi bi-currency-dollar me-1"></i>
                                <strong class="text-success"></strong>
                            </span>
                        </div>
                    </div>
                </div>
                <small class="text-muted">Selecione um ou mais planos para o aluno.</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Modalidades</h5>
            </div>
            <div class="card-body">
                <?php if (!$tabela_relacionamento_existe): ?>
                    <div class="alert alert-warning py-2 mb-0">
                        <small>A tabela de relacionamento não foi encontrada.</small>
                    </div>
                <?php elseif (empty($modalidades)): ?>
                    <div class="alert alert-info py-2 mb-0">
                        <small>Nenhuma modalidade ativa encontrada.</small>
                    </div>
                <?php else: ?>
                    <div class="modalidades-checkboxes" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($modalidades as $modalidade): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modalidades[]" id="mod_<?= $modalidade['id'] ?>" value="<?= $modalidade['id'] ?>">
                                <label class="form-check-label" for="mod_<?= $modalidade['id'] ?>">
                                    <?= sanitizar($modalidade['nome']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Configurações</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="ativo" selected>Ativo</option>
                        <option value="inativo">Inativo</option>
                        <option value="suspenso">Suspenso</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-check-lg me-1"></i>Cadastrar Aluno
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function adicionarPlano() {
    const container = document.getElementById('container-planos');
    const index = container.querySelectorAll('.plano-item').length + 1;
    const div = document.createElement('div');
    div.className = 'plano-item mb-3 p-3 border rounded bg-light position-relative';
    div.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-bold mb-0">Plano ${index}</label>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.parentElement.remove(); renumerarPlanos()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <select class="form-select plano-select" name="planos[]" required onchange="atualizarPrecoPlano(this)">
            <option value="">Selecione um plano...</option>
            <?php foreach ($planos as $plano): ?>
                <option value="<?= $plano['id'] ?>" data-preco="<?= $plano['preco'] ?>" data-dias="<?= $plano['duracao_dias'] ?>">
                    <?= sanitizar($plano['nome']) ?> (<?= sanitizar($plano['modalidade_nome']) ?>) - <?= formatarMoeda($plano['preco']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="plano-info mt-2 text-muted small" style="display: none;">
            <i class="bi bi-calendar3 me-1"></i>
            <span class="plano-duracao"></span>
            <span class="plano-valor ms-2" style="display: none;">
                <i class="bi bi-currency-dollar me-1"></i>
                <strong class="text-success"></strong>
            </span>
        </div>
    `;
    container.appendChild(div);
}

function renumerarPlanos() {
    const labels = document.querySelectorAll('.plano-item label');
    labels.forEach((label, i) => {
        label.textContent = 'Plano ' + (i + 1);
    });
}

function atualizarPrecoPlano(select) {
    const item = select.closest('.plano-item');
    const infoDiv = item.querySelector('.plano-info');
    const duracaoSpan = item.querySelector('.plano-duracao');
    const valorDiv = item.querySelector('.plano-valor');
    const valorStrong = item.querySelector('.plano-valor strong');
    
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        const preco = option.dataset.preco;
        const dias = option.dataset.dias;
        
        duracaoSpan.textContent = 'Duração: ' + dias + ' dias';
        valorStrong.textContent = 'Valor: R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');
        
        infoDiv.style.display = 'block';
        valorDiv.style.display = 'inline';
    } else {
        infoDiv.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
