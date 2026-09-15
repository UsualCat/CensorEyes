# CensorEyes Setup Guide (Web & Bot Notifications)

CensorEyes is a lightweight tracking application for Tether (USDT) blacklisted addresses on the Ethereum network, built purely with PHP and SQLite. This guide explains how to fill in the credentials in your `config.php` file.

## 1. Getting the Etherscan API Key
Etherscan is used to track the blockchain history in real-time.
1. Open [Etherscan.io](https://etherscan.io/) and register for an account.
2. Login to your dashboard and navigate to the **API Keys** menu.
3. Click the **Add** button to create a new token.
4. Copy the API token and paste it into the `$etherscanKey` variable in `config.php`.

## 2. Getting the Telegram Bot Token & Webhook Setup
The Telegram bot is used to send instant notifications and allow users to subscribe.
1. Open the Telegram app and search for **@BotFather**.
2. Send the `/newbot` command and follow the instructions to create a bot.
3. BotFather will provide an **HTTP API Token** (e.g., `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11`).
4. Paste the token into the `$tgToken` variable in `config.php`.
5. **Set up the Webhook**: To allow your bot to reply to users who type `/start`, you must register your server's URL with Telegram. Open this URL in your browser (replace `<YOUR_TOKEN>` and `<YOUR_DOMAIN>`):
   `https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://<YOUR_DOMAIN>/telegram_webhook.php`
   *(Note: You must have an HTTPS domain. Localhost will not work).*

## 3. Getting the Discord Bot Token & Public Key
To allow your community to invite the bot and use Slash Commands (like `/setchannel`):
1. Go to the [Discord Developer Portal](https://discord.com/developers/applications) and create a New Application.
2. In the **General Information** tab, copy your **Public Key** and paste it into `$discordPublicKey` in `config.php`.
3. Go to the **Bot** tab, click **Reset Token**, and copy the **Bot Token**. Paste it into `$discordBotToken` in `config.php`.
4. In the **Interactions Endpoint URL** field (General Information tab), paste your server's endpoint:
   `https://<YOUR_DOMAIN>/discord_webhook.php`
   *(Discord will instantly send a PING to verify your server. It requires a valid HTTPS domain).*
5. Invite your bot to your server using the OAuth2 URL Generator (Select `bot` and `applications.commands` scopes).
6. Run `php register_commands.php` from your terminal to register the Slash Commands to Discord's API.
7. In any channel on your server, type `/setchannel` to register that channel for CensorEyes broadcast alerts.

## 4. Database Security
This application uses SQLite (`censoreyes.db`). It is critical to prevent the public from downloading this file.
- **Apache Users**: The `.htaccess` file is already provided to block access to `.db` files.
- **Nginx Users**: Open the `nginx.conf` file in this project and place the provided `location` block into your Nginx server configuration.

## 5. Initial Historical Setup (Important)
Before activating the regular sync, it's highly recommended to grab all historical addresses. 
1. Log into your Admin Panel (`https://yourdomain.com/admin.php`).
2. Scroll to the bottom and click **Run Setup (New Tab)**.
3. Keep the new tab open until it says "Initial Setup Completed Successfully!".
*(This process fetches all ~1,500 historical blacklisted addresses and caches them as `0` balance in your database without triggering Etherscan API limits or sending spam notifications).*

## 6. Background Auto-Sync (Cron Job)
The manual sync button on the admin panel is good for testing, but to continuously monitor the blockchain, you must set up a Cron Job on your server.
Run this command in your VPS SSH terminal to open the cron editor:
```bash
crontab -e
```
Add the following line to the bottom of the file (this runs the sync every 5 minutes):
```bash
*/5 * * * * php /www/wwwroot/yourdomain.com/sync.php >> /www/wwwroot/yourdomain.com/sync.log 2>&1
```
*(Make sure to replace `/www/wwwroot/yourdomain.com/` with the actual absolute path to your web directory).*
