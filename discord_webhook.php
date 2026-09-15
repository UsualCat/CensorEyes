<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Discord Interactions Endpoint
$signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? '';
$timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? '';
$body = file_get_contents('php://input');

if (!$signature || !$timestamp || !$body) {
    http_response_code(401);
    exit('Missing signature');
}

// Verify Ed25519 signature
if (!function_exists('sodium_crypto_sign_verify_detached')) {
    http_response_code(500);
    exit('Libsodium is not installed.');
}

$publicKeyBin = hex2bin($discordPublicKey);
$signatureBin = hex2bin($signature);

$isValid = @sodium_crypto_sign_verify_detached($signatureBin, $timestamp . $body, $publicKeyBin);

if (!$isValid) {
    http_response_code(401);
    exit('Invalid signature');
}

$interaction = json_decode($body, true);
$type = $interaction['type'] ?? 0;

// Type 1: PING
if ($type === 1) {
    header('Content-Type: application/json');
    echo json_encode(['type' => 1]);
    exit;
}

// Type 2: APPLICATION_COMMAND
if ($type === 2) {
    $commandName = $interaction['data']['name'] ?? '';
    $channelId = $interaction['channel_id'] ?? null;
    $guildId = $interaction['guild_id'] ?? null;
    
    if ($commandName === 'setchannel') {
        if ($channelId) {
            addDiscordSubscriber($channelId, $guildId);
            $response = [
                'type' => 4, // ChannelMessageWithSource
                'data' => [
                    'content' => '✅ This channel has been set to receive CensorEyes blacklist alerts.'
                ]
            ];
        } else {
            $response = [
                'type' => 4,
                'data' => ['content' => '❌ Could not determine channel ID.']
            ];
        }
    } elseif ($commandName === 'stats') {
        $count = getBlacklistCount();
        $total = number_format(getTotalFrozenAssets(), 2);
        $response = [
            'type' => 4,
            'data' => [
                'content' => "📊 **CensorEyes Statistics**\n\n**Total Blacklisted Addresses:** $count\n**Total Frozen USDT:** $$total\n\n*Use decentralized alternatives like Monero (XMR).*"
            ]
        ];
    } else {
        $response = [
            'type' => 4,
            'data' => ['content' => 'Unknown command.']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
