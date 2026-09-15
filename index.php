<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sync.php';
require_once __DIR__ . '/config.php';

$db = initDB();

$assetFilter = isset($_GET['asset']) ? $_GET['asset'] : '';
$whereClause = "WHERE balance > 0";
if ($assetFilter && array_key_exists($assetFilter, $trackedCoins)) {
    $whereClause .= " AND asset = '" . SQLite3::escapeString($assetFilter) . "'";
}

$totalRes = $db->querySingle("SELECT COUNT(*) FROM blacklisted_addresses $whereClause");
$limit = 50;
$totalPages = ceil($totalRes / $limit);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $limit;

$res = $db->query("SELECT * FROM blacklisted_addresses $whereClause ORDER BY timestamp DESC LIMIT $limit OFFSET $offset");
$addresses = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $addresses[] = $row;
}
$totalFrozen = getTotalFrozenAssets();
$oldestTimestamp = getOldestTimestamp();
$startDateStr = date('Y-m-d', $oldestTimestamp);

// Chart Data Processing
$resChart = $db->query("SELECT timestamp, balance FROM blacklisted_addresses WHERE balance > 0 ORDER BY timestamp ASC");
$daily = []; $weekly = []; $monthly = []; $yearly = [];
$cumDaily = 0; $cumWeekly = 0; $cumMonthly = 0; $cumYearly = 0;

while ($r = $resChart->fetchArray(SQLITE3_ASSOC)) {
    $ts = $r['timestamp'];
    $bal = $r['balance'];
    
    $dKey = date('Y-m-d', $ts);
    $wKey = date('o-\WW', $ts);
    $mKey = date('Y-m', $ts);
    $yKey = date('Y', $ts);
    
    if (!isset($daily[$dKey])) $daily[$dKey] = 0;
    $daily[$dKey] += $bal;
    
    if (!isset($weekly[$wKey])) $weekly[$wKey] = 0;
    $weekly[$wKey] += $bal;
    
    if (!isset($monthly[$mKey])) $monthly[$mKey] = 0;
    $monthly[$mKey] += $bal;
    
    if (!isset($yearly[$yKey])) $yearly[$yKey] = 0;
    $yearly[$yKey] += $bal;
}

function buildCumulative($arr) {
    $labels = []; $data = []; $cum = 0;
    foreach ($arr as $k => $v) {
        $cum += $v;
        $labels[] = $k;
        $data[] = $cum;
    }
    return ['labels' => $labels, 'data' => $data];
}

function formatCompact($num) {
    if ($num >= 1000000000) return round($num / 1000000000, 2) . 'B';
    if ($num >= 1000000) return round($num / 1000000, 2) . 'M';
    if ($num >= 1000) return round($num / 1000, 2) . 'K';
    return number_format($num, 2);
}

