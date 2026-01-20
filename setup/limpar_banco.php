<?php
/**
 * Script de Limpeza e Correção do Banco de Dados
 * Remove foreign keys problemáticas e corrige registros órfãos
 */

require_once '../config/conexao.php';

echo '<h1>Limpeza e Correção do Banco de Dados</h1>';
echo '<p>Este script remove foreign keys problemáticas e corrige registros órfãos.</p>';

try {
    // Desabilitar verificação de foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    echo '<h3>1. Removendo Foreign Keys Problemáticas</h3>';
    
    // Foreign keys da tabela plans
    $foreign_keys_plans = [
        'plans_ibfk_1',
        'plans_gym_id_foreign',
        'plans_modalidade_id_foreign'
    ];
    
    foreach ($foreign_keys_plans as $fk) {
        try {
            $pdo->exec("ALTER TABLE plans DROP FOREIGN KEY $fk");
            echo "<p style='color: green;'>✓ Removida FK: $fk de plans</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    // Foreign keys da tabela students
    $foreign_keys_students = [
        'students_ibfk_1',
        'students_gym_id_foreign',
        'students_plano_atual_id_foreign'
    ];
    
    foreach ($foreign_keys_students as $fk) {
        try {
            $pdo->exec("ALTER TABLE students DROP FOREIGN KEY $fk");
            echo "<p style='color: green;'>✓ Removida FK: $fk de students</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    // Foreign keys da tabela subscriptions
    $foreign_keys_subs = [
        'subscriptions_ibfk_1',
        'subscriptions_gym_id_foreign',
        'subscriptions_aluno_id_foreign',
        'subscriptions_plano_id_foreign'
    ];
    
    foreach ($foreign_keys_subs as $fk) {
        try {
            $pdo->exec("ALTER TABLE subscriptions DROP FOREIGN KEY $fk");
            echo "<p style='color: green;'>✓ Removida FK: $fk de subscriptions</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    // Foreign keys da tabela transactions
    $foreign_keys_trans = [
        'transactions_ibfk_1',
        'transactions_gym_id_foreign',
        'transactions_aluno_id_foreign',
        'transactions_inscricao_id_foreign'
    ];
    
    foreach ($foreign_keys_trans as $fk) {
        try {
            $pdo->exec("ALTER TABLE transactions DROP FOREIGN KEY $fk");
            echo "<p style='color: green;'>✓ Removida FK: $fk de transactions</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    // Foreign keys da tabela assessments
    $foreign_keys_assess = [
        'assessments_ibfk_1',
        'assessments_gym_id_foreign',
        'assessments_aluno_id_foreign',
        'assessments_instrutor_id_foreign'
    ];
    
    foreach ($foreign_keys_assess as $fk) {
        try {
            $pdo->exec("ALTER TABLE assessments DROP FOREIGN KEY $fk");
            echo "<p style='color: green;'>✓ Removida FK: $fk de assessments</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    // Foreign keys da tabela workouts
    $foreign_keys_workouts = [
        'workouts_ibfk_1',
        'workouts_gym_id_foreign',
        'workouts_aluno_id_foreign',
        'workouts_instrutor_id_foreign'
    ];
    
    foreach ($foreign_keys_workouts as $fk) {
        try {
            $pdo->exec("ALTER TABLE workouts DROP FOREIGN KEY $fk");
            echo "<p style='color: green;'>✓ Removida FK: $fk de workouts</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    echo '<h3>2. Removendo Foreign Keys de checkins</h3>';
    foreach (['checkins_ibfk_1', 'checkins_gym_id_foreign', 'checkins_aluno_id_foreign'] as $fk) {
        try {
            $pdo->exec("ALTER TABLE checkins DROP FOREIGN KEY $fk");
            echo "<p style='color: green;'>✓ Removida FK: $fk de checkins</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    echo '<h3>3. Corrigindo Registros Órfãos</h3>';
    
    // Corrigir students.plano_atual_id
    $pdo->exec("UPDATE students s SET s.plano_atual_id = NULL WHERE s.plano_atual_id IS NOT NULL AND s.plano_atual_id NOT IN (SELECT id FROM plans)");
    echo "<p style='color: green;'>✓ Corrigidos plano_atual_id órfãos</p>";
    
    // Corrigir plans.modalidade_id
    $pdo->exec("UPDATE plans p SET p.modalidade_id = NULL WHERE p.modalidade_id IS NOT NULL AND p.modalidade_id NOT IN (SELECT id FROM modalities)");
    echo "<p style='color: green;'>✓ Corrigidos modalidade_id órfãos</p>";
    
    // Corrigir transactions.aluno_id
    $pdo->exec("UPDATE transactions t SET t.aluno_id = NULL WHERE t.aluno_id IS NOT NULL AND t.aluno_id NOT IN (SELECT id FROM students)");
    echo "<p style='color: green;'>✓ Corrigidos transactions.aluno_id órfãos</p>";
    
    // Corrigir subscriptions
    $pdo->exec("UPDATE subscriptions s SET s.aluno_id = NULL WHERE s.aluno_id IS NOT NULL AND s.aluno_id NOT IN (SELECT id FROM students)");
    $pdo->exec("UPDATE subscriptions s SET s.plano_id = NULL WHERE s.plano_id IS NOT NULL AND s.plano_id NOT IN (SELECT id FROM plans)");
    echo "<p style='color: green;'>✓ Corrigidos subscriptions órfãos</p>";
    
    // Corrigir checkins.aluno_id
    $pdo->exec("UPDATE checkins c SET c.aluno_id = NULL WHERE c.aluno_id IS NOT NULL AND c.aluno_id NOT IN (SELECT id FROM students)");
    echo "<p style='color: green;'>✓ Corrigidos checkins.aluno_id órfãos</p>";
    
    // Corrigir assessments.instrutor_id
    $pdo->exec("UPDATE assessments a SET a.instrutor_id = NULL WHERE a.instrutor_id IS NOT NULL AND a.instrutor_id NOT IN (SELECT id FROM users)");
    echo "<p style='color: green;'>✓ Corrigidos assessments.instrutor_id órfãos</p>";
    
    // Corrigir workouts.instrutor_id
    $pdo->exec("UPDATE workouts w SET w.instrutor_id = NULL WHERE w.instrutor_id IS NOT NULL AND w.instrutor_id NOT IN (SELECT id FROM users)");
    echo "<p style='color: green;'>✓ Corrigidos workouts.instrutor_id órfãos</p>";
    
    echo '<h3>4. Removendo ÍNDICES Foreign Keys (se existirem)</h3>';
    
    // Remover índices que podem impedir recriação
    $indices_to_remove = [
        'plans_gym_id_index', 'plans_modalidade_id_index', 'plans_plano_atual_id_index',
        'students_gym_id_index', 'students_plano_atual_id_index',
        'transactions_gym_id_index', 'transactions_aluno_id_index',
        'checkins_gym_id_index', 'checkins_aluno_id_index',
        'assessments_gym_id_index', 'assessments_aluno_id_index',
        'workouts_gym_id_index', 'workouts_aluno_id_index'
    ];
    
    foreach ($indices_to_remove as $index) {
        try {
            $pdo->exec("ALTER TABLE DROP INDEX $index");
            echo "<p style='color: green;'>✓ Removido índice: $index</p>";
        } catch (PDOException $e) {
            // Pode não existir
        }
    }
    
    // Reabilitar foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo '<hr>';
    echo '<h2 style="color: green;">✓ Limpeza Concluída!</h2>';
    echo '<p>As foreign keys problemáticas foram removidas e os registros órfãos foram corrigidos.</p>';
    echo '<p>Agora você deve conseguir salvar dados sem erros de integridade referencial.</p>';
    echo '<p><a href="../dashboard.php" class="btn btn-primary">Ir para o Dashboard</a></p>';
    
} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo '<p style="color: red;">Erro: ' . $e->getMessage() . '</p>';
}
?>
