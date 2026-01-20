<?php
/**
 * Script de Diagnóstico - Verificar estrutura de arquivos
 */

echo "<h2>🔍 Diagnóstico do Titanium Gym</h2>";
echo "<hr>";

// 1. Mostrar pasta atual
echo "<h3>📁 Localização:</h3>";
echo "<p><strong>Pasta atual:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Arquivo:</strong> " . basename(__FILE__) . "</p>";

// Definir pasta raiz do projeto (pai da pasta setup)
$root_dir = dirname(__DIR__);

echo "<p><strong>Pasta raiz do projeto:</strong> $root_dir</p>";

// 2. Verificar arquivos na pasta raiz
echo "<h3>⚙️ Verificar Arquivos:</h3>";

$files_to_check = [
    'config/conexao.php' => 'Configuração do Banco',
    'config/functions.php' => 'Funções do Sistema',
    'includes/header.php' => 'Cabeçalho',
    'includes/footer.php' => 'Rodapé',
    'dashboard.php' => 'Dashboard',
    'login.php' => 'Login'
];

foreach ($files_to_check as $file => $description) {
    $full_path = $root_dir . '/' . $file;
    $exists = file_exists($full_path);
    $status = $exists ? '✅' : '❌';
    echo "<p>$status <strong>$description:</strong> $file";
    if (!$exists) {
        echo " - <span style='color:red'>NÃO ENCONTRADO!</span>";
    }
    echo "</p>";
}

// 3. Listar pasta config
echo "<h3>📂 Conteúdo da pasta 'config':</h3>";
$config_dir = $root_dir . '/config';
if (is_dir($config_dir)) {
    $files = scandir($config_dir);
    echo "<ul>";
    foreach ($files as $f) {
        if ($f != '.' && $f != '..') {
            echo "<li>$f</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>❌ Pasta 'config' não existe!</p>";
}

// 4. Listar pasta raiz
echo "<h3>📂 Conteúdo da pasta raiz:</h3>";
if (is_dir($root_dir)) {
    $files = scandir($root_dir);
    echo "<ul>";
    foreach ($files as $f) {
        if ($f != '.' && $f != '..') {
            $icon = is_dir($root_dir . '/' . $f) ? '📁' : '📄';
            echo "<li>$icon $f</li>";
        }
    }
    echo "</ul>";
}

// 5. Testar include do conexao.php
echo "<h3>🧪 Testar Inclusão:</h3>";
$test_file = $root_dir . '/config/conexao.php';
if (file_exists($test_file)) {
    try {
        require_once $test_file;
        echo "<p style='color:green'>✅ conexao.php carregado com sucesso!</p>";
        
        if (isset($pdo)) {
            echo "<p style='color:green'>✅ Conexão PDO disponível!</p>";
        } else {
            echo "<p style='color:orange'>⚠️ PDO não inicializado no conexao.php</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Erro ao carregar: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Arquivo não encontrado para teste!</p>";
}

// 6. Informações do PHP
echo "<h3>ℹ️ Informações do Servidor:</h3>";
echo "<ul>";
echo "<li>PHP Version: " . PHP_VERSION . "</li>";
echo "<li>SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "</li>";
echo "<li>DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</li>";
echo "<li>SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>🔗 Links:</h3>";
echo "<a href='../login.php' class='btn btn-primary'>Testar Login</a> ";
echo "<a href='../dashboard.php' class='btn btn-secondary'>Testar Dashboard</a>";

?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h2 { color: #333; }
h3 { color: #555; margin-top: 20px; }
p { padding: 5px 0; }
ul { margin-left: 20px; }
.btn { padding: 10px 20px; margin: 5px; display: inline-block; text-decoration: none; border-radius: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
</style>
