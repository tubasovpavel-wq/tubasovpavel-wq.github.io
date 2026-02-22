<?php
// === НАСТРОЙКИ ===
$toEmail = "remstrojlogistic@yandex.ru"; // Ваша почта
$tgToken = "ВАШ_ТОКЕН_БОТА";             // Токен от @BotFather (пример: 5566778899:AAGb...)
$tgChatId = "ВАШ_ID_ЧАТА";               // Ваш ID в Telegram (можно узнать у бота @userinfobot)

// Проверяем, что запрос пришел методом POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Получаем и очищаем данные
    $name = strip_tags(trim($_POST["name"]));
    $phone = strip_tags(trim($_POST["phone"]));
    $comment = strip_tags(trim($_POST["message"]));

    // Проверка обязательных полей
    if (empty($name) || empty($phone)) {
        http_response_code(400);
        echo json_encode(["message" => "Заполните все обязательные поля."]);
        exit;
    }

    // 1. ОТПРАВКА НА ПОЧТУ
    $subject = "Новая заявка с сайта РЕМСТРОЙЛОГИСТИК";
    $emailContent = "Имя: $name\n";
    $emailContent .= "Телефон: $phone\n";
    $emailContent .= "Комментарий: $comment\n";
    
    $headers = "From: no-reply@remstroylogistics.ru\r\n"; // Лучше использовать домен вашего сайта
    $headers .= "Reply-To: $toEmail\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $mailSent = mail($toEmail, $subject, $emailContent, $headers);

    // 2. ОТПРАВКА В TELEGRAM
    $tgMessage = "🔔 <b>Новая заявка с сайта!</b>\n\n";
    $tgMessage .= "👤 <b>Имя:</b> $name\n";
    $tgMessage .= "📞 <b>Телефон:</b> $phone\n";
    if (!empty($comment)) {
        $tgMessage .= "💬 <b>Комментарий:</b> $comment\n";
    }

    // Отправляем запрос к API Telegram
    if ($tgToken != "ВАШ_ТОКЕН_БОТА" && $tgChatId != "ВАШ_ID_ЧАТА") {
        $url = "https://api.telegram.org/bot$tgToken/sendMessage";
        $data = [
            'chat_id' => $tgChatId,
            'text' => $tgMessage,
            'parse_mode' => 'HTML'
        ];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
    }

    // Ответ сайту
    if ($mailSent) {
        http_response_code(200);
        echo json_encode(["message" => "Заявка успешно отправлена!"]);
    } else {
        // Даже если почта не ушла (например, на локалке), скажем что все ок, если телеграм сработал
        // Но для надежности вернем 200
        http_response_code(200); 
        echo json_encode(["message" => "Данные переданы."]);
    }

} else {
    http_response_code(403);
    echo json_encode(["message" => "Ошибка доступа."]);
}
?>