# SIMPEG - Sistem Informasi Kepegawaian

Aplikasi manajemen kepegawaian (HRIS) berbasis Laravel untuk mengelola data pegawai, pengajuan cuti, izin, dan hari libur.

## Tech Stack

- **Backend:** Laravel 10, PHP 8.2+
- **Frontend:** Blade, Bootstrap 5 (CDN), Alpine.js, jQuery (CDN), Vite
- **Auth/RBAC:** Spatie Laravel Permission (custom Role/Permission models)
- **Testing:** PHPUnit (unit + feature), Playwright (E2E)
- **Containerization:** Docker (multi-mode: Sail / Apache dev / Nginx production)

## Fitur Utama

- Manajemen data pegawai (CRUD, import/export)
- Pengajuan & verifikasi cuti (2-level: atasan → pimpinan)
- Pengajuan & verifikasi izin (2-level: atasan → pimpinan)
- Kalender hari libur nasional
- Cetak surat izin/cuti ke PDF
- Dashboard statistik kepegawaian
- RBAC: super-admin, admin, pimpinan, atasan-pimpinan, verifikator, pegawai

## Setup Lokal (Tanpa Docker)

```bash
# 1. Install dependencies
composer install
npm ci

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi database di .env (default: MySQL)
#    Untuk testing otomatis menggunakan SQLite :memory:

# 4. Migrasi & seeder
php artisan migrate --seed

# 5. Build frontend
npm run build

# 6. Jalankan server
php artisan serve
```

---

## Deploy dengan Docker

Proyek ini menyediakan 3 mode Docker:

| Mode | File Compose | Web Server | PHP | Use Case |
|------|-------------|------------|-----|----------|
| **Sail** | `docker-compose.yml` | Built-in (Sail) | 8.4 | Development cepat, satu container |
| **Apache Dev** | `docker-compose.dev.yml` | Apache 2 | 8.2 | Development dengan Redis, hot-reload |
| **Production** | manual build | Nginx + PHP-FPM | 8.4 | Deploy ke server produksi |

### Struktur Docker

```
docker/
├── apache/                      # Mode 2: Dev Apache
│   ├── Dockerfile               # PHP 8.2 + Apache + ekstensi Laravel
│   ├── 000-default.conf         # VirtualHost → /var/www/html/public
│   ├── php.ini                  # Upload 64M, memory 256M, timezone Asia/Jakarta
│   └── entrypoint.sh            # Fix permission storage/ & bootstrap/cache/
├── common/
│   └── php-fpm/
│       └── Dockerfile           # Multi-stage: builder → production → development
├── development/
│   ├── workspace/
│   │   └── Dockerfile           # PHP 8.4 CLI + Node.js (via NVM) + Xdebug
│   ├── php-fpm/
│   │   └── entrypoint.sh        # Fix permission, clear config cache
│   └── nginx/
│       └── nginx.conf           # Nginx config untuk development
└── production/
    ├── nginx/
    │   ├── Dockerfile           # Multi-stage: build assets → Nginx Alpine
    │   └── nginx.conf           # Nginx reverse proxy → php-fpm:9000
    └── php-fpm/
        └── entrypoint.sh        # Migrate, config:cache, route:cache
```

---

### Mode 1: Laravel Sail (Development)

