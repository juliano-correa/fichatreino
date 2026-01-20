<?php
/**
 * Debug Avançado - Identificar origem do erro de SQL
 */

require_once '../config/conexao.php';
require_once '../config/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Debug Avançado - Erro SQL</h2>";
echo "<hr>";

// 1. Mostrar qual arquivo está sendo executado
echo "<h3>1. Arquivo sendo executado:</h3>";
echo "<p>Arquivo atual: <code>" . __FILE__ . "</code></p>";

// 2. Verificar versão dos arquivos
echo "<h3>2. Verificação de Arquivos:</h3>";

$files_to_check = [
    'fichas_treino/index.php',
    'fichas_treino/novo.php',
    'fichas_treino/visualizar.php',
    'fichas_treino/editar.php',
    'fichas_treino/excluir.php'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/../' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        $has_student_id = strpos($content, 'student_id') !== false;
        $has_aluno_id = strpos($content, 'aluno_id') !== false;

        echo "<p><strong>$file</strong>: ";
        if ($has_student_id) {
            echo "<span class='text-danger'>❌ Contém student_id</span>";
        } elseif ($has_aluno_id) {
            echo "<span class='text-success'>✅ Contém aluno_id</span>";
        } else {
            echo "<span class='text-muted'>⚪ Nenhum dos dois</span>";
        }
        echo "</p>";
    } else {
        echo "<p><strong>$file</strong>: <span class='text-warning'>⚠️ Não encontrado</span></p>";
    }
}

echo "<hr>";

// 3. Testar a query diretamente
echo "<h3>3. Teste Direto da Query:</h3>";

$gym_id = getGymId();
echo "<p>ID da Academia (gym_id): <strong>$gym_id</strong></p>";

try {
    // Query CORRETA (com aluno_id)
    $sql_correct = "SELECT w.*, s.nome as aluno_nome
                    FROM workouts w
                    LEFT JOIN students s ON w.aluno_id = s.id
                    WHERE w.gym_id = :gym_id
                    LIMIT 5";

    echo "<p><strong>Query Correta:</strong></p>";
    echo "<code>" . nl2br(htmlspecialchars($sql_correct)) . "</code>";

    $stmt = $pdo->prepare($sql_correct);
    $stmt->bindValue(':gym_id', $gym_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p class='text-success'>✅ Query executada com sucesso! " . count($result) . " registros encontrados.</p>";

} catch (PDOException $e) {
    echo "<p class='text-danger'>❌ Erro na query correta: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 4. Verificar se há Views ou Procedures que usam student_id
echo "<h3>4. Verificar Views e Triggers:</h3>";

try {
    // Verificar views
    $sql_views = "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()";
    $stmt_views = $pdo->query($sql_views);
    $views = $stmt_views->fetchAll(PDO::FETCH_COLUMN);

    if (count($views) > 0) {
        foreach ($views as $view) {
            $sql_def = "SELECT VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_NAME = '" . $view . "' AND TABLE_SCHEMA = DATABASE()";
            $stmt_def = $pdo->query($sql_def);
            $def = $stmt_def->fetch(PDO::FETCH_COLUMN);

            if (strpos($def, 'student_id') !== false) {
                echo "<p class='text-danger'>❌ View '$view' contém student_id!</p>";
                echo "<details><summary>Ver definição</summary><pre>" . htmlspecialchars($def) . "</pre></details>";
            }
        }
    } else {
        echo "<p>✅ Nenhuma view encontrada.</p>";
    }

    // Verificar triggers
    $sql_triggers = "SELECT TRIGGER_NAME, EVENT_MANIPULATION FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()";
    $stmt_triggers = $pdo->query($sql_triggers);
    $triggers = $stmt_triggers->fetchAll(PDO::FETCH_ASSOC);

    if (count($triggers) > 0) {
        echo "<p>Triggers encontradas:</p>";
        foreach ($triggers as $trigger) {
            echo "<p><strong>" . $trigger['TRIGGER_NAME'] . "</strong> (" . $trigger['EVENT_MANIPULATION'] . ")</p>";
        }
    } else {
        echo "<p>✅ Nenhuma trigger encontrada.</p>";
    }

} catch (PDOException $e) {
    echo "<p class='text-warning'>⚠️ Erro ao verificar views/triggers: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 5. Verificar o arquivo de conexão
echo "<h3>5. Configuração do Banco:</h3>";
echo "<p>Host: <code>" . DB_HOST . "</code></p>";
echo "<p>Database: <code>" . DB_NAME . "</code></p>";

try {
    $sql_version = "SELECT VERSION() as version";
    $stmt_version = $pdo->query($sql_version);
    $version = $stmt_version->fetch(PDO::FETCH_ASSOC);
    echo "<p>MySQL Version: <strong>" . $version['version'] . "</strong></p>";
} catch (PDOException $e) {
    echo "<p class='text-warning'>⚠️ Não foi possível obter versão do MySQL</p>";
}

echo "<hr>";

// 6. Fazer teste com a query exata que causa o erro
echo "<h3>6. Simular o Erro:</h3>";

echo "<p>Se o erro menciona <code>w.student_id</code>, a query deveria ter algo assim:</p>";
echo "<code>... ON w.student_id = s.id ...</code>";

echo "<p>Vamos testar se essa query causa o erro:</p>";

try {
    $sql_wrong = "SELECT w.*, s.nome as aluno_nome
                  FROM workouts w
                  LEFT JOIN students s ON w.student_id = s.id
                  WHERE w.gym_id = :gym_id
                  LIMIT 5";

    $stmt_wrong = $pdo->prepare($sql_wrong);
    $stmt_wrong->bindValue(':gym_id', $gym_id, PDO::PARAM_INT);
    $stmt_wrong->execute();

    echo "<p class='text-warning'>⚠️ Query com student_id também funcionou (isso é estranho!)</p>";

} catch (PDOException $e) {
    echo "<p class='text-danger'>❌ Erro esperado: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>7. Conteúdo Real do Arquivo index.php:</h3>";

$index_file = __DIR__ . '/../fichas_treino/index.php';
if (file_exists($index_file)) {
    $content = file_get_contents($index_file);

    // Extrair a query SQL do arquivo
    if (preg_match('/\$sql\s*=\s*["\'](.*?)["\'];/s', $content, $matches)) {
        echo "<p>Query encontrada no arquivo:</p>";
        echo "<pre class='bg-light p-3'>" . htmlspecialchars($matches[0]) . "</pre>";
    } else {
        echo "<p>Procurando por consultas SQL...</p>";

        // Mostrar linhas relevantes
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (stripos($line, 'student_id') !== false || stripos($line, 'aluno_id') !== false) {
                echo "<p>Linha " . ($i + 1) . ": <code>" . htmlspecialchars(trim($line)) . "</code></p>";
            }
        }
    }
} else {
    echo "<p class='text-warning'>⚠️ Arquivo não encontrado: $index_file</p>";
}

echo "<hr>";
echo "<p><a href='../fichas_treino/index.php' class='btn btn-primary'>Voltar para Fichas de Treino</a></p>";

?>
