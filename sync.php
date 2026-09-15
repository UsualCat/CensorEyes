<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

function fetchEtherscanLogs($apiKey, $contractAddress, $topic0, $fromBlock = 0, $page = 1) {
    $url = "https://api.etherscan.io/v2/api?chainid=1&module=logs&action=getLogs&fromBlock=$fromBlock&toBlock=latest&address=$contractAddress&topic0=$topic0&page=$page&offset=1000&apikey=$apiKey";
    $json = @file_get_contents($url);
    if(!$json) return [];
    $data = json_decode($json, true);
    return $data['result'] ?? [];
}

function fetchTokenBalance($address, $contractAddress, $decimals, $apiKey = null) {
    global $rpcEndpoints;
    $rpcUrl = $rpcEndpoints['Ethereum'] ?? 'https://eth-mainnet.g.alchemy.com/v2/YOUR_ALCHEMY_KEY'; 
    $cleanAddress = str_replace('0x', '', strtolower($address));
    $data = "0x70a08231" . str_pad($cleanAddress, 64, "0", STR_PAD_LEFT);
    
    $payload = json_encode([
        "jsonrpc" => "2.0",
        "method" => "eth_call",
        "params" => [["to" => $contractAddress, "data" => $data], "latest"],
        "id" => 1
    ]);
    
    $opts = ['http' => ['header' => "Content-type: application/json\r\n", 'method' => 'POST', 'content' => $payload]];
    $json = @file_get_contents($rpcUrl, false, stream_context_create($opts));
    if (!$json) return 0.0;
    
    $resp = json_decode($json, true);
    if (isset($resp['result']) && $resp['result'] !== '0x') {
        return (float)(hexdec($resp['result']) / pow(10, $decimals));
    }
    return 0.0;
}

function hexToBase58($hex) {
    if (strpos($hex, '0x') === 0) {
        $hex = '41' . substr($hex, 2);
    }
    $bin = hex2bin($hex);
    $hash0 = hash('sha256', $bin, true);
    $hash1 = hash('sha256', $hash0, true);
    $checksum = substr($hash1, 0, 4);
    $raw = $bin . $checksum;
    
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base58 = '';
    $num = '0';
    for ($i = 0; $i < strlen($raw); $i++) {
        $num = bcadd(bcmul($num, '256'), ord($raw[$i]));
    }
    while (bccomp($num, '0') > 0) {
        $rem = bcmod($num, '58');
        $num = bcdiv($num, '58', 0);
        $base58 = $alphabet[(int)$rem] . $base58;
    }
    for ($i = 0; $i < strlen($raw) && $raw[$i] === "\x00"; $i++) {
        $base58 = '1' . $base58;
    }
    return $base58;
}

function fetchTronGridLogs($contractAddress, $eventName, $minTimestamp = 0) {
    global $tronGridKey;
    $url = "https://api.trongrid.io/v1/contracts/$contractAddress/events?event_name=$eventName&limit=200";
    if ($minTimestamp > 0) {
        $url .= "&min_block_timestamp=" . $minTimestamp;
    }
    
    $headers = "Accept: application/json\r\n";
    if (!empty($tronGridKey)) {
        $headers .= "TRON-PRO-API-KEY: $tronGridKey\r\n";
    }
    $opts = [
        'http' => [
            'header' => $headers,
            'method' => 'GET'
        ]
    ];
    $json = @file_get_contents($url, false, stream_context_create($opts));
    if(!$json) return [];
    
    $data = json_decode($json, true);
    return $data['data'] ?? [];
}

function fetchTronTokenBalance($base58Address, $contractAddress, $decimals) {
    global $tronGridKey;
    $url = "https://api.trongrid.io/v1/accounts/$base58Address";
    
    $headers = "Accept: application/json\r\n";
    if (!empty($tronGridKey)) {
        $headers .= "TRON-PRO-API-KEY: $tronGridKey\r\n";
    }
    $opts = [
        'http' => [
            'header' => $headers,
            'method' => 'GET'
        ]
    ];
    $json = @file_get_contents($url, false, stream_context_create($opts));
    if(!$json) return 0.0;
    
    $resp = json_decode($json, true);
    if (!empty($resp['data']) && !empty($resp['data'][0]['trc20'])) {
        foreach ($resp['data'][0]['trc20'] as $token) {
            foreach ($token as $addr => $bal) {
                if ($addr === $contractAddress) {
                    return (float)($bal / pow(10, $decimals));
                }
            }
        }
    }
    return 0.0;
}

