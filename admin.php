<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sync.php';

$error = '';

if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = 'Invalid password';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$syncMessage = '';

if ($isLoggedIn && isset($_POST['sync'])) {
    runSync($etherscanKey, $tgToken, $discordBotToken);
    $syncMessage = 'Sync completed successfully!';
}

$addresses = [];
if ($isLoggedIn) {
    $db = initDB();
    $res = $db->query("SELECT * FROM blacklisted_addresses ORDER BY timestamp DESC LIMIT 50");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $addresses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CensorEyes Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --surface-color: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent-color: #3b82f6;
            --accent-hover: #2563eb;
            --danger-color: #ef4444;
            --success-color: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background-color: var(--surface-color);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            width: 100%;
            max-width: <?php echo $isLoggedIn ? '800px' : '400px'; ?>;
            text-align: center;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            background: linear-gradient(135deg, var(--danger-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-main);
        }

        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .btn {
            background-color: var(--accent-color);
            color: #ffffff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn:hover {
            background-color: var(--accent-hover);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--text-muted);
            box-shadow: none;
            border: 1px solid var(--border-color);
            margin-top: 1rem;
        }
        
        .btn-outline:hover {
            background-color: #f1f5f9;
            color: var(--text-main);
        }

        .error {
            color: var(--danger-color);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .success {
            background-color: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .admin-content h2 {
            margin-bottom: 1.5rem;
            color: var(--text-main);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-top: 2rem;
            font-size: 0.875rem;
        }

        th {
            background-color: rgba(0,0,0,0.02);
            padding: 1rem;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; }

        .address {
            font-family: monospace;
            color: var(--accent-color);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <div class="card">
        <div class="logo">CensorEyes Admin</div>

        <?php if (!$isLoggedIn): ?>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="password">Admin Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" name="login" class="btn">Login</button>
            </form>

        <?php else: ?>

            <div class="admin-content">
                <h2>Control Panel</h2>
                
                <?php if ($syncMessage): ?>
                    <div class="success"><?= htmlspecialchars($syncMessage) ?></div>
                <?php endif; ?>

                <form method="post">
                    <button type="submit" name="sync" class="btn">Trigger Manual Sync</button>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Address</th>
                            <th>Frozen Balance</th>
                            <th>Date Frozen</th>
                            <th>Notified</th>
                        </tr>
                    </thead>
                    <tbody>
            <?php
            $db = initDB();
            $totalRes = $db->querySingle("SELECT COUNT(*) FROM blacklisted_addresses WHERE balance > 0");
            $limit = 50;
            $totalPages = ceil($totalRes / $limit);
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) $page = 1;
            if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
            $offset = ($page - 1) * $limit;
            
            $res = $db->query("SELECT * FROM blacklisted_addresses WHERE balance > 0 ORDER BY timestamp DESC LIMIT $limit OFFSET $offset");
            $hasData = false;
            while ($row = $res->fetchArray(SQLITE3_ASSOC)): 
                $hasData = true;
            ?>
            <?php $coin = $trackedCoins[$row['asset']] ?? ['name' => $row['asset'], 'color' => '#888']; ?>
            <tr>
                <td><span style="font-weight: 700; color: <?= $coin['color'] ?>;"><?= htmlspecialchars($coin['name']) ?></span></td>
                <td><span class="address"><?= htmlspecialchars($row['address']) ?></span></td>
                <td style="font-weight: 600;">$<?= number_format($row['balance'], 2) ?></td>
                <td style="color: var(--text-muted); font-size: 0.9rem;">
                    <?= date('Y-m-d H:i', $row['timestamp']) ?>
                </td>
                <td>
                    <span class="badge <?= $row['notified'] ? 'badge-success' : 'badge-pending' ?>">
                        <?= $row['notified'] ? 'Yes' : 'Pending' ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
            
            <?php if (!$hasData): ?>
            <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted);">No addresses with frozen balance found.</td>
            </tr>
            <?php endif; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 1rem 0;">
                    <?php renderPagination($page, $totalPages); ?>
                </div>

                <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: left;">
                    <h3 style="margin-bottom: 10px;">🚀 Initial Setup</h3>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 15px;">Run this ONCE to quickly grab all historical blacklisted addresses (caches them as zero balance so future syncs are fast).</p>
                    <form method="POST" target="_blank" action="setup.php">
                        <button type="submit" class="btn" style="background-color: #ff9800; box-shadow: 0 4px 6px -1px rgba(255, 152, 0, 0.4);">Run Setup (New Tab)</button>
                    </form>
                </div>
                
                <a href="?logout=1" style="text-decoration:none; display:block; margin-top:2rem;">
                    <button type="button" class="btn btn-outline">Logout</button>
                </a>
            </div>

        <?php endif; ?>
    </div>

</body>
</html>
