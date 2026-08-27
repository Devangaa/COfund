# Panduan Setup & Operasional Queue Driver Redis

Langkah-langkah untuk mengonfigurasi dan menjalankan Laravel Queue Worker dengan Redis pada environment Windows/Laragon.

## 1. Prasyarat

### Redis Server (via Laragon)

Redis disediakan sebagai servis Laragon. Untuk mengaktifkannya:

1. Buka **Laragon**
2. Buka **Tools** → **Quick app** → **Tambahkan "redis"** (URL: `https://redis.io`, nama: `Redis`)
3. Atau jalankan Redis langsung dari panel servis Laragon (port 6379)

Verifikasi Redis sudah berjalan:

```bash
redis-cli ping
# Diperkirakan: PONG
```

Tidak diperlukan instalasi manual bila Redis sudah dijalankan lewat Laragon.

### Klien Redis PHP (via Composer)

Laravel mendukung dua library klien Redis:

| Library | Metode Instalasi | Deskripsi |
|---------|-----------------|-------------|
| `phpredis` | Ekstensi PHP (C extension) | Lebih cepat, memerlukan kompilasi extension |
| `predis` | Paket Composer | Pure PHP, tidak perlu ekstensi tambahan, disarankan untuk setup ini |

Proyek ini menggunakan `predis/predis`:

```bash
cd backend
composer require predis/predis
```

> Tidak diperlukan ekstensi PHP Redis tambahan bila memakai `predis`.

## 2. Konfigurasi Environment

### .env

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DBNAME=0
```

Laravel akan otomatis memakai klien `predis` ketika paket `composer require predis/predis` terpasang dan ekstensi `phpredis` tidak dimuat — tidak perlu konfigurasi tambahan.

## 3. Database Migration — Tabel Queue

Buat tabel untuk melacak pekerjaan yang terqueue dan yang gagal:

```bash
php artisan queue:table
php artisan migrate
```

Tabel yang dibuat:
- `jobs` — penyimpanan job yang terqueue
- `failed_jobs` — catatan job yang gagal diproses

## 4. Memulai Queue Worker

```bash
php artisan queue:work redis --tries=3 --timeout=90
```

### Penjelasan Parameter

| Parameter | Deskripsi |
|-----------|-----------|
| `redis` | Nama koneksi queue driver yang diproses |
| `--tries=3` | Maksimal 3 kali percobaan ulang jika job gagal |
| `--timeout=90` | Maksimal detik yang dibutuhkan job sebelum di-kill |
| `--sleep=3` | Detik tidur ketika tidak ada job baru (opsional) |
| `--memory=128` | Batas memori dalam MB sebelum worker restart (opsional) |

Biarkan worker tetap berjalan — ia akan terus memantau Redis untuk job baru.

## 5. Jobs yang Diproses oleh Queue

| Job | Trigger | Deskripsi |
|-----|---------|-----------|
| `DisburseCampaignJob` | Event `CampaignFunded` | Menyalurkan dana dari escrow ke kreator kampanye |
| `RefundBackersJob` | Event `CampaignFailed` | Mengembalikan dana ke backer kampanye yang gagal |

## 6. Deployment Produksi — Monitoring Proses

Untuk produksi, gunakan process monitor untuk menjaga worker tetap berjalan:

### Supervisor (Linux)

Buat file `/etc/supervisor/conf.d/cofund-worker.conf`:

```ini
[program:cofund-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cofund/backend/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/cofund-worker.log
stopwaitsecs=3600
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cofund-worker:*
```

### Windows — Service via NSSM

```bash
nssm install CofundWorker "C:\php\php.exe" "C:\laragon\www\COfund\backend\artisan" "queue:work" "redis" "--tries=3" "--timeout=90"
nssm set CofundWorker AppDirectory "C:\laragon\www\COfund\backend"
nssm start CofundWorker
```

## 7. Monitoring & Debugging

### Cek Panjang Antrian

```bash
# Via Redis CLI
redis-cli LLEN queue:default  # Jumlah job yang menunggu

# Via Laravel
php artisan tinker
>>> \Queue::connection('redis')->size()
```

### Lihat Job yang Gagal

```bash
php artisan queue:failed
```

### Retry / Hapus Job

```bash
php artisan queue:retry {id}        # Retry job yang gagal
php artisan queue:clear redis       # Hapus semua job yang menunggu
php artisan queue:flush             # Hapus semua job yang gagal
```

### Deploy Tanpa Downtime

```bash
php artisan queue:restart
```

Worker akan menyelesaikan job saat ini lalu keluar secara gracefull; process monitor akan otomatis merestartnya.

## 8. Troubleshooting

### Redis Tidak Berjalan

```bash
redis-cli ping
# Jika error: Connection refused
# → Jalankan Redis di Laragon atau sebagai service Windows
```

### predis Tidak Ditemukan

```bash
composer require predis/predis
```

### Worker Masih Behaiver Seperti sync

Pastikan `QUEUE_CONNECTION=redis` di `.env`, lalu:

```bash
php artisan config:clear
php artisan cache:clear
```

Verifikasi:

```bash
php artisan tinker
>>> config('queue.default')  // harus mengembalikan 'redis'
```

### Job Timeout

Tingkatkan `--timeout` atau investigasi logika job:

```bash
php artisan queue:work redis --tries=3 --timeout=120
```

## 9. Verifikasi

```bash
# 1. Redis sudah berjalan
redis-cli ping  # Diperkirakan: PONG

# 2. predis terpasang
php artisan tinker
>>> class_exists('Predis\Client')  // Diperkirakan: true

# 3. Koneksi queue bekerja
>>> \Queue::connection('redis')->size()  // Diperkirakan: integer (0 jika kosong)

# 4. Dispatch job uji coba
>>> \Queue::push(new \App\Jobs\DisburseCampaignJob(1));
>>> \Queue::connection('redis')->size()  // Diperkirakan: 1 (terqueue, tidak dijalankan inline)

# 5. Proses job uji coba
# Jendela terminal 2:
php artisan queue:work redis --once
```