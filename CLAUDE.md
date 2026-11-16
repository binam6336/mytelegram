# CLAUDE.md — telegram-panel

Separate Laravel 13 app (not part of Karlix) living in `telegram-panel/`. Persian RTL UI; reply to the user in Persian.
See README.md for architecture and shared-hosting deployment.

- Must run on shared hosting: never add long-lived connections, daemons, queues workers or websockets. Telegram work
  happens only in short runs: `php artisan telegram:exec` (spawned by `TelegramRunner`) or `telegram:sync` (cron).
  `MadelineGateway::api()` defines `MADELINE_WORKER_TYPE` so MadelineProto runs fully in-process (no IPC server).
- Business logic lives in `app/Services` (`TelegramService`, `KeywordFilter`); controllers stay thin.
- No frontend build: plain `public/css/app.css` + `public/js/app.js`.
- Local testing without Telegram: `TELEGRAM_FAKE=true` (code 12345; 22222 → 2FA password `secret`), sqlite, drive with Playwright.
- Syntax check: `php -l`, `node --check public/js/app.js`.
