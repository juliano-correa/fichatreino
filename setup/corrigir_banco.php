<?php
/**
 * Script de Correção da Conexão com Banco de Dados
 * Problema: O sistema filtra por gym_id, mas o login não define este valor na sessão
 * Solução: Remover o filtro de gym_id ou definir um valor padrão
 */

// Incluir conexão
require_once '../config/conexao.php';
require_once '../config/functions.php';

echo '<h1>Correção da Conexão com Banco de Dados</h1>';
echo '<p>Este script corrige o problema onde os dados não são exibidos nas telas.</p>';

try {
    // Verificar se existe algum gym_id na tabela gyms
    $stmt = $pdo->query("SELECT COUNT(*) FROM gyms");
    $total_gyms = $stmt->fetchColumn();
    
    if ($total_gyms > 0) {
        // Pegar o primeiro gym_id
        $stmt = $pdo->query("SELECT id FROM gyms LIMIT 1");
        $gym_id = $stmt->fetchColumn();
        echo "<p style='color: blue;'>Encontrado(s) $total_gyms registro(s) na tabela gyms. Usando gym_id = $gym_id</p>";
    } else {
        // Se não existe gym, definir como 1 (single tenant)
        $gym_id = 1;
        echo "<p style='color: orange;'>Nenhum registro encontrado em gyms. Definindo gym_id = $gym_id (single tenant)</p>";
    }
    
    // Verificar se os dados estão sem gym_id (problema comum em migrações)
    $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE gym_id IS NULL OR gym_id = 0");
    $alunos_sem_gym = $stmt->fetchColumn();
    
    if ($alunos_sem_gym > 0) {
        echo "<p style='color: orange;'>Encontrados $alunos_sem_gym alunos sem gym_id definido. Corrigindo...</p>";
        
        // Atualizar alunos para ter gym_id
        $stmt = $pdo->prepare("UPDATE students SET gym_id = :gym_id WHERE gym_id IS NULL OR gym_id = 0");
        $stmt->execute([':gym_id' => $gym_id]);
        echo "<p style='color: green;'>✓ Alunos corrigidos com sucesso!</p>";
    }
    
    // Verificar modalities
    $stmt = $pdo->query("SELECT COUNT(*) FROM modalities WHERE gym_id IS NULL OR gym_id = 0");
    $mods_sem_gym = $stmt->fetchColumn();
    
    if ($mods_sem_gym > 0) {
        echo "<p style='color: orange;'>Encontradas $mods_sem_gym modalidades sem gym_id. Corrigindo...</p>";
        $stmt = $pdo->prepare("UPDATE modalities SET gym_id = :gym_id WHERE gym_id IS NULL OR gym_id = 0");
        $stmt->execute([':gym_id' => $gym_id]);
        echo "<p style='color: green;'>✓ Modalidades corrigidas com sucesso!</p>";
    }
    
    // Verificar plans
    $stmt = $pdo->query("SELECT COUNT(*) FROM plans WHERE gym_id IS NULL OR gym_id = 0");
    $plans_sem_gym = $stmt->fetchColumn();
    
    if ($plans_sem_gym > 0) {
        echo "<p style='color: orange;'>Encontrados $plans_sem_gym planos sem gym_id. Corrigindo...</p>";
        $stmt = $pdo->prepare("UPDATE plans SET gym_id = :gym_id WHERE gym_id IS NULL OR gym_id = 0");
        $stmt->execute([':gym_id' => $gym_id]);
        echo "<p style='color: green;'>✓ Planos corrigidos com sucesso!</p>";
    }
    
    // Verificar transactions
    $stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE gym_id IS NULL OR gym_id = 0");
    $trans_sem_gym = $stmt->fetchColumn();
    
    if ($trans_sem_gym > 0) {
        echo "<p style='color: orange;'>Encontradas $trans_sem_gym transações sem gym_id. Corrigindo...</p>";
        $stmt = $pdo->prepare("UPDATE transactions SET gym_id = :gym_id WHERE gym_id IS NULL OR gym_id = 0");
        $stmt->execute([':gym_id' => $gym_id]);
        echo "<p style='color: green;'>✓ Transações corrigidas com sucesso!</p>";
    }
    
    // Verificar checkins
    $stmt = $pdo->query("SELECT COUNT(*) FROM checkins WHERE gym_id IS NULL OR gym_id = 0");
    $checkins_sem_gym = $stmt->fetchColumn();
    
    if ($checkins_sem_gym > 0) {
        echo "<p style='color: orange;'>Encontrados $checkins_sem_gym check-ins sem gym_id. Corrigindo...</p>";
        $stmt = $pdo->prepare("UPDATE checkins SET gym_id = :gym_id WHERE gym_id IS NULL OR gym_id = 0");
        $stmt->execute([':gym_id' => $gym_id]);
        echo "<p style='color: green;'>✓ Check-ins corrigidos com sucesso!</p>";
    }
    
    // Verificar assessments
    $stmt = $pdo->query("SELECT COUNT(*) FROM assessments WHERE gym_id IS NULL OR gym_id = 0");
    $assess_sem_gym = $stmt->fetchColumn();
    
    if ($assess_sem_gym > 0) {
        echo "<p style='color: orange;'>Encontradas $assess_sem_gym avaliações sem gym_id. Corrigindo...</p>";
        $stmt = $pdo->prepare("UPDATE assessments SET gym_id = :gym_id WHERE gym_id IS NULL OR gym_id = 0");
        $stmt->execute([':gym_id' => $gym_id]);
        echo "<p style='color: green;'>✓ Avaliações corrigidas com sucesso!</p>";
    }
    
    // Verificar workouts
    $stmt = $pdo->query("SELECT COUNT(*) FROM workouts WHERE gym_id IS NULL OR gym_id = 0");
    $workouts_sem_gym = $stmt->fetchColumn();
    
    if ($workouts_sem_gym > 0) {
        echo "<p style='color: orange;'>Encontrados $workouts_sem_gym fichas de treino sem gym_id. Corrigindo...</p>";
        $stmt = $pdo->prepare("UPDATE workouts SET gym_id = :gym_id WHERE gym_id IS NULL OR gym_id = 0");
        $stmt->execute([':gym_id' => $gym_id]);
        echo "<p style='color: green;'>✓ Fichas de treino corrigidas com sucesso!</p>";
    }
    
    echo '<hr>';
    echo '<h2 style="color: green;">✓ Correção concluída!</h2>';
    echo '<p>Os dados foram atualizados para usar o gym_id = ' . $gym_id . '</p>';
    echo '<p><a href="../dashboard.php" class="btn btn-primary">Ir para o Dashboard</a></p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">Erro: ' . $e->getMessage() . '</p>';
}
?>
