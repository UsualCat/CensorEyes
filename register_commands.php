<?php
echo "<pre>\n";
require_once __DIR__ . '/config.php';

if (empty($discordBotToken)) {
    die("Error: Discord Bot Token is empty in config.php\n");
}

// 1. Get Application ID from the Bot Token (the first part of the token before the dot, base64 decoded)
$tokenParts = explode('.', $discordBotToken);
if (count($tokenParts) < 3) {
    die("Error: Invalid Discord Bot Token format.\n");
}
$appId = base64_decode($tokenParts[0]);

$url = "https://discord.com/api/v10/applications/$appId/commands";

$commands = [
    [
        "name" => "setchannel",
        "description" => "Set the current channel to receive CensorEyes USDT blacklist alerts",
        "type" => 1
    ],
    [
        "name" => "stats",
        "description" => "View CensorEyes statistics (Total Frozen USDT)",
        "type" => 1
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-type: application/json",
    "Authorization: Bot $discordBotToken"
]);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($commands));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
    echo "Successfully registered commands to Discord:\n";
    echo $response . "\n";
} else {
    echo "Failed to register commands. HTTP Code: $httpcode\n";
    echo $response . "\n";
}
