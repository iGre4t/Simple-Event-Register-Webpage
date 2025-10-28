#!/usr/bin/env python3
import json
import os
import sys
import time
import urllib.parse
import urllib.request


def api_get(base_url, method, params=None, timeout=70):
    if params is None:
        params = {}
    query = urllib.parse.urlencode(params)
    url = f"{base_url}{method}"
    if query:
        url += "?" + query
    req = urllib.request.Request(url, headers={"User-Agent": "PyTgBot/1.0"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read().decode("utf-8")


def main():
    token = None
    if len(sys.argv) >= 2 and sys.argv[1].strip():
        token = sys.argv[1].strip()
    if not token:
        token = os.environ.get("BOT_TOKEN", "").strip()
    if not token:
        print("Usage: python bot.py <TELEGRAM_BOT_TOKEN>\nOr set BOT_TOKEN environment variable.")
        sys.exit(1)

    base = f"https://api.telegram.org/bot{token}/"
    last_offset = 0
    print("Bot started. Waiting for messages... (Ctrl+C to stop)")

    while True:
        try:
            params = {
                "timeout": 50,
                "allowed_updates": json.dumps(["message"]),
            }
            if last_offset:
                params["offset"] = last_offset + 1

            raw = api_get(base, "getUpdates", params=params)
            data = json.loads(raw)
            if not isinstance(data, dict) or not data.get("ok"):
                time.sleep(1)
                continue

            results = data.get("result", [])
            max_update_id = last_offset
            for upd in results:
                if isinstance(upd, dict):
                    uid = upd.get("update_id")
                    if isinstance(uid, int) and uid > max_update_id:
                        max_update_id = uid

                    msg = upd.get("message") or {}
                    if not isinstance(msg, dict):
                        continue

                    frm = msg.get("from") or {}
                    chat = msg.get("chat") or {}
                    if not isinstance(frm, dict) or not isinstance(chat, dict):
                        continue

                    user_id = frm.get("id")
                    chat_id = chat.get("id")
                    if not isinstance(user_id, int) or not isinstance(chat_id, int):
                        continue

                    username = frm.get("username")
                    first = frm.get("first_name")
                    last = frm.get("last_name")
                    lang = frm.get("language_code")
                    is_premium = frm.get("is_premium")

                    lines = [
                        "Here are your Telegram details:",
                        f"- user_id: {user_id}",
                    ]
                    if username:
                        lines.append(f"- username: @{username}")
                    if first:
                        lines.append(f"- first_name: {first}")
                    if last:
                        lines.append(f"- last_name: {last}")
                    if lang:
                        lines.append(f"- language_code: {lang}")
                    if isinstance(is_premium, bool):
                        lines.append(f"- is_premium: {'true' if is_premium else 'false'}")
                    lines.append(f"- chat_id: {chat_id}")

                    text = "\n".join(lines)
                    params = {
                        "chat_id": chat_id,
                        "text": text,
                        "disable_web_page_preview": "true",
                    }
                    # fire-and-forget; ignore response body
                    try:
                        api_get(base, "sendMessage", params=params, timeout=30)
                    except Exception:
                        pass

            last_offset = max_update_id

            if not results:
                time.sleep(0.2)

        except KeyboardInterrupt:
            print("\nStopping...")
            break
        except Exception as e:
            # transient network/json errors
            try:
                print(f"Error: {e}")
            except Exception:
                pass
            time.sleep(2)


if __name__ == "__main__":
    main()

