<?php
// Fichas de Treino - Editar com Grupos e Integração com Tabela de Exercícios
$titulo_pagina = 'Editar Ficha';
$subtitulo_pagina = 'Atualizar Ficha de Treino';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

if (isAluno()) {
    redirecionar('index.php');
}

$id = $_GET['id'] ?? 0;
$error = '';

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Obter ficha existente
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
    error_log('Erro ao carregar ficha: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Obter lista de alunos
try {
    $sql_alunos = "SELECT id, nome FROM students WHERE gym_id = :gym_id ORDER BY nome";
    $stmt_alunos = $pdo->prepare($sql_alunos);
    $stmt_alunos->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_alunos->execute();
    $alunos = $stmt_alunos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alunos = [];
}

// Obter lista de exercícios da tabela exercises
try {
    $sql_exercicios = "SELECT id, nome, grupo_muscular FROM exercicios WHERE gym_id = :gym_id ORDER BY grupo_muscular, nome";
    $stmt_exercicios = $pdo->prepare($sql_exercicios);
    $stmt_exercicios->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_exercicios->execute();
    $exercicios_banco = $stmt_exercicios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $exercicios_banco = [];
}

// Organizar exercícios por grupo muscular para o select
$exercicios_por_grupo = [];
foreach ($exercicios_banco as $ex) {
    $gm = $ex['grupo_muscular'] ?? 'Outros';
    if (!isset($exercicios_por_grupo[$gm])) {
        $exercicios_por_grupo[$gm] = [];
    }
    $exercicios_por_grupo[$gm][] = $ex;
}

// Processar dados de grupos existentes
$grupos_existentes = [];
$dados_json = json_decode($ficha['exercicios'] ?? '{}', true);

// Verificar se é o formato novo (objeto com grupos) ou novo com exercise_id
if (isset($dados_json['grupos']) && is_array($dados_json['grupos'])) {
    // Novo formato com grupos
    $grupos_existentes = $dados_json['grupos'];
} elseif (is_array($dados_json) && !empty($dados_json)) {
    // Formato antigo - verificar se tem exercise_id (novo) ou nome (antigo)
    $primeiro_item = reset($dados_json);
    if (isset($primeiro_item['exercise_id'])) {
        // Novo formato com exercise_id - precisa resolver nomes do banco
        foreach ($dados_json as $ex) {
            $ex['nome'] = $ex['nome'] ?? '';
            // Tentar encontrar o nome no banco de dados
            foreach ($exercicios_banco as $ex_banco) {
                if ($ex_banco['id'] == $ex['exercise_id']) {
                    $ex['nome'] = $ex_banco['nome'];
                    $ex['grupo_muscular'] = $ex_banco['grupo_muscular'];
                    break;
                }
            }
        }
        $grupos_existentes[] = [
            'nome' => 'Exercícios',
            'exercicios' => $dados_json
        ];
    } else {
        // Formato antigo - converter para novo formato
        $grupos_existentes[] = [
            'nome' => 'Exercícios',
            'exercicios' => $dados_json
        ];
    }
}

// Grupos musculares para o select
$grupos_musculares = [
    'Peito' => 'Peito',
    'Costas' => 'Costas',
    'Ombros' => 'Ombros',
    'Bíceps' => 'Bíceps',
    'Tríceps' => 'Tríceps',
    'Antebraço' => 'Antebraço',
    'Abdômen' => 'Abdômen',
    'Quadríceps' => 'Quadríceps',
    'Posterior de Coxa' => 'Posterior de Coxa',
    'Glúteos' => 'Glúteos',
    'Panturrilha' => 'Panturrilha',
    'Cardio' => 'Cardio',
    'Full Body' => 'Full Body'
];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aluno_id = $_POST['aluno_id'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $objetivo = $_POST['objetivo'] ?? '';
    $frequencia = $_POST['frequencia'] ?? '';
    $ativa = isset($_POST['ativa']) ? 1 : 0;
    
    // Obter grupos de exercícios do formulário
    $grupos_post = $_POST['grupos'] ?? [];
    
    // Validações
    if (empty($aluno_id)) {
        $error = 'Por favor, selecione um aluno.';
    } elseif (empty($nome)) {
        $error = 'Por favor, informe o nome da ficha.';
    } elseif (empty($grupos_post) || !is_array($grupos_post)) {
        $error = 'Por favor, adicione pelo menos um grupo de exercícios.';
    } else {
        // Verificar se há exercícios válidos em algum grupo
        $tem_exercicio_valido = false;
        $tem_grupo_valido = false;
        $grupos_processados = [];
        
        foreach ($grupos_post as $grupo) {
            $nome_grupo = trim($grupo['nome'] ?? '');
            $exercicios = $grupo['exercicios'] ?? [];
            
            $exercicios_limpos = [];
            foreach ($exercicios as $ex) {
                if (!empty(trim($ex['nome'] ?? '')) || !empty($ex['exercise_id'] ?? '')) {
                    $exercicios_limpos[] = [
                        'exercise_id' => !empty($ex['exercise_id']) ? intval($ex['exercise_id']) : null,
                        'nome' => !empty(trim($ex['nome'])) ? trim($ex['nome']) : null,
                        'grupo_muscular' => $ex['grupo_muscular'] ?? null,
                        'series' => isset($ex['series']) && $ex['series'] !== '' ? $ex['series'] : 3,
                        'repeticoes' => isset($ex['repeticoes']) && $ex['repeticoes'] !== '' ? $ex['repeticoes'] : 15,
                        'carga' => !empty($ex['carga']) ? $ex['carga'] : null,
                        'descanso' => !empty($ex['descanso']) ? $ex['descanso'] : null,
                        'observacoes' => $ex['observacoes'] ?? null
                    ];
                    
                    if (!empty(trim($ex['nome'] ?? '')) || !empty($ex['exercise_id'])) {
                        $tem_exercicio_valido = true;
                    }
                }
            }
            
            // Só adiciona grupo se tiver nome ou exercícios
            if (!empty($nome_grupo) || count($exercicios_limpos) > 0) {
                $grupos_processados[] = [
                    'nome' => !empty($nome_grupo) ? $nome_grupo : 'Grupo ' . (count($grupos_processados) + 1),
                    'exercicios' => $exercicios_limpos
                ];
                
                if (count($exercicios_limpos) > 0) {
                    $tem_grupo_valido = true;
                }
            }
        }
        
        if (!$tem_exercicio_valido) {
            $error = 'Por favor, adicione pelo menos um exercício com nome válido.';
        } elseif (!$tem_grupo_valido) {
            $error = 'Por favor, adicione pelo menos um exercício em algum grupo.';
        } else {
            try {
                // Preparar grupos como JSON
                $grupos_json = json_encode(['grupos' => $grupos_processados], JSON_UNESCAPED_UNICODE);
                
                // Atualizar ficha
                $sql = "UPDATE workouts SET
                            aluno_id = :aluno_id,
                            nome = :nome,
                            descricao = :descricao,
                            exercicios = :exercicios,
                            frequencia = :frequencia,
                            objetivo = :objetivo,
                            ativa = :ativa,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $id,
                    ':aluno_id' => $aluno_id,
                    ':nome' => $nome,
                    ':descricao' => $descricao,
                    ':exercicios' => $grupos_json,
                    ':frequencia' => !empty($frequencia) ? $frequencia : null,
                    ':objetivo' => !empty($objetivo) ? $objetivo : null,
                    ':ativa' => $ativa
                ]);
                
                $_SESSION['success'] = 'Ficha atualizada com sucesso!';
                header('Location: visualizar.php?id=' . $id);
                exit;
                
            } catch (PDOException $e) {
                $error = 'Erro ao atualizar ficha: ' . $e->getMessage();
            }
        }
    }
}

