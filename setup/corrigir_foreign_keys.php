<?php
/**
 * Script de Diagnóstico e Correção de Foreign Keys
 * Problema: Integrity constraint violation ao salvar dados
 */

require_once '../config/conexao.php';
require_once '../config/functions.php';

echo '<h1>Diagnóstico de Foreign Keys</h1>';
echo '<p>Este script identifica e corrige problemas de integridade referencial.</p>';

try {
    // Desabilitar verificação de foreign keys temporariamente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    echo '<h3>1. Verificando students com plano_atual_id inválido</h3>';
    $stmt = $pdo->query("
        SELECT s.id, s.nome, s.plano_atual_id 
        FROM students s 
        WHERE s.plano_atual_id IS NOT NULL 
        AND s.plano_atual_id NOT IN (SELECT id FROM plans)
    ");
    $invalid_planos = $stmt->fetchAll();
    
    if (count($invalid_planos) > 0) {
        echo "<p style='color: orange;'>Encontrados " . count($invalid_planos) . " registros com plano_atual_id inválido.</p>";
        foreach ($invalid_planos as $aluno) {
            echo "- Aluno ID {$aluno['id']}: {$aluno['nome']} (plano_atual_id: {$aluno['plano_atual_id']})<br>";
        }
        // Corrigir
        $pdo->exec("UPDATE students SET plano_atual_id = NULL WHERE plano_atual_id IS NOT NULL AND plano_atual_id NOT IN (SELECT id FROM plans)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Nenhum problema encontrado.</p>";
    }
    
    echo '<h3>2. Verificando plans com modalidade_id inválido</h3>';
    $stmt = $pdo->query("
        SELECT p.id, p.nome, p.modalidade_id 
        FROM plans p 
        WHERE p.modalidade_id IS NOT NULL 
        AND p.modalidade_id NOT IN (SELECT id FROM modalities)
    ");
    $invalid_modalidades = $stmt->fetchAll();
    
    if (count($invalid_modalidades) > 0) {
        echo "<p style='color: orange;'>Encontrados " . count($invalid_modalidades) . " registros com modalidade_id inválido.</p>";
        foreach ($invalid_modalidades as $plano) {
            echo "- Plano ID {$plano['id']}: {$plano['nome']} (modalidade_id: {$plano['modalidade_id']})<br>";
        }
        // Corrigir
        $pdo->exec("UPDATE plans SET modalidade_id = NULL WHERE modalidade_id IS NOT NULL AND modalidade_id NOT IN (SELECT id FROM modalities)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Nenhum problema encontrado.</p>";
    }
    
    echo '<h3>3. Verificando transactions com aluno_id inválido</h3>';
    $stmt = $pdo->query("
        SELECT t.id, t.aluno_id, t.descricao 
        FROM transactions t 
        WHERE t.aluno_id IS NOT NULL 
        AND t.aluno_id NOT IN (SELECT id FROM students)
    ");
    $invalid_alunos = $stmt->fetchAll();
    
    if (count($invalid_alunos) > 0) {
        echo "<p style='color: orange;'>Encontradas " . count($invalid_alunos) . " transações com aluno_id inválido.</p>";
        foreach ($invalid_alunos as $trans) {
            echo "- Transação ID {$trans['id']}: {$trans['descricao']} (aluno_id: {$trans['aluno_id']})<br>";
        }
        // Corrigir
        $pdo->exec("UPDATE transactions SET aluno_id = NULL WHERE aluno_id IS NOT NULL AND aluno_id NOT IN (SELECT id FROM students)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Nenhum problema encontrado.</p>";
    }
    
    echo '<h3>4. Verificando checkins com aluno_id inválido</h3>';
    $stmt = $pdo->query("
        SELECT c.id, c.aluno_id 
        FROM checkins c 
        WHERE c.aluno_id NOT IN (SELECT id FROM students)
    ");
    $invalid_checkins = $stmt->fetchAll();
    
    if (count($invalid_checkins) > 0) {
        echo "<p style='color: orange;'>Encontrados " . count($invalid_checkins) . " check-ins com aluno_id inválido.</p>";
        // Corrigir - definir como NULL
        $pdo->exec("UPDATE checkins SET aluno_id = NULL WHERE aluno_id NOT IN (SELECT id FROM students)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Nenhum problema encontrado.</p>";
    }
    
    echo '<h3>5. Verificando subscriptions com registros órfãos</h3>';
    $stmt = $pdo->query("
        SELECT sub.id, sub.aluno_id, sub.plano_id 
        FROM subscriptions sub 
        WHERE sub.aluno_id NOT IN (SELECT id FROM students) 
        OR sub.plano_id NOT IN (SELECT id FROM plans)
    ");
    $invalid_subs = $stmt->fetchAll();
    
    if (count($invalid_subs) > 0) {
        echo "<p style='color: orange;'>Encontradas " . count($invalid_subs) . " subscriptions inválidas.</p>";
        // Corrigir
        $pdo->exec("UPDATE subscriptions SET aluno_id = NULL WHERE aluno_id NOT IN (SELECT id FROM students)");
        $pdo->exec("UPDATE subscriptions SET plano_id = NULL WHERE plano_id NOT IN (SELECT id FROM plans)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Nenhum problema encontrado.</p>";
    }
    
    echo '<h3>6. Verificando assessments com instrutor_id inválido</h3>';
    $stmt = $pdo->query("
        SELECT a.id, a.instrutor_id 
        FROM assessments a 
        WHERE a.instrutor_id IS NOT NULL 
        AND a.instrutor_id NOT IN (SELECT id FROM users)
    ");
    $invalid_assess = $stmt->fetchAll();
    
    if (count($invalid_assess) > 0) {
        echo "<p style='color: orange;'>Encontradas " . count($invalid_assess) . " avaliações com instrutor_id inválido.</p>";
        $pdo->exec("UPDATE assessments SET instrutor_id = NULL WHERE instrutor_id IS NOT NULL AND instrutor_id NOT IN (SELECT id FROM users)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Nenhum problema encontrado.</p>";
    }
    
    echo '<h3>7. Verificando workouts com instrutor_id inválido</h3>';
    $stmt = $pdo->query("
        SELECT w.id, w.instrutor_id 
        FROM workouts w 
        WHERE w.instrutor_id IS NOT NULL 
        AND w.instrutor_id NOT IN (SELECT id FROM users)
    ");
    $invalid_workouts = $stmt->fetchAll();
    
    if (count($invalid_workouts) > 0) {
        echo "<p style='color: orange;'>Encontradas " . count($invalid_workouts) . " fichas com instrutor_id inválido.</p>";
        $pdo->exec("UPDATE workouts SET instrutor_id = NULL WHERE instrutor_id IS NOT NULL AND instrutor_id NOT IN (SELECT id FROM users)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Nenhum problema encontrado.</p>";
    }
    
    // Reabilitar foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo '<hr>';
    echo '<h2 style="color: green;">✓ Diagnóstico e correção concluídos!</h2>';
    echo '<p>Agora tente salvar os dados novamente.</p>';
    echo '<p><a href="../dashboard.php" class="btn btn-primary">Ir para o Dashboard</a></p>';
    
} catch (Exception $e) {
    // Em caso de erro, reabilitar foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo '<p style="color: red;">Erro: ' . $e->getMessage() . '</p>';
    echo '<p>Stack trace: ' . $e->getTraceAsString() . '</p>';
}
?>
