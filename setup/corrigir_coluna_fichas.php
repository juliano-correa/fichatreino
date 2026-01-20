<?php
/**
 * Script de Correção - Coluna student_id vs aluno_id
 * Diagnostica e corrige problemas de nomenclatura de colunas
 */

require_once '../config/conexao.php';
require_once '../config/functions.php';

$debug = [];
$errors = [];

echo "<h2>Diagnóstico da Estrutura do Banco de Dados</h2>";
echo "<hr>";

// 1. Verificar estrutura da tabela workouts
echo "<h3>1. Verificando tabela 'workouts'...</h3>";
try {
    $sql = "DESCRIBE workouts";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Tabela <strong>workouts</strong> encontrada com as seguintes colunas:</p>";
    echo "<ul>";
    $has_student_id = false;
    $has_aluno_id = false;

    foreach ($columns as $col) {
        echo "<li><strong>" . $col['Field'] . "</strong> - Tipo: " . $col['Type'] . "</li>";
        if ($col['Field'] === 'student_id') {
            $has_student_id = true;
        }
        if ($col['Field'] === 'aluno_id') {
            $has_aluno_id = true;
        }
    }
    echo "</ul>";

    if ($has_student_id && !$has_aluno_id) {
        echo "<p class='text-warning'>⚠️ Encontrada coluna <strong>student_id</strong>, mas não <strong>aluno_id</strong>.</p>";
        echo "<p>Isso pode causar o erro que você está enfrentando.</p>";

        // Verificar se há dados na tabela
        $sql_count = "SELECT COUNT(*) as total FROM workouts";
        $stmt_count = $pdo->query($sql_count);
        $count = $stmt_count->fetch(PDO::FETCH_ASSOC);

        if ($count['total'] > 0) {
            echo "<p class='text-danger'>⚠️ A tabela já contém <strong>" . $count['total'] . "</strong> registros.</p>";
            echo "<p>Para corrigir, precisamos renomear a coluna de <strong>student_id</strong> para <strong>aluno_id</strong>.</p>";
            echo "<form method='POST'>";
            echo "<button type='submit' name='corrigir' value='1' class='btn btn-warning'>Corrigir Coluna student_id → aluno_id</button>";
            echo "</form>";
        }
    } elseif ($has_aluno_id && !$has_student_id) {
        echo "<p class='text-success'>✅ A tabela já tem a coluna <strong>aluno_id</strong> correta!</p>";
    } elseif ($has_student_id && $has_aluno_id) {
        echo "<p class='text-warning'>⚠️ A tabela tem AMBAS as colunas <strong>student_id</strong> e <strong>aluno_id</strong>.</p>";
        echo "<p>Isso pode causar confusão. Precisamos verificar qual coluna tem dados.</p>";
    } else {
        echo "<p class='text-danger'>❌ Nenhuma coluna de aluno encontrada na tabela workouts!</p>";
    }

} catch (PDOException $e) {
    $errors[] = "Erro ao verificar tabela workouts: " . $e->getMessage();
    echo "<p class='text-danger'>Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 2. Verificar estrutura da tabela students
echo "<h3>2. Verificando tabela 'students'...</h3>";
try {
    $sql = "DESCRIBE students";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Tabela <strong>students</strong> encontrada com as seguintes colunas de ID:</p>";
    echo "<ul>";
    foreach ($columns as $col) {
        if (strpos($col['Field'], 'id') !== false || $col['Field'] === 'gym_id') {
            echo "<li><strong>" . $col['Field'] . "</strong> - Tipo: " . $col['Type'] . "</li>";
        }
    }
    echo "</ul>";

} catch (PDOException $e) {
    $errors[] = "Erro ao verificar tabela students: " . $e->getMessage();
    echo "<p class='text-danger'>Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 3. Verificar se há Views que usam student_id
echo "<h3>3. Verificando Views...</h3>";
try {
    $sql = "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()";
    $stmt = $pdo->query($sql);
    $views = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($views) > 0) {
        echo "<p>Views encontradas:</p>";
        echo "<ul>";
        foreach ($views as $view) {
            echo "<li><strong>" . $view . "</strong></li>";

            // Verificar definição da view
            $sql_def = "SELECT VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_NAME = '" . $view . "' AND TABLE_SCHEMA = DATABASE()";
            $stmt_def = $pdo->query($sql_def);
            $def = $stmt_def->fetch(PDO::FETCH_COLUMN);

            if (strpos($def, 'student_id') !== false) {
                echo "<p class='text-warning'>⚠️ A view <strong>" . $view . "</strong> usa <strong>student_id</strong>!</p>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p>Nenhuma view encontrada no banco de dados.</p>";
    }

} catch (PDOException $e) {
    echo "<p class='text-muted'>Não foi possível verificar views: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 4. Processar correção se solicitada
if (isset($_POST['corrigir']) && $_POST['corrigir'] == '1') {
    echo "<h3>🔧 Executando Correção...</h3>";

    try {
        $pdo->beginTransaction();

        // Verificar se a coluna student_id existe e aluno_id não existe
        $sql_check = "SHOW COLUMNS FROM workouts LIKE 'student_id'";
        $stmt_check = $pdo->query($sql_check);
        $has_student = $stmt_check->rowCount() > 0;

        $sql_check2 = "SHOW COLUMNS FROM workouts LIKE 'aluno_id'";
        $stmt_check2 = $pdo->query($sql_check2);
        $has_aluno = $stmt_check2->rowCount() > 0;

        if ($has_student && !$has_aluno) {
            // Renomear student_id para aluno_id
            $sql_rename = "ALTER TABLE workouts CHANGE student_id aluno_id INT NOT NULL";
            $pdo->exec($sql_rename);
            echo "<p class='text-success'>✅ Coluna <strong>student_id</strong> renomeada para <strong>aluno_id</strong> com sucesso!</p>";

            // Verificar e adicionar FK se necessário
            $sql_fk = "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                      WHERE CONSTRAINT_NAME = 'fk_workout_student'
                      AND TABLE_NAME = 'workouts'
                      AND TABLE_SCHEMA = DATABASE()";
            $stmt_fk = $pdo->query($sql_fk);
            if ($stmt_fk->fetchColumn() == 0) {
                // A coluna foi renomeada, então a FK também precisa ser recriada
                // Mas primeiro verificamos se a FK antiga existe
                try {
                    $sql_drop_fk = "ALTER TABLE workouts DROP FOREIGN KEY IF EXISTS fk_workout_student";
                    $pdo->exec($sql_drop_fk);
                } catch (PDOException $e) {
                    // FK pode não existir, ignoramos o erro
                }

                try {
                    $sql_add_fk = "ALTER TABLE workouts
                                  ADD CONSTRAINT fk_workout_aluno
                                  FOREIGN KEY (aluno_id) REFERENCES students(id) ON DELETE CASCADE";
                    $pdo->exec($sql_add_fk);
                    echo "<p class='text-success'>✅ Foreign key <strong>fk_workout_aluno</strong> criada com sucesso!</p>";
                } catch (PDOException $e) {
                    echo "<p class='text-warning'>⚠️ Não foi possível criar FK: " . $e->getMessage() . "</p>";
                }
            }
        } elseif ($has_aluno) {
            echo "<p class='text-success'>✅ A coluna <strong>aluno_id</strong> já existe. Nenhuma correção necessária.</p>";
        } else {
            echo "<p class='text-danger'>❌ Nenhuma coluna de aluno encontrada para corrigir!</p>";
        }

        $pdo->commit();
        echo "<p><a href='corrigir_coluna_fichas.php' class='btn btn-primary'>Recarregar página para verificar</a></p>";

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<p class='text-danger'>❌ Erro ao executar correção: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<h3>Resumo</h3>";
if (count($errors) > 0) {
    echo "<ul class='text-danger'>";
    foreach ($errors as $error) {
        echo "<li>" . $error . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='text-success'>Nenhum erro crítico encontrado.</p>";
}

echo "<p><a href='../fichas_treino/index.php' class='btn btn-outline-primary'>Ir para Módulo de Fichas de Treino</a></p>";

?>
