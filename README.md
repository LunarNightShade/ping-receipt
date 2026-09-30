# Ping Receipt

> A personal fork of [aschmelyun's ping project](https://ping.aschmelyun.com) with refactors and cleanups. Watch the [original video](https://www.youtube.com/watch?v=7KtyekivpRM) for the backstory.

A tiny website that lets anyone send a short, anonymous message that prints out on the receipt printer on my desk.

---

## How it works

1. A visitor loads `/` and sees a receipt-shaped form ([`routes/web.php`](routes/web.php), [`resources/views/app.blade.php`](resources/views/app.blade.php)).
2. Submitting posts to `/send-message`, handled by [`SendMessageController`](app/Http/Controllers/SendMessageController.php):
   - [`SendMessageRequest`](app/Http/Requests/SendMessageRequest.php) validates and cleans the text (ASCII only, converts smart quotes).
   - The message is saved as a `Receipt` row.
   - A [`PrintReceipt`](app/Jobs/PrintReceipt.php) job is put on the queue, so the visitor never waits on the printer.
3. A background queue worker (started automatically inside the container) picks the job up. It uses [`ReceiptPrinter`](app/Services/ReceiptPrinter.php) to format and send the receipt to the printer, then marks the row `has_printed`.

**If the printer is off or unreachable, it retries automatically** — see [Automatic retries](#automatic-retries). The message is saved first, so nothing is lost either way.

Printer connection settings live in [`config/printer.php`](config/printer.php) and are read from the environment.

---

## Hardware

- An Epson (or other **ESC/POS**) receipt printer **with a network/Ethernet port**, reachable at a known IP on port `9100`. Older models like the TM-T88V can be found cheaply on eBay.
- Any always-on machine that can run Docker (a Raspberry Pi is plenty).

> Earlier versions of this project printed over USB. This fork prints over the **network** — set the printer's IP in the configuration below.

---

## Running it

**1. Clone the repo** onto your Docker host and open a terminal in it.

**2. Create the database file** (the container bind-mounts it, so it must exist first):

```sh
touch database/database.sqlite
```

**3. Set your printer's IP** in [`compose.yml`](compose.yml) under `environment:`:

```yaml
    environment:
      PRINTER_HOST: "192.168.1.217"   # <-- your printer's IP
      PRINTER_PORT: "9100"
```

**4. Build and start:**

```sh
docker compose up -d --build
```

The site is now at `http://localhost:8000`. The container builds Composer dependencies and front-end assets, generates an app key, and runs database migrations automatically on first boot.

To view logs or stop:

```sh
docker compose logs -f
docker compose down
```

---

## Configuration

| Variable         | Default            | Purpose                                   |
| ---------------- | ------------------ | ----------------------------------------- |
| `PRINTER_HOST`   | `192.168.1.217`    | Printer IP address                        |
| `PRINTER_PORT`   | `9100`             | Printer raw-print port                    |
| `PRINTER_TIMEOUT` | `5`               | Seconds to wait when connecting to the printer |
| `PRINTER_RETRY_HOURS` | `6`           | How long to keep retrying a failed print  |
| `PRINTER_WIDTH`  | `48`               | Characters per printed line               |
| `PRINTER_RECIPIENT` | `MESSAGE FOR LUNAR AURORA` | Sub-heading printed under "PING" |
| `APP_ENV`        | `production`       | Baked into the image                      |
| `APP_DEBUG`      | `false`            | Baked into the image                      |

**Persisting the app key (optional).** On each fresh start the container generates a new `APP_KEY`, which invalidates any open sessions (a visitor mid-form may need to refresh). To keep it stable, generate one and set it in `compose.yml`:

```sh
openssl rand -base64 32     # prepend "base64:" to the output
```

```yaml
    environment:
      APP_KEY: "base64:....your-generated-key...."
```

---

## Running behind a reverse proxy

This app is built to sit behind a reverse proxy (Traefik, Caddy, nginx, Cloudflare Tunnel, etc.):

- Forward your proxy to the published container port (`8000` by default).
- The app **trusts proxy headers** ([`bootstrap/app.php`](bootstrap/app.php)) so HTTPS is detected correctly and rate limiting keys off the real visitor IP. This assumes the container is only reachable *through* the proxy — if it's directly reachable too, change `trustProxies(at: '*')` to your proxy's IP/subnet.
- In production the app also forces `https://` when generating URLs.
- Make sure your proxy passes `X-Forwarded-For` and `X-Forwarded-Proto`.

The `/send-message` endpoint is rate limited to 10 requests/minute per IP ([`routes/web.php`](routes/web.php)).

---

## Automatic retries

If the printer can't be reached (powered off, out of range, a network hiccup), the print job is retried on its own with a growing delay: after 10 seconds, 30 seconds, 1 minute, 5 minutes, then every 15 minutes. It keeps trying for `PRINTER_RETRY_HOURS` (6 hours by default) from the moment the message was sent, so a message sent while the printer is off still prints when you switch it back on. The visitor always sees a normal "sent" message.

The worker that does this runs inside the same container as the website, and restarts itself if it ever stops. Each failed attempt is written to the log:

```sh
docker compose exec ping-app tail -f storage/logs/laravel.log
```

Once the retry window has passed, the job is recorded as failed and the receipt stays `has_printed = false`.

## Recovering failed prints

If a print exhausted its retries, reprint anything that didn't make it:

```sh
docker compose exec ping-app php artisan receipts:reprint          # only un-printed messages
docker compose exec ping-app php artisan receipts:reprint --all    # every stored message
```

**Upgrading from an older version?** A one-time migration runs on first start and marks every message already in the database as printed, so `receipts:reprint` won't print your whole history again.

`receipts:reprint` prints straight away, so avoid running it while a message is still being retried automatically, or it can print twice. To see what has given up (or clear the record of it):

```sh
docker compose exec ping-app php artisan queue:failed
docker compose exec ping-app php artisan queue:flush
```

---

## Local development (without Docker)

Requires PHP 8.2+, Composer, and Node.

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# run the dev server, queue worker and Vite together
composer run dev
```

`composer run dev` includes the queue worker, which is what actually prints receipts. If you start the server some other way, also run `php artisan queue:work` in another terminal, or messages will sit in the queue and never print.

Then visit the URL shown by `php artisan serve` (usually `http://localhost:8000`).
