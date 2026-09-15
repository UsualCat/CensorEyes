<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sync.php';
require_once __DIR__ . '/config.php';

echo "Patching missed assets without wiping USDT progress...\n";
$db = initDB();

foreach ($trackedCoins as $assetCode => $coin) {
    if ($assetCode === 'USDT') continue; // Skip ETH USDT since it's already 100%
    
    if (isset($coin['chain']) && $coin['chain'] === 'TRON') {
        echo "Fetching $assetCode history from TronGrid...\n";
        $fingerprint = '';
        $page = 1;
        while (true) {
            usleep(250000);
            $url = "https://api.trongrid.io/v1/contracts/{$coin['address']}/events?event_name=AddedBlackList&limit=200";
            if ($fingerprint !== '') {
                $url .= "&fingerprint=" . urlencode($fingerprint);
            }
            $headers = "Accept: application/json\r\n";
            if (!empty($tronGridKey)) {
                $headers .= "TRON-PRO-API-KEY: $tronGridKey\r\n";
            }
            $opts = ['http' => ['header' => $headers, 'method' => 'GET']];
            $json = @file_get_contents($url, false, stream_context_create($opts));
            if(!$json) {
                echo "Error: Failed to fetch TronGrid (HTTP Request Failed). Probably Rate Limited!\n";
                break;
            }
            
            $data = json_decode($json, true);
            if (isset($data['success']) && $data['success'] === false) {
                echo "TronGrid Error: " . ($data['error'] ?? 'Unknown API Error') . "\n";
                break;
            }
            
            $logs = $data['data'] ?? [];
            if (empty($logs)) {
                echo "No more logs found.\n";
                break;
            }
            
            $inserted = 0;
            $maxTimestamp = 0;
            foreach($logs as $log) {
                if (empty($log['result']['_user'])) continue;
                $address = strtolower($log['result']['_user']);
                $logTimestamp = isset($log['block_timestamp']) ? floor($log['block_timestamp'] / 1000) : time();
                
                if (isset($log['block_timestamp']) && $log['block_timestamp'] > $maxTimestamp) {
                    $maxTimestamp = $log['block_timestamp'];
                }
                
                $exists = $db->querySingle("SELECT 1 FROM blacklisted_addresses WHERE asset = '$assetCode' AND address = '$address'");
                if (!$exists) {
                    $insert = $db->prepare('INSERT INTO blacklisted_addresses (asset, address, timestamp, notified, balance) VALUES (:asset, :address, :ts, 1, -1)');
                    $insert->bindValue(':asset', $assetCode, SQLITE3_TEXT);
                    $insert->bindValue(':address', $address, SQLITE3_TEXT);
                    $insert->bindValue(':ts', $logTimestamp, SQLITE3_INTEGER);
                    
                    if ($insert->execute()) {
                        $inserted++;
                    }
                }
            }
            
            if ($maxTimestamp > 0) {
                setLastSyncBlock($assetCode, $maxTimestamp);
            }
            
            echo "Page $page: Inserted $inserted TRON addresses.\n";
            if (!empty($data['meta']['fingerprint'])) {
                $fingerprint = $data['meta']['fingerprint'];
                $page++;
            } else {
                break;
            }
        }
        echo "Finished $assetCode.\n";
        continue;
    }

    $page = 1;
    // FORCE from block 0 because previous buggy runs corrupted sync_meta!
    $highestBlock = 0;
    
    echo "Fetching $assetCode from block $highestBlock...\n";
    
    while (true) {
        usleep(250000); // Respect Etherscan limit
        $logs = fetchEtherscanLogs($etherscanKey, $coin['address'], $coin['topic0'], $highestBlock, $page);
        
        if (!is_array($logs) || empty($logs)) {
            echo "Finished $assetCode.\n";
            break;
        }
        
        $maxBlockInPage = $highestBlock;
        $inserted = 0;
        
        foreach($logs as $log) {
            $address = '0x';
            if (!empty($log['topics'][1])) {
                $address = '0x' . substr($log['topics'][1], 26);
            } elseif (!empty($log['data']) && strlen($log['data']) >= 66) {
                $address = '0x' . substr($log['data'], 26);
            }
            if ($address === '0x') {
                echo "Skipped: Address is 0x. Topics: " . json_encode($log['topics'] ?? []) . " Data: " . ($log['data'] ?? '') . "\n";
                continue;
            }
            
            $logTimestamp = isset($log['timeStamp']) ? hexdec($log['timeStamp']) : time();
            $blockNumber = isset($log['blockNumber']) ? hexdec($log['blockNumber']) : 0;
            
            if ($blockNumber > $maxBlockInPage) {
                $maxBlockInPage = $blockNumber;
            }
            
            $exists = $db->querySingle("SELECT 1 FROM blacklisted_addresses WHERE asset = '$assetCode' AND address = '$address'");
            if (!$exists) {
                // FORCE NOTIFIED = 1 TO PREVENT SPAM!!!
                $insert = $db->prepare('INSERT INTO blacklisted_addresses (asset, address, timestamp, notified, balance) VALUES (:asset, :address, :ts, 1, -1)');
                $insert->bindValue(':asset', $assetCode, SQLITE3_TEXT);
                $insert->bindValue(':address', $address, SQLITE3_TEXT);
                $insert->bindValue(':ts', $logTimestamp, SQLITE3_INTEGER);
                
                if ($insert->execute()) {
                    $inserted++;
                } else {
                    echo "Failed to insert $address for $assetCode!\n";
                }
            } else {
                echo "Address $address already exists for $assetCode!\n";
            }
        }
        
        if ($maxBlockInPage > $highestBlock) {
            $highestBlock = $maxBlockInPage;
            setLastSyncBlock($assetCode, $highestBlock);
        }
        
        echo "Page $page: Inserted $inserted addresses.\n";
        $page++;
    }
}
echo "Patch complete! You can safely delete this file.\n";
