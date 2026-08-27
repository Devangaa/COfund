# Arsitektur & Infrastruktur

Dokumentasi rinci tentang arsitektur backend, pekerjaan latar belakang, sistem antrian, penjadwal cron, dan persyaratan infrastruktur untuk platform CoFund.

---

## Daftar Isi

1. [Lapisan Arsitektur](#1-lapisan-arsitektur)
2. [Sistem Berbasis Event](#2-sistem-berbasis-event)
3. [Pekerjaan Latar Belakang](#3-pekerjaan-latar-belakang)
4. [Sistem Antrian](#4-sistem-antrian)
5. [Penjadwal Cron](#5-penjadwal-cron)
6. [Sistem Email](#6-sistem-email)
7. [Penyimpanan Berkas](#7-penyimpanan-berkas)
8. [Deployment Produksi](#8-deployment-produksi)
9. [Checklist Infrastruktur](#9-checklist-infrastruktur)

---

## 1. Lapisan Arsitektur

Backend CoFund mengikuti arsitektur berlapis dengan pemisahan kekhawatiran yang jelas:

```
┌─────────────────────────────────────────────────────────┐
│                    HTTP Router                           │
│  Laravel Routes (routes/api.php)                        │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Middleware Layer                          │
│  - auth:sanctum, role:*, verified, throttle:*           │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Controller Layer                          │
│  - Controller tipis, mendelegasikan ke service          │
│  - Form Requests menangani validasi                     │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Service Layer (Business Logic)            │
│  - AuthService, CampaignService, BackingService         │
│  - WalletService, TransactionService, UserService        │
│  - TierService, CampaignUpdateService, ImageService     │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Event System                              │
│  Events → Listeners → Notifications / Emails / Jobs     │
└───────────────┬─────┬─────┬───────────────────────────────┘
                │     │     │
    ┌───────────▼──┐ ┌▼───┐ ┌▼──────────────┐
    │   Database   │ │Filesystem│ │Email Service│
    │   (MySQL)    │ │(Local)    │ │(SMTP/Mail)│
    └──────────────┘ └──────────┘ └─────────────┘
```

### Detail Lapisan

| Lapisan | Tanggung Jawab | File Kunci |
|-------|----------------|-----------|
| **Router** | Memetakan URL ke controller; menerapkan middleware | `routes/api.php` |
| **Middleware** | Auth, pengecekan peran, pembatasan laju, verifikasi email | `app/Http/Middleware/*.php` |
| **Controllers** | Penanganan permintaan; mendelegasikan ke service; mengembalikan Resources | `app/Http/Controllers/Api/**/*.php` |
| **Form Requests** | Validasi dan otorisasi per permintaan | `app/Http/Requests/*.php` |
| **Services** | Logika bisnis inti; transaksi DB; penctriggeran event | `app/Services/*.php` |
| **Events/Listeners** | Side-effects yang terdekoup (notifikasi, email, job) | `app/Events/*`, `app/Listeners/*` |
| **Jobs** | Tugas berjalan lama atau latar belakang | `app/Jobs/*.php` |
| **Models** | Akses data; relasi; accessor/mutator | `app/Models/*.php` |
| **Resources** | Transformasi JSON untuk respons API | `app/Http/Resources/*.php` |

---

## 2. Sistem Berbasis Event

Aplikasi menggunakan sistem event Laravel untuk mendekupulkan side-effects dari logika bisnis inti. Ini mengikuti **Observer Pattern** dengan varian Publish-Subscribe.

### Event yang Tersedia

Event didefinisikan di `app/Events/*.php` dan terdaftar di `app/Providers/EventServiceProvider.php`.

```php
// Struktur event
class CampaignApproved extends ShouldDispatch
{
    use Dispatchable, SerializesModels;

    public function __construct(public Campaign $campaign) {}
}
```

### Pendaftaran Event

Semua event secara eksplisit terdaftar (tidak ada auto-discovery):

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    CampaignApproved::class => [
        HandleCampaignApproved::class,
    ],
    CampaignRejected::class => [
        HandleCampaignRejected::class,
    ],
    CampaignFunded::class => [
        HandleCampaignFunded::class,
    ],
    BackingCreated::class => [
        HandleBackingCreated::class,
    ],
    DepositProcessed::class => [
        [HandleWalletTransaction::class, 'handleDeposit'],
    ],
    WithdrawalProcessed::class => [
        [HandleWalletTransaction::class, 'handleWithdrawal'],
    ],
];
```

### Listener Berbasis Metode

Event `DepositProcessed` dan `WithdrawalProcessed` keduanya menggunakan kelas listener yang sama `HandleWalletTransaction`, tetapi mengarah ke metode yang berbeda:

```php
// EventServiceProvider
DepositProcessed::class => [
    [HandleWalletTransaction::class, 'handleDeposit'],
],
WithdrawalProcessed::class => [
    [HandleWalletTransaction::class, 'handleWithdrawal'],
],
```

### Keamanan Transaksional Event

Event dipicu menggunakan `DB::afterCommit()` untuk memastikan hanya dieksekusi setelah transaksi database berhasil:

```php
DB::transaction(function () use ($campaign) {
    // ... update kampanye ...
    
    // Trigger event setelah commit
    $campaign->load('creator');
    event(new CampaignApproved($campaign));
});
```

Ini mencegah pemic.triggeran event untuk transaksi yang dibatalkan.

### ⚠️ Event Tidak Terdaftar

Event `UserSuspended` dan `UserUnsuspended` dipicu di `UserService` tetapi **tidak terdaftar** di `EventServiceProvider::$listen`. Karena `shouldDiscoverEvents()` mengembalikan `false`, tidak ada listener yang akan beraksi untuk event-event ini.

---

## 3. Pekerjaan Latar Belakang

Pekerjaan digunakan untuk operasi yang:
- Dapat berlangsung lebih dari 1-2 detik
- Dapat berjalan independen dari permintaan HTTP
- Harus mencoba lagi pada kegagalan

### Daftar Pekerjaan

| Job | File | Implements | Dipicu Oleh | Tujuan |
|-----|------|------------|---------------|---------|
| `DisburseCampaignJob` | `app/Jobs/DisburseCampaignJob.php` | `ShouldQueue` | Listener `HandleCampaignFunded` | Mencairkan dana ke creator kampanye |
| `RefundBackersJob` | `app/Jobs/RefundBackersJob.php` | `ShouldQueue` | Perintah `CheckExpiredCampaigns` | Mengembalikan dana semua backer kampanye yang gagal |

### Alur Pekerjaan

```
Event CampaignFunded
    ↓
Listener HandleCampaignFunded (sinkron)
    ↓
Dispatch DisburseCampaignJob::dispatch($campaign)
    ↓
(Jika QUEUE_CONNECTION=sync: berjalan segera)
    ↓
TransactionService::disburseCampaign($campaign)
    ↓
- Hitung biaya platform 5%
- Deposit 95% ke saldo creator
- Buat transaksi DISBURSEMENT + PLATFORM_FEE
- Buat notifikasi dalam aplikasi + email ke creator
```

```
Perintah CheckExpiredCampaigns
    ↓
Untuk setiap kampanye FAILED:
    Dispatch RefundBackersJob::dispatch($campaign)
    ↓
TransactionService::refundBackers($campaign)
    ↓
- Dapatkan semua backing yang belum dikembalikan
- Deposit jumlah penuh ke setiap backer
- Buat transaksi REFUND
- Perbarui status backing menjadi 'refunded'
- Buat notifikasi dalam aplikasi + email
```

### Menjalankan Pekerjaan

#### Dengan Driver Sinkron (Default)

Pekerjaan berjalan inline selama permintaan HTTP. Tidak diperlukan proses worker.

```bash
php artisan campaign:check-expired
```

#### Dengan Driver Antrian Database

Pekerjaan disimpan di tabel `jobs` dan diproses oleh proses worker.

```bash
# Konfigurasi di .env
QUEUE_CONNECTION=database

# Jalankan migrasi untuk tabel jobs
php artisan queue:table
php artisan migrate

# Mulai worker
php artisan queue:work
```

### Retry & Penanganan Kegagalan Pekerjaan

Setiap pekerjaan dapat dikonfigurasi dengan:
- Properti `$tries` (maks percobaan ulang)
- Properti `$timeout` (maks detik per percobaan)
- Properti `$backoff` (penundaan antar percobaan)

Saat ini tidak diatur secara eksplisit, jadi default berlaku:
- `tries`: 1 (tidak mencoba lagi)
- `timeout`: 0 (tidak ada batas)
- Pekerjaan yang gagal disimpan di tabel `failed_jobs`

### Memantau Pekerjaan yang Gagal

```bash
# Daftar pekerjaan yang gagal
php artisan queue:failed

# Coba lagi pekerjaan yang gagal
php artisan queue:retry {job-id-atau-uuid}

# Hapus semua pekerjaan yang gagal
php artisan queue:flush
```

---

## 4. Sistem Antrian

Saat ini dikonfigurasi sebagai `sync` secara default, tetapi dirancang untuk dapat diganti di produksi.

### Konfigurasi Saat Ini

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'sync'),

'connections' => [
    'sync' => [
        'driver' => 'sync',
    ],
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
    // ... konfigurasi redis, beanstalkd, sqs
],
```

### Skema Tabel Antrian

Saat menggunakan driver `database`, tabel berikut diperlukan:

| Kolom | Tipe | Deskripsi |
|--------|------|-------------|
| `id` | bigint | PK |
| `queue` | varchar | Nama antrian |
| `payload` | longtext | Data pekerjaan yang diserialisasi |
| `attempts` | integer | Jumlah percobaan |
| `reserved_at` | timestamp | Kapan pekerjaan dicadangkan |
| `available_at` | timestamp | Kapan pekerjaan tersedia |
| `created_at` | timestamp | Timestamp pembuatan |

### Beralih ke Antrian Async

Untuk menggunakan pemrosesan antrian asinkron:

1. **Atur `.env`**:
   ```env
   QUEUE_CONNECTION=redis
   ```

2. **Buat tabel antrian** (hanya diperlukan untuk driver database):
   ```bash
   php artisan queue:table
   php artisan migrate
   ```

3. **Mulai worker**:
   ```bash
   php artisan queue:work redis
   ```

> Catatan: Proyek ini saat ini sudah dikonfigurasi untuk menggunakan Redis sebagai koneksi antrian default. File `queue-setup.md` di folder docs berisi petunjuk rinci untuk mensetup Redis sebagai driver antrian.

### Konfigurasi Supervisor (Linux Produksi)

Untuk menjaga worker antrian berjalan di produksi:

```ini
# /etc/supervisor/conf.d/cofund-worker.conf
[program:cofund-worker]
command=php /var/www/cofund/backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/cofund-worker.log
stdout_logfile_maxbytes=10MB
```

```bash
# Muat kembali supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cofund-worker:*
```

### Opsi Worker Antrian

| Opsi | Deskripsi |
|--------|-------------|
| `--sleep=3` | Detik untuk tidur ketika tidak ada pekerjaan |
| `--tries=3` | Maksimum percobaan ulang per pekerjaan |
| `--timeout=60` | Maksimum detik per percobaan |
| `--queue=high,default` | Memproses antrian dalam urutan prioritas |

### Memantau Antrian

Lihat statistik antrian melalui perintah artisan:

```bash
# Periksa statistik pemrosesan pekerjaan
php artisan queue:work --verbose

# Lihat pekerjaan yang gagal
php artisan queue:failed

# Periksa ukuran antrian (hanya driver database)
php artisan tinker
>>> DB::table('jobs')->count()
```

---

## 5. Penjadwal Cron

Penjadwal tugas Laravel memungkinkan mendefinisikan tugas yang dijadwalkan di kode, hanya memerlukan **satu** entri cron pada server.

### Konfigurasi Penjadwal

Didefinisikan di `app/Console/Kernel.php` → metode `schedule()`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('campaign:check-expired')
        ->dailyAt('00:05');

    $schedule->command('campaign:notify-deadline')
        ->dailyAt('09:00');
}
```

### Tugas yang DiJadwalkan

| Tugas | Frekuensi | Deskripsi |
|------|-----------|-------------|
| `campaign:check-expired` | Harian pukul 00:05 | Memproses kampanye yang melewati deadline |
| `campaign:notify-deadline` | Harian pukul 09:00 | Mengirim peringatan deadline ke backer |

### Detail Tugas

#### `campaign:check-expired` (Perintah `CheckExpiredCampaigns`)

**File**: `app/Console/Commands/CheckExpiredCampaigns.php`

**Logika**:
1. Query kampanye dengan `status = 'active'` DAN `deadline < NOW()`
2. Untuk setiap kampanye yang kadaluarsa:
   - **Jika terdanai** (`collected_amount >= target_amount`):
     - Atur `status = 'success'`
     - Dispatch `DisburseCampaignJob` → kredit saldo creator (95%) + rekam biaya platform 5%
   - **Jika tidak terdanai**:
     - Atur `status = 'failed'`
     - Dispatch `RefundBackersJob` → kembalikan dana ke semua backer

**✅ Bug sudah diperbaiki**: Perintah `NotifyDeadlineApproaching` sebelumnya mereferensikan variabel yang tidak terdefinisi `$countH3` dan `$countH1`. Ini sudah diperbaiki — perintah kini menampilkan total notifikasi yang dikirim.

#### `campaign:notify-deadline` (Perintah `NotifyDeadlineApproaching`)

**File**: `app/Console/Commands/NotifyDeadlineApproaching.php`

**Logika**:
1. Temukan kampanye dengan deadline tepat 3 hari dan 1 hari ke depan
2. Untuk setiap kampanye, kumpulkan ID backer pengguna yang unik
3. Mass insert catatan `Notification` dengan `type = 'deadline_approaching'`

---

### Persyaratan Entri Cron

Hanya **satu** entri cron yang diperlukan pada server produksi:

```bash
* * * * * cd /var/www/cofund/backend && php artisan schedule:run >> /dev/null 2>&1
```

Ini adalah **satu-satunya** entri cron yang harus dikonfigurasi. Penjadwal Laravel menangani sisahnya secara internal berdasarkan `Kernel::schedule()`.

### Eksekusi Manual

Anda dapat menjalankan perintah yang dijadwalkan secara manual untuk pengujian:

```bash
# Jalankan check-expired secara manual
php artisan campaign:check-expired

# Jalankan notify-deadline secara manual
php artisan campaign:notify-deadline

# Simulasikan penjadwal (untuk debug)
php artisan schedule:run

# Daftar tugas yang dijadwalkan
php artisan schedule:list
```

### Pertimbangan Zona Waktu

Penjadwal menggunakan zona waktu default aplikasi (`UTC` per `config/app.php`).

Untuk menjalankan tugas tertentu pada zona waktu tertentu:

```php
$schedule->command('campaign:check-expired')
    ->dailyAt('00:05')
    ->timezone('Asia/Jakarta');
```

### Debug Penjadwal

```bash
# Lihat semua perintah yang dijadwalkan
php artisan schedule:list

# Lihat entri cron penjadwal
crontab -l

# Bersihkan cache penjadwal jika perubahan tidak tercermin
php artisan schedule:clear-cache
```

---

## 6. Sistem Email

### Konfigurasi Mail

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=hello@cofund.test
```

Untuk pengembangan lokal, Mailpit digunakan (server SMTP debugging yang menangkap email tanpa mengirimkannya).

### Beralih Driver Mail ke Produksi

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

### Antrian Email

Saat ini, semua email dikirim **secara sinkron** selama siklus permintaan:

```php
// Di listener
Mail::to($user->email)->send(new CampaignApproved($creator, $campaign));
```

Untuk produksi, pertimbangkan untuk mengantrekan email:

```php
// Tambahkan ke kelas mailable
class CampaignApproved extends Mailable implements ShouldQueue
{
    // ...
}

// Atau konfigurasi di config/queue.php
'failed' => [
    'driver' => 'database-uuids',
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

### Mailable yang Tersedia

| Mailable | File | Template | Dikirim Saat |
|----------|------|----------|-----------|
| `CampaignApproved` | `app/Mail/CampaignApproved.php` | `mail.campaign-approved` | Admin menyetujui kampanye |
| `CampaignRejected` | `app/Mail/CampaignRejected.php` | `mail.campaign-rejected` | Admin menolak kampanye |
| `BackingConfirmation` | `app/Mail/BackingConfirmation.php` | `mail.backing-confirmation` | Backer membuat backing |
| `DisbursementProcessed` | `app/Mail/DisbursementProcessed.php` | `mail.disbursement` | Dana kampanye dicairkan |
| `RefundProcessed` | `app/Mail/RefundProcessed.php` | `mail.refund` | Backer mendapatkan pengembalian |

### Aturan Penekanan Email

Email hanya dikirim jika penerima memiliki `email_verified_at` yang diatur:

```php
if ($creator->email_verified_at) {
    Mail::to($creator->email)->send(new CampaignApproved(...));
}
```

---

## 7. Penyimpanan Berkas

### Disk Penyimpanan

| Disk | Driver | Path | Kasus Penggunaan |
|------|--------|------|----------|
| `local` | local | `storage/app/` | Berkas pribadi |
| `public` | local | `storage/app/public/` | Berkas yang dapat diakses publik |
| `campaigns` | local | `storage/app/public/campaigns/` | Gambar kampanye |

### Konfigurasi Penyimpanan

```php
// config/filesystems.php
'default' => env('FILESYSTEM_DISK', 'local'),

'disks' => [
    'local' => ['driver' => 'local', 'root' => storage_path('app')],
    'public' => [
        'driver' => 'local',
        'root' => storage_path('public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
    'campaigns' => [
        'driver' => 'local',
        'root' => storage_path('public/campaigns'),
        'url' => env('APP_URL').'/storage/campaigns',
        'visibility' => 'public',
    ],
],
```

### Perintah Penyimpanan

```bash
# Buat symlink penyimpanan
php artisan storage:link

# Buat symlink khusus untuk disk campaigns
php artisan storage:link campaigns
```

### Membersihkan Berkas Terasing

Saat bug "delete all images" terjadi (lihat Masalah yang Diketahui di dokumen umum), beberapa berkas gambar mungkin dihapus dari disk tetapi catatannya tetap ada di database. Untuk membersihkan:

```bash
# Temukan dan hapus berkas yang tidak memiliki catatan DB
php artisan tinker
>>> $images = CampaignImage::whereNull('deleted_at')->get();
>>> foreach($images as $img) {
...     if (!Storage::disk('campaigns')->exists(basename($img->url))) {
...         $img->forceDelete(); // hapus permanen catatan DB yang terasing
...     }
... }
```

### Validasi Berkas

Pengunggahan gambar divalidasi:
- Ukuran maks: 2MB (`max:2048`)
- Format yang diizinkan: `jpeg`, `png`, `jpg`, `gif`
- Pemeriksaan tipe MIME melalui PHP's `fileinfo`

---

## 8. Deployment Produksi

### Persyaratan Server

| Komponen | Minimum |
|-----------|---------|
| PHP | 8.1+ dengan: `ctype`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `session`, `tokenizer`, `xml` |
| Composer | 2.x |
| Web Server | Apache 2.4+ atau Nginx 1.14+ |
| Database | MySQL 8.0+ atau MariaDB 10.4+ |
| Cache | Redis (disarankan) atau file |
| Queue | Redis (disarankan) atau database |

### Langkah Deployment

1. **Clone dan instal dependensi**:
   ```bash
   git clone <repo>
   composer install --optimize-autoloader --no-dev
   npm install && npm run build
   ```

2. **Konfigurasi lingkungan**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   # Edit .env dengan nilai produksi
   ```

3. **Siapkan symlink penyimpanan**:
   ```bash
   php artisan storage:link
   ```

4. **Jalankan migrasi**:
   ```bash
   php artisan migrate --force
   ```

5. **Optimalkan**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. **Siapkan cron**:
   ```bash
   * * * * * cd /var/www/cofund/backend && php artisan schedule:run >> /dev/null 2>&1
   ```

7. **Mulai worker antrian (jika menggunakan async queue)**:
   ```bash
   # Gunakan supervisor atau systemd untuk mengelola worker
   php artisan queue:work --daemon
   ```

### Konfigurasi Web Server

#### Apache (`public/.htaccess` ada secara default)

```apache
<Directory /var/www/cofund/backend/public>
    AllowOverride All
    Require all granted
</Directory>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name cofund.test;
    root /var/www/cofund/backend/public;

    add_header X-Frame-Options "SAMEORIGIN");
    add_header X-Content-Type-Options "nosniff");

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### SSL (Produksi)

Pastikan aplikasi memaksa HTTPS:

```php
// AppServiceProvider::boot()
if (App::environment('production')) {
    \URL::forceScheme('https');
}

// Atau di .env
FORCE_HTTPS=true
```

### Konfigurasi Berdasarkan Lingkungan

```env
# .env.production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.cofund.com

DB_HOST=127.0.0.1
DB_DATABASE=cofund_prod
DB_USERNAME=cofund_user
DB_PASSWORD=secure_password

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@cofund.com
MAIL_PASSWORD=mail_password
```

---

## 9. Checklist Infrastruktur

### ✅ Harus Ada

- [ ] Satu entri cron untuk penjadwal Laravel
- [ ] Proses worker antrian (jika QUEUE_CONNECTION ≠ sync)
- [ ] Symlink penyimpanan (`php artisan storage:link`)
- [ ] APP_KEY yang dihasilkan
- [ ] SSL/TLS dikonfigurasi untuk HTTPS
- [ ] Pencadangan database dikonfigurasi
- [ ] File `.env` dikecualikan dari kontrol versi (`.gitignore`)

### ⚠️ Masalah Kritis yang Harus Diperbaiki Sebelum Produksi

- [ ] **Daftarkan event `UserSuspended` dan `UserUnsuspended` di `EventServiceProvider`**
- [ ] **Buat `config/cofund.php` dengan pengaturan biaya platform (saat ini 5% di-hardcode vs 10% di fallback config)**
- [ ] **Perbaiki metode `down()` migrasi `database.md` untuk indeks FULLTEXT**

### 🛡️ Keamanan

- [ ] `.env` **tidak** ada di `.gitignore` (saat ini dilacak di repo)
- [ ] APP_DEBUG=false di produksi
- [ ] HTTPS diterapkan
- [ ] Rate limiting aktif pada endpoint otentikasi
- [ ] Password di-hash dengan bcrypt
- [ ] Token Sanctum disimpan sebagai hash
- [ ] Pengunggahan berkas divalidasi (tipe MIME + ukuran)

### 📊 Pemantauan

- [ ] Pemantauan worker antrian (Supervisor/Nova)
- [ ] Log aplikasi (storage/logs)
- [ ] Pemantauan tabel pekerjaan yang gagal
- [ ] Pemantauan eksekusi cron
- [ ] Log query lambat database
- [ ] Pemantauan uptime

### 📈 Pertimbangan Scaling

| Komponen | Saat Ini | Disarankan |
|-----------|---------|-------------|
| Cache | File | Redis |
| Queue | Sync | Redis |
| Session | File | Redis |
| Database | MySQL tunggal | Kluster MySQL (di masa depan) |
| Storage | Local | S3 compatible |
| Scheduler | Cron (1 node) | Beberapa node dengan koordinasi lock |

### 🔄 Strategi Pencadangan

1. **Database**: Pencadupan penuh harian + binlog jam-anjang
2. **Storage**: Sinkronkan `storage/app/public` ke penyimpanan cloud
3. **Kode**: Repositori Git + tag untuk rilis
4. **Log**: Rotasi via logrotate atau channel log harian Laravel
