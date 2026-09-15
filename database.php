<?php

function initDB() {
    $db = new SQLite3(__DIR__ . '/censoreyes.db');
    // Enable WAL mode for better concurrency and prevent DB lock
    $db->exec('PRAGMA journal_mode = wal;');
    $db->exec('PRAGMA synchronous = NORMAL;');
    
    // Table for tracking blacklisted addresses
    $db->exec("CREATE TABLE IF NOT EXISTS blacklisted_addresses (
        id INTEGER PRIMARY KEY,
        asset TEXT,
        address TEXT,
        timestamp INTEGER,
        notified BOOLEAN,
        balance REAL DEFAULT 0,
        UNIQUE(asset, address)
    )");
    
    // Table for tracking Telegram bot subscribers
    $db->exec("CREATE TABLE IF NOT EXISTS telegram_subscribers (
        chat_id TEXT PRIMARY KEY,
        joined_at INTEGER
    )");

    // Table for tracking Discord channels to broadcast
    $db->exec("CREATE TABLE IF NOT EXISTS discord_subscribers (
        channel_id TEXT PRIMARY KEY,
        guild_id TEXT,
        joined_at INTEGER
    )");
    
    // Table for tracking last synced blocks
    $db->exec("CREATE TABLE IF NOT EXISTS sync_meta (
        asset TEXT PRIMARY KEY,
        last_block INTEGER DEFAULT 0
    )");
    
    return $db;
}

function isAddressNotified($asset, $address) {
    $db = initDB();
    $stmt = $db->prepare('SELECT notified FROM blacklisted_addresses WHERE asset = :asset AND address = :address');
    $stmt->bindValue(':asset', $asset, SQLITE3_TEXT);
    $stmt->bindValue(':address', $address, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ? (bool)$row['notified'] : null;
}

function addAddress($asset, $address, $balance = 0, $timestamp = null) {
    if ($timestamp === null) $timestamp = time();
    $db = initDB();
    $stmt = $db->prepare('INSERT OR IGNORE INTO blacklisted_addresses (asset, address, timestamp, notified, balance) VALUES (:asset, :address, :time, 0, :balance)');
    $stmt->bindValue(':asset', $asset, SQLITE3_TEXT);
    $stmt->bindValue(':address', $address, SQLITE3_TEXT);
    $stmt->bindValue(':time', $timestamp, SQLITE3_INTEGER);
    $stmt->bindValue(':balance', $balance, SQLITE3_FLOAT);
    $stmt->execute();
    
    // Update balance if it was 0 and now we have a new value
    if ($balance > 0) {
        $stmt = $db->prepare('UPDATE blacklisted_addresses SET balance = :balance WHERE asset = :asset AND address = :address AND balance = 0');
        $stmt->bindValue(':balance', $balance, SQLITE3_FLOAT);
        $stmt->bindValue(':asset', $asset, SQLITE3_TEXT);
        $stmt->bindValue(':address', $address, SQLITE3_TEXT);
        $stmt->execute();
    }
}

function markAsNotified($asset, $address) {
    $db = initDB();
    $stmt = $db->prepare('UPDATE blacklisted_addresses SET notified = 1 WHERE asset = :asset AND address = :address');
    $stmt->bindValue(':asset', $asset, SQLITE3_TEXT);
    $stmt->bindValue(':address', $address, SQLITE3_TEXT);
    $stmt->execute();
}


function getTotalFrozenAssets() {
    $db = initDB();
    $res = $db->query('SELECT SUM(balance) as total FROM blacklisted_addresses');
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ? (float)$row['total'] : 0.0;
}

function getBlacklistCount() {
    $db = initDB();
    $res = $db->querySingle('SELECT COUNT(*) as cnt FROM blacklisted_addresses WHERE balance > 0');
    return $res ? $res : 0;
}

function getOldestTimestamp() {
    $db = initDB();
    $res = $db->querySingle('SELECT MIN(timestamp) FROM blacklisted_addresses');
    return $res ? $res : time();
}

function getLastSyncBlock($asset) {
    $db = initDB();
    $stmt = $db->prepare('SELECT last_block FROM sync_meta WHERE asset = :asset');
    $stmt->bindValue(':asset', $asset, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ? (int)$row['last_block'] : 0;
}

function setLastSyncBlock($asset, $blockNumber) {
    $db = initDB();
    $stmt = $db->prepare('INSERT OR REPLACE INTO sync_meta (asset, last_block) VALUES (:asset, :block)');
    $stmt->bindValue(':asset', $asset, SQLITE3_TEXT);
    $stmt->bindValue(':block', $blockNumber, SQLITE3_INTEGER);
    $stmt->execute();
}

function addTelegramSubscriber($chatId) {
    $db = initDB();
    $stmt = $db->prepare('INSERT OR IGNORE INTO telegram_subscribers (chat_id, joined_at) VALUES (:chat_id, :time)');
    $stmt->bindValue(':chat_id', $chatId, SQLITE3_TEXT);
    $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function getTelegramSubscribers() {
    $db = initDB();
    $res = $db->query('SELECT chat_id FROM telegram_subscribers');
    $subscribers = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $subscribers[] = $row['chat_id'];
    }
    return $subscribers;
}

function addDiscordSubscriber($channelId, $guildId = null) {
    $db = initDB();
    $stmt = $db->prepare('INSERT OR REPLACE INTO discord_subscribers (channel_id, guild_id, joined_at) VALUES (:channel_id, :guild_id, :time)');
    $stmt->bindValue(':channel_id', $channelId, SQLITE3_TEXT);
    $stmt->bindValue(':guild_id', $guildId, SQLITE3_TEXT);
    $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function getDiscordSubscribers() {
    $db = initDB();
    $res = $db->query('SELECT channel_id FROM discord_subscribers');
    $subscribers = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $subscribers[] = $row['channel_id'];
    }
    return $subscribers;
}

function renderPagination($page, $totalPages, $baseUrl = '?') {
    if ($totalPages <= 1) return;
    
    echo '<div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; flex-wrap: wrap;">';
    
    // Prev button
    if ($page > 1) {
        echo '<a href="'.$baseUrl.'page='.($page-1).'" style="text-decoration:none;"><button type="button" class="chart-btn" style="padding: 0.4rem 0.8rem; margin:0;">&laquo;</button></a>';
    }
    
    // Numbered buttons
    $window = 2; // how many buttons to show around the current page
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $page - $window && $i <= $page + $window)) {
            if ($i == $page) {
                echo '<button type="button" class="chart-btn active" style="padding: 0.4rem 0.8rem; margin:0; cursor:default;">'.$i.'</button>';
            } else {
                echo '<a href="'.$baseUrl.'page='.$i.'" style="text-decoration:none;"><button type="button" class="chart-btn" style="padding: 0.4rem 0.8rem; margin:0;">'.$i.'</button></a>';
            }
        } elseif ($i == $page - $window - 1 || $i == $page + $window + 1) {
            echo '<span style="color:var(--text-muted); padding: 0.4rem;">...</span>';
        }
    }
    
    // Next button
    if ($page < $totalPages) {
        echo '<a href="'.$baseUrl.'page='.($page+1).'" style="text-decoration:none;"><button type="button" class="chart-btn" style="padding: 0.4rem 0.8rem; margin:0;">&raquo;</button></a>';
    }
    
    echo '</div>';
}
