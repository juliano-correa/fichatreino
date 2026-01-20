<?php
// Avaliação Física - Editar
$titulo_pagina = 'Editar Avaliação';
$subtitulo_pagina = 'Atualizar Avaliação Física';

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$id = $_GET['id'] ?? 0;
$error = '';

if (empty($id)) {
    header('Location: index.php');
    exit;
}

// Obter avaliação existente
try {
    $sql = "SELECT a.*, s.nome as aluno_nome 
            FROM assessments a
            LEFT JOIN students s ON a.aluno_id = s.id
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

// Obter lista de alunos (para o select)
try {
    $sql_alunos = "SELECT id, nome FROM students WHERE gym_id = :gym_id ORDER BY nome";
    $stmt_alunos = $pdo->prepare($sql_alunos);
    $stmt_alunos->bindValue(':gym_id', getGymId(), PDO::PARAM_INT);
    $stmt_alunos->execute();
    $alunos = $stmt_alunos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alunos = [];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aluno_id = $_POST['aluno_id'] ?? '';
    $data_avaliacao = $_POST['data_avaliacao'] ?? '';
    $peso = str_replace(',', '.', $_POST['peso'] ?? 0);
    $altura = str_replace(',', '.', $_POST['altura'] ?? 0);
    $percentual_gordura = !empty($_POST['percentual_gordura']) ? str_replace(',', '.', $_POST['percentual_gordura']) : null;
    $massa_magra = !empty($_POST['massa_magra']) ? str_replace(',', '.', $_POST['massa_magra']) : null;
    
    // Perímetros
    $ombro = !empty($_POST['ombro']) ? str_replace(',', '.', $_POST['ombro']) : null;
    $torax = !empty($_POST['torax']) ? str_replace(',', '.', $_POST['torax']) : null;
    $braco_direito = !empty($_POST['braco_direito']) ? str_replace(',', '.', $_POST['braco_direito']) : null;
    $braco_esquerdo = !empty($_POST['braco_esquerdo']) ? str_replace(',', '.', $_POST['braco_esquerdo']) : null;
    $cintura = !empty($_POST['cintura']) ? str_replace(',', '.', $_POST['cintura']) : null;
    $abdomen = !empty($_POST['abdomen']) ? str_replace(',', '.', $_POST['abdomen']) : null;
    $quadril = !empty($_POST['quadril']) ? str_replace(',', '.', $_POST['quadril']) : null;
    $coxa_direita = !empty($_POST['coxa_direita']) ? str_replace(',', '.', $_POST['coxa_direita']) : null;
    $coxa_esquerda = !empty($_POST['coxa_esquerda']) ? str_replace(',', '.', $_POST['coxa_esquerda']) : null;
    $panturrilha_direita = !empty($_POST['panturrilha_direita']) ? str_replace(',', '.', $_POST['panturrilha_direita']) : null;
    $panturrilha_esquerda = !empty($_POST['panturrilha_esquerda']) ? str_replace(',', '.', $_POST['panturrilha_esquerda']) : null;
    
    // Testes físicos
    $flexibilidade = !empty($_POST['flexibilidade']) ? (int)$_POST['flexibilidade'] : null;
    $flexoes = !empty($_POST['flexoes']) ? (int)$_POST['flexoes'] : null;
    $abdominal = !empty($_POST['abdominal']) ? (int)$_POST['abdominal'] : null;
    
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Validações
    if (empty($aluno_id)) {
        $error = 'Por favor, selecione um aluno.';
    } elseif (empty($peso) || empty($altura)) {
        $error = 'Por favor, preencha peso e altura.';
    } else {
        // Calcular IMC
        $imc = $peso / ($altura * $altura);
        $imc = round($imc, 2);
        
        try {
            $sql = "UPDATE assessments SET
                        aluno_id = :aluno_id,
                        data_avaliacao = :data_avaliacao,
                        peso = :peso,
                        altura = :altura,
                        imc = :imc,
                        percentual_gordura = :percentual_gordura,
                        massa_magra = :massa_magra,
                        ombro = :ombro,
                        torax = :torax,
                        braco_direito = :braco_direito,
                        braco_esquerdo = :braco_esquerdo,
                        cintura = :cintura,
                        abdomen = :abdomen,
                        quadril = :quadril,
                        coxa_direita = :coxa_direita,
                        coxa_esquerda = :coxa_esquerda,
                        panturrilha_direita = :panturrilha_direita,
                        panturrilha_esquerda = :panturrilha_esquerda,
                        flexibilidade = :flexibilidade,
                        flexoes = :flexoes,
                        abdominal = :abdominal,
                        observacoes = :observacoes,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':aluno_id' => $aluno_id,
                ':data_avaliacao' => $data_avaliacao,
                ':peso' => $peso,
                ':altura' => $altura,
                ':imc' => $imc,
                ':percentual_gordura' => $percentual_gordura,
                ':massa_magra' => $massa_magra,
                ':ombro' => $ombro,
                ':torax' => $torax,
                ':braco_direito' => $braco_direito,
                ':braco_esquerdo' => $braco_esquerdo,
                ':cintura' => $cintura,
                ':abdomen' => $abdomen,
                ':quadril' => $quadril,
                ':coxa_direita' => $coxa_direita,
                ':coxa_esquerda' => $coxa_esquerda,
                ':panturrilha_direita' => $panturrilha_direita,
                ':panturrilha_esquerda' => $panturrilha_esquerda,
                ':flexibilidade' => $flexibilidade,
                ':flexoes' => $flexoes,
                ':abdominal' => $abdominal,
                ':observacoes' => $observacoes
            ]);
            
            $_SESSION['success'] = 'Avaliação atualizada com sucesso!';
            header('Location: visualizar.php?id=' . $id);
            exit;
            
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar avaliação: ' . $e->getMessage();
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

<!-- Formulário -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Avaliação de <?= sanitizar($avaliacao['aluno_nome']) ?></h5>
            <a href="visualizar.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" id="formAvaliacao">
            <!-- Seção 1: Dados Básicos -->
            <h6 class="text-muted mb-3 border-bottom pb-2">
                <i class="bi bi-person-vcard me-2"></i>Dados Básicos
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="aluno_id" class="form-label">Aluno *</label>
                    <select class="form-select" id="aluno_id" name="aluno_id" required>
                        <option value="">Selecione um aluno...</option>
                        <?php foreach ($alunos as $aluno): ?>
                            <option value="<?= $aluno['id'] ?>" <?= ($avaliacao['aluno_id'] == $aluno['id']) ? 'selected' : '' ?>>
                                <?= sanitizar($aluno['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="data_avaliacao" class="form-label">Data da Avaliação *</label>
                    <input type="date" class="form-control" id="data_avaliacao" name="data_avaliacao" 
                           value="<?= $avaliacao['data_avaliacao'] ?>" required>
                </div>
                <div class="col-md-2">
                    <label for="peso" class="form-label">Peso (kg) *</label>
                    <input type="number" class="form-control" id="peso" name="peso" 
                           step="0.01" min="0" placeholder="0,00" required
                           value="<?= number_format($avaliacao['peso'], 2, '.', '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="altura" class="form-label">Altura (m) *</label>
                    <input type="number" class="form-control" id="altura" name="altura" 
                           step="0.01" min="0" max="3" placeholder="0,00" required
                           value="<?= number_format($avaliacao['altura'], 2, '.', '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">IMC</label>
                    <input type="text" class="form-control bg-light" id="imc_display" value="<?= number_format($avaliacao['imc'], 2, ',', '.') ?>" readonly>
                    <input type="hidden" name="imc" id="imc" value="<?= $avaliacao['imc'] ?>">
                </div>
            </div>
            
            <!-- Seção 2: Composição Corporal -->
            <h6 class="text-muted mb-3 border-bottom pb-2">
                <i class="bi bi-pie-chart me-2"></i>Composição Corporal
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="percentual_gordura" class="form-label">% Gordura</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="percentual_gordura" name="percentual_gordura" 
                               step="0.1" min="0" max="100" placeholder="0,0"
                               value="<?= $avaliacao['percentual_gordura'] ? number_format($avaliacao['percentual_gordura'], 1, '.', '') : '' ?>">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="massa_magra" class="form-label">Massa Magra (kg)</label>
                    <input type="number" class="form-control" id="massa_magra" name="massa_magra" 
                           step="0.01" min="0" placeholder="0,00"
                           value="<?= $avaliacao['massa_magra'] ? number_format($avaliacao['massa_magra'], 2, '.', '') : '' ?>">
                </div>
            </div>
            
            <!-- Seção 3: Perímetros Corporais -->
            <h6 class="text-muted mb-3 border-bottom pb-2">
                <i class="bi bi-rulers me-2"></i>Perímetros Corporais (cm)
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="ombro" class="form-label">Ombro</label>
                    <input type="number" class="form-control" id="ombro" name="ombro" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['ombro'] ? number_format($avaliacao['ombro'], 1, '.', '') : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="torax" class="form-label">Tórax</label>
                    <input type="number" class="form-control" id="torax" name="torax" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['torax'] ? number_format($avaliacao['torax'], 1, '.', '') : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="cintura" class="form-label">Cintura</label>
                    <input type="number" class="form-control" id="cintura" name="cintura" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['cintura'] ? number_format($avaliacao['cintura'], 1, '.', '') : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="abdomen" class="form-label">Abdômen</label>
                    <input type="number" class="form-control" id="abdomen" name="abdomen" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['abdomen'] ? number_format($avaliacao['abdomen'], 1, '.', '') : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="quadril" class="form-label">Quadril</label>
                    <input type="number" class="form-control" id="quadril" name="quadril" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['quadril'] ? number_format($avaliacao['quadril'], 1, '.', '') : '' ?>">
                </div>
            </div>
            
            <!-- Braços -->
            <div class="row g-3 mb-2">
                <div class="col-12"><strong class="text-muted">Braços</strong></div>
                <div class="col-md-3">
                    <label for="braco_direito" class="form-label small">Direito</label>
                    <input type="number" class="form-control" id="braco_direito" name="braco_direito" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['braco_direito'] ? number_format($avaliacao['braco_direito'], 1, '.', '') : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="braco_esquerdo" class="form-label small">Esquerdo</label>
                    <input type="number" class="form-control" id="braco_esquerdo" name="braco_esquerdo" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['braco_esquerdo'] ? number_format($avaliacao['braco_esquerdo'], 1, '.', '') : '' ?>">
                </div>
            </div>
            
            <!-- Coxas -->
            <div class="row g-3 mb-2">
                <div class="col-12"><strong class="text-muted">Coxas</strong></div>
                <div class="col-md-3">
                    <label for="coxa_direita" class="form-label small">Direita</label>
                    <input type="number" class="form-control" id="coxa_direita" name="coxa_direita" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['coxa_direita'] ? number_format($avaliacao['coxa_direita'], 1, '.', '') : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="coxa_esquerda" class="form-label small">Esquerda</label>
                    <input type="number" class="form-control" id="coxa_esquerda" name="coxa_esquerda" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['coxa_esquerda'] ? number_format($avaliacao['coxa_esquerda'], 1, '.', '') : '' ?>">
                </div>
            </div>
            
            <!-- Panturrilhas -->
            <div class="row g-3 mb-4">
                <div class="col-12"><strong class="text-muted">Panturrilhas</strong></div>
                <div class="col-md-3">
                    <label for="panturrilha_direita" class="form-label small">Direita</label>
                    <input type="number" class="form-control" id="panturrilha_direita" name="panturrilha_direita" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['panturrilha_direita'] ? number_format($avaliacao['panturrilha_direita'], 1, '.', '') : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="panturrilha_esquerda" class="form-label small">Esquerda</label>
                    <input type="number" class="form-control" id="panturrilha_esquerda" name="panturrilha_esquerda" 
                           step="0.1" placeholder="0,0"
                           value="<?= $avaliacao['panturrilha_esquerda'] ? number_format($avaliacao['panturrilha_esquerda'], 1, '.', '') : '' ?>">
                </div>
            </div>
            
            <!-- Seção 4: Testes Físicos -->
            <h6 class="text-muted mb-3 border-bottom pb-2">
                <i class="bi bi-activity me-2"></i>Testes Físicos
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="flexibilidade" class="form-label">Flexibilidade (cm)</label>
                    <input type="number" class="form-control" id="flexibilidade" name="flexibilidade" 
                           placeholder="0"
                           value="<?= $avaliacao['flexibilidade'] ?>">
                </div>
                <div class="col-md-4">
                    <label for="flexoes" class="form-label">Flexões (repetições)</label>
                    <input type="number" class="form-control" id="flexoes" name="flexoes" 
                           placeholder="0"
                           value="<?= $avaliacao['flexoes'] ?>">
                </div>
                <div class="col-md-4">
                    <label for="abdominal" class="form-label">Abdominal (repetições)</label>
                    <input type="number" class="form-control" id="abdominal" name="abdominal" 
                           placeholder="0"
                           value="<?= $avaliacao['abdominal'] ?>">
                </div>
            </div>
            
            <!-- Observações -->
            <h6 class="text-muted mb-3 border-bottom pb-2">
                <i class="bi bi-chat-left-text me-2"></i>Observações
            </h6>
            <div class="mb-4">
                <label for="observacoes" class="form-label">Observações e Recomendações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="4" 
                          placeholder="Descreva observações relevantes sobre a avaliação..."><?= sanitizar($avaliacao['observacoes']) ?></textarea>
            </div>
            
            <!-- Botões -->
            <div class="d-flex justify-content-end gap-2">
                <a href="visualizar.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Script para cálculo automático de IMC -->
<script>
function calcularIMC() {
    const peso = parseFloat(document.getElementById('peso').value.replace(',', '.'));
    const altura = parseFloat(document.getElementById('altura').value.replace(',', '.'));
    
    if (peso > 0 && altura > 0) {
        const imc = peso / (altura * altura);
        const imcArredondado = imc.toFixed(2);
        document.getElementById('imc_display').value = imcArredondado.replace('.', ',');
        document.getElementById('imc').value = imcArredondado;
    } else {
        document.getElementById('imc_display').value = '-';
        document.getElementById('imc').value = '';
    }
}

document.getElementById('peso').addEventListener('input', calcularIMC);
document.getElementById('altura').addEventListener('input', calcularIMC);
</script>

<?php include '../includes/footer.php'; ?>
