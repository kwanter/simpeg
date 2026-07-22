# SIMPEG VPS Deployment Guide

This guide deploys SIMPEG on a fresh Ubuntu 24.04 VPS using Docker Compose and PostgreSQL. Caddy runs on the host, owns public ports `80` and `443`, obtains TLS certificates automatically, and proxies requests to the application Nginx container on `127.0.0.1:8080`.

## Target architecture

```text
Internet
  │ TCP 80/443
  ▼
Caddy on Ubuntu host (automatic HTTPS)
  │ HTTP 127.0.0.1:8080
  ▼
Docker Nginx (`web`, container port 80)
  │ FastCGI, private Docker network
  ▼
PHP-FPM (`php-fpm`)
  ├── PostgreSQL 16 (`postgres`, private only)
  └── Redis (`redis`, private only)
```

Only SSH, HTTP, and HTTPS are public. PostgreSQL, Redis, PHP-FPM, and application port `8080` are not reachable from another host.

> **Deployment status:** code checks pass, but deployment remains conditional on accepting the Laravel 10 residual risks documented in `SECURITY-AUDIT.md` and completing the backup/restore and HTTPS smoke tests below. Schedule an upgrade to Laravel `12.61.1+`.

## Values used in this guide

Replace these placeholders before running commands:

| Placeholder | Example |
| --- | --- |
| `<VPS_IP>` | `203.0.113.10` |
| `<DOMAIN>` | `simpeg.example.go.id` |
| `<REPOSITORY_URL>` | `git@github.com:organization/simpeg.git` |
| `<RELEASE_TAG>` | `v1.0.0` |
| `<ADMIN_EMAIL>` | `admin@example.go.id` |

Commands assume the repository lives at `/opt/simpeg` and the Linux deployment user is `deploy`.

---

## 1. Provision DNS and VPS

### VPS minimum

Recommended minimum for a small internal deployment:

- Ubuntu Server 24.04 LTS, 64-bit
- 2 vCPU
- 4 GB RAM
- 40 GB SSD
- Static public IPv4 address

Increase storage based on employee photos, Cuti/Izin documents, logs, and backup retention.

### DNS

Create an `A` record:

```text
<DOMAIN>  A  <VPS_IP>
```

Create an `AAAA` record only when the VPS has working public IPv6. A stale or incorrect `AAAA` record can prevent Caddy from obtaining a certificate.

Verify DNS from your workstation:

```bash
dig +short <DOMAIN> A
dig +short <DOMAIN> AAAA
```

Wait until the expected VPS address appears before configuring Caddy.

---

## 2. Create and secure the deployment user

Connect as the provider-created root or sudo user:

```bash
ssh root@<VPS_IP>
```

Create a non-root user:

```bash
adduser deploy
usermod -aG sudo deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
```

From your workstation, copy your SSH key:

```bash
ssh-copy-id deploy@<VPS_IP>
```

Open a **second terminal** and verify key-based access before changing SSH settings:

```bash
ssh deploy@<VPS_IP>
```

After key login works, create a hardening override on the VPS:

```bash
sudo tee /etc/ssh/sshd_config.d/99-simpeg-hardening.conf >/dev/null <<'EOF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
EOF

sudo sshd -t
sudo systemctl reload ssh
```

Do not close the working SSH session until a second key-based login succeeds.

---

## 3. Update Ubuntu and configure the firewall

Set the server timezone and install base packages:

```bash
sudo timedatectl set-timezone Asia/Jakarta
sudo apt update
sudo apt full-upgrade -y
sudo apt install -y \
  ca-certificates curl git gnupg openssl ufw unattended-upgrades \
  debian-keyring debian-archive-keyring apt-transport-https
sudo dpkg-reconfigure -plow unattended-upgrades
```

Configure UFW:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw limit OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

Docker-published ports can bypass normal UFW forwarding rules. This project avoids that problem by binding application port `8080` to `127.0.0.1` only and publishing no PostgreSQL or Redis ports.

Verify time synchronization:

```bash
timedatectl status
```

---

## 4. Install Docker Engine and Compose v2

Remove conflicting packages if present:

```bash
sudo apt remove -y docker.io docker-compose docker-compose-v2 docker-doc podman-docker containerd runc || true
```

