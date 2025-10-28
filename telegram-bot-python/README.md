Telegram Bot (Python / Windows)

Overview
- Minimal Python 3 bot using only the standard library (urllib + json).
- Replies to /start (or any message) with your Telegram user_id and basic details.

Requirements
- Python 3 installed and available as `py` or `python` in your PATH.

Run
- From this folder `telegram-bot-python`:
  - `run.bat <YOUR_TELEGRAM_BOT_TOKEN>`
  - or: `set BOT_TOKEN=<YOUR_TELEGRAM_BOT_TOKEN>` then `run.bat`
  - or directly: `python bot.py <YOUR_TELEGRAM_BOT_TOKEN>`

Behavior
- Uses long polling (`getUpdates`) with `allowed_updates=["message"]`.
- Replies with:
  - user_id, username, first_name, last_name, language_code, is_premium, chat_id

Notes
- Stop with Ctrl+C.
- Users must send /start to your bot to initiate a chat.