$chartDaily = buildCumulative($daily);
$chartWeekly = buildCumulative($weekly);
$chartMonthly = buildCumulative($monthly);
$chartYearly = buildCumulative($yearly);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CensorEyes - USDT (ERC-20) Blacklist Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --surface-color: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --accent-color: #3b82f6;
            --accent-hover: #60a5fa;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        nav {
            width: 100%;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--surface-color);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            background: linear-gradient(135deg, var(--danger-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .theme-toggle {
            background: none;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            color: var(--text-main);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .theme-toggle:hover {
            background-color: var(--border-color);
        }

        main {
            width: 100%;
            max-width: 1000px;
            padding: 2rem;
            flex-grow: 1;
        }

        .hero {
            text-align: center;
            margin-bottom: 3rem;
            padding: 3rem 0 1rem 0;
        }

        .total-amount {
            font-size: 5rem;
            font-weight: 800;
            color: var(--danger-color);
            margin: 1rem 0 0.5rem 0;
            letter-spacing: -0.05em;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1.125rem;
        }
        
        .marketing {
            margin-top: 1.5rem;
            font-size: 1.125rem;
            font-weight: 500;
            color: var(--text-main);
            background-color: rgba(239, 68, 68, 0.1);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--danger-color);
        }

        .card {
            background-color: var(--surface-color);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .chart-controls {
            display: flex;
            gap: 0.5rem;
        }
        
        .chart-btn {
            background: none;
            border: 1px solid var(--border-color);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .chart-btn.active {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background-color: rgba(0,0,0,0.02);
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] th {
            background-color: rgba(255,255,255,0.02);
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-success { background-color: #dcfce7; color: #166534; }
        [data-theme="dark"] .badge-success { background-color: rgba(6, 78, 59, 0.5); color: #34d399; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; }
        [data-theme="dark"] .badge-pending { background-color: rgba(66, 32, 6, 0.5); color: #fde047; }
        .address { font-family: monospace; color: var(--accent-color); background: rgba(59, 130, 246, 0.1); padding: 0.2rem 0.4rem; border-radius: 4px; }
    </style>
</head>
<body>

    <nav>
        <div class="logo">CensorEyes</div>
        <button class="theme-toggle" id="themeToggle">
            <span id="themeIcon">🌙</span> <span id="themeText">Dark Mode</span>
        </button>
    </nav>

    <main>
        <div class="hero">
            <p class="subtitle">Total Stablecoins Frozen on Ethereum<br><small>(Tracked since <?= htmlspecialchars($startDateStr) ?>)</small></p>
            <h1 class="total-amount">$<?= formatCompact($totalFrozen) ?></h1>
            <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500; margin-bottom: 2rem;">
                Exact: $<?= number_format($totalFrozen, 2) ?>
            </p>
            <div class="marketing">
                Don't let foreign parties freeze your assets! Use real crypto like Monero (XMR) where no one can block or freeze your balance.
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Frozen Assets Timeline</h2>
                <div class="chart-controls">
                    <button class="chart-btn" onclick="updateChart('daily')">Daily</button>
                    <button class="chart-btn active" onclick="updateChart('weekly')">Weekly</button>
                    <button class="chart-btn" onclick="updateChart('monthly')">Monthly</button>
                    <button class="chart-btn" onclick="updateChart('yearly')">Yearly</button>
                </div>
            </div>
            <div style="padding: 1.5rem;">
                <canvas id="frozenChart" height="100"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Blacklisted Addresses</h2>
            </div>
            
            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; gap: 0.5rem; flex-wrap: wrap; background: var(--card-bg);">
                <a href="?" class="badge" style="text-decoration:none; padding: 0.4rem 1rem; cursor: pointer; background: <?= empty($assetFilter) ? 'var(--accent-color)' : 'var(--border-color)' ?>; color: <?= empty($assetFilter) ? '#fff' : 'var(--text-color)' ?>;">ALL</a>
                <?php foreach($trackedCoins as $code => $coin): ?>
                    <a href="?asset=<?= urlencode($code) ?>" class="badge" style="text-decoration:none; padding: 0.4rem 1rem; cursor: pointer; background: <?= $assetFilter === $code ? $coin['color'] : 'var(--border-color)' ?>; color: <?= $assetFilter === $code ? '#fff' : 'var(--text-color)' ?>;"><?= htmlspecialchars($code) ?></a>
                <?php endforeach; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Address</th>
                        <th>Frozen Balance</th>
                        <th>Date Frozen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($addresses)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No data available.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach($addresses as $a): ?>
                    <?php $coin = $trackedCoins[$a['asset']] ?? ['name' => $a['asset'], 'color' => '#888']; ?>
                    <tr>
                        <td><span style="font-weight: 700; color: <?= $coin['color'] ?>;"><?= htmlspecialchars($coin['name']) ?></span></td>
                        <td><span class="address"><?= htmlspecialchars($a['address']) ?></span></td>
                        <td style="font-weight: 600;">$<?= number_format($a['balance'], 2) ?></td>
                        <td style="color: var(--text-muted); font-size: 0.9rem;">
                            <?= date('Y-m-d H:i', $a['timestamp']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border-color);">
                <?php 
                    $baseUrl = '?';
                    if (!empty($assetFilter)) {
                        $baseUrl = '?asset=' . urlencode($assetFilter) . '&';
                    }
                    renderPagination($page, $totalPages, $baseUrl); 
                ?>
            </div>
        </div>
    </main>

    <script>
        const toggleBtn = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        setTheme(savedTheme);

        toggleBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        });

        function setTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            if (theme === 'dark') {
                document.getElementById('themeIcon').textContent = '☀️';
                document.getElementById('themeText').textContent = 'Light Mode';
                updateChartTheme('dark');
            } else {
                document.getElementById('themeIcon').textContent = '🌙';
                document.getElementById('themeText').textContent = 'Dark Mode';
                updateChartTheme('light');
            }
        }
        
        // Chart Data
        const chartData = {
            daily: <?= json_encode($chartDaily) ?>,
            weekly: <?= json_encode($chartWeekly) ?>,
            monthly: <?= json_encode($chartMonthly) ?>,
            yearly: <?= json_encode($chartYearly) ?>
        };
        
        let frozenChart = null;
        
        function initChart() {
            const ctx = document.getElementById('frozenChart').getContext('2d');
            frozenChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.weekly.labels,
                    datasets: [{
                        label: 'Cumulative Frozen USDT',
                        data: chartData.weekly.data,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#ef4444',
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
        
        function updateChart(period) {
            // Update active button
            document.querySelectorAll('.chart-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update chart data
            frozenChart.data.labels = chartData[period].labels;
            frozenChart.data.datasets[0].data = chartData[period].data;
            frozenChart.update();
        }
        
        function updateChartTheme(theme) {
            if (!frozenChart) return;
            const gridColor = theme === 'dark' ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const textColor = theme === 'dark' ? '#94a3b8' : '#64748b';
            frozenChart.options.scales.y.grid.color = gridColor;
            frozenChart.options.scales.y.ticks.color = textColor;
            frozenChart.options.scales.x.ticks.color = textColor;
            frozenChart.update();
        }
        
        window.onload = initChart;
    </script>
</body>
</html>