Add Docker's official Ubuntu repository:

```bash
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

sudo tee /etc/apt/sources.list.d/docker.sources >/dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update
sudo apt install -y \
  docker-ce docker-ce-cli containerd.io \
  docker-buildx-plugin docker-compose-plugin
```

Enable Docker and grant the deployment user access:

```bash
sudo systemctl enable --now docker
sudo usermod -aG docker deploy
```

Membership in the `docker` group is effectively root-level access. Grant it only to trusted operators.

Log out and reconnect so group membership takes effect:

```bash
exit
ssh deploy@<VPS_IP>
```

Verify Docker:

```bash
docker version
docker compose version
docker run --rm hello-world
```

---

## 5. Install Caddy on the host

Install Caddy from its official repository:

```bash
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
  | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg

curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
  | sudo tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null

sudo chmod o+r /usr/share/keyrings/caddy-stable-archive-keyring.gpg
sudo chmod o+r /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install -y caddy
sudo systemctl enable caddy
```

Do not configure the domain yet. Start the application upstream first.

---

## 6. Clone a release into `/opt/simpeg`

Create the application directory:

```bash
sudo install -d -m 0750 -o deploy -g deploy /opt/simpeg
cd /opt/simpeg
```

Clone the repository and check out a tested release tag or commit:

```bash
git clone <REPOSITORY_URL> .
git fetch --tags --prune
git checkout <RELEASE_TAG>
git status --short
```

`git status --short` must be empty. Do not deploy an uncommitted working tree.

For a private repository, use a read-only deploy key or another non-personal machine credential. Never store a Git access token in the repository.

---

## 7. Create production secrets and environment

Generate secrets on the VPS:

```bash
APP_KEY_VALUE="base64:$(openssl rand -base64 32 | tr -d '\n')"
POSTGRES_PASSWORD_VALUE="$(openssl rand -hex 32)"
REDIS_PASSWORD_VALUE="$(openssl rand -hex 32)"
SEEDER_PASSWORD_VALUE="$(openssl rand -hex 24)"

printf 'APP_KEY=%s\n' "$APP_KEY_VALUE"
printf 'POSTGRES_PASSWORD=%s\n' "$POSTGRES_PASSWORD_VALUE"
printf 'REDIS_PASSWORD=%s\n' "$REDIS_PASSWORD_VALUE"
printf 'SEEDER_DEFAULT_PASSWORD=%s\n' "$SEEDER_PASSWORD_VALUE"
```

Save the values in a password manager. The bootstrap password appears only during initial provisioning and must be rotated afterward.

Create `/opt/simpeg/.env.production`:

```bash
umask 077
nano /opt/simpeg/.env.production
```

Use this template and replace every `<...>` value:

```dotenv
COMPOSE_PROJECT_NAME=simpeg
APP_ENV_FILE=.env.production
APP_HTTP_PORT=8080

APP_NAME="Sistem Informasi Pegawai"
APP_ENV=production
APP_KEY="<GENERATED_APP_KEY>"
APP_DEBUG=false
APP_URL=https://<DOMAIN>
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

POSTGRES_DATABASE=simpeg
POSTGRES_USERNAME=simpeg
POSTGRES_PASSWORD="<GENERATED_POSTGRES_PASSWORD>"

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=simpeg
DB_USERNAME=simpeg
DB_PASSWORD="<GENERATED_POSTGRES_PASSWORD>"

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD="<GENERATED_REDIS_PASSWORD>"
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*

QUEUE_CONNECTION=sync
BROADCAST_DRIVER=log
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=<SMTP_HOST>
MAIL_PORT=587
MAIL_USERNAME=<SMTP_USERNAME>
MAIL_PASSWORD="<SMTP_PASSWORD>"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<ADMIN_EMAIL>
MAIL_FROM_NAME="${APP_NAME}"

SEEDER_SUPERADMIN_EMAIL=<ADMIN_EMAIL>
SEEDER_DEFAULT_PASSWORD="<GENERATED_BOOTSTRAP_PASSWORD>"
```

