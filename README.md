# CensorEyes 👀

CensorEyes is a real-time, multi-chain tracker that monitors blacklisted stablecoin addresses on Ethereum and TRON networks. It sends instant notifications to your Discord server or Telegram chat whenever a wallet holding assets gets frozen by centralized issuers like Tether (USDT) or Circle (USDC).

CensorEyes promotes decentralization by bringing awareness to frozen assets and encouraging alternatives like Monero (XMR).
Discord screenshots:
<img width="875" height="462" alt="Discord" src="https://github.com/user-attachments/assets/3b20da15-d66c-4c6e-94fc-972f951b7aa1" />
Telegram Screenshots:
<img width="500" height="385" alt="telegram" src="https://github.com/user-attachments/assets/6e4fff47-cb78-43b7-858c-971d89598c62" />



---

## 🤖 Try the Live Bots

If you just want to receive real-time alerts without hosting the code yourself, you can invite our official bots:

### Telegram Bot
1. Open [**@CensorEyesXMR_Bot**](https://t.me/CensorEyesXMR_Bot) on Telegram.
2. Send the `/start` command to the bot.
3. You will now automatically receive real-time alerts!

### Discord Bot
1. Invite the bot to your server using this **[Authorization Link](https://discord.com/oauth2/authorize?client_id=1545259650541297666)**.
2. Go to the specific text channel where you want the alerts to appear.
3. Type the `/setchannel` command in that channel.
4. The bot will confirm the setup and start sending real-time alerts to that channel!

---

## 🛠️ Self-Hosting Guide

If you want to run your own instance of CensorEyes, follow the steps below:

### 1. Requirements
- A server with PHP 8.x and SQLite3 extension enabled.
- Cron Jobs enabled on your server.
- Web server (Nginx/Apache) to receive webhooks from Telegram/Discord.

### 2. Configuration
1. Rename or open `config.php`.
2. Fill in your own API keys and bot tokens:
   - **Telegram Token**: Get from `@BotFather`.
   - **Discord Token & Public Key**: Get from Discord Developer Portal.
   - **Etherscan & TronGrid API Keys**: For fetching historical events.
   - **RPC Endpoints**: Replace the placeholder with your own paid/private Web3 RPC endpoints (e.g., Alchemy, Infura) for reliable balance checking.

### 3. Setup Webhooks
- **Telegram**: Run `setup.php` in your browser or CLI to register your Telegram Webhook URL with Telegram servers.
- **Discord**: Point your Discord App's Interactions Endpoint URL to `discord_webhook.php` in your Discord Developer Portal.

### 4. Running the Tracker
Set up a Cron Job to run `sync.php` every minute to check for newly blacklisted addresses:
```bash
* * * * * /usr/bin/php /path/to/your/folder/sync.php >> /dev/null 2>&1
```

### 5. Historical Data (Optional)
If you want to backfill the database with all historical blacklisted addresses from the dawn of time, run:
```bash
php patch.php
```
*Note: This will download thousands of addresses and set them to "muted" so they don't spam your Discord/Telegram.*

---

## ⚖️ License
This project is licensed under the **GNU Affero General Public License v3.0 (AGPLv3)**. 
You are free to use and modify this code, but if you run a modified version on a server or distribute it, you **MUST** publish your modified source code.
