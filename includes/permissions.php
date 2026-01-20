<?php
/**
 * Sistema de Permissões (RBAC)
 * Definição centralizada das regras de acesso por perfil
 */

// Cache de permissões para performance
$permissions_cache = [];

/**
 * Verifica se o usuário tem permissão para uma ação específica
 */
function hasPermission($permission) {
    if (!isset($_SESSION['user_role']) || !isset($_SESSION['logged_in'])) {
        return false;
    }
    
    $role = $_SESSION['user_role'];
    
    // Administrador tem acesso total
    if ($role === 'admin') {
        return true;
    }
    
    $permissions = getPermissions();
    return in_array($permission, $permissions[$role] ?? []);
}

/**
 * Retorna array com todas as permissões por perfil
 */
function getPermissions() {
    return [
        'admin' => [
            // Dashboard
            'dashboard.view',
            
            // Alunos
            'alunos.view',
            'alunos.create',
            'alunos.edit',
            'alunos.delete',
            'alunos.export',
            
            // Modalidades
            'modalidades.view',
            'modalidades.create',
            'modalidades.edit',
            'modalidades.delete',
            
            // Financeiro
            'financeiro.view',
            'financeiro.create',
            'financeiro.edit',
            'financeiro.delete',
            'financeiro.relatorio',
            
            // Avaliações Físicas
            'avaliacoes.view',
            'avaliacoes.create',
            'avaliacoes.edit',
            'avaliacoes.delete',
            
            // Fichas de Treino
            'fichas.view',
            'fichas.create',
            'fichas.edit',
            'fichas.delete',
            'fichas.export',
            
            // Agenda
            'agenda.view',
            'agenda.create',
            'agenda.edit',
            'agenda.delete',
            
            // Relatórios
            'relatorios.view',
            'relatorios.export',
            
            // Usuários
            'usuarios.view',
            'usuarios.create',
            'usuarios.edit',
            'usuarios.delete',
            
            // Configurações
            'configuracoes.view',
            'configuracoes.edit',
        ],
        
        'recepcao' => [
            // Dashboard
            'dashboard.view',
            
            // Alunos
            'alunos.view',
            'alunos.create',
            'alunos.edit',
            'alunos.export',
            
            // Modalidades
            'modalidades.view',
            
            // Financeiro
            'financeiro.view',
            'financeiro.create',
            'financeiro.edit',
            'financeiro.relatorio',
            
            // Avaliações Físicas
            'avaliacoes.view',
            
            // Fichas de Treino
            'fichas.view',
            'fichas.export',
            
            // Agenda
            'agenda.view',
            'agenda.create',
            'agenda.edit',
        ],
        
        'instrutor' => [
            // Dashboard
            'dashboard.view',
            
            // Alunos
            'alunos.view',
            
            // Modalidades
            'modalidades.view',
            
            // Financeiro
            'financeiro.view',
            
            // Avaliações Físicas
            'avaliacoes.view',
            'avaliacoes.create',
            'avaliacoes.edit',
            
            // Fichas de Treino
            'fichas.view',
            'fichas.create',
            'fichas.edit',
            'fichas.export',
            
            // Agenda
            'agenda.view',
        ],
        
        'aluno' => [
            // Dashboard (limitado)
            'dashboard.view',
            
            // Próprias Fichas
            'fichas.view_self',
            
            // Próprias Avaliações
            'avaliacoes.view_self',
        ]
    ];
}

/**
 * Verifica e redireciona se não tiver permissão
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('Location: unauthorized.php');
        exit;
    }
}

/**
 * Retorna true se o usuário for admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

/**
 * Retorna true se o usuário for instrutor
 */
if (!function_exists('isInstrutor')) {
    function isInstrutor() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'instrutor';
    }
}

/**
 * Retorna true se o usuário for da recepção
 */
function isRecepcao() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'recepcao';
}

/**
 * Retorna true se o usuário for aluno
 */
function isAluno() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'aluno';
}

/**
 * Obtém o ID do aluno logado (se for perfil aluno)
 */
function getAlunoId() {
    return $_SESSION['student_id'] ?? null;
}

/**
 * Obtém o nome do papel em português
 */
function getRoleLabel($role) {
    $labels = [
        'admin' => 'Administrador',
        'instrutor' => 'Instrutor',
        'recepcao' => 'Recepção',
        'aluno' => 'Aluno'
    ];
    return $labels[$role] ?? $role;
}

/**
 * Obtém a classe CSS do badge para cada papel
 */
function getRoleBadgeClass($role) {
    $classes = [
        'admin' => 'bg-danger',
        'instrutor' => 'bg-primary',
        'recepcao' => 'bg-success',
        'aluno' => 'bg-info'
    ];
    return $classes[$role] ?? 'bg-secondary';
}
