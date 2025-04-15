<?php
/**
 * Обработка запроса на консультацию
 * Принимает POST-запрос с контактными данными и отправляет email
 */

/**
 * Установка заголовка ответа в формате JSON
 */
header('Content-Type: application/json');

/**
 * Проверка, что запрос является POST
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit;
}

/**
 * Получение и очистка данных из POST
 */
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
$company = isset($_POST['company']) ? htmlspecialchars(trim($_POST['company'])) : '';
$position = isset($_POST['position']) ? htmlspecialchars(trim($_POST['position'])) : '';
$phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone'])) : '';
$email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : '';
$consultation = isset($_POST['consultation']) && $_POST['consultation'] === 'true';

/**
 * Проверка заполнения всех обязательных полей
 */
if (empty($name) || empty($company) || empty($position) || empty($phone) || empty($email) || !$consultation) {
    echo json_encode(['success' => false, 'error' => 'Все поля должны быть заполнены']);
    exit;
}

/**
 * Проверка корректности формата email
 */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Неверный формат email']);
    exit;
}

/**
 * Формирование сообщения для отправки
 */
$to = 'vv@polaris-capital.ru';
$subject = 'Запрос на консультацию по IPO';
$message = "Запрос на консультацию:\n\n" .
           "Имя: $name\n" .
           "Компания: $company\n" .
           "Должность: $position\n" .
           "Телефон: $phone\n" .
           "Email: $email\n";
$headers = "From: no-reply@vitvin.online\r\n" .
           "Content-Type: text/plain; charset=UTF-8\r\n";

/**
 * Отправка email с обработкой ошибок
 */
try {
    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Failed to send consultation email to $to", 3, '/tmp/consultation_errors.log');
        echo json_encode(['success' => false, 'error' => 'Не удалось отправить запрос']);
    }
} catch (Exception $e) {
    error_log("Consultation email error: " . $e->getMessage(), 3, '/tmp/consultation_errors.log');
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>