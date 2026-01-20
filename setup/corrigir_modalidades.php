<?php
/**
 * Script para Diagnosticar Foreign Key Específica no Cadastro de Modalidades
 */

require_once '../config/conexao.php';

echo '<h1>Diagnóstico: Erro no Cadastro de Modalidades</h1>';
echo '<p>Este script identifica qual foreign key está causando o erro ao salvar modalidades.</p>';

try {
    // Desabilitar foreign keys temporariamente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    echo '<h3>1. Verificando Foreign Keys da Tabela modalities</h3>';
    try {
        $stmt = $pdo->query("
            SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'modalities'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fks = $stmt->fetchAll();
        
        if (empty($fks)) {
            echo '<p style="color: green;">✓ Nenhuma foreign key encontrada em modalities</p>';
        } else {
            echo '<table class="table table-striped">';
            echo '<tr><th>Constraint</th><th>Coluna</th><th>Tabela Referenciada</th><th>Coluna Referenciada</th></tr>';
            foreach ($fks as $fk) {
                echo '<tr>';
                echo '<td>' . $fk['CONSTRAINT_NAME'] . '</td>';
                echo '<td>' . $fk['COLUMN_NAME'] . '</td>';
                echo '<td>' . $fk['REFERENCED_TABLE_NAME'] . '</td>';
                echo '<td>' . $fk['REFERENCED_COLUMN_NAME'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    } catch (PDOException $e) {
        echo '<p>Erro ao verificar FKs: ' . $e->getMessage() . '</p>';
    }
    
    echo '<h3>2. Removendo Foreign Keys de modalities (todas)</h3>';
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'modalities'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($fks as $fk) {
        try {
            $pdo->exec("ALTER TABLE modalities DROP FOREIGN KEY `$fk`");
            echo "<p style='color: green;'>✓ Removida FK: $fk</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>FK $fk não existe ou já foi removida: " . $e->getMessage() . "</p>";
        }
    }
    
    echo '<h3>3. Verificando Foreign Keys da Tabela plans</h3>';
    try {
        $stmt = $pdo->query("
            SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'plans'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fks = $stmt->fetchAll();
        
        echo '<table class="table table-striped">';
        foreach ($fks as $fk) {
            echo "<tr><td>Coluna: {$fk['COLUMN_NAME']}</td><td>-> {$fk['REFERENCED_TABLE_NAME']}</td><td>Constraint: {$fk['CONSTRAINT_NAME']}</td></tr>";
        }
        echo '</table>';
        
        // Remover foreign keys de plans que referenciam modalities
        foreach ($fks as $fk) {
            if ($fk['REFERENCED_TABLE_NAME'] === 'modalities') {
                try {
                    $pdo->exec("ALTER TABLE plans DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                    echo "<p style='color: green;'>✓ Removida FK de plans: {$fk['CONSTRAINT_NAME']} (modalities)</p>";
                } catch (PDOException $e) {
                    echo "<p style='color: orange;'>Erro ao remover: " . $e->getMessage() . "</p>";
                }
            }
        }
    } catch (PDOException $e) {
        echo '<p>Erro: ' . $e->getMessage() . '</p>';
    }
    
    echo '<h3>4. Verificando Estrutura da Tabela modalities</h3>';
    $stmt = $pdo->query("DESCRIBE modalities");
    $columns = $stmt->fetchAll();
    echo '<table class="table table-striped">';
    echo '<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>';
    foreach ($columns as $col) {
        echo '<tr>';
        echo '<td>' . $col['Field'] . '</td>';
        echo '<td>' . $col['Type'] . '</td>';
        echo '<td>' . $col['Null'] . '</td>';
        echo '<td>' . $col['Key'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Reabilitar foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo '<h3>5. Testando Inserção de Modalidade</h3>';
    
    // Testar inserção
    try {
        $stmt = $pdo->prepare("INSERT INTO modalities (gym_id, nome, cor, icone, ativa) VALUES (:gym_id, :nome, :cor, :icone, 1)");
        $stmt->execute([
            ':gym_id' => 1,
            ':nome' => 'TESTE FK - Delete-me',
            ':cor' => '#000000',
            ':icone' => 'test'
        ]);
        $test_id = $pdo->lastInsertId();
        
        echo "<p style='color: green;'>✓ Inserção de teste realizada com sucesso! ID: $test_id</p>";
        
        // Remover registro de teste
        $pdo->exec("DELETE FROM modalities WHERE id = $test_id");
        echo "<p style='color: green;'>✓ Registro de teste removido</p>";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Erro na inserção de teste: " . $e->getMessage() . "</p>";
        echo "<p>Código do erro: " . $e->getCode() . "</p>";
    }
    
    echo '<hr>';
    echo '<h2 style="color: green;">✓ Diagnóstico Concluído!</h2>';
    echo '<p>As foreign keys problemáticas foram removidas.</p>';
    echo '<p>Tente salvar a modalidade novamente.</p>';
    echo '<p><a href="../modalidades/index.php" class="btn btn-primary">Ir para Modalidades</a></p>';
    
} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo '<p style="color: red;">Erro geral: ' . $e->getMessage() . '</p>';
}
?>
