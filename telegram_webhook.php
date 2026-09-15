<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Get incoming POST data from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message'])) {
    http_response_code(200);
    exit;
}

$message = $update['message'];
$chatId = $message['chat']['id'] ?? null;
$text = trim($message['text'] ?? '');

if (!$chatId) {
    http_response_code(200);
    exit;
}

// Helper function to send reply
function sendReply($chatId, $text) {
    global $tgToken;
    if (empty($tgToken)) return;
    $url = "https://api.telegram.org/bot$tgToken/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ]
    ];
    @file_get_contents($url, false, stream_context_create($options));
}

// Handle Commands
if ($text === '/start') {
    addTelegramSubscriber($chatId);
    $reply = "Welcome to CensorEyes Bot! 👁‍🗨\n\nYou are now subscribed. I will notify you immediately whenever an address is blacklisted on the Ethereum or TRON networks.\n\nType /stats to see the current statistics.";
    sendReply($chatId, $reply);
} 
elseif ($text === '/stats') {
    $count = getBlacklistCount();
    $total = number_format(getTotalFrozenAssets(), 2);
    $reply = "📊 *CensorEyes Statistics*\n\n*Total Blacklisted Addresses:* $count\n*Total Frozen Assets:* $$total\n\n_Consider using decentralized alternatives like Monero (XMR) to protect your assets from arbitrary freezes._";
    sendReply($chatId, $reply);
}

http_response_code(200);
