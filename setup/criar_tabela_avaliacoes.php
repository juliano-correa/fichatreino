<?php
/**
 * Script de criação da tabela de Avaliações Físicas
 * Execute este arquivo pelo navegador: setup/criar_tabela_avaliacoes.php
 */

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$message = '';
$success = false;

try {
    // Verificar se a tabela já existe
    $check_sql = "SHOW TABLES LIKE 'avaliacoes_fisicas'";
    $stmt_check = $pdo->query($check_sql);
    
    if ($stmt_check->rowCount() > 0) {
        $message = 'A tabela "avaliacoes_fisicas" já existe no banco de dados.';
    } else {
        // Criar a tabela
        $sql = "
        CREATE TABLE avaliacoes_fisicas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            avaliador_id INT NOT NULL,
            data_avaliacao DATE NOT NULL,
            
            -- Dados Básicos
            peso_kg DECIMAL(5,2) NOT NULL,
            altura_m DECIMAL(3,2) NOT NULL,
            imc DECIMAL(4,2),
            
            -- Composição Corporal
            gordura_percentual DECIMAL(4,2),
            massa_muscular_kg DECIMAL(5,2),
            agua_corporal_percentual DECIMAL(4,2),
            
            -- Perímetros (cm)
            ombro DECIMAL(5,2),
            torax DECIMAL(5,2),
            braco_direito_relaxado DECIMAL(5,2),
            braco_direito_contraido DECIMAL(5,2),
            braco_esquerdo_relaxado DECIMAL(5,2),
            braco_esquerdo_contraido DECIMAL(5,2),
            cintura DECIMAL(5,2),
            abdomen DECIMAL(5,2),
            quadril DECIMAL(5,2),
            coxa_direita DECIMAL(5,2),
            coxa_esquerda DECIMAL(5,2),
            panturrilha_direita DECIMAL(5,2),
            panturrilha_esquerda DECIMAL(5,2),
            
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_aluno (aluno_id),
            INDEX idx_data (data_avaliacao),
            INDEX idx_gym (aluno_id, data_avaliacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($sql);
        $message = 'Tabela "avaliacoes_fisicas" criada com sucesso!';
        $success = true;
    }
} catch (PDOException $e) {
    $message = 'Erro ao criar tabela: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Avaliações Físicas</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi <?= $success ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?> fs-1"></i>
                            <h4 class="mt-3"><?= $success ? 'Sucesso!' : 'Erro' ?></h4>
                        </div>
                        <p class="text-center"><?= $message ?></p>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <strong>Próximos passos:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>O módulo de Avaliações Físicas está pronto para uso</li>
                                    <li>Atualize a página inicial para ver o novo item no menu</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong>Detalhes do erro:</strong>
                                <pre class="mt-2 mb-0"><?= $message ?></pre>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="../index.php" class="btn btn-primary">
                                <i class="bi bi-house me-2"></i>Voltar ao Início
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