// Valores para o formulário (do POST ou do banco)
$form_data = [
    'aluno_id' => $_POST['aluno_id'] ?? $ficha['aluno_id'],
    'nome' => $_POST['nome'] ?? $ficha['nome'],
    'descricao' => $_POST['descricao'] ?? $ficha['descricao'],
    'objetivo' => $_POST['objetivo'] ?? $ficha['objetivo'],
    'frequencia' => $_POST['frequencia'] ?? $ficha['frequencia'],
    'ativa' => isset($_POST['ativa']) ? $_POST['ativa'] : $ficha['ativa']
];

// Exercícios resolvidos para o JavaScript (para compatibilidade)
$exercicios_resolvidos = [];
foreach ($grupos_existentes as $grupo) {
    $grupo_resolvido = [
        'nome' => $grupo['nome'],
        'exercicios' => []
    ];
    foreach ($grupo['exercicios'] as $ex) {
        // Se tem exercise_id, resolver nome do banco
        if (!empty($ex['exercise_id'])) {
            foreach ($exercicios_banco as $ex_banco) {
                if ($ex_banco['id'] == $ex['exercise_id']) {
                    $ex['nome'] = $ex_banco['nome'];
                    $ex['grupo_muscular'] = $ex_banco['grupo_muscular'];
                    break;
                }
            }
        }
        $grupo_resolvido['exercicios'][] = $ex;
    }
    $exercicios_resolvidos[] = $grupo_resolvido;
}
$exercicios_resolvidos_json = json_encode($exercicios_resolvidos);
?>

