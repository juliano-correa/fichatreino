<?php
/**
 * Funções Utilitárias - Titanium Gym Manager
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configurações de segurança para cookies de sessão
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    session_start();
}

/**
 * Retorna o caminho base relativo para navegação interna
 * Útil para links de navegação que devem funcionar em qualquer ambiente
 */
function getBasePath() {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $current_dir = dirname($script_name);
    
    // Contar níveis de profundidade
    $parts = array_filter(explode('/', $current_dir));
    $depth = count($parts);
    
    // Se estiver na raiz, retornar './'
    if ($depth <= 1) {
        return './';
    }
    
    // Retornar o número correto de '../' baseado na profundidade
    $path = '';
    for ($i = 1; $i < $depth; $i++) {
        $path .= '../';
    }
    
    return $path;
}

/**
 * Retorna a URL base do projeto - versão melhorada
 */
function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Obter o diretório base do script atual
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // Detectar o diretório base do projeto
    // Se SCRIPT_NAME contém /titanium-gym-php/ ou /titanium-gym/, usamos isso como base
    if (preg_match('#^/(titanium-gym-php|titanium-gym)/#', $script_name, $matches)) {
        $base_dir = '/' . $matches[1];
    } else {
        // Para produção (acesso direto sem subdiretório) ou localhost
        // Sempre usar raiz vazia para garantir URLs absolutas
        $base_dir = '';
    }
    
    // Remover /index.php se existir
    $base_dir = str_replace('/index.php', '', $base_dir);
    
    // Retornar URL completa
    return $protocol . '://' . $host . $base_dir . '/' . ltrim($path, '/');
}

/**
 * Retorna a URL base do site sem trailing slash
 */
function site_url($path = '') {
    return base_url($path);
}

/**
 * Gera URL absoluta para recursos estáticos (CSS, JS, imagens)
 */
