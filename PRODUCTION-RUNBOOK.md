# SIMPEG Production Runbook

For Ubuntu 24.04 VPS provisioning with Docker Compose and host Caddy, follow `VPS-DEPLOYMENT-GUIDE.md`. Target traffic flow:

```text
Internet :80/:443 → host Caddy → 127.0.0.1:8080 → Docker Nginx :80 → PHP-FPM
```

PostgreSQL and Redis remain private to the Docker network. Commands below are compact release gates; the VPS guide contains first-install, systemd scheduler, backup/restore, smoke-test, and rollback steps.

## 1. Required environment

Set in production `.env` (never commit values):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://simpeg.example.go.id
APP_KEY=base64:<strong-generated-key>
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*
APP_ENV_FILE=.env.production
APP_HTTP_PORT=8080
SEEDER_SUPERADMIN_EMAIL=<official-admin-email>
SEEDER_DEFAULT_PASSWORD=<strong-unique-bootstrap-password>
POSTGRES_PASSWORD=<strong-unique-db-password>
REDIS_PASSWORD=<strong-unique-redis-password>
MAIL_MAILER=smtp
MAIL_ENCRYPTION=tls
```

Generate key once: `php artisan key:generate --show`. Store in secret manager. Losing `APP_KEY` invalidates encrypted data/sessions.

## 2. Pre-deploy gate

```bash
rm -f bootstrap/cache/config.php bootstrap/cache/routes*.php
composer install --no-dev --optimize-autoloader --no-interaction
composer audit
npm ci
npm audit
npm run build
php artisan test
./vendor/bin/pint --test
POSTGRES_PASSWORD='<validation-value>' docker compose -f compose.prod.yaml config >/dev/null
```

Expected:

- Tests all pass.
- NPM audit clean.
- Composer audit reports only documented Laravel residuals from `SECURITY-AUDIT.md`. Any new advisory blocks deploy.

## 3. Deploy

```bash
docker compose -f compose.prod.yaml build --pull
POSTGRES_PASSWORD='<real-secret>' docker compose -f compose.prod.yaml up -d
```

PHP-FPM entrypoint automatically:

1. initializes storage permissions,
2. runs `php artisan migrate --force`,
3. runs `php artisan documents:privatize`,
4. caches config/routes.

**Migration warning:** automatic migrations can change shared production schema. Take backup first. Use one deploy replica during migration.

## 4. Required edge/TLS setup

- TLS terminates at trusted reverse proxy/load balancer.
- Redirect HTTP→HTTPS at edge.
- Set `TRUSTED_PROXIES` narrowly where possible.
- Do not expose PostgreSQL or Redis ports publicly.
- Nginx app container only exposes HTTP to edge/private network.
- Confirm `/storage/dokumen/*` returns 404.

## 5. Smoke tests

1. Open `/login` over HTTPS; no debug trace.
2. Login redirects to `/`.
3. Unverified account redirects from `/cuti` to email verification.
4. Employee sees own Cuti/Izin only.
5. Employee cannot PUT/DELETE another employee's pending Cuti (403).
6. Employee cannot trigger `cuti/update-all-balances` (403).
7. Owner downloads own Cuti/Izin document; unrelated user gets 403; logged-out user redirects to login.
8. Admin cannot edit/delete super-admin.
9. Upload PDF/JPG/PNG succeeds; `.exe`/spoofed upload fails.
10. Headers: HSTS, CSP, X-Frame-Options, nosniff present.

## 6. Backup / restore gate

Before first production release:

- Configure `spatie/laravel-backup` destination outside app host.
- Back up PostgreSQL plus persistent storage volume.
- Restore both into isolated environment.
- Verify login, employee records, Cuti/Izin, and private documents.
- Record restore time and last successful restore timestamp.

No restore test = no production-ready claim.

## 7. Monitoring

- Configure Sentry DSN via secret environment variable.
- Alert on HTTP 500 rate, failed jobs, DB health, disk usage, backup failure.
- Log access to private documents at reverse proxy/app if required by agency policy.
- Never log passwords, tokens, document contents, or full sensitive requests.

## 8. Rollback

1. Stop new release.
2. Restore prior image tag.
3. If migration is backward-compatible, restart old image.
4. If migration is not backward-compatible, restore pre-deploy DB + storage backup.
5. Re-run smoke tests.

Never use `git reset --hard` or delete persistent volumes as rollback.

## 9. Post-deploy

```bash
docker compose -f compose.prod.yaml ps
docker compose -f compose.prod.yaml logs --tail=200 php-fpm
docker compose -f compose.prod.yaml exec php-fpm php artisan about --only=environment,cache,drivers
docker compose -f compose.prod.yaml exec php-fpm php artisan schedule:list
```

Rotate bootstrap seeder password after creating/verifying admin accounts. Do not run seeders routinely in production.

## 10. Required follow-up

Upgrade Laravel to **12.61.1+** to remove residual framework advisories and then remove temporary `SafeEmail` ASCII restriction if internationalized addresses are required.
