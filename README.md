# Ping Receipt

> A personal fork of [aschmelyun's ping project](https://ping.aschmelyun.com) with refactors and cleanups. Watch the [original video](https://www.youtube.com/watch?v=7KtyekivpRM) for the backstory.

A tiny website that lets anyone send a short, anonymous message that prints out on the receipt printer on my desk.

---

## How it works

1. A visitor loads `/` and sees a receipt-shaped form ([`routes/web.php`](routes/web.php), [`resources/views/app.blade.php`](resources/views/app.blade.php)).
2. Submitting posts to `/send-message`, handled by [`SendMessageController`](app/Http/Controllers/SendMessageController.php):
   - [`SendMessageRequest`](app/Http/Requests/SendMessageRequest.php) validates and cleans the text (ASCII only, converts smart quotes).
   - The message is saved as a `Receipt` row.
   - A [`PrintReceipt`](app/Jobs/PrintReceipt.php) job is dispatched **after the response is sent**, so the visitor never waits on the printer.
3. The job uses [`ReceiptPrinter`](app/Services/ReceiptPrinter.php) to format and send the receipt to the printer, then marks the row `has_printed`.

If the printer is offline the message is still saved (with `has_printed = false`) and the error is logged — nothing is lost. See [Recovering failed prints](#recovering-failed-prints).

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

## Recovering failed prints

If the printer was off or unreachable, reprint anything that didn't make it:

```sh
docker compose exec ping-app php artisan receipts:reprint          # only un-printed messages
docker compose exec ping-app php artisan receipts:reprint --all    # every stored message
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

# run the dev server + Vite together
composer run dev
```

Then visit the URL shown by `php artisan serve` (usually `http://localhost:8000`).