function sendGroupedWebhook($webhooks, $tgToken, $discordBotToken) {
    global $trackedCoins;
    if (empty($webhooks)) return;
    
    $message = "⚠️ **New Blacklisted Addresses Detected!** ⚠️\n\n";
    $totalBalance = 0;
    foreach ($webhooks as $w) {
        $coin = $trackedCoins[$w['asset']];
        $totalBalance += $w['balance'];
        $message .= "• Asset: " . $coin['name'] . "\n";
        $displayAddress = (isset($coin['chain']) && $coin['chain'] === 'TRON') ? hexToBase58($w['address']) : $w['address'];
        $message .= "• Wallet: `" . $displayAddress . "`\n";
        $message .= "💰 Frozen Balance: $" . number_format($w['balance'], 2) . "\n";
        $message .= "🔗 [View Explorer](" . $coin['explorer_url'] . $displayAddress . ")\n\n";
    }
    
    $totalBalanceFormatted = number_format($totalBalance, 2);
    $totalM = round($totalBalance / 1000000, 1);
    $message .= count($webhooks) . " Transaction(s) worth of $" . $totalBalanceFormatted . " (" . $totalM . "M) frozen!\n\n_Consider using decentralized alternatives like Monero (XMR) to protect your assets from arbitrary freezes._";

    if (!empty($tgToken)) {
        $db = initDB();
        $res = $db->query('SELECT chat_id FROM telegram_subscribers');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $chatId = $row['chat_id'];
            $url = "https://api.telegram.org/bot$tgToken/sendMessage";
            $data = [
                'chat_id' => $chatId,
                'text' => str_replace(['**', '`'], ['*', '`'], $message),
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true
            ];
            $options = ['http' => ['header' => "Content-type: application/json\r\n", 'method' => 'POST', 'content' => json_encode($data)]];
            @file_get_contents($url, false, stream_context_create($options));
        }
    }

    if (!empty($discordBotToken)) {
        $db = initDB();
        $res = $db->query('SELECT channel_id FROM discord_subscribers');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $channelId = $row['channel_id'];
            $url = "https://discord.com/api/v10/channels/$channelId/messages";
            $data = ['content' => $message];
            $options = [
                'http' => [
                    'header'  => "Content-type: application/json\r\nAuthorization: Bot $discordBotToken\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($data)
                ]
            ];
            @file_get_contents($url, false, stream_context_create($options));
        }
    }
}

