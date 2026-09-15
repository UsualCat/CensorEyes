<?php
// config.php

// 1. Etherscan API Key (Get it at https://etherscan.io/apis)
$etherscanKey = 'YOUR_ETHERSCAN_API_KEY_HERE';

// 2. Telegram Bot Token (Get it from @BotFather on Telegram)
$tgToken = 'YOUR_TELEGRAM_BOT_TOKEN_HERE';

// 3. Discord Bot Token (Get it from Discord Developer Portal -> Bot -> Token)
$discordBotToken = 'YOUR_DISCORD_BOT_TOKEN_HERE';

// 4. Discord Public Key (Get it from Discord Developer Portal -> General Information -> Public Key)
$discordPublicKey = 'YOUR_DISCORD_PUBLIC_KEY_HERE';

// 5. Admin Panel Password
$adminPassword = 'YOUR_ADMIN_PASSWORD_HERE';

// 6. TronGrid API Key (Optional, get it at https://www.trongrid.io/)
$tronGridKey = 'YOUR_TRONGRID_API_KEY_HERE';


$trackedCoins = [
    'USDT' => [
        'name' => 'USDT (ERC-20)',
        'chain' => 'ETH',
        'address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
        'topic0' => '0x42e160154868087d6bfdc0ca23d96a1c1cfa32f1b72ba9ba27b69b98a0d819dc',
        'decimals' => 6,
        'color' => '#22c55e',
        'explorer_url' => 'https://etherscan.io/address/'
    ],
    'USDC' => [
        'name' => 'USDC (ERC-20)',
        'chain' => 'ETH',
        'address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
        'topic0' => '0xffa4e6181777692565cf28528fc88fd1516ea86b56da075235fa575af6a4b855',
        'decimals' => 6,
        'color' => '#3b82f6',
        'explorer_url' => 'https://etherscan.io/address/'
    ],
    'USDT_TRON' => [
        'name' => 'USDT (TRC-20)',
        'chain' => 'TRON',
        'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
        'topic0' => '0x42e160154868087d6bfdc0ca23d96a1c1cfa32f1b72ba9ba27b69b98a0d819dc', // AddedBlackList(address)
        'decimals' => 6,
        'color' => '#ef4444',
        'explorer_url' => 'https://tronscan.org/#/address/'
    ]
];
