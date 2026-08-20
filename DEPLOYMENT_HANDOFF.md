# Kattie.uk — Deployment Handoff & Status

_Ultima actualizare: 2026-08-19_

Rezumatul stării de deploy pe producție (Laravel Forge). **Fără secrete aici** — cheile
(API keys, parole) sunt doar în Forge → Environment.

---

## Infrastructură / fapte cheie

| Ce | Valoare |
|---|---|
| Domeniu | **kattie.uk** (cumpărat pe GoDaddy) |
| Server | Laravel Forge pe DigitalOcean, IP **159.65.208.209**, Ubuntu 24.04 |
| User site (website isolation) | **cattie-eibkj2so** |
| **Calea reală a aplicației** | `/home/cattie-eibkj2so/kattie.uk/` → `current` → `releases/NNN` |
| ⚠️ Director vechi (stale) | `/home/cattie-eibkj2so/cattie-eibkj2so.on-forge.com/` — rest gol după ce Forge a redenumit site-ul la setarea domeniului. NU e aplicația. |
| Bază de date | MySQL, db `kattie`, user `kattie` |
| Repo | GitHub `danieliancu/cattie`, branch `main` |
| Deploy | Zero-downtime. Deploy script rulează `migrate --force` (NU `migrate:fresh` — a fost revertit după setup). Build assets prin `npm run build`. |
| SSH | `ssh forge@159.65.208.209` (cheie SSH). Pentru artisan pe site izolat: `sudo -u cattie-eibkj2so ...` (cere parola de sudo din Forge → Server → Settings). |
| Rulare artisan corectă | `sudo -u cattie-eibkj2so bash -c 'cd /home/cattie-eibkj2so/kattie.uk/current && php artisan ...'` |

---

## ✅ Ce e gata și funcțional

- **Site live** pe `https://kattie.uk`, SSL Let's Encrypt, redirect http→https.
- **MySQL** + migrații + seed (admin, catalog, categorii, stiluri artwork, metode livrare).
- **Queue worker** (background process Forge): `queue:work`, connection `database`, `--timeout=240 --tries=2 --sleep=3`. Env `DB_QUEUE_RETRY_AFTER=300`.
- **Scheduler**: cron `php artisan schedule:run` la fiecare minut (task real: `artwork:purge-expired` zilnic 03:30).
- **Checkout + salvare adresă** — reparate (erau incompatibilități SQLite→MySQL).
- **Stripe (TEST mode)** — funcțional. Webhook: `https://kattie.uk/api/webhooks/stripe`, destinație „Your account", 4 evenimente `checkout.session.*` (`completed`, `async_payment_succeeded`, `async_payment_failed`, `expired`). Config în `config/payments.php`.
- **Email (Resend, API)** — funcțional. `MAIL_MAILER=resend` + `RESEND_KEY`. Pachet `resend/resend-php` adăugat în `composer.json`. Domeniu `kattie.uk` verificat în Resend (DKIM `resend._domainkey` + SPF pe `send`: MX + TXT, în GoDaddy).
  - ⚠️ **DigitalOcean blochează porturile SMTP (25/465/587)** → SMTP-ul dă timeout. De aceea folosim **API-ul Resend** (port 443), nu SMTP.
- **AI real (OpenAI)** — funcțional. `AI_IMAGE_PROVIDER=openai` + `OPENAI_API_KEY`.
  - Python venv cu `rembg==2.0.67`, `onnxruntime==1.20.1`, `Pillow==11.1.0`.
  - Model rembg `isnet-general-use` descărcat în `/home/cattie-eibkj2so/.u2net/`.
  - Scripturi: `scripts/remove_artwork_background.py`, `scripts/upscale_artwork.py`.

## Fix-uri de cod commit-uite (toate pe `main`)

- Nume index MySQL prea lung (`create_cattie_admin_tables`) → nume scurt explicit.
- Ordine drop index/FK pe MySQL (`allow_design_template_assignment_history`).
- Coloane adrese criptate (`orders.shipping_address`, `customer_profiles.default_shipping_address`): `json` → `text` (migrația `2026_08_19_120000_...`).
- Seeder: produsele primesc `status=published` (altfel invizibile la un `migrate:fresh` viitor).
- Adăugat `resend/resend-php` ca dependență de producție.

---

