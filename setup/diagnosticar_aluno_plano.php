<?php
/**
 * Script de Diagnóstico: Vínculo de Aluno com Plano/Modalidade
 */

require_once '../config/conexao.php';

echo '<h1>Diagnóstico: Vínculo Aluno-Plano-Modalidade</h1>';
echo '<p>Este script verifica a estrutura e dados relacionados ao vínculo de alunos.</p>';

try {
    echo '<h3>1. Verificando Estrutura da Tabela students</h3>';
    
    $stmt = $pdo->query("DESCRIBE students");
    $columns = $stmt->fetchAll();
    
    echo '<table class="table table-striped">';
    echo '<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>';
    foreach ($columns as $col) {
        $highlight = '';
        if (in_array($col['Field'], ['plano_atual_id', 'gym_id'])) {
            $highlight = ' style="background-color: #ffffcc;"';
        }
        echo '<tr' . $highlight . '>';
        echo '<td>' . $col['Field'] . '</td>';
        echo '<td>' . $col['Type'] . '</td>';
        echo '<td>' . $col['Null'] . '</td>';
        echo '<td>' . $col['Key'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '<h3>2. Verificando Estrutura da Tabela plans</h3>';
    
    $stmt = $pdo->query("DESCRIBE plans");
    $columns = $stmt->fetchAll();
    
    echo '<table class="table table-striped">';
    echo '<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>';
    foreach ($columns as $col) {
        $highlight = '';
        if (in_array($col['Field'], ['modalidade_id', 'gym_id'])) {
            $highlight = ' style="background-color: #ffffcc;"';
        }
        echo '<tr' . $highlight . '>';
        echo '<td>' . $col['Field'] . '</td>';
        echo '<td>' . $col['Type'] . '</td>';
        echo '<td>' . $col['Null'] . '</td>';
        echo '<td>' . $col['Key'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '<h3>3. Verificando Dados de Planos</h3>';
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM plans");
    $total_plans = $stmt->fetch()['total'];
    
    if ($total_plans == 0) {
        echo '<p style="color: red; font-weight: bold;">⚠ NENHUM PLANO CADASTRADO!</p>';
        echo '<p>Você precisa criar pelo menos um plano antes de vincular alunos.</p>';
        echo '<p><a href="../planos/index.php" class="btn btn-primary">Ir para Planos</a></p>';
    } else {
        echo "<p>Total de planos: $total_plans</p>";
        
        $stmt = $pdo->query("SELECT p.*, m.nome as modalidade_nome FROM plans p LEFT JOIN modalities m ON p.modalidade_id = m.id LIMIT 10");
        $planos = $stmt->fetchAll();
        
        echo '<table class="table table-striped">';
        echo '<tr><th>ID</th><th>Nome</th><th>Modalidade</th><th>Preço</th><th>Gym ID</th></tr>';
        foreach ($planos as $plano) {
            echo '<tr>';
            echo '<td>' . $plano['id'] . '</td>';
            echo '<td>' . htmlspecialchars($plano['nome']) . '</td>';
            echo '<td>' . htmlspecialchars($plano['modalidade_nome'] ?? 'NULL') . '</td>';
            echo '<td>' . $plano['preco'] . '</td>';
            echo '<td>' . $plano['gym_id'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    echo '<h3>4. Verificando Dados de Modalidades</h3>';
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM modalities");
    $total_mods = $stmt->fetch()['total'];
    
    if ($total_mods == 0) {
        echo '<p style="color: red; font-weight: bold;">⚠ NENHUMA MODALIDADE CADASTRADA!</p>';
        echo '<p>Você precisa criar modalidades antes de vincular aos planos.</p>';
        echo '<p><a href="../modalidades/index.php" class="btn btn-primary">Ir para Modalidades</a></p>';
    } else {
        echo "<p>Total de modalidades: $total_mods</p>";
        
        $stmt = $pdo->query("SELECT id, nome, gym_id FROM modalities LIMIT 10");
        $modalidades = $stmt->fetchAll();
        
        echo '<table class="table table-striped">';
        echo '<tr><th>ID</th><th>Nome</th><th>Gym ID</th></tr>';
        foreach ($modalidades as $mod) {
            echo '<tr>';
            echo '<td>' . $mod['id'] . '</td>';
            echo '<td>' . htmlspecialchars($mod['nome']) . '</td>';
            echo '<td>' . $mod['gym_id'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    echo '<h3>5. Verificando Alunos com Plano Vinculado</h3>';
    
    $stmt = $pdo->query("
        SELECT s.id, s.nome, s.plano_atual_id, p.nome as plano_nome 
        FROM students s 
        LEFT JOIN plans p ON s.plano_atual_id = p.id 
        WHERE s.plano_atual_id IS NOT NULL 
        LIMIT 10
    ");
    $alunos_com_plano = $stmt->fetchAll();
    
    if (empty($alunos_com_plano)) {
        echo '<p style="color: orange;">Nenhum aluno com plano vinculado encontrado.</p>';
    } else {
        echo '<table class="table table-striped">';
        echo '<tr><th>Aluno ID</th><th>Nome</th><th>Plano ID</th><th>Plano Nome</th></tr>';
        foreach ($alunos_com_plano as $aluno) {
            echo '<tr>';
            echo '<td>' . $aluno['id'] . '</td>';
            echo '<td>' . htmlspecialchars($aluno['nome']) . '</td>';
            echo '<td>' . $aluno['plano_atual_id'] . '</td>';
            echo '<td>' . htmlspecialchars($aluno['plano_nome'] ?? 'NÃO ENCONTRADO') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    echo '<h3>6. Verificando Plano ID nos Alunos</h3>';
    
    // Verificar alunos com plano_atual_id que não existe em plans
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM students 
        WHERE plano_atual_id IS NOT NULL 
        AND plano_atual_id NOT IN (SELECT id FROM plans)
    ");
    $orphan_plans = $stmt->fetchColumn();
    
    if ($orphan_plans > 0) {
        echo "<p style='color: red;'>⚠ $orphan_planos alunos têm plano_atual_id que não existe na tabela plans!</p>";
        // Corrigir
        $pdo->exec("UPDATE students SET plano_atual_id = NULL WHERE plano_atual_id IS NOT NULL AND plano_atual_id NOT IN (SELECT id FROM plans)");
        echo "<p style='color: green;'>✓ Corrigidos!</p>";
    } else {
        echo "<p style='color: green;'>✓ Todos os alunos têm plano_atual_id válido.</p>";
    }
    
    echo '<hr>';
    echo '<h2>Resumo</h2>';
    echo '<ul>';
    echo '<li>Planos cadastrados: <strong>' . $total_plans . '</strong></li>';
    echo '<li>Modalidades cadastradas: <strong>' . $total_mods . '</strong></li>';
    echo '</ul>';
    
    if ($total_plans == 0) {
        echo '<div class="alert alert-warning">';
        echo '<strong>Ação necessária:</strong> Cadastre pelo menos um plano para poder vincular aos alunos.';
        echo '</div>';
    }
    
    echo '<p><a href="../dashboard.php" class="btn btn-primary">Ir para o Dashboard</a></p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">Erro: ' . $e->getMessage() . '</p>';
}
?>