Why `TRUSTED_PROXIES=*` is acceptable here: PHP-FPM is reachable only through the private Docker network, application Nginx is loopback-only, and Caddy normalizes proxy headers. Do not use this value if PHP-FPM or application Nginx becomes publicly reachable. For tighter control, set `TRUSTED_PROXIES` to the Docker bridge subnet (e.g., `172.18.0.0/16`) instead of `*`. Run `docker network inspect simpeg_laravel-production` after first start to find the actual subnet.

Protect the file:

```bash
chmod 600 /opt/simpeg/.env.production
```

The repository ignores `.env.*`, and `.dockerignore` excludes environment files from image build contexts.

### SMTP requirement

SIMPEG enforces email verification. Configure a working SMTP provider before onboarding users or changing account emails. Send a verification test after deployment.

---

## 8. Validate and build the stack

Define a shell helper for the current SSH session:

```bash
cd /opt/simpeg

dc() {
  docker compose \
    --env-file /opt/simpeg/.env.production \
    -f /opt/simpeg/compose.prod.yaml \
    "$@"
}
```

Validate rendered Compose configuration:

```bash
dc config >/dev/null
echo "Compose configuration is valid"
```

Confirm important exposure rules:

```bash
dc config | grep -E '127\.0\.0\.1|5432|6379'
```

Expected:

- Web publishes `127.0.0.1:8080:80`.
- PostgreSQL and Redis have no host `ports` mappings.

Before deploying this commit, confirm CI or a trusted build workstation has passed:

```bash
php artisan test
./vendor/bin/pint --test
npm audit
npm run build
```

Current security baseline: `244` PHPUnit tests and `477` assertions passed before this deployment-guide work. Re-run CI for the exact release commit.

Build images:

```bash
dc build --pull
```

The Nginx image uses Node 22 and `npm ci`; the PHP-FPM image installs production Composer dependencies only. Frontend assets are immutable image contents, not an empty shared volume that could hide the build output.

---

## 9. Start fresh PostgreSQL, Redis, PHP-FPM, and Nginx

Start the stack:

```bash
dc up -d
dc ps
```

The PHP-FPM entrypoint automatically:

1. initializes the persistent storage volume,
2. runs `php artisan migrate --force`,
3. moves legacy HR documents to private storage,
4. caches Laravel configuration and routes,
5. starts PHP-FPM.

Watch startup:

```bash
dc logs -f --tail=200 php-fpm
```

Exit log streaming with `Ctrl+C`. Then verify every service is running or healthy:

```bash
dc ps
curl -I http://127.0.0.1:8080/login
```

If PHP-FPM exits, inspect logs before retrying:

```bash
dc logs --tail=300 php-fpm postgres redis
```

Do not use `docker compose down -v`; `-v` deletes production data volumes.

---

## 10. Initialize roles and the first super-admin

Do **not** run `DatabaseSeeder` in production. It creates 50 fake employee records.

Run only the production-safe seeders, once, in this order:

```bash
dc exec -T php-fpm php artisan db:seed \
  --class=Database\\Seeders\\UserRolePermissionSeeder --force

dc exec -T php-fpm php artisan db:seed \
  --class=Database\\Seeders\\HariLiburPermissionSeeder --force

dc exec -T php-fpm php artisan db:seed \
  --class=Database\\Seeders\\HariLiburRolePermissionSeeder --force
```

In production, `UserRolePermissionSeeder` creates only the account named by `SEEDER_SUPERADMIN_EMAIL`. Use the official email configured in `.env.production` and the generated `SEEDER_DEFAULT_PASSWORD` for the first login. Immediately:

1. change the password,
2. verify login again,
3. create real users through the UI,
4. remove the bootstrap password from `.env.production`.

Edit the environment file:

```bash
nano /opt/simpeg/.env.production
```

Set:

```dotenv
SEEDER_DEFAULT_PASSWORD=
```

Recreate PHP-FPM so the bootstrap secret is removed from its environment:

```bash
dc up -d --force-recreate php-fpm
dc ps
```

Never run the bootstrap seeder routinely in production.

---

## 11. Configure Caddy automatic HTTPS

Create `/etc/caddy/Caddyfile`:

```bash
sudo tee /etc/caddy/Caddyfile >/dev/null <<'EOF'
<DOMAIN> {
    encode zstd gzip
    reverse_proxy 127.0.0.1:8080
}
EOF
```

Replace `<DOMAIN>` with the real hostname, then format and validate:

