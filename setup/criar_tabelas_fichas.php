<?php
/**
 * Script de criação das tabelas de Fichas de Treino
 * Execute este arquivo pelo navegador: setup/criar_tabelas_fichas.php
 */

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

$message = '';
$success = false;

try {
    // Verificar se a tabela já existe
    $check_sql = "SHOW TABLES LIKE 'fichas_treino'";
    $stmt_check = $pdo->query($check_sql);
    
    if ($stmt_check->rowCount() > 0) {
        $message = 'A tabela "fichas_treino" já existe no banco de dados.';
    } else {
        // Criar a tabela de fichas de treino
        $sql_fichas = "
        CREATE TABLE fichas_treino (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            objetivo VARCHAR(100),
            data_inicio DATE NOT NULL,
            data_fim DATE,
            observacoes TEXT,
            status ENUM('ativa', 'arquivada') DEFAULT 'ativa',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_aluno (aluno_id),
            INDEX idx_status (status),
            INDEX idx_data (data_inicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($sql_fichas);
        
        // Criar a tabela de exercícios da ficha
        $sql_exercicios = "
        CREATE TABLE fichas_exercicios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ficha_id INT NOT NULL,
            grupo VARCHAR(50) NOT NULL,
            exercicio VARCHAR(150) NOT NULL,
            series INT DEFAULT 3,
            repeticoes VARCHAR(50) DEFAULT '12',
            carga DECIMAL(5,2) DEFAULT 0,
            descanso_segundos INT DEFAULT 60,
            ordem INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (ficha_id) REFERENCES fichas_treino(id) ON DELETE CASCADE,
            INDEX idx_ficha (ficha_id),
            INDEX idx_grupo (grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($sql_exercicios);
        
        $message = 'Tabelas de Fichas de Treino criadas com sucesso!<br>
                    - fichas_treino (cabeçalho)<br>
                    - fichas_exercícios (itens)';
        $success = true;
    }
} catch (PDOException $e) {
    $message = 'Erro ao criar tabelas: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Fichas de Treino</title>
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
                                    <li>O módulo de Fichas de Treino está pronto para uso</li>
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
