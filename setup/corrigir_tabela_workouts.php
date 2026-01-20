<?php
/**
 * Script de Correção - Tabela Workouts Completa
 * Executar: https://fichaonline.gt.tc/setup/corrigir_tabela_workouts.php
 */

require_once '../config/conexao.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Correção Tabela Workouts</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>";
echo "<style>body { background: #f5f5f5; padding: 20px; } .log-item { padding: 5px 0; border-bottom: 1px solid #eee; }</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<div class='row justify-content-center'>";
echo "<div class='col-md-10'>";
echo "<div class='card shadow-sm'>";
echo "<div class='card-header bg-primary text-white'>";
echo "<h5 class='mb-0'><i class='bi bi-tools me-2'></i>Correção da Tabela Workouts</h5>";
echo "</div>";
echo "<div class='card-body'>";

$logs = [];
$erros = [];

try {
    // Verificar estrutura atual da tabela
    echo "<h6 class='mb-3'>Verificando estrutura da tabela 'workouts':</h6>";
    
    $stmt = $pdo->query("DESCRIBE workouts");
    $colunas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $colunas_existentes_map = array_flip($colunas_existentes);
    
    echo "<div class='mb-3'><strong>Colunas encontradas:</strong> " . implode(', ', $colunas_existentes) . "</div>";
    
    // Colunas necessárias para o sistema
    $colunas_necessarias = [
        'id' => ['tipo' => 'INT AUTO_INCREMENT PRIMARY KEY', 'apos' => null],
        'gym_id' => ['tipo' => 'INT NOT NULL DEFAULT 1', 'apos' => 'id'],
        'aluno_id' => ['tipo' => 'INT', 'apos' => 'gym_id'],
        'instrutor_id' => ['tipo' => 'INT', 'apos' => 'aluno_id'],
        'nome' => ['tipo' => 'VARCHAR(100) NOT NULL', 'apos' => 'instrutor_id'],
        'descricao' => ['tipo' => 'TEXT', 'apos' => 'nome'],
        'exercicios' => ['tipo' => 'TEXT', 'apos' => 'descricao'],
        'frequencia' => ['tipo' => 'VARCHAR(50)', 'apos' => 'exercicios'],
        'objetivo' => ['tipo' => 'VARCHAR(50)', 'apos' => 'frequencia'],
        'ativa' => ['tipo' => 'TINYINT(1) DEFAULT 1', 'apos' => 'objetivo'],
        'created_at' => ['tipo' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'apos' => 'ativa'],
        'updated_at' => ['tipo' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'apos' => 'created_at']
    ];
    
    $colunas_adicionadas = [];
    
    foreach ($colunas_necessarias as $coluna => $info) {
        if (isset($colunas_existentes_map[$coluna])) {
            $logs[] = "<div class='log-item text-success'><i class='bi bi-check-circle me-2'></i>Coluna '$coluna' - Já existe</div>";
        } else {
            try {
                // Determinar posição da coluna
                if ($info['apos'] === null) {
                    $sql_add = "ADD COLUMN $coluna {$info['tipo']} FIRST";
                } else {
                    $sql_add = "ADD COLUMN $coluna {$info['tipo']} AFTER {$info['apos']}";
                }
                
                $pdo->exec("ALTER TABLE workouts $sql_add");
                $colunas_adicionadas[] = $coluna;
                $logs[] = "<div class='log-item text-primary'><i class='bi bi-plus-circle me-2'></i>Coluna '$coluna' - Adicionada com sucesso</div>";
            } catch (PDOException $e) {
                $erros[] = "<div class='log-item text-danger'><i class='bi bi-x-circle me-2'></i>Coluna '$coluna' - Erro: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // Adicionar índices se não existirem
    echo "<h6 class='mt-4 mb-3'>Verificando índices:</h6>";
    
    $indices_desejados = [
        'idx_workouts_gym_id' => 'gym_id',
        'idx_workouts_aluno' => 'aluno_id',
        'idx_workouts_ativa' => 'ativa'
    ];
    
    $stmt = $pdo->query("SHOW INDEX FROM workouts");
    $indices_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $indices_map = [];
    foreach ($indices_existentes as $idx) {
        $indices_map[$idx['Key_name']] = true;
    }
    
    foreach ($indices_desejados as $nome_indice => $coluna) {
        if (!isset($indices_map[$nome_indice])) {
            try {
                $pdo->exec("ALTER TABLE workouts ADD INDEX $nome_indice ($coluna)");
                $logs[] = "<div class='log-item text-primary'><i class='bi bi-plus-circle me-2'></i>Índice '$nome_indice' - Adicionado na coluna '$coluna'</div>";
            } catch (PDOException $e) {
                $logs[] = "<div class='log-item text-muted'><i class='bi bi-info-circle me-2'></i>Índice '$nome_indice' - " . $e->getMessage() . "</div>";
            }
        } else {
            $logs[] = "<div class='log-item text-success'><i class='bi bi-check-circle me-2'></i>Índice '$nome_indice' - Já existe</div>";
        }
    }
    
    // Verificar foreign keys
    echo "<h6 class='mt-4 mb-3'>Verificando chaves estrangeiras:</h6>";
    
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM workouts w 
            LEFT JOIN students s ON w.aluno_id = s.id 
            WHERE w.aluno_id IS NOT NULL AND s.id IS NULL
        ");
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $logs[] = "<div class='log-item text-warning'><i class='bi bi-exclamation-triangle me-2'></i>Encontrados {$result['total']} registros órfãos (aluno_id inexistente)</div>";
            
            // Corrigir órfãos
            $pdo->exec("UPDATE workouts SET aluno_id = NULL WHERE aluno_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM students WHERE id = workouts.aluno_id)");
            $logs[] = "<div class='log-item text-success'><i class='bi bi-check-circle me-2'></i>Registros órfãos corrigidos (aluno_id definido como NULL)</div>";
        } else {
            $logs[] = "<div class='log-item text-success'><i class='bi bi-check-circle me-2'></i>Integridade referencial OK</div>";
        }
    } catch (PDOException $e) {
        $logs[] = "<div class='log-item text-muted'><i class='bi bi-info-circle me-2'></i>Verificação de integridade: " . $e->getMessage() . "</div>";
    }
    
    // Exibir resultados
    echo "<div class='mt-4'>";
    foreach ($logs as $log) {
        echo $log;
    }
    echo "</div>";
    
    if (!empty($erros)) {
        echo "<div class='mt-4'><strong>Erros encontrados:</strong></div>";
        foreach ($erros as $erro) {
            echo $erro;
        }
    }
    
    if (empty($erros) && !empty($colunas_adicionadas)) {
        echo "<div class='alert alert-success mt-4'>";
        echo "<i class='bi bi-check-circle me-2'></i>";
        echo "<strong>Correção concluída!</strong> Colunas adicionadas: " . implode(', ', $colunas_adicionadas);
        echo "</div>";
    } elseif (empty($erros) && empty($colunas_adicionadas)) {
        echo "<div class='alert alert-info mt-4'>";
        echo "<i class='bi bi-info-circle me-2'></i>";
        echo "A tabela 'workouts' já está com todas as colunas necessárias. Nenhuma modificação necessária.";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<div class='d-flex gap-2'>";
    echo "<a href='../fichas_treino/novo.php' class='btn btn-primary'>";
    echo "<i class='bi bi-plus-lg me-2'></i>Testar Nova Ficha";
    echo "</a>";
    echo "<a href='../fichas_treino/index.php' class='btn btn-secondary'>";
    echo "<i class='bi bi-list me-2'></i>Ver Fichas";
    echo "</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<i class='bi bi-x-circle me-2'></i>";
    echo "<strong>Erro fatal:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div></div></div></div></body></html>";
