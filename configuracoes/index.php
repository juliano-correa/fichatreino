<?php
// Configurações
$titulo_pagina = 'Configurações';
$subtitulo_pagina = 'Configurações da Academia';

// auth_check.php já inclui conexao.php e functions.php
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Diretório para uploads de logos
$logo_upload_dir = __DIR__ . '/../assets/uploads/logos/';
if (!is_dir($logo_upload_dir)) {
    mkdir($logo_upload_dir, 0755, true);
}

// Processar atualização de dados da academia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_academia') {
    $nome = trim($_POST['nome'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    
    if (empty($nome)) {
        $error = 'O nome da academia é obrigatório.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE gyms SET 
                nome = :nome, 
                cnpj = :cnpj, 
                telefone = :telefone, 
                whatsapp = :whatsapp, 
                email = :email, 
                endereco = :endereco, 
                cidade = :cidade, 
                estado = :estado 
                WHERE id = :gym_id");
            $stmt->execute([
                ':gym_id' => getGymId(),
                ':nome' => $nome,
                ':cnpj' => $cnpj,
                ':telefone' => $telefone,
                ':whatsapp' => $whatsapp,
                ':email' => $email,
                ':endereco' => $endereco,
                ':cidade' => $cidade,
                ':estado' => $estado
            ]);
            $success = 'Dados da academia atualizados com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar dados: ' . $e->getMessage();
        }
    }
}

// Processar upload de logotipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'upload_logo') {
    $logo_texto = trim($_POST['logo_texto'] ?? '');
    
    // Verificar se quer remover o logo
    $remover_logo = isset($_POST['remover_logo']);
    
    if ($remover_logo) {
        // Remover logo atual
        try {
            $stmt = $pdo->prepare("SELECT logo_path FROM gyms WHERE id = :gym_id");
            $stmt->execute([':gym_id' => getGymId()]);
            $current_logo = $stmt->fetch();
            
            if ($current_logo && !empty($current_logo['logo_path']) && file_exists(__DIR__ . '/..' . $current_logo['logo_path'])) {
                unlink(__DIR__ . '/..' . $current_logo['logo_path']);
            }
            
            $stmt = $pdo->prepare("UPDATE gyms SET logo_path = NULL, logo_tipo = 'texto' WHERE id = :gym_id");
            $stmt->execute([':gym_id' => getGymId()]);
            $success = 'Logotipo removido com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao remover logotipo: ' . $e->getMessage();
        }
    } elseif (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_file'];
        
        // Validar tipo de arquivo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Tipo de arquivo não permitido. Envie apenas imagens JPG, PNG, GIF ou SVG.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'O arquivo é muito grande. O tamanho máximo é 2MB.';
        } else {
            // Gerar nome único para o arquivo
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo_' . getGymId() . '_' . time() . '.' . $extension;
            $upload_path = '/assets/uploads/logos/' . $new_filename;
            $full_path = __DIR__ . '/..' . $upload_path;
            
            if (move_uploaded_file($file['tmp_name'], $full_path)) {
                try {
                    // Remover logo antigo se existir
                    $stmt = $pdo->prepare("SELECT logo_path FROM gyms WHERE id = :gym_id");
                    $stmt->execute([':gym_id' => getGymId()]);
                    $current_logo = $stmt->fetch();
                    
                    if ($current_logo && !empty($current_logo['logo_path']) && file_exists(__DIR__ . '/..' . $current_logo['logo_path'])) {
                        unlink(__DIR__ . '/..' . $current_logo['logo_path']);
                    }
                    
                    // Atualizar banco de dados
                    $stmt = $pdo->prepare("UPDATE gyms SET logo_path = :logo_path, logo_tipo = 'imagem', logo_texto = :logo_texto WHERE id = :gym_id");
                    $stmt->execute([
                        ':gym_id' => getGymId(),
                        ':logo_path' => $upload_path,
                        ':logo_texto' => !empty($logo_texto) ? $logo_texto : null
                    ]);
                    $success = 'Logotipo enviado com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao salvar no banco de dados: ' . $e->getMessage();
                }
            } else {
                $error = 'Erro ao fazer upload do arquivo.';
            }
        }
    } elseif (!empty($logo_texto)) {
        // Apenas atualizar o texto alternativo
        try {
            $stmt = $pdo->prepare("UPDATE gyms SET logo_texto = :logo_texto WHERE id = :gym_id");
            $stmt->execute([
                ':gym_id' => getGymId(),
                ':logo_texto' => $logo_texto
            ]);
            $success = 'Texto do logotipo atualizado com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar texto: ' . $e->getMessage();
        }
    }
}