function runSync($etherscanKey, $tgToken, $discordBotToken, $isInitialSetup = false) {
    global $trackedCoins;
    
    // PART A: EVENT INGESTION (Fast)
    foreach ($trackedCoins as $assetCode => $coin) {
        $page = 1;
        $highestBlock = getLastSyncBlock($assetCode);
        
        if ($isInitialSetup) {
            echo "\n=== Syncing Events for $assetCode ({$coin['name']}) ===\n";
            echo "Starting from block pointer: $highestBlock\n";
            flush();
        }

        if (isset($coin['chain']) && $coin['chain'] === 'TRON') {
            $logs = fetchTronGridLogs($coin['address'], 'AddedBlackList', $highestBlock);
            if ($isInitialSetup) { echo "Fetching TronGrid Logs...\n"; flush(); }
            
            if (is_array($logs) && !empty($logs)) {
                $maxTimestamp = $highestBlock;
                foreach($logs as $log) {
                    if (empty($log['result']['_user'])) continue;
                    $address = strtolower($log['result']['_user']);
                    $logTimestamp = isset($log['block_timestamp']) ? floor($log['block_timestamp'] / 1000) : time();
                    
                    if (isset($log['block_timestamp']) && $log['block_timestamp'] > $maxTimestamp) {
                        $maxTimestamp = $log['block_timestamp'];
                    }
                    
                    $db = initDB();
                    $stmt = $db->prepare('SELECT id FROM blacklisted_addresses WHERE asset = :asset AND address = :address');
                    $stmt->bindValue(':asset', $assetCode, SQLITE3_TEXT);
                    $stmt->bindValue(':address', $address, SQLITE3_TEXT);
                    $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    
                    if (!$exists) {
                        $notifiedStatus = $isInitialSetup ? 1 : 0;
                        $insert = $db->prepare('INSERT INTO blacklisted_addresses (asset, address, timestamp, notified, balance) VALUES (:asset, :address, :ts, :notified, -1)');
                        $insert->bindValue(':asset', $assetCode, SQLITE3_TEXT);
                        $insert->bindValue(':address', $address, SQLITE3_TEXT);
                        $insert->bindValue(':ts', $logTimestamp, SQLITE3_INTEGER);
                        $insert->bindValue(':notified', $notifiedStatus, SQLITE3_INTEGER);
                        $insert->execute();
                    }
                }
                
                if ($maxTimestamp > $highestBlock) {
                    setLastSyncBlock($assetCode, $maxTimestamp);
                }
            }
        } else {
            while (true) {
                usleep(250000); // 0.25s delay to strictly respect Etherscan's 5 req/sec limit
                $logs = fetchEtherscanLogs($etherscanKey, $coin['address'], $coin['topic0'], $highestBlock, $page);
                if ($isInitialSetup) { echo "Fetching Etherscan Logs (Page $page)...\n"; flush(); }
                
                if (!is_array($logs) || empty($logs)) break;
                
                $maxBlockInPage = $highestBlock;
                
                foreach($logs as $log) {
                    $address = '0x';
                    if (!empty($log['topics'][1])) {
                        $address = '0x' . substr($log['topics'][1], 26);
                    } elseif (!empty($log['data']) && strlen($log['data']) >= 66) {
                        $address = '0x' . substr($log['data'], 26);
                    }
                    if ($address === '0x') continue;
                    $logTimestamp = isset($log['timeStamp']) ? hexdec($log['timeStamp']) : time();
                    $blockNumber = isset($log['blockNumber']) ? hexdec($log['blockNumber']) : 0;
                    
                    if ($blockNumber > $maxBlockInPage) {
                        $maxBlockInPage = $blockNumber;
                    }
                    
                    // Add to database if not exists
                    $db = initDB();
                    $stmt = $db->prepare('SELECT id FROM blacklisted_addresses WHERE asset = :asset AND address = :address');
                    $stmt->bindValue(':asset', $assetCode, SQLITE3_TEXT);
                    $stmt->bindValue(':address', $address, SQLITE3_TEXT);
                    $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    
                    if (!$exists) {
                        // Set balance = -1 (Unchecked Queue Marker)
                        $notifiedStatus = $isInitialSetup ? 1 : 0;
                        
                        $insert = $db->prepare('INSERT INTO blacklisted_addresses (asset, address, timestamp, notified, balance) VALUES (:asset, :address, :ts, :notified, -1)');
                        $insert->bindValue(':asset', $assetCode, SQLITE3_TEXT);
                        $insert->bindValue(':address', $address, SQLITE3_TEXT);
                        $insert->bindValue(':ts', $logTimestamp, SQLITE3_INTEGER);
                        $insert->bindValue(':notified', $notifiedStatus, SQLITE3_INTEGER);
                        $insert->execute();
                    }
                }
                
                if ($maxBlockInPage > $highestBlock) {
                    $highestBlock = $maxBlockInPage;
                    setLastSyncBlock($assetCode, $highestBlock);
                }
                
                if (count($logs) < 1000) break;
                $page++;
            }
        }
    }
    
    // PART B: BALANCE QUEUE PROCESSOR (Rate-limited)
    $db = initDB();
    $newWebhooks = [];
    
    // Grab up to 100 unchecked balances across all assets (Superfast RPC)
    $res = $db->query("SELECT * FROM blacklisted_addresses WHERE balance = -1 LIMIT 100");
    
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $assetCode = $row['asset'];
        $address = $row['address'];
        $coin = $trackedCoins[$assetCode];
        
        if (isset($coin['chain']) && $coin['chain'] === 'TRON') {
            $base58Address = hexToBase58($address);
            $realBalance = fetchTronTokenBalance($base58Address, $coin['address'], $coin['decimals']);
        } else {
            $realBalance = fetchTokenBalance($address, $coin['address'], $coin['decimals'], $etherscanKey);
        }
        
        // Update DB
        $update = $db->prepare('UPDATE blacklisted_addresses SET balance = :bal WHERE id = :id');
        $update->bindValue(':bal', $realBalance, SQLITE3_FLOAT);
        $update->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $update->execute();
        
        if ($isInitialSetup) {
            echo "Fetched balance for $address [$assetCode] ($" . number_format($realBalance, 2) . ")...\n";
            flush();
        }
        
        // Send Webhook if it has money AND hasn't been notified (historical data has notified=1)
        if ($realBalance > 0 && $row['notified'] == 0) {
            $newWebhooks[] = ['asset' => $assetCode, 'address' => $address, 'balance' => $realBalance];
            markAsNotified($assetCode, $address);
        }
    }
    
    // If we're in initial setup, print a message explaining the queue
    if ($isInitialSetup) {
        $left = $db->querySingle("SELECT COUNT(*) FROM blacklisted_addresses WHERE balance = -1");
        if ($left > 0) {
            echo "\n[INFO] Fast Event Ingestion Complete!\n";
            echo "There are $left addresses queued for balance checking.\n";
            echo "The background Cron Job will process them safely over time.\n";
        }
    }
    
    if (!$isInitialSetup && !empty($newWebhooks)) {
        sendGroupedWebhook($newWebhooks, $tgToken, $discordBotToken);
    }
}

// Execute if run directly via CLI or via Web with ?cron=1
if ((php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) || isset($_GET['cron'])) {
    $lockFile = fopen(__DIR__ . '/sync.lock', 'w+');
    if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
        die("Process is already running.\n");
    }
    
    runSync($etherscanKey, $tgToken, $discordBotToken, false);
    
    flock($lockFile, LOCK_UN);
    fclose($lockFile);
}
