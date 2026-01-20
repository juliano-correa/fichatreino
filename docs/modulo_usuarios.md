# Módulo de Usuários e Controle de Acesso -钛金健身房

## Resumo da Implementação

Este módulo adiciona autenticação segura e controle de acesso baseado em papéis (RBAC) ao sistema Titanium Gym.

## Arquivos Criados/Modificados

### 1. Banco de Dados
- **`setup/criar_tabela_usuarios.php`** - Script de setup para criar a tabela de usuários
  - Executar este arquivo uma vez para criar a tabela
  - Cria automaticamente um usuário admin padrão (admin@titanium.com / admin123)

### 2. Autenticação
- **`login.php`** - Página de login pública
  - Layout limpo e centralizado
  - Validação de credenciais
  - Tratamento de erros
  
- **`logout.php`** - Script de logout
  - Destrói a sessão completamente
  - Redireciona para login

- **`includes/auth_check.php`** - Middleware de autenticação
  - Verifica se o usuário está logado
  - Timeout de sessão (30 minutos)
  - Verifica se usuário ainda está ativo
  - Carrega sistema de permissões

- **`includes/permissions.php`** - Sistema de permissões
  - Matriz de permissões por papel
  - Funções auxiliares para verificação
  - Suporte a papéis: Admin, Instrutor, Recepção, Aluno

- **`unauthorized.php`** - Página de acesso negado (403)

### 3. Gerenciamento de Usuários (Admin)
- **`admin/users/index.php`** - Listagem de usuários
  - Tabela com filtros e ações em lote
  - Visualização de status e perfil
  
- **`admin/users/create.php`** - Formulário de criação
  - Campos: nome, email, senha, perfil, vínculo com aluno
  - Validações de segurança
  
- **`admin/users/edit.php`** - Formulário de edição
  - Edição de dados do usuário
  - Alteração de senha opcional
  
- **`admin/users/delete.php`** - Exclusão de usuário
  - Proteção contra auto-exclusão

### 4. Interface
- **`includes/header.php`** - Menu dinâmico
  - Menu lateral filtrado por permissões
  - Exibição de nome e perfil do usuário logado
  - Logout acessível

## Perfis de Usuário

| Perfil | Descrição | Acesso |
|--------|-----------|--------|
| **Admin** | Administrador do sistema | Acesso completo a todos os módulos e funcionalidades |
| **Instrutor** | Professor de Educação Física | Alunos, Modalidades, Avaliações, Fichas de Treino, Agenda |
| **Recepção** | Atendimento/Recepção | Alunos, Modalidades, Financeiro, Agenda |
| **Aluno** | Aluno da academia | Visualização própria (Fichas, Avaliações, Mensalidades) |

## Como Instalar

1. **Executar o script de setup:**
   ```
   Acesse: http://seu-sistema/setup/criar_tabela_usuarios.php
   ```

2. **Fazer login com o admin padrão:**
   - Email: `admin@titanium.com`
   - Senha: `admin123`

3. **Criar outros usuários conforme necessário:**
   - Acesse: Menu > Usuários > Novo Usuário

## Funcionalidades de Segurança

- **Hash de Senhas:** Uso de `password_hash()` com BCRYPT
- **Session Timeout:** 30 minutos de inatividade
- **Verificação de Status:** Usuários inativos não podem logar
- **Proteção CSRF:** Validação de formulários
- **Sanitização:** Função `sanitizar()` em todas as saídas

## Próximos Passos Sugeridos

1. ✅ Modulo de Usuários implementado
2. Agenda/Calendário (com controle de acesso)
3. Relatórios de Alunos (com controle de acesso)

## Observações

- O perfil "Aluno" é vinculado a um registro existente na tabela `students`
- Alunos só conseguem visualizar suas próprias fichas e avaliações
- O menu lateral é automaticamente ajustado conforme o perfil do usuário