// Processar atualização de senha do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_senha') {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
        $error = 'Todos os campos de senha são obrigatórios.';
    } elseif ($nova_senha !== $confirmar_senha) {
        $error = 'A nova senha e a confirmação não coincidem.';
    } elseif (strlen($nova_senha) < 6) {
        $error = 'A nova senha deve ter pelo menos 6 caracteres.';
    } else {
        // Verificar senha atual
        $stmt = $pdo->prepare("SELECT senha FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($senha_atual, $user['senha'])) {
            // Atualizar senha
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET senha = :senha WHERE id = :user_id");
            $stmt->execute([':senha' => $nova_senha_hash, ':user_id' => $_SESSION['user_id']]);
            $success = 'Senha atualizada com sucesso!';
        } else {
            $error = 'Senha atual incorreta.';
        }
    }
}

// Processar atualização de configurações do sistema
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_sistema') {
    $checkin_duracao_maxima = $_POST['checkin_duracao_maxima'] ?? null;
    
    if (!empty($checkin_duracao_maxima) && $checkin_duracao_maxima < 1) {
        $error = 'A duração máxima deve ser pelo menos 1 hora.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE gyms SET checkin_duracao_maxima = :duracao WHERE id = :gym_id");
            $stmt->execute([
                ':gym_id' => getGymId(),
                ':duracao' => !empty($checkin_duracao_maxima) ? (int)$checkin_duracao_maxima : null
            ]);
            $success = 'Configurações do sistema atualizadas com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar configurações: ' . $e->getMessage();
        }
    }
}

// Obter dados da academia
$stmt = $pdo->prepare("SELECT * FROM gyms WHERE id = :gym_id");
$stmt->execute([':gym_id' => getGymId()]);
$gym = $stmt->fetch();

// Obter dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Estados brasileiros
$estados = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];
?>

<?php include '../includes/header.php'; ?>

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

<!-- Abas de Navegação -->
<ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="identidade-tab" data-bs-toggle="tab" data-bs-target="#identidade" type="button">
            <i class="bi bi-palette me-2"></i>Identidade Visual
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="academia-tab" data-bs-toggle="tab" data-bs-target="#academia" type="button">
            <i class="bi bi-building me-2"></i>Dados da Academia
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="conta-tab" data-bs-toggle="tab" data-bs-target="#conta" type="button">
            <i class="bi bi-person-gear me-2"></i>Minha Conta
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="sistema-tab" data-bs-toggle="tab" data-bs-target="#sistema" type="button">
            <i class="bi bi-sliders me-2"></i>Sistema
        </button>
    </li>
</ul>

