<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sync.php';

// Security check: Only allow CLI execution or via Admin Panel
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        die('Unauthorized');
    }
}

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@set_time_limit(0);
ob_implicit_flush(true);
while (ob_get_level() > 0) { ob_end_flush(); }

echo "<pre style='background:#111; color:#0f0; padding:20px; font-family:monospace;'>\n";
echo "Starting Initial Setup Sync...\n";
echo "Wiping existing database to start fresh...\n";
$dbPath = __DIR__ . '/censoreyes.db';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

echo "This will fetch all historical data from the beginning without fetching balances to prevent Etherscan API limits.\n";
echo "Please wait...\n\n";

// The true flag tells runSync to just save the addresses as notified with 0 balance
runSync($etherscanKey, $tgToken, $discordBotToken, true);

echo "\nInitial Setup Completed Successfully!\n";
echo "</pre>";
