# Laravel Sandbox Migration Plan

## Boundary

This directory is the isolated Laravel 13.26 sandbox. It uses the dedicated `arsip_layar_sandbox` database. It must not read, write, delete, or serve files from the live application root, the live `media/` directory, or the production `arsip_layar` database.

## Target architecture

| Current module | Laravel target | Security boundary |
| --- | --- | --- |
| Admin login / TOTP | Laravel auth starter kit + Fortify 2FA | Admin-only middleware, login throttle, encrypted TOTP secret |
| Videos / categories | `Video`, `Category` models and policies | Private media disk; no public `media/` folder |
| Access code | `AccessToken` model + signed session grant | Token hash at rest, status/revocation check, session regeneration |
| HLS transcoding | queued `TranscodeVideo` job | worker user and private storage only |
| HLS delivery | media controller + Nginx `X-Accel-Redirect` | authorization before every manifest/segment request |
| Midtrans | `MidtransService`, `PaymentOrder`, webhook controller | Server Key in `.env`; signature verification; idempotent database transaction |
| Telegram | queued notification job | Bot key in `.env`, never database/UI response |
| Analytics / audit | event listeners + `AnalyticsEvent` / `ActivityLog` | retention policy and no raw IP storage where not required |

## Sandbox implementation order

1. Install Laravel starter kit (Livewire is the recommended first migration UI) and disable public registration.
2. Add admin authorization middleware, Fortify two-factor confirmation, and account import command.
3. Implement read-only imports from a sanitized SQL export into the sandbox database; import no credentials, session data, or live payment secrets.
4. Implement private-media upload, FFmpeg queue job, HLS manifest authorization, and signed download policy.
5. Implement token issuance/revocation plus an integration test for rejected direct media access.
6. Move Midtrans into a dedicated service. Configure keys only through server environment variables; validate webhook signature and transaction status; issue a token exactly once.
7. Move Telegram notification into a queue and test with a sandbox bot.
8. Rebuild public pages and admin panel, then perform feature, authorization, payment, and load tests.

## Data mapping

| Legacy table | Laravel sandbox table | Migration rule |
| --- | --- | --- |
| `admins` | `users` | Set `is_admin=true`; retain hash only after compatibility verification; force password reset if needed. |
| `categories` | `categories` | Preserve IDs only during controlled import. |
| `videos` | `videos` | Copy metadata first; copy media to private storage in a later, checksummed step. |
| `access_tokens` | `access_tokens` | Reissue or hash legacy plaintext tokens; never log a plaintext token. |
| `payment_orders` | `payment_orders` | Import ledger only after reconciliation; never replay notifications. |
| `settings` | `settings` / `.env` | Public settings may migrate; all secrets move to `.env` or a secret manager. |

## Cutover gates

Do not replace the root application until all gates pass:

1. Full backup and restore rehearsal for MySQL and media.
2. Laravel migration tested from an anonymized production copy.
3. HLS and MP4 direct URLs return `403`; authorized stream and download tests pass.
4. Midtrans Sandbox callback, duplicate callback, invalid signature, pending, expire, and settlement tests pass.
5. Production has HTTPS, queue worker, scheduler, FFmpeg, Nginx private-media rules, and monitoring.
6. A maintenance window, rollback DNS/Nginx plan, and immutable backup are approved.

## Production cutover / rollback

1. Put legacy site into maintenance mode and stop uploads.
2. Backup database and media; record checksums.
3. Final incremental data import into Laravel.
4. Switch Nginx document root to Laravel `public/` only after smoke tests.
5. Keep the current application and its Nginx site definition intact for rollback.
6. If a gate fails, restore the previous Nginx root and keep the Laravel database for diagnosis; do not overwrite production data.

## Local sandbox commands

```bash
cd /var/www/arsip-layar/sandbox
php artisan migrate:status
php artisan serve --host=127.0.0.1 --port=8081
curl http://127.0.0.1:8081/migration-readiness
```