<!-- Conteúdo das Abas -->
<div class="tab-content" id="configTabsContent">
    
    <!-- Aba Identidade Visual -->
    <div class="tab-pane fade show active" id="identidade" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-palette me-2"></i>Identidade Visual da Academia</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="upload_logo">
                    
                    <!-- Preview do Logo Atual -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Logotipo Atual</label>
                        <div class="border rounded p-4 text-center bg-light" style="max-width: 400px;">
                            <?php if (!empty($gym['logo_path'])): ?>
                                <img src="<?= $gym['logo_path'] ?>" alt="Logotipo" class="img-fluid" style="max-height: 100px;">
                            <?php else: ?>
                                <div class="text-muted">
                                    <i class="bi bi-image fs-1"></i>
                                    <p class="mb-0">Nenhum logotipo cadastrado</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Upload de Nova Logo -->
                    <div class="mb-3">
                        <label for="logo_file" class="form-label fw-bold">Enviar Novo Logotipo</label>
                        <input type="file" class="form-control" id="logo_file" name="logo_file" accept="image/jpeg,image/png,image/gif,image/svg+xml">
                        <small class="text-muted">Formatos aceitos: JPG, PNG, GIF, SVG. Tamanho máximo: 2MB. Dimensão recomendada: 200x60px.</small>
                    </div>
                    
                    <!-- Texto Alternativo -->
                    <div class="mb-3">
                        <label for="logo_texto" class="form-label">Nome da Academia (para exibir se não houver imagem)</label>
                        <input type="text" class="form-control" id="logo_texto" name="logo_texto" 
                               value="<?= sanitizar($gym['logo_texto'] ?? '') ?>" 
                               placeholder="Ex: Academia Titanium">
                        <small class="text-muted">Este texto será usado como nome da academia no login e no cabeçalho.</small>
                    </div>
                    
                    <!-- Botões -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Enviar Logotipo
                        </button>
                        
                        <?php if (!empty($gym['logo_path'])): ?>
                            <button type="submit" name="remover_logo" value="1" class="btn btn-outline-danger" 
                                    onclick="return confirm('Tem certeza que deseja remover o logotipo?')">
                                <i class="bi bi-trash me-2"></i>Remover Logotipo
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Preview em Tempo Real -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Prévia</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Veja como ficará o nome da sua academia:</p>
                
                <!-- Preview Login -->
                <div class="border rounded p-4 mb-3 bg-light">
                    <h6 class="mb-2">Tela de Login:</h6>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($gym['logo_path'])): ?>
                            <img src="<?= $gym['logo_path'] ?>" alt="Logo" style="max-height: 40px;">
                        <?php endif; ?>
                        <span class="h4 mb-0 <?= empty($gym['logo_path']) ? 'text-primary' : '' ?>">
                            <?= sanitizar($gym['logo_texto'] ?: $gym['nome']) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Preview Sidebar -->
                <div class="border rounded p-4 bg-dark text-white">
                    <h6 class="mb-2 text-muted">Sidebar:</h6>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($gym['logo_path'])): ?>
                            <img src="<?= $gym['logo_path'] ?>" alt="Logo" style="max-height: 35px;">
                        <?php endif; ?>
                        <span class="h5 mb-0 <?= empty($gym['logo_path']) ? 'text-primary' : '' ?>">
                            <?= sanitizar($gym['logo_texto'] ?: $gym['nome']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Aba Dados da Academia -->
    <div class="tab-pane fade" id="academia" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Informações da Academia</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="atualizar_academia">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label fw-bold">Nome da Academia *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= sanitizar($gym['nome'] ?? '') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="cnpj" class="form-label">CNPJ</label>
                            <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= sanitizar($gym['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= sanitizar($gym['email'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" value="<?= sanitizar($gym['telefone'] ?? '') ?>" placeholder="(00) 0000-0000">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="whatsapp" class="form-label">WhatsApp</label>
                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" value="<?= sanitizar($gym['whatsapp'] ?? '') ?>" placeholder="(00) 00000-0000">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="cidade" class="form-label">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" value="<?= sanitizar($gym['cidade'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Selecione...</option>
                                <?php foreach ($estados as $sigla => $nome): ?>
                                    <option value="<?= $sigla ?>" <?= ($gym['estado'] ?? '') === $sigla ? 'selected' : '' ?>><?= $nome ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="endereco" class="form-label">Endereço</label>
                            <input type="text" class="form-control" id="endereco" name="endereco" value="<?= sanitizar($gym['endereco'] ?? '') ?>" placeholder="Rua, número, bairro">
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Salvar Alterações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Aba Minha Conta -->
    <div class="tab-pane fade" id="conta" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Informações do Usuário</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" value="<?= sanitizar($user['nome'] ?? '') ?>" readonly>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" value="<?= sanitizar($user['email'] ?? '') ?>" readonly>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Perfil</label>
                        <input type="text" class="form-control" value="<?= ($user['role'] ?? '') === 'admin' ? 'Administrador' : 'Usuário' ?>" readonly>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i>Alterar Senha</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="atualizar_senha">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="senha_atual" class="form-label fw-bold">Senha Atual *</label>
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="nova_senha" class="form-label fw-bold">Nova Senha *</label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required minlength="6">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="confirmar_senha" class="form-label fw-bold">Confirmar Nova Senha *</label>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Alterar Senha
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Aba Sistema -->
    <div class="tab-pane fade" id="sistema" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Configurações do Sistema</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="atualizar_sistema">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="checkin_duracao_maxima" class="form-label fw-bold">
                                <i class="bi bi-clock-history me-2"></i>Duração Máxima de Check-in
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="checkin_duracao_maxima" name="checkin_duracao_maxima" 
                                       value="<?= $gym['checkin_duracao_maxima'] ?? '' ?>" min="1" max="24" placeholder="Ex: 3">
                                <span class="input-group-text">horas</span>
                            </div>
                            <small class="text-muted">Tempo máximo permitido para permanência na academia. Deixe vazio para ilimitado.</small>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Salvar Configurações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Sistema</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted">Versão do Sistema</label>
                        <input type="text" class="form-control" value="1.0.0" readonly>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label text-muted">Plano Atual</label>
                        <input type="text" class="form-control" value="Profissional" readonly>
                    </div>
                    
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Titanium Gym Manager</strong> - Sistema de Gestão para Academias<br>
                            <small>Desenvolvido para facilitar o gerenciamento da sua academia com ferramentas completas de controle de alunos, financeiro e muito mais.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<style>
/* Estilo melhorado para as abas de configuração */
#configTabs {
    border-bottom: 2px solid #e9ecef;
}

#configTabs .nav-link {
    color: #495057;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-bottom: none;
    margin-right: 4px;
    border-radius: 8px 8px 0 0;
    padding: 12px 20px;
    font-weight: 500;
    transition: all 0.2s ease;
    position: relative;
    top: 2px;
}

#configTabs .nav-link:hover {
    color: #0d6efd;
    background-color: #e9ecef;
    border-color: #dee2e6;
}

#configTabs .nav-link.active {
    color: #0d6efd;
    background-color: #ffffff;
    border-color: #dee2e6;
    border-bottom: 3px solid #0d6efd;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
}

#configTabsContent .tab-pane {
    background: #ffffff;
    border-radius: 8px;
}

/* Melhorar contraste do conteúdo */
.tab-pane:not(.show active) {
    opacity: 0.7;
}
</style>

<?php include '../includes/footer.php'; ?>