<?php include '../includes/header.php'; ?>

<!-- Mensagens -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Formulário -->
<form method="POST" id="formFicha">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Ficha de <?= sanitizar($ficha['aluno_nome']) ?></h5>
                <a href="<?= base_url('fichas_treino/visualizar.php?id=' . $id) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="aluno_id" class="form-label">Aluno *</label>
                    <select class="form-select" id="aluno_id" name="aluno_id" required>
                        <option value="">Selecione um aluno...</option>
                        <?php foreach ($alunos as $aluno): ?>
                            <option value="<?= $aluno['id'] ?>" <?= ($form_data['aluno_id'] == $aluno['id']) ? 'selected' : '' ?>>
                                <?= sanitizar($aluno['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome da Ficha *</label>
                    <input type="text" class="form-control" id="nome" name="nome" 
                           placeholder="Ex: Hipertrofia A/B, Perda de Peso..."
                           value="<?= sanitizar($form_data['nome']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="objetivo" class="form-label">Objetivo</label>
                    <select class="form-select" id="objetivo" name="objetivo">
                        <option value="">Selecione...</option>
                        <option value="Hipertrofia" <?= $form_data['objetivo'] == 'Hipertrofia' ? 'selected' : '' ?>>Hipertrofia</option>
                        <option value="Perda de Peso" <?= $form_data['objetivo'] == 'Perda de Peso' ? 'selected' : '' ?>>Perda de Peso</option>
                        <option value="Condicionamento" <?= $form_data['objetivo'] == 'Condicionamento' ? 'selected' : '' ?>>Condicionamento</option>
                        <option value="Força" <?= $form_data['objetivo'] == 'Força' ? 'selected' : '' ?>>Força</option>
                        <option value="Resistência" <?= $form_data['objetivo'] == 'Resistência' ? 'selected' : '' ?>>Resistência</option>
                        <option value="Definição" <?= $form_data['objetivo'] == 'Definição' ? 'selected' : '' ?>>Definição</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="frequencia" class="form-label">Frequência</label>
                    <select class="form-select" id="frequencia" name="frequencia">
                        <option value="">Selecione...</option>
                        <option value="1x semana" <?= $form_data['frequencia'] == '1x semana' ? 'selected' : '' ?>>1x semana</option>
                        <option value="2x semana" <?= $form_data['frequencia'] == '2x semana' ? 'selected' : '' ?>>2x semana</option>
                        <option value="3x semana" <?= $form_data['frequencia'] == '3x semana' ? 'selected' : '' ?>>3x semana</option>
                        <option value="4x semana" <?= $form_data['frequencia'] == '4x semana' ? 'selected' : '' ?>>4x semana</option>
                        <option value="5x semana" <?= $form_data['frequencia'] == '5x semana' ? 'selected' : '' ?>>5x semana</option>
                        <option value="6x semana" <?= $form_data['frequencia'] == '6x semana' ? 'selected' : '' ?>>6x semana</option>
                        <option value="Diária" <?= $form_data['frequencia'] == 'Diária' ? 'selected' : '' ?>>Diária</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="ativa" class="form-label">Status</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="ativa" name="ativa" value="1" <?= $form_data['ativa'] == 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ativa">Ficha Ativa</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <label for="descricao" class="form-label">Descrição/Observações</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="2" 
                              placeholder="Instruções gerais, intervalo entre séries, dicas..."><?= sanitizar($form_data['descricao']) ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Grupos de Exercícios -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-collection me-2"></i>Grupos de Exercícios</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="adicionarGrupo()">
                    <i class="bi bi-plus-lg me-1"></i>Adicionar Grupo
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="containerGrupos">
                <!-- Grupos serão adicionados aqui via JavaScript -->
            </div>
            
            <div class="text-center py-5 text-muted" id="semGrupos" style="<?= count($exercicios_resolvidos) > 0 ? 'display: none;' : '' ?>">
                <i class="bi bi-collection fs-1"></i>
                <p class="mb-0 mt-2">Nenhum grupo adicionado ainda.</p>
                <small>Clique em "Adicionar Grupo" para começar.</small>
            </div>
        </div>
    </div>
    
    <!-- Botões -->
    <div class="d-flex justify-content-end gap-2">
        <a href="<?= base_url('fichas_treino/visualizar.php?id=' . $id) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Salvar Alterações
        </button>
    </div>
</form>

<!-- Template de Grupo de Exercícios -->
<template id="templateGrupo">
    <div class="grupo-exercicios border-bottom p-3" data-grupo-index="__GRUPO_INDEX__">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <i class="bi bi-grip-vertical text-muted"></i>
                <input type="text" class="form-control form-control-sm nome-grupo" 
                       name="grupos[__GRUPO_INDEX__][nome]" placeholder="Nome do Grupo (ex: Grupo A)" 
                       style="max-width: 200px;" value="__NOME_GRUPO__">
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removerGrupo(this)">
                <i class="bi bi-trash"></i> Remover Grupo
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 25%;">Exercício</th>
                        <th style="width: 12%;">Grupo Muscular</th>
                        <th style="width: 8%;">Séries</th>
                        <th style="width: 8%;">Reps</th>
                        <th style="width: 10%;">Carga (kg)</th>
                        <th style="width: 10%;">Descanso (s)</th>
                        <th style="width: 17%;">Observações</th>
                        <th style="width: 10%;"></th>
                    </tr>
                </thead>
                <tbody class="corpo-exercicios-grupo">
                    <!-- Exercícios serão adicionados aqui -->
                </tbody>
            </table>
        </div>
        
        <div class="mt-2">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarExercicioGrupo(this)">
                <i class="bi bi-plus-lg me-1"></i>Adicionar Exercício
            </button>
        </div>
    </div>
</template>

<!-- Template de linha de exercício - COM dropdown de busca -->
<template id="templateExercicioComSelect">
    <tr class="linha-exercicio">
        <td>
            <div class="input-group input-group-sm">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-search"></i>
                </button>
                <ul class="dropdown-menu exercicio-dropdown-menu" style="max-height: 300px; overflow-y: auto; min-width: 250px;">
                    <li><h6 class="dropdown-header">Selecione um exercício:</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (!empty($exercicios_por_grupo)): ?>
                        <?php foreach ($exercicios_por_grupo as $gm => $exs): ?>
                            <li><h6 class="dropdown-header px-3 text-primary"><?= sanitizar($gm) ?></h6></li>
                            <?php foreach ($exs as $ex): ?>
                                <li><button class="dropdown-item exercicio-selecionado" type="button" data-id="<?= $ex['id'] ?>" data-nome="<?= sanitizar($ex['nome']) ?>" data-grupo="<?= sanitizar($ex['grupo_muscular']) ?>"><?= sanitizar($ex['nome']) ?></button></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span class="dropdown-item-text text-muted">Nenhum exercício cadastrado.</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item-text text-muted">Digite o nome abaixo.</span></li>
                    <?php endif; ?>
                </ul>
                <input type="text" class="form-control nome-exercicio" 
                       name="grupos[__GRUPO_INDEX__][exercicios][__EXERCICIO_INDEX__][nome]" 
                       placeholder="Digite o nome do exercício" required>
            </div>
        </td>
        <td>
            <select class="form-select form-select-sm" name="grupos[__GRUPO_INDEX__][exercicios][__EXERCICIO_INDEX__][grupo_muscular]">
                <option value="">Selecione...</option>
                <?php foreach ($grupos_musculares as $gm): ?>
                    <option value="<?= $gm ?>"><?= $gm ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm"
                   name="grupos[__GRUPO_INDEX__][exercicios][__EXERCICIO_INDEX__][series]" placeholder="3" min="1" max="20">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm"
                   name="grupos[__GRUPO_INDEX__][exercicios][__EXERCICIO_INDEX__][repeticoes]" placeholder="15">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm" 
                   name="grupos[__GRUPO_INDEX__][exercicios][__EXERCICIO_INDEX__][carga]" placeholder="kg" min="0" step="0.5">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm" 
                   name="grupos[__GRUPO_INDEX__][exercicios][__EXERCICIO_INDEX__][descanso]" placeholder="60" min="0">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="grupos[__GRUPO_INDEX__][exercicios][__EXERCICIO_INDEX__][observacoes]" placeholder="Observação">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removerExercicio(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
// Grupos carregados do banco com exercícios resolvidos
const gruposCarregados = <?= $exercicios_resolvidos_json ?>;
console.log('Grupos carregados:', gruposCarregados);

let indiceGrupo = 0;
let indiceExercicioPorGrupo = {};

// Inicializar contador de exercícios
function getIndiceExercicio(grupoIndex) {
    if (!indiceExercicioPorGrupo[grupoIndex]) {
        indiceExercicioPorGrupo[grupoIndex] = 0;
    }
    return indiceExercicioPorGrupo[grupoIndex]++;
}

function adicionarGrupo(nomeGrupo, exercicios) {
    const template = document.getElementById('templateGrupo');
    const clone = template.content.cloneNode(true);
    const container = document.getElementById('containerGrupos');
    
    // Substituir __GRUPO_INDEX__ e __NOME_GRUPO__
    let html = clone.querySelector('.grupo-exercicios').outerHTML;
    const grupoIndex = indiceGrupo;
    html = html.replace(/__GRUPO_INDEX__/g, grupoIndex);
    html = html.replace(/__NOME_GRUPO__/g, nomeGrupo || '');
    
    const grupoDiv = document.createElement('div');
    grupoDiv.innerHTML = html;
    
    container.appendChild(grupoDiv.firstElementChild);
    indiceGrupo++;
    
    // Inicializar contador de exercícios para este grupo
    indiceExercicioPorGrupo[grupoIndex] = 0;
    
    // Adicionar exercícios do grupo
    const grupoElement = container.lastElementChild;
    
    if (exercicios && exercicios.length > 0) {
        exercicios.forEach(function(ex) {
            adicionarExercicioPreenchido(grupoElement, ex, grupoIndex);
        });
    } else {
        // Adicionar 2 exercícios vazios por padrão
        adicionarExercicioGrupo(grupoElement.querySelector('button[onclick^="adicionarExercicioGrupo"]'));
        adicionarExercicioGrupo(grupoElement.querySelector('button[onclick^="adicionarExercicioGrupo"]'));
    }
    
    // Ocultar mensagem de "sem grupos"
    const msgVazia = document.getElementById('semGrupos');
    if (msgVazia) {
        msgVazia.style.display = 'none';
    }
}

function adicionarExercicioGrupo(botao) {
    const grupoDiv = botao.closest('.grupo-exercicios');
    const grupoIndex = parseInt(grupoDiv.dataset.grupoIndex);
    adicionarExercicioPreenchido(grupoDiv, null, grupoIndex);
}

function adicionarExercicioPreenchido(grupoDiv, dadosExercicio, grupoIndex) {
    const tbody = grupoDiv.querySelector('.corpo-exercicios-grupo');
    const template = document.getElementById('templateExercicioComSelect');
    const clone = template.content.cloneNode(true);
    
    // Substituir placeholders
    let html = clone.querySelector('tr').outerHTML;
    const exercicioIndex = getIndiceExercicio(grupoIndex);
    html = html.replace(/__GRUPO_INDEX__/g, grupoIndex);
    html = html.replace(/__EXERCICIO_INDEX__/g, exercicioIndex);
    
    const tr = document.createElement('tr');
    tr.innerHTML = html;
    tr.className = 'linha-exercicio';
    
    tbody.appendChild(tr);
    
    // Preencher dados se fornecidos
    if (dadosExercicio) {
        console.log('Preenchendo exercício:', dadosExercicio);
        const inputNome = tr.querySelector('.nome-exercicio');
        const selectGrupo = tr.querySelector('select[name$="[grupo_muscular]"]');
        
        if (inputNome && dadosExercicio.nome) {
            inputNome.value = dadosExercicio.nome;
            console.log('Nome preenchido:', dadosExercicio.nome);
        }
        
        // Preencher grupo muscular
        if (dadosExercicio.grupo_muscular && selectGrupo) {
            selectGrupo.value = dadosExercicio.grupo_muscular;
            console.log('Grupo muscular preenchido:', dadosExercicio.grupo_muscular);
        }
        
        // Preencher outros campos (series, repeticoes, carga, descanso, observacoes)
        const inputs = tr.querySelectorAll('input:not(.nome-exercicio)');
        console.log('Inputs encontrados:', inputs.length);
        inputs.forEach(input => {
            const name = input.name;
            // Extrair o nome do campo sem os índices
            const match = name.match(/exercicios\[(\d+)\]\[(.+)\]/);
            if (match) {
                const campo = match[2];
                console.log(`Campo ${campo}:`, dadosExercicio[campo]);
                // Preencher o valor se existir no dadosExercicio
                if (dadosExercicio[campo] !== undefined && dadosExercicio[campo] !== null) {
                    input.value = dadosExercicio[campo];
                    console.log(`${campo} preenchido com:`, dadosExercicio[campo]);
                } else if (campo === 'series' && !input.value) {
                    // Valor default para séries se não houver valor salvo
                    input.value = 3;
                    console.log('Series preenchido com default: 3');
                } else if (campo === 'repeticoes' && !input.value) {
                    // Valor default para repetições se não houver valor salvo
                    input.value = 15;
                    console.log('Repeticoes preenchido com default: 15');
                }
            }
        });
    } else {
        console.log('Nenhum dado de exercício fornecido, aplicando defaults');
        // Aplicar defaults para novos exercícios
        const inputs = tr.querySelectorAll('input:not(.nome-exercicio)');
        inputs.forEach(input => {
            const name = input.name;
            const match = name.match(/exercicios\[(\d+)\]\[(.+)\]/);
            if (match) {
                const campo = match[2];
                if (campo === 'series' && !input.value) {
                    input.value = 3;
                } else if (campo === 'repeticoes' && !input.value) {
                    input.value = 15;
                }
            }
        });
    }
    
    // Adicionar event listeners para os botões de seleção de exercício
    setupExercicioListeners(tr);
}

function setupExercicioListeners(tr) {
    // Event listeners para seleção de exercício do dropdown
    const botoesExercicio = tr.querySelectorAll('.exercicio-selecionado');
    botoesExercicio.forEach(botao => {
        botao.addEventListener('click', function() {
            const inputNome = tr.querySelector('.nome-exercicio');
            const selectGrupo = tr.querySelector('select[name$="[grupo_muscular]"]');
            
            inputNome.value = this.dataset.nome;
            
            if (this.dataset.grupo && selectGrupo) {
                selectGrupo.value = this.dataset.grupo;
            }
            
            // Fechar o dropdown
            const dropdown = this.closest('.dropdown-menu');
            const toggle = tr.querySelector('.dropdown-toggle');
            if (toggle && window.bootstrap && window.bootstrap.Dropdown) {
                const bsDropdown = bootstrap.Dropdown.getInstance(toggle);
                if (bsDropdown) {
                    bsDropdown.hide();
                }
            }
        });
    });
}

function removerExercicio(botao) {
    const linha = botao.closest('tr');
    const grupoDiv = linha.closest('.grupo-exercicios');
    linha.remove();
    
    // Verificar se ainda há exercícios no grupo
    const tbody = grupoDiv.querySelector('.corpo-exercicios-grupo');
    if (tbody.children.length === 0) {
        // Adicionar pelo menos um exercício vazio
        adicionarExercicioGrupo(grupoDiv.querySelector('button[onclick^="adicionarExercicioGrupo"]'));
    }
}

function removerGrupo(botao) {
    const grupo = botao.closest('.grupo-exercicios');
    grupo.remove();
    
    // Mostrar mensagem se não houver mais grupos
    const container = document.getElementById('containerGrupos');
    if (container.children.length === 0) {
        const msgVazia = document.getElementById('semGrupos');
        if (msgVazia) {
            msgVazia.style.display = 'block';
        }
        indiceGrupo = 0;
        indiceExercicioPorGrupo = {};
    }
}

// Carregar grupos existentes ou adicionar grupos vazios
document.addEventListener('DOMContentLoaded', function() {
    if (gruposCarregados && gruposCarregados.length > 0) {
        // Carregar grupos existentes do banco
        gruposCarregados.forEach(function(grupo) {
            adicionarGrupo(grupo.nome, grupo.exercicios || []);
        });
    } else {
        // Adicionar um grupo vazio por padrão
        adicionarGrupo('', []);
    }
});

// Validação do formulário
document.getElementById('formFicha').addEventListener('submit', function(e) {
    const grupos = document.querySelectorAll('.grupo-exercicios');
    
    if (grupos.length === 0) {
        e.preventDefault();
        alert('Por favor, adicione pelo menos um grupo de exercícios.');
        return false;
    }
    
    // Verificar se há pelo menos um exercício com nome preenchido
    let temValido = false;
    
    grupos.forEach(grupo => {
        const linhas = grupo.querySelectorAll('.linha-exercicio');
        linhas.forEach(linha => {
            const inputNome = linha.querySelector('.nome-exercicio');
            
            if (inputNome && inputNome.value.trim()) {
                temValido = true;
            }
        });
    });
    
    if (!temValido) {
        e.preventDefault();
        alert('Por favor, preencha o nome de pelo menos um exercício.');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>
