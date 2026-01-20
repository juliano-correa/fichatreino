<?php
/**
 * Script para Adicionar coluna gym_id na tabela workouts
 * Necessário para o sistema multi-tenant funcionar corretamente
 */

require_once '../config/conexao.php';
require_once '../config/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔧 Correção da Tabela workouts</h2>";
echo "<hr>";

$gym_id = getGymId();
echo "<p>ID da Academia atual: <strong>$gym_id</strong></p>";

// 1. Verificar estrutura atual da tabela
echo "<h3>1. Estrutura Atual da Tabela workouts:</h3>";

try {
    $sql = "DESCRIBE workouts";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table class='table table-bordered table-striped'>";
    echo "<thead><tr><th>Coluna</th><th>Tipo</th><th>Nullable</th><th>Padrão</th></tr></thead>";
    echo "<tbody>";

    $has_gym_id = false;
    $has_aluno_id = false;

    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . ($col['Null'] === 'YES' ? 'Sim' : 'Não') . "</td>";
        echo "<td>" . ($col['Default'] ?? 'Nenhum') . "</td>";
        echo "</tr>";

        if ($col['Field'] === 'gym_id') {
            $has_gym_id = true;
        }
        if ($col['Field'] === 'aluno_id') {
            $has_aluno_id = true;
        }
    }

    echo "</tbody></table>";

} catch (PDOException $e) {
    echo "<p class='text-danger'>Erro ao verificar estrutura: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 2. Verificar registros existentes
echo "<h3>2. Registros na Tabela workouts:</h3>";

try {
    $sql_count = "SELECT COUNT(*) as total FROM workouts";
    $stmt_count = $pdo->query($sql_count);
    $count = $stmt_count->fetch(PDO::FETCH_ASSOC);

    echo "<p>Total de registros: <strong>" . $count['total'] . "</strong></p>";

    if ($count['total'] > 0) {
        // Mostrar alguns registros
        $sql_sample = "SELECT * FROM workouts LIMIT 5";
        $stmt_sample = $pdo->query($sql_sample);
        $samples = $stmt_sample->fetchAll(PDO::FETCH_ASSOC);

        echo "<table class='table table-bordered table-sm'>";
        echo "<thead><tr>";
        foreach ($samples[0] as $key => $value) {
            echo "<th>" . $key . "</th>";
        }
        echo "</tr></thead>";
        echo "<tbody>";

        foreach ($samples as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }

        echo "</tbody></table>";
    }

} catch (PDOException $e) {
    echo "<p class='text-danger'>Erro ao verificar registros: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 3. Verificar se as colunas necessárias existem
echo "<h3>3. Verificação de Colunas Necessárias:</h3>";

if (!$has_gym_id) {
    echo "<p class='text-danger'>❌ A coluna <strong>gym_id</strong> NÃO existe!</p>";

    // Verificar se há registros
    if ($count['total'] > 0) {
        echo "<div class='alert alert-warning'>";
        echo "<strong>Atenção!</strong> A tabela já contém registros.";
        echo "<br>Ao adicionar a coluna <strong>gym_id</strong>, TODOS os registros receberão o valor do gym_id atual (<strong>$gym_id</strong>).";
        echo "</div>";
    }

    echo "<form method='POST'>";
    echo "<input type='hidden' name='add_gym_id' value='1'>";
    echo "<button type='submit' class='btn btn-warning btn-lg'>";
    echo "<i class='bi bi-plus-circle me-2'></i>";
    echo "ADICIONAR COLUNA gym_id";
    echo "</button>";
    echo "</form>";

} else {
    echo "<p class='text-success'>✅ A coluna <strong>gym_id</strong> já existe!</p>";
}

if (!$has_aluno_id) {
    echo "<p class='text-danger'>❌ A coluna <strong>aluno_id</strong> NÃO existe!</p>";
} else {
    echo "<p class='text-success'>✅ A coluna <strong>aluno_id</strong> já existe!</p>";
}

echo "<hr>";

// 4. Executar a correção
if (isset($_POST['add_gym_id']) && $_POST['add_gym_id'] == '1') {
    echo "<h3>🔧 Executando Correção...</h3>";

    try {
        $pdo->beginTransaction();

        // Verificar se gym_id já existe (pode ter sido adicionada entre a verificação e o submit)
        $sql_check = "SHOW COLUMNS FROM workouts LIKE 'gym_id'";
        $stmt_check = $pdo->query($sql_check);

        if ($stmt_check->rowCount() == 0) {
            // Adicionar coluna gym_id
            $sql_add = "ALTER TABLE workouts ADD COLUMN gym_id INT NOT NULL DEFAULT $gym_id AFTER id";
            $pdo->exec($sql_add);
            echo "<p class='text-success'>✅ Coluna <strong>gym_id</strong> adicionada com sucesso!</p>";

            // Criar índice para melhorar performance
            $sql_index = "ALTER TABLE workouts ADD INDEX idx_gym_id (gym_id)";
            $pdo->exec($sql_index);
            echo "<p class='text-success'>✅ Índice criado para <strong>gym_id</strong>!</p>";
        } else {
            echo "<p class='text-warning'>⚠️ A coluna <strong>gym_id</strong> já existe!</p>";
        }

        $pdo->commit();
        echo "<div class='alert alert-success mt-3'>";
        echo "<strong>Correção concluída!</strong>";
        echo "<br>Agora você pode testar o módulo de fichas de treino.";
        echo "</div>";

        echo "<p><a href='../fichas_treino/index.php' class='btn btn-primary me-2'>Testar Fichas de Treino</a></p>";
        echo "<p><a href='debug_fichas.php' class='btn btn-outline-secondary'>Verificar Novamente</a></p>";

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>";
        echo "<strong>Erro ao executar correção:</strong>";
        echo "<br>" . $e->getMessage();
        echo "</div>";
    }
}

echo "<hr>";
echo "<p><a href='../fichas_treino/index.php' class='btn btn-outline-primary'>Voltar para Fichas de Treino</a></p>";

?>