```bash
sudo caddy fmt --overwrite /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
sudo systemctl status caddy --no-pager
```

Caddy automatically requests and renews the certificate when:

- DNS resolves to this VPS,
- public ports `80` and `443` reach Caddy,
- no other process owns those ports.

Inspect certificate or proxy errors:

```bash
sudo journalctl -u caddy -n 200 --no-pager
```

Verify public HTTPS:

```bash
curl -I http://<DOMAIN>/login
curl -I https://<DOMAIN>/login
```

Expected:

- HTTP redirects to HTTPS.
- HTTPS returns an application response.
- Generated links use `https://<DOMAIN>`.

Verify application port isolation from another machine:

```bash
nc -vz <VPS_IP> 8080
```

The connection must fail. On the VPS, this must succeed:

```bash
curl -I http://127.0.0.1:8080/login
```

---

## 12. Configure Laravel scheduler with systemd

SIMPEG schedules the annual Cuti balance update through Laravel's scheduler. Run `schedule:run` every minute from a systemd timer.

Create `/etc/systemd/system/simpeg-scheduler.service`:

```bash
sudo tee /etc/systemd/system/simpeg-scheduler.service >/dev/null <<'EOF'
[Unit]
Description=SIMPEG Laravel scheduler
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
User=deploy
Group=deploy
WorkingDirectory=/opt/simpeg
ExecStart=/usr/bin/docker compose --env-file /opt/simpeg/.env.production -f /opt/simpeg/compose.prod.yaml exec -T php-fpm php artisan schedule:run
TimeoutStartSec=120
EOF
```

Create `/etc/systemd/system/simpeg-scheduler.timer`:

```bash
sudo tee /etc/systemd/system/simpeg-scheduler.timer >/dev/null <<'EOF'
[Unit]
Description=Run SIMPEG scheduler every minute

[Timer]
OnCalendar=*-*-* *:*:00
AccuracySec=1s
Persistent=true
Unit=simpeg-scheduler.service

[Install]
WantedBy=timers.target
EOF
```

Enable and test:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now simpeg-scheduler.timer
sudo systemctl start simpeg-scheduler.service
sudo systemctl status simpeg-scheduler.service --no-pager
sudo systemctl list-timers simpeg-scheduler.timer --no-pager
journalctl -u simpeg-scheduler.service -n 50 --no-pager
```

Confirm Laravel sees the scheduled command:

```bash
dc exec -T php-fpm php artisan schedule:list
```

### Queue workers

The deployment template uses `QUEUE_CONNECTION=sync`; no worker is required. If the application later uses Redis queues, add a dedicated long-running worker service with restart and retry limits. Do not run a queue worker from an interactive SSH session.

---

## 13. Configure daily backups

A production backup must include:

1. PostgreSQL database,
2. Laravel persistent storage, including private Cuti/Izin documents,
3. production environment file or separately escrowed secrets.

Create a root-owned backup script:

```bash
sudo install -d -m 0700 /var/backups/simpeg
sudo tee /usr/local/sbin/simpeg-backup >/dev/null <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

APP=/opt/simpeg
BACKUP_ROOT=/var/backups/simpeg
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
DEST="$BACKUP_ROOT/$STAMP"
DC=(docker compose --env-file "$APP/.env.production" -f "$APP/compose.prod.yaml")

mkdir -p "$DEST"

"${DC[@]}" exec -T php-fpm php artisan down --retry=60
trap '{
  "${DC[@]}" exec -T php-fpm php artisan up || {
    echo "CRITICAL: Failed to bring app out of maintenance mode after backup" >&2
    # TODO: Add notification (email/Slack/webhook) for your ops team here
  }
}' EXIT

"${DC[@]}" exec -T postgres sh -c \
  'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' \
  > "$DEST/database.dump"

"${DC[@]}" exec -T php-fpm \
  tar -C /var/www/storage -czf - . \
  > "$DEST/storage.tar.gz"