## ✅ REZOLVAT (2026-08-20) — conversia AI

**Simptom:** conversia eșua la 55% („Removing the background" — vezi `ArtworkProcessingStage`), afișând „Try again / Upload a new photo".

**Cauză reală:** env-ul din Forge fusese setat pe `AI_BACKGROUND_REMOVAL_PYTHON=.../kattie.uk/.venv-ai/bin/python`, dar venv-ul **nu fusese mutat** acolo — încă era în directorul stale `/home/cattie-eibkj2so/cattie-eibkj2so.on-forge.com/.venv-ai/`. Deci aplicația apela un Python inexistent (`No such file or directory`, exit 127). Eroarea era ascunsă de PHP (`LocalBackgroundRemovalRunner` o transformă în mesaj generic).

**Fix aplicat:** recreat venv-ul curat la calea din env:
```bash
sudo -u cattie-eibkj2so -H bash -c 'cd /home/cattie-eibkj2so/kattie.uk && python3 -m venv .venv-ai && .venv-ai/bin/pip install --upgrade pip && .venv-ai/bin/pip install rembg==2.0.67 onnxruntime==1.20.1 Pillow==11.1.0'
sudo -u cattie-eibkj2so bash -c 'cd /home/cattie-eibkj2so/kattie.uk/current && php artisan queue:restart'
```
Modelul `isnet-general-use.onnx` era deja în `/home/cattie-eibkj2so/.u2net/` (nu s-a redescărcat). Confirmat funcțional pe site.

**Notă:** directorul stale `.../cattie-eibkj2so.on-forge.com/.venv-ai/` mai există — se poate șterge, nu mai e folosit.

---

## 🔐 Autentificare clienți — cod pe email la înscriere + Google (2026-08-20)

Adăugat pe `main` (necesită deploy):

- **Verificare email la înscriere:** la `register`, contul se creează neverificat, se trimite un cod de 6 cifre (Notification `EmailVerificationCodeNotification`, prin Resend), iar clientul îl introduce pe `/register/verify`. Codul e hash-uit în tabelul `email_verification_codes`, expiră în 10 min, max 5 încercări, buton „resend" (throttle 3/min). Rutele `/account/*` sunt acum în spatele middleware-ului `verified`.
  - Migrațiile fac backfill `email_verified_at = now()` pentru clienții existenți (nu sunt blocați).
- **Google sign-in:** `laravel/socialite` + `GoogleAuthController` (`/auth/google/redirect` + `/auth/google/callback`). Buton „Continue with Google" pe login/register, afișat **doar dacă** `GOOGLE_CLIENT_ID` e setat. Conturile Google sunt marcate verificate automat.

**De configurat în Forge → Environment** (altfel butonul Google e ascuns, restul merge):
```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://kattie.uk/auth/google/callback
```
Apoi `php artisan config:cache`. În Google Cloud Console, la OAuth client, „Authorized redirect URI" = exact `https://kattie.uk/auth/google/callback`.

⚠️ La deploy: `composer install` (aduce socialite) + `migrate --force` (adaugă `users.google_id`, `users.avatar_url`, face `password` nullable, creează `email_verification_codes`).

---

## ⏳ De făcut (pending)

1. **Configurează Google OAuth** (vezi secțiunea de mai sus) — creează credențialele în Google Cloud Console și pune-le în Forge Environment.
2. **Stripe → Live** (când e gata contul Stripe pentru plăți reale): chei `sk_live_`/`pk_live_`, webhook nou în Live mode (același URL), `whsec_` live. Înlocuiește cele 4 valori + `config:cache`.
3. **Email — răspunsuri** (opțional): forwarding GoDaddy `hello@kattie.uk` → gmail, sau `Reply-To`.
4. **TreatPod (print)** — pe hold; se așteaptă detaliile API pentru trimiterea automată la print. Env: `TREATPOD_APP_ID`, `TREATPOD_SECRET_KEY`. Webhook deja rutat: `/api/webhooks/treatpod/orders/{event}`.

---

## Note utile

- Forge „Commands" (web) adesea nu afișează output (`No output available`) — pentru debug real folosește **SSH**.
- `.env` real e la `/home/cattie-eibkj2so/kattie.uk/.env` (symlink-uit în fiecare release).
- La orice schimbare de `.env`: `php artisan config:cache` (sau un deploy).
