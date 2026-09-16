<?php
/**
 * Telegram Contact Form Handler
 * This script receives contact form submissions and sends them to a Telegram bot
 */
date_default_timezone_set('Asia/Kolkata');
// Configuration
define('TELEGRAM_BOT_TOKEN', 'BOT_TOKEN'); // Replace with your bot token
define('TELEGRAM_CHAT_ID', 'USER_CHAT_ID');     // Replace with your chat ID

// CORS headers (if needed for cross-origin requests)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Function to send message to Telegram
function sendTelegramMessage($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data
    $name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    // If validation fails
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Create formatted message for Telegram
    $telegramMessage = "🔔 <b>New Contact Form Submission</b>\n\n";
    $telegramMessage .= "👤 <b>Name:</b> $name\n";
    $telegramMessage .= "📧 <b>Email:</b> $email\n";
    $telegramMessage .= "💬 <b>Message:</b>\n$message\n\n";
    $telegramMessage .= "⏰ <b>Time:</b> " . date('Y-m-d H:i:s');
    
    // Send to Telegram
    $result = sendTelegramMessage($telegramMessage);
    
    // Check if message was sent successfully
    if (isset($result['ok']) && $result['ok'] === true) {
        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send message. Please try again later.'
        ]);
    }
    
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>