function assets_url($path = '') {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Gera URL para link de voltar baseada na página atual
 */
function voltar_url($pagina_destino = 'dashboard') {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $current_dir = dirname($script_name);
    
    // Determinar o caminho de volta
    if ($pagina_destino === 'dashboard') {
        return base_url('dashboard.php');
    } elseif ($pagina_destino === 'index') {
        // Extrair nome do módulo do diretório atual
        $parts = array_filter(explode('/', $current_dir));
        $module = end($parts);
        if (empty($module) || $module === 'titanium-gym-php') {
            return base_url('index.php');
        }
        return base_url($module . '/index.php');
    }
    
    return base_url($pagina_destino);
}

/**
 * Verifica se o usuário está logado
 */
function estaLogado() {
    return isset($_SESSION['user_id']) && isset($_SESSION['gym_id']);
}

/**
 * Redireciona para outra página
 */
function redirecionar($url) {
    header("Location: $url");
    exit;
}

/**
 * Sanitiza entrada de dados
 */
function sanitizar($dado) {
    return htmlspecialchars(trim($dado), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata valor monetário para exibição
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Formata data para padrão brasileiro
 */
function formatarData($data, $formato = 'd/m/Y') {
    if (empty($data)) return '-';
    $timestamp = strtotime($data);
    return date($formato, $timestamp);
}

/**
 * Formata data e hora para padrão brasileiro
 */
function formatarDataHora($data) {
    if (empty($data)) return '-';
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Formata telefone para exibição
 */
function formatarTelefone($telefone) {
    if (empty($telefone)) return '-';
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
    }
    return $telefone;
}

/**
 * Formata CPF para exibição
 */
function formatarCPF($cpf) {
    if (empty($cpf)) return '-';
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9);
    }
    return $cpf;
}

/**
 * Gera link do WhatsApp
 */
function gerarLinkWhatsapp($telefone, $mensagem = '') {
    $telefone = preg_replace('/\D/', '', $telefone);
    $mensagemUrl = urlencode($mensagem);
    return "https://wa.me/{$telefone}?text={$mensagemUrl}";
}

/**
 * Gera mensagem de cobrança para WhatsApp
 */
function gerarMensagemCobranca($alunoNome, $valor, $vencimento, $academiaNome, $pixChave = '') {
    $mensagem = "Olá {$alunoNome}! 👋\n\n";
    $mensagem .= "Tudo bem? Entrando em contato da *{$academiaNome}*.\n\n";
    $mensagem .= "Sua mensalidade no valor de *{$valor}* vence em *{$vencimento}*.\n\n";
    
    if (!empty($pixChave)) {
        $mensagem .= "Para facilitar, segue nossa chave PIX: {$pixChave}\n\n";
        $mensagem .= "Após o pagamento, por favor nos enviar o comprovante para regularização.\n\n";
    }
    
    $mensagem .= "Qualquer dúvida, estamos à disposição!\n\n";
    $mensagem .= "Att,\n{$academiaNome}";
    
    return $mensagem;
}

/**
 * Retorna badge de status
 */
function getStatusBadge($status) {
    $badges = [
        'ativo' => '<span class="badge bg-success">Ativo</span>',
        'inativo' => '<span class="badge bg-secondary">Inativo</span>',
        'suspenso' => '<span class="badge bg-warning text-dark">Suspenso</span>',
        'cancelado' => '<span class="badge bg-danger">Cancelado</span>',
        'pendente' => '<span class="badge bg-warning text-dark">Pendente</span>',
        'pago' => '<span class="badge bg-success">Pago</span>',
        'vencido' => '<span class="badge bg-danger">Vencido</span>',
        'pausado' => '<span class="badge bg-info text-dark">Pausado</span>',
        'expirado' => '<span class="badge bg-secondary">Expirado</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Retorna badge de gênero
 */
function getGeneroBadge($genero) {
    $badges = [
        'M' => '<span class="badge bg-primary">Masculino</span>',
        'F' => '<span class="badge bg-danger">Feminino</span>',
        'O' => '<span class="badge bg-secondary">Outro</span>',
    ];
    return $badges[$genero] ?? '-';
}

/**
 * Calcula idade a partir da data de nascimento
 */
function calcularIdade($dataNascimento) {
    if (empty($dataNascimento)) return '-';
    $nascimento = new DateTime($dataNascimento);
    $hoje = new DateTime();
    $idade = $hoje->diff($nascimento)->y;
    return $idade . ' anos';
}

/**
 * Calcula IMC
 */
function calcularIMC($peso, $alturaCm) {
    if (empty($peso) || empty($alturaCm) || $alturaCm == 0) return null;
    $alturaM = $alturaCm / 100;
    return number_format($peso / ($alturaM * $alturaM), 1);
}

/**
 * Retorna categoria do IMC
 */
function getCategoriaIMC($imc) {
    if (empty($imc)) return '-';
    if ($imc < 18.5) return 'Abaixo do peso';
    if ($imc < 25) return 'Peso normal';
    if ($imc < 30) return 'Sobrepeso';
    if ($imc < 35) return 'Obesidade grau I';
    if ($imc < 40) return 'Obesidade grau II';
    return 'Obesidade grau III';
}

/**
 * Mensagem de alerta
 */
function mostrarAlerta($tipo, $mensagem) {
    return "<div class='alert alert-{$tipo} alert-dismissible fade show' role='alert'>
                {$mensagem}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Fechar'></button>
            </div>";
}

/**
 * Formata data para input date
 */
function formatarDataInput($data) {
    if (empty($data)) return '';
    return date('Y-m-d', strtotime($data));
}

/**
 * Calcula diferença de dias entre datas
 */
function diferencaDias($data1, $data2) {
    $d1 = new DateTime($data1);
    $d2 = new DateTime($data2);
    return $d2->diff($d1)->days;
}

/**
 * Retorna o primeiro dia do mês
 */
function primeiroDiaMes($data = null) {
    $data = $data ?: date('Y-m-d');
    return date('Y-m-01', strtotime($data));
}

/**
 * Retorna o último dia do mês
 */
function ultimoDiaMes($data = null) {
    $data = $data ?: date('Y-m-d');
    return date('Y-m-t', strtotime($data));
}

/**
 * Gera slug a partir de texto
 */
function gerarSlug($texto) {
    $texto = strtolower(trim($texto));
    $texto = preg_replace('/[^a-z0-9-]/', '-', $texto);
    $texto = preg_replace('/-+/', '-', $texto);
    return trim($texto, '-');
}

/**
 * Valida CPF
 */
function validarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) != 11) return false;
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

/**
 * Redireciona se não estiver logado
 */
function requerAutenticacao() {
    if (!estaLogado()) {
        redirecionar('../index.php');
    }
}

/**
 * Pega o gym_id da sessão
 */
function getGymId() {
    $gym_id = $_SESSION['gym_id'] ?? null;
    
    // Se gym_id não estiver definido, retorna 1 como padrão (single tenant)
    if ($gym_id === null) {
        $gym_id = 1;
    }
    
    return $gym_id;
}

/**
 * Pega o user_id da sessão
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Pega o nome do usuário logado
 */
function getUserName() {
    return $_SESSION['user_nome'] ?? 'Usuário';
}

/**
 * Pega o papel do usuário
 */
function getUserPapel() {
    return $_SESSION['user_role'] ?? 'recepcionista';
}

/**
 * Verifica se o usuário é admin
 */
function isAdmin() {
    return getUserPapel() === 'admin';
}

/**
 * Verifica se o usuário é instrutor
 */
function isInstrutor() {
    return getUserPapel() === 'instrutor';
}