Menggunakan [Laravel Sail](https://laravel.com/docs/sail) — cara paling cepat untuk mulai development.

**Prasyarat:** Docker Desktop sudah berjalan.

```bash
# 1. Konfigurasi environment
cp .env.example .env

# Sesuaikan .env:
# DB_CONNECTION=mysql
# DB_HOST=mysql
# DB_PORT=3306
# DB_DATABASE=simpeg
# DB_USERNAME=sail
# DB_PASSWORD=password

# 2. Build & jalankan container
docker compose up -d

# 3. Install dependencies di dalam container
docker compose exec laravel.test composer install
docker compose exec laravel.test npm ci
docker compose exec laravel.test npm run build

# 4. Generate app key & migrasi
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed

# 5. Aplikasi berjalan di http://localhost:80
#    Vite HMR: http://localhost:5173
```

**Perintah Sail yang sering dipakai:**

```bash
# Masuk ke shell container
docker compose exec laravel.test bash

# Jalankan artisan
docker compose exec laravel.test php artisan <command>

# Jalankan test
docker compose exec laravel.test php artisan test

# Lihat log
docker compose exec laravel.test tail -f storage/logs/laravel.log

# Stop
docker compose down

# Stop & hapus data MySQL
docker compose down -v
```

---

### Mode 2: Apache Development

Stack lengkap: PHP 8.2 + Apache + MySQL 8 + Redis. Cocok untuk development yang butuh Redis (queue, cache).

```bash
# 1. Konfigurasi environment
cp .env.example .env

# Sesuaikan .env:
# DB_CONNECTION=mysql
# DB_HOST=mysql
# DB_PORT=3306
# DB_DATABASE=simpeg
# DB_USERNAME=simpeg
# DB_PASSWORD=secret
# CACHE_DRIVER=redis
# QUEUE_CONNECTION=redis
# REDIS_HOST=redis

# 2. Build & jalankan
docker compose -f docker-compose.dev.yml up -d --build

# 3. Install dependencies
docker compose -f docker-compose.dev.yml exec app composer install
docker compose -f docker-compose.dev.yml exec app npm ci
docker compose -f docker-compose.dev.yml exec app npm run build

# 4. Setup aplikasi
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --seed

# 5. Aplikasi berjalan di http://localhost:8080
#    MySQL: localhost:3306
#    Redis: localhost:6379
```

**Yang sudah termasuk di image Apache:**
- PHP extensions: pdo_mysql, mbstring, gd, zip, intl, opcache, redis, bcmath, pcntl, exif, xml, curl
- Apache modules: `rewrite`, `headers`
- Custom php.ini: upload 64M, memory 256M, display_errors On, timezone Asia/Jakarta
- Auto-fix permission `storage/` dan `bootstrap/cache/` saat container start

**Perintah umum:**

```bash
# Shell ke container
docker compose -f docker-compose.dev.yml exec app bash

# Laravel artisan
docker compose -f docker-compose.dev.yml exec app php artisan <command>

# Cek MySQL
docker compose -f docker-compose.dev.yml exec mysql mysql -usimpeg -psecret simpeg

# Lihat log
docker compose -f docker-compose.dev.yml logs -f app

# Restart setelah ganti php.ini
docker compose -f docker-compose.dev.yml restart app

# Stop
docker compose -f docker-compose.dev.yml down
```

---

### Mode 3: Production (Nginx + PHP-FPM)

Arsitektur 2 container terpisah: **Nginx** (serving static + reverse proxy) dan **PHP-FPM** (application). Menggunakan multi-stage build untuk image yang kecil dan aman.

> Catatan: Saat ini belum ada `docker-compose.prod.yml`. Container perlu dibuild dan dijalankan manual atau via orchestrator (Docker Swarm, Kubernetes, dll).

#### Build Image

```bash
# Build PHP-FPM (multi-stage: builder → production)
docker build -f docker/common/php-fpm/Dockerfile \
  --target production \
  -t simpeg-php-fpm:latest .

# Build Nginx (multi-stage: build assets → nginx)
docker build -f docker/production/nginx/Dockerfile \
  -t simpeg-nginx:latest .
```

#### Jalankan Production

```bash
# Buat network
docker network create simpeg-net

# Jalankan MySQL (external atau container terpisah)
docker run -d --name simpeg-mysql --network simpeg-net \
  -e MYSQL_DATABASE=simpeg \
  -e MYSQL_USER=simpeg \
  -e MYSQL_PASSWORD=secret \
  -e MYSQL_ROOT_PASSWORD=rootsecret \
  mysql:8.0

# Jalankan PHP-FPM
docker run -d --name simpeg-php-fpm --network simpeg-net \
  -e DB_HOST=simpeg-mysql \
  -e DB_DATABASE=simpeg \
  -e DB_USERNAME=simpeg \
  -e DB_PASSWORD=secret \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_KEY=base64:xxxxx \
  simpeg-php-fpm:latest

# Jalankan Nginx
docker run -d --name simpeg-nginx --network simpeg-net \
  -p 80:80 \
  simpeg-nginx:latest
```

#### Opsi: docker-compose.prod.yml (buat sendiri)

Buat file `docker-compose.prod.yml`:

```yaml
services:
  php-fpm:
    build:
      context: .
      dockerfile: docker/common/php-fpm/Dockerfile
      target: production
    container_name: simpeg-php-fpm
    restart: unless-stopped
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: "${APP_KEY}"
      DB_HOST: mysql
      DB_DATABASE: "${DB_DATABASE}"
      DB_USERNAME: "${DB_USERNAME}"
      DB_PASSWORD: "${DB_PASSWORD}"
    depends_on:
      mysql:
        condition: service_healthy
    networks:
      - simpeg-net

  nginx:
    build:
      context: .
      dockerfile: docker/production/nginx/Dockerfile
    container_name: simpeg-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    depends_on:
      - php-fpm
    networks:
      - simpeg-net

  mysql:
    image: mysql:8.0
    container_name: simpeg-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: "${DB_PASSWORD}"
      MYSQL_DATABASE: "${DB_DATABASE}"
      MYSQL_USER: "${DB_USERNAME}"
      MYSQL_PASSWORD: "${DB_PASSWORD}"
    volumes:
      - simpeg-mysql-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - simpeg-net

networks:
  simpeg-net:
    driver: bridge

volumes:
  simpeg-mysql-data:
```

Lalu jalankan:

```bash
# Build & start
docker compose -f docker-compose.prod.yml up -d --build

# Cek status
docker compose -f docker-compose.prod.yml ps

# Lihat log
docker compose -f docker-compose.prod.yml logs -f
```

**Apa yang dilakukan entrypoint production:**
1. Inisialisasi `storage/` dari template jika kosong
2. Jalankan `php artisan migrate --force`
3. Cache config dan routes (`config:cache`, `route:cache`)
4. Mulai PHP-FPM

---

### Variabel Environment Penting

| Variable | Dev Default | Prod Default | Keterangan |
|----------|------------|-------------|------------|
| `APP_ENV` | `local` | `production` | Environment Laravel |
| `APP_DEBUG` | `true` | `false` | Tampilkan error detail |
| `APP_KEY` | auto-generate | wajib set | `php artisan key:generate` |
| `APP_PORT` | `80` (Sail) | - | Port host Sail |
| `DB_HOST` | `mysql` | `mysql` | Hostname database |
| `DB_DATABASE` | `simpeg` | `simpeg` | Nama database |
| `DB_USERNAME` | `sail` / `simpeg` | - | User database |
| `DB_PASSWORD` | `password` / `secret` | - | Password database |
| `CACHE_DRIVER` | `file` / `redis` | `redis` | Cache driver |
| `QUEUE_CONNECTION` | `sync` / `redis` | `redis` | Queue driver |
| `SESSION_DRIVER` | `file` | `database` / `redis` | Session driver |

---

## Testing

### PHP (PHPUnit)

```bash
# Semua test
php artisan test

# Test spesifik
php artisan test --filter=CutiPolicyTest
php artisan test --filter=IzinPolicyTest
php artisan test --filter=WorkdayServiceTest
php artisan test --filter=SecureHeadersMiddlewareTest
```

### E2E (Playwright)

```bash
# Install browser (sekali saja)
npx playwright install chromium

# Jalankan test
npx playwright test --project=chromium

# Dengan UI
npx playwright test --ui
```

## Struktur Direktori

```
app/
├── Http/Controllers/    # CutiController, IzinController, PegawaiController, dll.
├── Http/Middleware/      # SecureHeadersMiddleware
├── Http/Policies/        # CutiPolicy, IzinPolicy (authorization)
├── Models/               # Eloquent models (Pegawai, Cuti, Izin, User, dll.)
├── Services/             # WorkdayService (hitung hari kerja)
└── Observers/            # Model observers
database/
├── factories/            # PegawaiFactory, CutiFactory, IzinFactory, UserFactory
├── migrations/           # Schema database
└── seeders/              # Demo data & permission seeder
resources/views/          # Blade templates
docker/                   # Dockerfiles & configs (Sail, Apache dev, Nginx prod)
e2e/                      # Playwright E2E tests
tests/                    # PHPUnit unit & feature tests
```

## Konvensi

- **Foreign key** menggunakan `uuid` (bukan auto-increment `id`) untuk relasi Pegawai.
- **Permission** menggunakan format: `verb resource` (contoh: `view cuti`, `create izin`).
- **Status flow cuti/izin:** `Diajukan` → `Disetujui Atasan` → `Disetujui` (atau `Ditolak` di salah satu tahap).
- **Navigasi:** Desktop dan mobile harus paralel — cek `resources/views/layouts/navigation.blade.php`.

## CI

- **PHP Tests** (`.github/workflows/php-tests.yml`): PHPUnit pada PHP 8.2 & 8.3.
- **Playwright** (`.github/workflows/playwright.yml`): E2E tests pada Chromium.

## License

Proprietary — internal use only.
