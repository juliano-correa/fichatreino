<?php
// Alunos - Criar Novo
$titulo_pagina = 'Novo Aluno';
$subtitulo_pagina = 'Cadastrar Aluno';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$error = '';
$success = '';

// Buscar planos e modalidades
try {
    $stmt = $pdo->prepare("SELECT p.*, m.nome as modalidade_nome FROM plans p LEFT JOIN modalities m ON p.modalidade_id = m.id WHERE p.gym_id = :gym_id AND p.ativo = 1 ORDER BY m.nome, p.nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $planos = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM modalities WHERE gym_id = :gym_id AND ativa = 1 ORDER BY nome");
    $stmt->execute([':gym_id' => getGymId()]);
    $modalidades = $stmt->fetchAll();
    
    // Verificar se existe a tabela de relacionamento
    $stmt = $pdo->query("SHOW TABLES LIKE 'aluno_modalidade'");
    $tabela_relacionamento_existe = $stmt->fetch() !== false;
    
    // Verificar se a tabela subscriptions existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'subscriptions'");
    $tabela_subscriptions_existe = $stmt->fetch() !== false;
    
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
    $planos_selecionados = $_POST['planos'] ?? [];
    $modalidades_selecionadas = $_POST['modalidades'] ?? [];
    $status = $_POST['status'] ?? 'ativo';
    
    // Validações
    if (empty($nome)) {
        $error = 'O nome é obrigatório.';
    } elseif (empty($telefone)) {
        $error = 'O telefone é obrigatório.';
    } elseif (!empty($cpf) && !validarCPF($cpf)) {
        $error = 'CPF inválido. Por favor, verifique o número digitado.';
    } elseif (!empty($cpf)) {
        // Verificar se CPF já existe no banco de dados
        try {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE gym_id = :gym_id AND cpf = :cpf");
            $stmt_check->execute([
                ':gym_id' => getGymId(),
                ':cpf' => $cpf
            ]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = 'Este CPF já está cadastrado para outro aluno nesta academia. Por favor, verifique o número ou entre em contato com a recepção.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao verificar CPF: ' . $e->getMessage();
        }
    }
    
    // Se não houver erro, prosseguir com o cadastro
    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            
            // Determinar plano atual (primeiro plano selecionado ou null)
            $plano_atual_id = !empty($planos_selecionados) ? $planos_selecionados[0] : null;
            
            // Inserir aluno
            $stmt = $pdo->prepare("INSERT INTO students (
                gym_id, nome, cpf, email, telefone, telefone2, data_nascimento, genero,
                endereco, cidade, contato_emergencia, telefone_emergencia, observacoes,
                objetivo, nivel, status, plano_atual_id, plano_validade
            ) VALUES (
                :gym_id, :nome, :cpf, :email, :telefone, :telefone2, :data_nascimento, :genero,
                :endereco, :cidade, :contato_emergencia, :telefone_emergencia, :observacoes,
                :objetivo, :nivel, :status, :plano_id, :plano_validade
            )");
            
            $params = [
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
                ':status' => $status,
                ':plano_id' => $plano_atual_id,
                ':plano_validade' => null
            ];
            
            $stmt->execute($params);
            $aluno_id = $pdo->lastInsertId();
            
            // Criar inscrições e transações para cada plano selecionado
            if (!empty($planos_selecionados) && $tabela_subscriptions_existe) {
                foreach ($planos_selecionados as $plano_id) {
                    // Buscar dados do plano
                    $stmt_plano = $pdo->prepare("SELECT * FROM plans WHERE id = :id");
                    $stmt_plano->execute([':id' => $plano_id]);
                    $plano = $stmt_plano->fetch();
                    
                    if ($plano) {
                        // Calcular validade
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
                        
                        // Criar transação (mensalidade) se houver preço
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
            
            // Vincular modalidades (se a tabela existir)
            if ($tabela_relacionamento_existe && !empty($modalidades_selecionadas)) {
                $stmt_mod = $pdo->prepare("INSERT INTO aluno_modalidade (aluno_id, modalidade_id, data_inicio, ativo) VALUES (:aluno_id, :modalidade_id, CURDATE(), 1)");
                foreach ($modalidades_selecionadas as $mod_id) {
                    try {
                        $stmt_mod->execute([':aluno_id' => $aluno_id, ':modalidade_id' => $mod_id]);
                    } catch (PDOException $e) {
                        // Pode já existir o vínculo
                    }
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Aluno cadastrado com sucesso!';
            redirecionar('index.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
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
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= sanitizar($_POST['nome'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control cpf-input" id="cpf" name="cpf" value="<?= sanitizar($_POST['cpf'] ?? '') ?>" placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= sanitizar($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefone" class="form-label fw-bold">Telefone/WhatsApp *</label>
                            <input type="text" class="form-control phone-input" id="telefone" name="telefone" value="<?= sanitizar($_POST['telefone'] ?? '') ?>" placeholder="(11) 99999-9999" required>
                        </div>
                        <div class="col-md-6">
                            <label for="telefone2" class="form-label">Telefone Alternativo</label>
                            <input type="text" class="form-control phone-input" id="telefone2" name="telefone2" value="<?= sanitizar($_POST['telefone2'] ?? '') ?>" placeholder="(11) 99999-9999">
                        </div>
                        <div class="col-md-6">
                            <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                            <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="<?= sanitizar($_POST['data_nascimento'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="genero" class="form-label">Gênero</label>
                            <select class="form-select" id="genero" name="genero">
                                <option value="">Selecione...</option>
                                <option value="M" <?= ($_POST['genero'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                                <option value="F" <?= ($_POST['genero'] ?? '') === 'F' ? 'selected' : '' ?>>Feminino</option>
                                <option value="O" <?= ($_POST['genero'] ?? '') === 'O' ? 'selected' : '' ?>>Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="cidade" class="form-label">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" value="<?= sanitizar($_POST['cidade'] ?? '') ?>">
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
                            <input type="text" class="form-control" id="contato_emergencia" name="contato_emergencia" value="<?= sanitizar($_POST['contato_emergencia'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefone_emergencia" class="form-label">Telefone</label>
                            <input type="text" class="form-control phone-input" id="telefone_emergencia" name="telefone_emergencia" value="<?= sanitizar($_POST['telefone_emergencia'] ?? '') ?>">
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
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Observações relevantes sobre o aluno..."><?= sanitizar($_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Barra Lateral -->
        <div class="col-lg-4">
            <!-- Planos -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-credit-card me-2"></i>Planos
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarPlano()">
                        <i class="bi bi-plus-lg"></i> Adicionar
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($planos)): ?>
                        <div class="alert alert-info py-2 mb-0">
                            <small>Nenhum plano ativo encontrado. <a href="../planos/index.php">Cadastre um plano primeiro.</a></small>
                        </div>
                    <?php else: ?>
                        <div id="container-planos">
                            <div class="plano-item mb-3 p-3 border rounded bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold mb-0">Plano 1</label>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerPlano(this)" title="Remover plano">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <select class="form-select plano-select" name="planos[]" required onchange="atualizarPrecoPlano(this)">
                                    <option value="">Selecione um plano...</option>
                                    <?php foreach ($planos as $plano): ?>
                                        <option value="<?= $plano['id'] ?>" data-preco="<?= $plano['preco'] ?>" data-dias="<?= $plano['duracao_dias'] ?>">
                                            <?= sanitizar($plano['nome']) ?> - <?= sanitizar($plano['modalidade_nome']) ?> (<?= formatarMoeda($plano['preco']) ?>)
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
                        <small class="text-muted">Selecione um ou mais planos. Cada plano gerará uma cobrança.</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Informações do Treino -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-activity me-2"></i>Informações do Treino
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="objetivo" class="form-label">Objetivo</label>
                        <select class="form-select" id="objetivo" name="objetivo">
                            <option value="">Selecione...</option>
                            <option value="emagrecimento" <?= ($_POST['objetivo'] ?? '') === 'emagrecimento' ? 'selected' : '' ?>>Emagrecimento</option>
                            <option value="hipertrofia" <?= ($_POST['objetivo'] ?? '') === 'hipertrofia' ? 'selected' : '' ?>>Hipertrofia</option>
                            <option value="condicionamento" <?= ($_POST['objetivo'] ?? '') === 'condicionamento' ? 'selected' : '' ?>>Condicionamento</option>
                            <option value="saude" <?= ($_POST['objetivo'] ?? '') === 'saude' ? 'selected' : '' ?>>Saúde</option>
                            <option value="competicao" <?= ($_POST['objetivo'] ?? '') === 'competicao' ? 'selected' : '' ?>>Competição</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nível</label>
                        <select class="form-select" id="nivel" name="nivel">
                            <option value="">Selecione...</option>
                            <option value="iniciante" <?= ($_POST['nivel'] ?? '') === 'iniciante' ? 'selected' : '' ?>>Iniciante</option>
                            <option value="intermediario" <?= ($_POST['nivel'] ?? '') === 'intermediario' ? 'selected' : '' ?>>Intermediário</option>
                            <option value="avancado" <?= ($_POST['nivel'] ?? '') === 'avancado' ? 'selected' : '' ?>>Avançado</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ativo" <?= ($_POST['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= ($_POST['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="suspenso" <?= ($_POST['status'] ?? '') === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
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
                            <small>A tabela de relacionamento ainda não foi criada. Por favor, execute o script de setup.</small>
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
                                           <?= in_array($modalidade['id'], $_POST['modalidades'] ?? []) ? 'checked' : '' ?>>
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
                        <i class="bi bi-check-lg me-2"></i>Salvar Aluno
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
// Contador para novos planos
let contadorPlanos = 1;

// Adicionar novo campo de plano
function adicionarPlano() {
    contadorPlanos++;
    const container = document.getElementById('container-planos');
    const novoItem = document.createElement('div');
    novoItem.className = 'plano-item mb-3 p-3 border rounded bg-light position-relative';
    novoItem.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-bold mb-0">Plano ${contadorPlanos}</label>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerPlano(this)" title="Remover plano">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <select class="form-select plano-select" name="planos[]" required onchange="atualizarPrecoPlano(this)">
            <option value="">Selecione um plano...</option>
            <?php foreach ($planos as $plano): ?>
                <option value="<?= $plano['id'] ?>" data-preco="<?= $plano['preco'] ?>" data-dias="<?= $plano['duracao_dias'] ?>">
                    <?= sanitizar($plano['nome']) ?> - <?= sanitizar($plano['modalidade_nome']) ?> (<?= formatarMoeda($plano['preco']) ?>)
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
    container.appendChild(novoItem);
}

// Remover campo de plano
function removerPlano(botao) {
    const container = document.getElementById('container-planos');
    if (container.querySelectorAll('.plano-item').length > 1) {
        botao.closest('.plano-item').remove();
        renumerarPlanos();
    } else {
        alert('É necessário ter pelo menos um plano.');
    }
}

// Renumerar os labels dos planos
function renumerarPlanos() {
    const planos = document.querySelectorAll('.plano-item');
    planos.forEach((plano, index) => {
        plano.querySelector('label').textContent = 'Plano ' + (index + 1);
    });
}

// Atualizar informações do plano selecionado
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
        valorDiv.style.display = valorSpan = 'inline';
    } else {
        infoDiv.style.display = 'none';
    }
}

// Validação do formulário
document.getElementById('formAluno').addEventListener('submit', function(e) {
    const planosSelecionados = document.querySelectorAll('.plano-select');
    let temPlanoValido = false;
    
    planosSelecionados.forEach(select => {
        if (select.value) temPlanoValido = true;
    });
    
    if (!temPlanoValido) {
        e.preventDefault();
        alert('Por favor, selecione pelo menos um plano para o aluno.');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
