<?php
/**
 * API de Agenda - Processar agendamentos e presenças
 */

require_once '../includes/auth_check.php';
require_once '../config/conexao.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_bookings':
            // Obter agendamentos de uma aula específica
            $class_id = (int)($_POST['class_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            
            if (empty($class_id) || empty($date)) {
                throw new Exception('Parâmetros inválidos');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    b.id as booking_id,
                    b.status,
                    s.id as student_id,
                    s.nome,
                    s.telefone,
                    a.present,
                    a.checked_in_at
                FROM class_bookings b
                LEFT JOIN students s ON b.student_id = s.id
                LEFT JOIN class_attendance a ON a.booking_id = b.id
                WHERE b.class_definition_id = :class_id 
                AND b.booking_date = :date
                AND b.gym_id = :gym_id
                ORDER BY s.nome
            ");
            $stmt->execute([
                ':class_id' => $class_id,
                ':date' => $date,
                ':gym_id' => getGymId()
            ]);
            $bookings = $stmt->fetchAll();
            
            // Contar total e confirmados
            $total = count($bookings);
            $confirmed = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
            
            $response['success'] = true;
            $response['data'] = [
                'bookings' => $bookings,
                'total' => $total,
                'confirmed' => $confirmed
            ];
            break;
            
        case 'add_booking':
            // Adicionar agendamento
            $class_id = (int)($_POST['class_id'] ?? 0);
            $student_id = (int)($_POST['student_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            
            if (empty($class_id) || empty($student_id) || empty($date)) {
                throw new Exception('Parâmetros inválidos');
            }
            
            // Verificar capacidade
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM class_bookings 
                WHERE class_definition_id = :class_id 
                AND booking_date = :date 
                AND status = 'confirmed'
            ");
            $stmt->execute([':class_id' => $class_id, ':date' => $date]);
            $current = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT max_capacity FROM class_definitions WHERE id = :id");
            $stmt->execute([':id' => $class_id]);
            $class = $stmt->fetch();
            
            if ($current['total'] >= $class['max_capacity']) {
                throw new Exception('Turma lotada! Não há mais vagas disponíveis.');
            }
            
            // Verificar se já está agendado
            $stmt = $pdo->prepare("
                SELECT id FROM class_bookings 
                WHERE class_definition_id = :class_id 
                AND student_id = :student_id 
                AND booking_date = :date
            ");
            $stmt->execute([':class_id' => $class_id, ':student_id' => $student_id, ':date' => $date]);
            if ($stmt->fetch()) {
                throw new Exception('Este aluno já está agendado para esta aula.');
            }
            
            // Criar agendamento
            $stmt = $pdo->prepare("
                INSERT INTO class_bookings (gym_id, class_definition_id, student_id, booking_date, status)
                VALUES (:gym_id, :class_id, :student_id, :date, 'confirmed')
            ");
            $stmt->execute([
                ':gym_id' => getGymId(),
                ':class_id' => $class_id,
                ':student_id' => $student_id,
                ':date' => $date
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Aluno agendado com sucesso!';
            break;
            
        case 'cancel_booking':
            // Cancelar agendamento
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            
            if (empty($booking_id)) {
                throw new Exception('Parâmetros inválidos');
            }
            
            $stmt = $pdo->prepare("
                UPDATE class_bookings SET status = 'canceled' 
                WHERE id = :id AND gym_id = :gym_id
            ");
            $stmt->execute([':id' => $booking_id, ':gym_id' => getGymId()]);
            
            // Remover presença se existir
            $stmt = $pdo->prepare("DELETE FROM class_attendance WHERE booking_id = :id");
            $stmt->execute([':id' => $booking_id]);
            
            $response['success'] = true;
            $response['message'] = 'Agendamento cancelado!';
            break;
            
        case 'toggle_attendance':
            // Marcar presença
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $present = (int)($_POST['present'] ?? 0);
            
            if (empty($booking_id)) {
                throw new Exception('Parâmetros inválidos');
            }
            
            // Verificar se já existe registro de presença
            $stmt = $pdo->prepare("SELECT id FROM class_attendance WHERE booking_id = :id");
            $stmt->execute([':id' => $booking_id]);
            $attendance = $stmt->fetch();
            
            if ($attendance) {
                // Atualizar
                $stmt = $pdo->prepare("
                    UPDATE class_attendance SET 
                        present = :present,
                        checked_in_at = NOW(),
                        checked_by_user_id = :user_id
                    WHERE booking_id = :id
                ");
                $stmt->execute([':id' => $booking_id, ':present' => $present, ':user_id' => $_SESSION['user_id']]);
            } else {
                // Criar novo registro
                // Primeiro, buscar dados do booking
                $stmt = $pdo->prepare("
                    SELECT class_definition_id, student_id, booking_date 
                    FROM class_bookings WHERE id = :id
                ");
                $stmt->execute([':id' => $booking_id]);
                $booking = $stmt->fetch();
                
                if ($booking) {
                    $stmt = $pdo->prepare("
                        INSERT INTO class_attendance 
                        (gym_id, booking_id, class_definition_id, student_id, attendance_date, present, checked_in_at, checked_by_user_id)
                        VALUES (:gym_id, :booking_id, :class_id, :student_id, :date, :present, NOW(), :user_id)
                    ");
                    $stmt->execute([
                        ':gym_id' => getGymId(),
                        ':booking_id' => $booking_id,
                        ':class_id' => $booking['class_definition_id'],
                        ':student_id' => $booking['student_id'],
                        ':date' => $booking['booking_date'],
                        ':present' => $present,
                        ':user_id' => $_SESSION['user_id']
                    ]);
                }
            }
            
            $response['success'] = true;
            $response['message'] = $present ? 'Presença marcada!' : 'Falta registrada!';
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
