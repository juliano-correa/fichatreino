<?php
/**
 * Processamento de Geração de Financeiro (Parcelas)
 */

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$aluno_id = (int)($_POST['aluno_id'] ?? 0);
$dia_vencimento = (int)($_POST['dia_vencimento'] ?? 0);
$qtd_parcelas = (int)($_POST['qtd_parcelas'] ?? 1);
$valor_total = (float)($_POST['valor_total'] ?? 0);

if ($aluno_id <= 0 || $dia_vencimento <= 0 || $qtd_parcelas <= 0 || $valor_total <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos ou inválidos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Buscar informações do aluno para descrição
    $stmt_aluno = $pdo->prepare("SELECT nome FROM students WHERE id = ? AND gym_id = ?");
    $stmt_aluno->execute([$aluno_id, getGymId()]);
    $aluno_nome = $stmt_aluno->fetchColumn();

    if (!$aluno_nome) {
        throw new Exception('Aluno não encontrado');
    }

    $valor_parcela = round($valor_total / $qtd_parcelas, 2);
    
    // Lógica de Vencimento:
    // Hoje: 26/01, Dia: 20 -> Já passou -> 1ª parcela em 20/02
    // Hoje: 26/01, Dia: 28 -> Não passou -> 1ª parcela em 28/01
    
    $hoje = new DateTime();
    $dia_atual = (int)$hoje->format('d');
    $mes_atual = (int)$hoje->format('m');
    $ano_atual = (int)$hoje->format('Y');

    if ($dia_vencimento < $dia_atual) {
        // Já passou o dia no mês atual, começa no próximo mês
        $mes_vencimento = $mes_atual + 1;
        $ano_vencimento = $ano_atual;
        if ($mes_vencimento > 12) {
            $mes_vencimento = 1;
            $ano_vencimento++;
        }
    } else {
        // Ainda não passou, começa no mês atual
        $mes_vencimento = $mes_atual;
        $ano_vencimento = $ano_atual;
    }

    $stmt_insert = $pdo->prepare("INSERT INTO transactions (
        gym_id, aluno_id, tipo, categoria, descricao, valor, data_vencimento, status
    ) VALUES (
        :gym_id, :aluno_id, 'entrada', 'mensalidade', :descricao, :valor, :data_vencimento, 'pendente'
    )");

    for ($i = 1; $i <= $qtd_parcelas; $i++) {
        // Ajustar para o último dia do mês se o dia_vencimento for maior que os dias do mês
        $data_venc = sprintf('%04d-%02d-%02d', $ano_vencimento, $mes_vencimento, $dia_vencimento);
        
        // Validar data (ex: 30 de fevereiro)
        $d = DateTime::createFromFormat('Y-m-d', $data_venc);
        if (!$d || $d->format('Y-m-d') !== $data_venc) {
            // Se for inválida (como 30/02), pega o último dia do mês
            $d = new DateTime(sprintf('%04d-%02d-01', $ano_vencimento, $mes_vencimento));
            $d->modify('last day of this month');
            $data_venc = $d->format('Y-m-d');
        }

        $stmt_insert->execute([
            ':gym_id' => getGymId(),
            ':aluno_id' => $aluno_id,
            ':descricao' => "Parcela {$i}/{$qtd_parcelas} - Mensalidade",
            ':valor' => $valor_parcela,
            ':data_vencimento' => $data_venc
        ]);

        // Avançar para o próximo mês
        $mes_vencimento++;
        if ($mes_vencimento > 12) {
            $mes_vencimento = 1;
            $ano_vencimento++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Financeiro gerado com sucesso ({$qtd_parcelas} parcelas)"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
