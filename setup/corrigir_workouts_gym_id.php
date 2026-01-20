<?php
/**
 * Script de Correção - Adicionar coluna gym_id à tabela workouts
 * Executar: https://fichaonline.gt.tc/setup/corrigir_workouts_gym_id.php
 */

require_once '../config/conexao.php';

$mensagens = [];
$erros = [];

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Correção - Tabela Workouts</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>";
echo "<style>body { background: #f5f5f5; padding: 20px; }</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<div class='row justify-content-center'>";
echo "<div class='col-md-8'>";
echo "<div class='card shadow-sm'>";
echo "<div class='card-header bg-primary text-white'>";
echo "<h5 class='mb-0'><i class='bi bi-tools me-2'></i>Correção da Tabela Workouts</h5>";
echo "</div>";
echo "<div class='card-body'>";

try {
    // Verificar se a coluna gym_id existe
    $stmt = $pdo->query("SHOW COLUMNS FROM workouts LIKE 'gym_id'");
    $coluna_existe = $stmt->fetch() !== false;
    
    if ($coluna_existe) {
        echo "<div class='alert alert-success'>";
        echo "<i class='bi bi-check-circle me-2'></i>";
        echo "A coluna 'gym_id' já existe na tabela 'workouts'. Nenhuma ação necessária.";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>";
        echo "<i class='bi bi-exclamation-triangle me-2'></i>";
        echo "A coluna 'gym_id' NÃO existe na tabela 'workouts'. Tentando adicionar...";
        echo "</div>";
        
        // Adicionar a coluna gym_id
        $pdo->exec("ALTER TABLE workouts ADD COLUMN gym_id INT NOT NULL DEFAULT 1 AFTER id");
        
        // Adicionar índice para performance
        $pdo->exec("ALTER TABLE workouts ADD INDEX idx_workouts_gym_id (gym_id)");
        
        echo "<div class='alert alert-success'>";
        echo "<i class='bi bi-check-circle me-2'></i>";
        echo "Coluna 'gym_id' adicionada com sucesso!";
        echo "</div>";
    }
    
    // Verificar outras colunas importantes
    $colunas_verificar = [
        'ativa' => ['tipo' => 'BOOLEAN', 'default' => 'TRUE'],
        'instrutor_id' => ['tipo' => 'INT', 'default' => 'NULL'],
        'exercicios' => ['tipo' => 'JSON', 'default' => 'NULL']
    ];
    
    echo "<h6 class='mt-4 mb-3'>Verificando outras colunas:</h6>";
    
    foreach ($colunas_verificar as $coluna => $info) {
        $stmt = $pdo->query("SHOW COLUMNS FROM workouts LIKE '$coluna'");
        $existe = $stmt->fetch() !== false;
        
        if ($existe) {
            echo "<div class='d-flex align-items-center mb-2 text-success'>";
            echo "<i class='bi bi-check-circle me-2'></i>";
            echo "Coluna '$coluna' - OK";
            echo "</div>";
        } else {
            echo "<div class='d-flex align-items-center mb-2 text-warning'>";
            echo "<i class='bi bi-exclamation-circle me-2'></i>";
            echo "Coluna '$coluna' - Faltando, adicionando...";
            echo "</div>";
            
            try {
                if ($info['tipo'] === 'BOOLEAN') {
                    $pdo->exec("ALTER TABLE workouts ADD COLUMN {$coluna} TINYINT(1) DEFAULT {$info['default']}");
                } elseif ($info['tipo'] === 'INT') {
                    $pdo->exec("ALTER TABLE workouts ADD COLUMN {$coluna} INT DEFAULT {$info['default']}");
                } elseif ($info['tipo'] === 'JSON') {
                    $pdo->exec("ALTER TABLE workouts ADD COLUMN {$coluna} TEXT DEFAULT {$info['default']}");
                }
                echo "<div class='text-success small mb-2'>✓ Adicionada coluna '$coluna'</div>";
            } catch (PDOException $e) {
                echo "<div class='text-danger small mb-2'>✗ Erro ao adicionar '$coluna': " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // Verificar chave estrangeira para students
    echo "<h6 class='mt-4 mb-3'>Verificando integridade referencial:</h6>";
    
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM workouts w 
            LEFT JOIN students s ON w.aluno_id = s.id 
            WHERE w.aluno_id IS NOT NULL AND s.id IS NULL
        ");
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            echo "<div class='alert alert-warning'>";
            echo "<i class='bi bi-exclamation-triangle me-2'></i>";
            echo "Encontradas {$result['total']} fichas com aluno_id inexistente. Corrigindo...";
            echo "</div>";
            
            // Atualizar registros órfãos
            $pdo->exec("UPDATE workouts SET aluno_id = NULL WHERE aluno_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM students WHERE id = workouts.aluno_id)");
            
            echo "<div class='alert alert-success'>Registros órfãos corrigidos.</div>";
        } else {
            echo "<div class='d-flex align-items-center mb-2 text-success'>";
            echo "<i class='bi bi-check-circle me-2'></i>";
            echo "Integridade referencial OK - Todos os alunos existem";
            echo "</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='text-muted small'>Nota: " . $e->getMessage() . "</div>";
    }
    
    echo "<hr>";
    echo "<a href='../fichas_treino/index.php' class='btn btn-primary'>";
    echo "<i class='bi bi-arrow-left me-2'></i>Voltar para Fichas de Treino";
    echo "</a>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<i class='bi bi-x-circle me-2'></i>";
    echo "Erro: " . $e->getMessage();
    echo "</div>";
}

echo "</div></div></div></div></body></html>";