cp "$APP/.env.production" "$DEST/environment.backup"
sha256sum "$DEST"/* > "$DEST/SHA256SUMS"

# Retain 14 days. This intentionally deletes older backup directories.
find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +14 -exec rm -rf -- {} +
EOF

sudo chmod 700 /usr/local/sbin/simpeg-backup
```

Run the first backup manually:

```bash
sudo /usr/local/sbin/simpeg-backup
sudo find /var/backups/simpeg -maxdepth 2 -type f -ls
```

Create a daily timer:

```bash
sudo tee /etc/systemd/system/simpeg-backup.service >/dev/null <<'EOF'
[Unit]
Description=Back up SIMPEG PostgreSQL and storage
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/simpeg-backup
TimeoutStartSec=1800
EOF

sudo tee /etc/systemd/system/simpeg-backup.timer >/dev/null <<'EOF'
[Unit]
Description=Daily SIMPEG backup

[Timer]
OnCalendar=*-*-* 02:30:00
RandomizedDelaySec=300
Persistent=true

[Install]
WantedBy=timers.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now simpeg-backup.timer
sudo systemctl list-timers simpeg-backup.timer --no-pager
```

Copy backups to encrypted off-site storage. A backup stored only on the same VPS does not protect against disk loss or account compromise.

### Restore drill

Test restoration on an isolated VPS or separate Compose project before declaring production ready. A restore overwrites database and storage; never test it against live production.

For a controlled recovery during approved downtime:

```bash
cd /opt/simpeg

dc stop web php-fpm redis

dc exec -T postgres sh -c \
  'dropdb --if-exists -U "$POSTGRES_USER" "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'

dc exec -T postgres sh -c \
  'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner' \
  < /path/to/database.dump

dc run --rm --no-deps -T --entrypoint sh php-fpm -c \
  'find /var/www/storage -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + && tar -xzf - -C /var/www/storage' \
  < /path/to/storage.tar.gz

dc up -d
```

After restoration, run all smoke tests in the next section and record the restoration date and duration.

---

## 14. Production smoke and security tests

### Infrastructure

```bash
dc ps
sudo systemctl is-active caddy docker simpeg-scheduler.timer simpeg-backup.timer
sudo ss -lntp
sudo ufw status verbose
```

Expected listeners:

- public `:22`, `:80`, `:443`,
- loopback `127.0.0.1:8080`,
- no public `5432`, `6379`, or `9000`.

### Laravel state

```bash
dc exec -T php-fpm php artisan about --only=environment,cache,drivers
dc exec -T php-fpm php artisan migrate:status
dc exec -T php-fpm php artisan schedule:list
dc exec -T php-fpm php artisan route:list --name=cuti.dokumen
dc exec -T php-fpm php artisan route:list --name=izin.dokumen
```

Confirm environment is `production`, debug is disabled, migrations are complete, and private document routes exist.

### HTTPS and headers

```bash
curl -sS -D - -o /dev/null https://<DOMAIN>/login
```

Confirm these headers are present:

- `Strict-Transport-Security`
- `Content-Security-Policy`
- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`

Confirm raw HR document paths are blocked:

```bash
curl -I https://<DOMAIN>/storage/dokumen/test.pdf
```

Expected: `404`.

### Application authorization

Test with separate accounts:

1. Employee sees only own Cuti/Izin plus explicitly assigned approval items.
2. Employee cannot edit or delete another employee's pending Cuti (`403`).
3. Employee cannot run `/cuti/update-all-balances` (`403`).
4. Cuti/Izin owner can download own document.
5. Unrelated user receives `403` for another user's document.
6. Logged-out user is redirected to login.
7. Admin cannot edit, demote, or delete super-admin.
8. PDF/JPG/PNG upload succeeds; spoofed or unsupported file fails.
9. Updating `Izin Tidak Masuk Kerja` beyond two workdays fails validation.
10. Changing an account email triggers the verification flow and sends mail successfully.

### Logs

```bash
dc logs --tail=200 web php-fpm postgres redis
sudo journalctl -u caddy -n 100 --no-pager
sudo journalctl -u simpeg-scheduler.service -n 100 --no-pager
```

No stack traces, secrets, or repeating 500 errors should appear.

---

## 15. Deploy an application update

Use a release tag, not an arbitrary moving branch.

```bash
cd /opt/simpeg

dc exec -T php-fpm php artisan down --retry=60
sudo /usr/local/sbin/simpeg-backup

git fetch --tags --prune
git checkout <NEW_RELEASE_TAG>
git status --short

dc config >/dev/null
dc build --pull
dc up -d --remove-orphans
dc exec -T php-fpm php artisan up
```

The PHP-FPM entrypoint applies pending migrations and caches routes/config. Review migrations before deploying; automatic migration does not make a destructive schema change safe.

Run smoke tests immediately. Monitor logs for at least one normal user workflow.

---

## 16. Rollback

### Application-only rollback

Use this when new migrations remain backward-compatible:

```bash
cd /opt/simpeg
dc exec -T php-fpm php artisan down --retry=60

git checkout <PREVIOUS_RELEASE_TAG>
dc build
dc up -d --remove-orphans
dc exec -T php-fpm php artisan up
```

Then run smoke tests.

### Database/storage rollback

If a release applied an incompatible migration or corrupted data:

1. keep maintenance mode active,
2. stop application writers,
3. restore the pre-deploy database and storage backup,
4. deploy the matching previous release,
5. run smoke tests,
6. document the incident.

Never use `git reset --hard`, delete Docker volumes, or run `docker compose down -v` as a rollback method.

---

## 17. Operations checklist

### Before go-live

- [ ] DNS resolves to the VPS.
- [ ] SSH key-only access verified in a second session.
- [ ] UFW allows only SSH, HTTP, and HTTPS.
- [ ] Application port binds to `127.0.0.1:8080`.
- [ ] PostgreSQL and Redis expose no host ports.
- [ ] `.env.production` is mode `600` and absent from images/Git.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL configured.
- [ ] Caddy certificate is valid and HTTP redirects to HTTPS.
- [ ] Fresh migrations and production-safe seeders complete.
- [ ] Bootstrap super-admin email/password rotated.
- [ ] SMTP verification mail succeeds.
- [ ] Scheduler timer runs successfully.
- [ ] Backup timer runs successfully.
- [ ] Database and storage restore drill succeeds.
- [ ] Security smoke tests pass.
- [ ] Laravel 10 residual risk is accepted in writing.
- [ ] Laravel `12.61.1+` upgrade is scheduled.

### Routine maintenance

- Apply Ubuntu security updates.
- Update Docker and Caddy through APT after staging validation.
- Review `composer audit` and `npm audit` on every release.
- Monitor disk usage and backup age.
- Test restore at least quarterly.
- Rotate admin, SMTP, database, and repository credentials according to agency policy.
- Review privileged users and roles regularly.

---

## Troubleshooting

### Caddy cannot obtain a certificate

```bash
dig +short <DOMAIN> A
sudo ss -lntp | grep -E ':80|:443'
sudo journalctl -u caddy -n 200 --no-pager
```

Check DNS, firewall, provider security groups, and stale `AAAA` records.

### `502 Bad Gateway`

```bash
curl -I http://127.0.0.1:8080/login
dc ps
dc logs --tail=200 web php-fpm
```

A failed PHP-FPM health check or migration usually causes this error.

### PHP-FPM exits during startup

```bash
dc logs --tail=300 php-fpm postgres
dc exec -T postgres pg_isready -U simpeg -d simpeg
```

Check PostgreSQL credentials, migration errors, and storage permissions.

### Login works but verification mail fails

```bash
dc logs --tail=200 php-fpm
```

Check SMTP host, port, credentials, encryption mode, sender address, outbound provider firewall, and DNS requirements such as SPF/DKIM.

### Upload returns `413 Request Entity Too Large`

The production Nginx limit is `3m`; application validation allows up to `2 MB`. Rebuild the Nginx image if configuration changed:

```bash
dc build web
dc up -d web
```

### Application URLs use HTTP

Confirm:

```dotenv
APP_ENV=production
APP_URL=https://<DOMAIN>
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*
```

Then recreate PHP-FPM so cached configuration refreshes:

```bash
dc up -d --force-recreate php-fpm
```

---

## References

- Project security findings: `SECURITY-AUDIT.md`
- Production gate and rollback summary: `PRODUCTION-RUNBOOK.md`
- Docker Ubuntu install: <https://docs.docker.com/engine/install/ubuntu/>
- Caddy Ubuntu install: <https://caddyserver.com/docs/install#debian-ubuntu-raspbian>
- Caddy reverse proxy: <https://caddyserver.com/docs/quick-starts/reverse-proxy>
