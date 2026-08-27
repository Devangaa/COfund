# Dokumentasi API CoFund

Dokumentasi API lengkap untuk backend platform crowdfunding CoFund.

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Ikhtisar Arsitektur](#2-ikhtisar-arsitektur)
3. [Memulai](#3-memulai)
4. [Otentikasi](#4-otentikasi)
5. [Peran Pengguna](#5-peran-pengguna)
6. [Pembatasan Laju](#6-pembatasan-laju)
7. [Penanganan Error](#7-penanganan-error)
8. [Ringkasan Endpoint API](#8-ringkasan-endpoint-api)
9. [Sistem Event](#9-sistem-event)
10. [Pekerjaan Latar Belakang](#10-pekerjaan-latar-belakang)
11. [Modul](#11-modul)
12. [Masalah yang Diketahui](#12-masalah-yang-diketahui)
13. [Pengaturan Postman](#13-pengaturan-postman)
14. [Perintah Pengembangan](#14-perintah-pengembangan)

---

## 1. Pendahuluan

CoFund adalah platform crowdfunding yang dibangun di atas Laravel 10 dan PHP 8.1+. API menyediakan endpoint untuk:
- Otentikasi pengguna dan manajemen akun
- Pembuatan, moderasi, dan penjelajahan kampanye
- Backing (pendanaan) ke kampanye
- Manajemen dompet (deposit/penarikan)
- Fungsionalitas panel admin (manajemen pengguna, statistik)
- Riwayat transaksi

**URL Dasar:** `http://localhost:8000/api`

**Protokol:** HTTPS (di produksi)  
**Format:** JSON (badan permintaan dan respons)  
**Encoding:** UTF-8  
**Otentikasi:** Token Bearer Sanctum Laravel

---

## 2. Ikhtisar Arsitektur

```
┌─────────────────────────────────────────────────────────────┐
│                      Frontend (Vite/React)                    │
└─────────────┬───────────────────────────────────────────────┘
              │ HTTP
┌─────────────▼──────────────────────────────────────────────┐
│                    Laravel Backend (API)                  │
├───────────────────────────────────────────────────────────┤
│  Routes (api.php) — Route Groups + Middleware             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Controllers                                         │  │
│  │  - AuthController, CampaignController                │  │
│  │  - BackingController, WalletController               │  │
│  │  - TransactionController, TierController             │  │
│  │  - CampaignUpdateController, CampaignImageController  │  │
│  │  - Admin\UserController, Admin\StatisticsController  │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Services (Business Logic)                           │  │
│  │  - AuthService, CampaignService, BackingService     │  │
│  │  - WalletService, TransactionService, UserService   │  │
│  │  - TierService, CampaignUpdateService, ImageService │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Models + Enums                                        │  │
│  │  - User, Campaign, Backing, Transaction             │  │
│  │  - CampaignTier, CampaignImage, CampaignUpdate      │  │
│  │  - Category, Notification                            │  │
│  │  - CampaignStatus, BackingStatus                     │  │
│  │  - TransactionType, TransactionStatus              │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Events ↔ Listeners ↔ Notifications                 │  │
│  │  - CampaignApproved, BackingCreated, etc.           │  │
│  │  - HandleCampaignApproved, HandleBackingCreated     │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Jobs (Queued)                                        │  │
│  │  - DisburseCampaignJob, RefundBackersJob            │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Database (MySQL 8)                                   │  │
│  │  - 9 tables with Foreign Key constraints             │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ File Storage (Local)                                 │  │
│  │  - Campaign images on `campaigns` disk                │  │
│  └─────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────┘
```

### Pola Desain

| Pola | Penggunaan |
|---------|-------|
| **Service Layer** | Semua logika bisnis di `app/Services/*.php`; controller tipis |
| **Form Request Validation** | Kelas request terpisah untuk validasi per endpoint |
| **Resource Transformers** | `app/Http/Resources/*.php` untuk keluaran JSON yang konsisten |
| **Event-Driven** | Event dipicu untuk aksi penting; listener membuat notifikasi |
| **Repository via Eloquent** | Model bertindak sebagai repository; penggunaan Eloquent langsung di service |
| **Dependency Injection** | Service di-injek ke controller melalui konstruktor |

### Teknologi

| Layer | Teknologi |
|-------|-----------|
| Framework | Laravel 10.x |
| PHP | 8.1+ |
| Database | MySQL 8 (utf8mb4) |
| Otentikasi | Sanctum API Tokens |
| Queue | Database driver (sync secara default) |
| Cache | File driver |
| Session | File driver |
| Storage | Local filesystem |
| Mail | SMTP (Mailpit untuk dev lokal) |
| Testing | PHPUnit (Laravel Dusk tidak dikonfigurasi) |

---

## 3. Memulai

### Prasyarat

- PHP 8.1+
- Composer
- MySQL 8.0+
- Node.js 18+ & NPM (untuk frontend)
- Git

### Instalasi

```bash
# Clone repositori
git clone <repo-url>
cd cofund/backend

# Instal dependensi PHP
composer install

# Salin .env dan konfigurasi
cp .env.example .env
# Edit .env dengan kredensial database Anda

# Generate kunci aplikasi
php artisan key:generate

# Jalankan migrasi
php artisan migrate

# Seed data sampel (opsional)
php artisan db:seed

# Buat symlink penyimpanan
php artisan storage:link

# Mulai server pengembangan
php artisan serve
```

### Variabel Lingkungan

```env
APP_NAME=CoFund
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cofund
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=sync
SESSION_DRIVER=file
CACHE_DRIVER=file
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=hello@cofund.test
```

---

## 4. Otentikasi

CoFund menggunakan **Laravel Sanctum** untuk otentikasi API dengan token bearer.

### Otentikasi Berbasis Token

1. Pengguna memanggil `POST /api/login` dengan email dan password
2. Server memvalidasi kredensial dan membuat token Sanctum
3. Token dikembalikan dalam respons
4. Klien menyertakan token dalam header `Authorization: Bearer {token}` untuk permintaan berikutnya

### Format Token

```
Authorization: Bearer 5|9e7a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a
```

### Cakupan Token

Token tidak memiliki tanggal kedaluwarsa secara default (`config/sanctum.php` `expiration` adalah `null`). Token dapat dicabut dengan:
- Logout (`POST /api/logout`) — menghapus token saat ini
- Intervensi admin (fitur masa depan — pencabutan token via database)

### Verifikasi Email

- Pengguna menerima tautan verifikasi email saat pendaftaran
- Middleware `verified` mencegah pengguna yang tidak diverifikasi mengakses endpoint dompet, backing, dan pembuatan kampanye
- Pengguna yang tidak diverifikasi mendapat respons `403`: `{"message": "Your email address is not verified."}`

### Alur Reset Kata Sandi

```
User → POST /api/forgot-password (email)
     → Laravel Password Broker mengirim email dengan tautan reset
     → User klik tautan → POST /api/reset-password (email, token, password, password_confirmation)
     → Kata sandi diperbarui
```

---

## 5. Peran Pengguna

CoFund menerapkan sistem RBAC 3-peran menggunakan kolom `role` pada tabel `users`.

| Peran | Nilai Enum | Izin |
|------|-----------|--------|
| **Backer** | `backer` | Jelajahi kampanye, buat backing, deposit/penarikan |
| **Creator** | `creator` | Semua izin backer + buat/edit/hapus kampanye sendiri, tier, gambar, pembaruan |
| **Admin** | `admin` | Semua izin + setujui/tolak kampanye, kelola pengguna, lihat statistik |

### Matriks Peran

| Aksi | Public | Backer | Creator | Admin |
|--------|--------|--------|---------|-------|
| Register | ✅ | — | — | — |
| Login | ✅ | ✅ | ✅ | ✅ |
| Jelajahi kampanye | ✅ | ✅ | ✅ | ✅ |
| Lihat detail kampanye | ✅ | ✅ | ✅ | ✅ |
| Daftar pembaruan kampanye | ✅ | ✅ | ✅ | ✅ |
| Buat kampanye | ❌ | ❌ | ✅ | ✅ |
| Edit kampanye sendiri | ❌ | ❌ | ✅ (pemilik) | ✅ |
| Ajukan kampanye untuk tinjauan | ❌ | ❌ | ✅ (pemilik) | ✅ |
| Setujui kampanye | ❌ | ❌ | ❌ | ✅ |
| Tolak kampanye | ❌ | ❌ | ❌ | ✅ |
| Buat backing | ❌ | ✅ | ❌* | ✅ |
| Daftar backing sendiri | ❌ | ✅ | ✅ | ✅ (semua) |
| Deposito ke dompet | ❌ | ✅ | ✅ | ✅ |
| Tarik dari dompet | ❌ | ✅ | ✅ | ✅ |
| Daftar transaksi | ❌ | ✅ | ✅ | ✅ |
| Kelola pengguna | ❌ | ❌ | ❌ | ✅ |
| Lihat statistik | ❌ | ❌ | ❌ | ✅ |

> \* Creator tidak dapat mendukung kampanyenya sendiri (ditegakkan di `BackingService::ensureCanBack()`)

### Middleware

| Middleware | Alias | Deskripsi |
|-----------|------|-------------|
| `auth:sanctum` | `auth` | Memvalidasi token bearer |
| `role:admin` | `role` | Memeriksa peran pengguna sesuai parameter |
| `verified` | — | Memeriksa `email_verified_at` tidak null |
| `throttle:login` | — | 5 upaya login/menit per email+IP |
| `throttle:register` | — | 3 pendaftaran/menit per IP |
| `throttle:password.request` | — | 5 permintaan password/menit per email+IP |
| `throttle:api` | — | 60 permintaan/menit per pengguna/IP (global) |

---

## 6. Pembatasan Laju

Batasan laju dikonfigurasi di `RouteServiceProvider::configureRateLimiting()`.

| Kelompok Endpoint | Batas | Kunci |
|------------------|-------|-----|
| Register | 3 permintaan / menit | Alamat IP |
| Login | 5 permintaan / menit | email + IP |
| Lupa Password | 5 permintaan / menit | email + IP |
| Reset Password | 5 permintaan / menit | email + IP |
| Semua endpoint API lain | 60 permintaan / menit | ID pengguna atau IP |

### Format Respons 429

```json
{
  "message": "Too many registration attempts. Please try again in 60 seconds."
}
```

### Header Batas Laju

Semua respons termasuk header batas laju standar:
- `X-RateLimit-Limit`: Maksimum permintaan yang diizinkan
- `X-RateLimit-Remaining`: Permintaan yang tersisa dalam jendela saat ini
- `Retry-After`: Detik sampai batas dilimit (hanya pada 429)

---

## 7. Penanganan Error

Semua endpoint API mengembalikan respons JSON. Error mengikuti format Laravel standar.

### Respons Error Standar

```json
{
  "message": "Descriptive error message"
}
```

### Respons Error Validasi (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Kode Error Umum

| Kode | Makna | Kapan Terjadi |
|------|---------|-----------------|
| 401 | Unauthenticated | Token bearer hilang/tidak valid/kadaluarsa |
| 403 | Forbidden | Pengguna tidak memiliki peran yang diperlukan atau email belum diverifikasi |
| 404 | Not Found | Sumber daya (kampanye, pengguna, backing) tidak ada |
| 409 | Conflict | Sumber daya dalam keadaan yang salah (mis., kampanye tidak dapat diedit) |
| 422 | Validation Error | Data input tidak valid |
| 422 | Business Rule Violation | Saldo tidak cukup, pengguna disuspend, dll. |
| 429 | Too Many Requests | Batas laju terlampair |
| 500 | Server Error | Error aplikasi tak terduga |

### Pengendel Pengecualian

Pengendel pengecualian kustom di `app/Exceptions/Handler.php` mengembalikan:
- **401** untuk `AuthenticationException` — `{"message": "Unauthenticated"}`
- **403** untuk `AuthorizationException` — `{"message": "This action is unauthorized"}`
- **404** untuk `ModelNotFoundException` — `{"message": "Resource not found"}`
- **409** untuk `ConflictHttpException` — menggunakan pesan dari pengecualian
- **422** untuk `ValidationException` — format validasi Laravel standar

---

## 8. Ringkasan Endpoint API

### Otentikasi

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| POST | `/api/register` | `throttle:register` | Daftar pengguna baru |
| POST | `/api/login` | `throttle:login` | Login & dapatkan token |
| POST | `/api/logout` | `auth:sanctum` | Logout (cabut token) |
| GET | `/api/me` | `auth:sanctum` | Dapatkan pengguna saat ini |
| POST | `/api/forgot-password` | `throttle:password.request` | Kirim tautan reset |
| POST | `/api/reset-password` | `throttle:password.request` | Reset password |
| POST | `/api/email/resend` | `auth:sanctum` | Kirim kembali email verifikasi |
| GET | `/api/email/verify/notice` | public | Notis verifikasi email (403) |
| GET | `/api/email/verify/{id}/{hash}` | `signed, throttle:6,1` | Verifikasi email |

### Kampanye

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| GET | `/api/campaigns` | public | Daftar kampanye (dengan filter) |
| GET | `/api/campaigns/{slug}` | public | Dapatkan detail kampanye |
| POST | `/api/campaigns` | `auth:sanctum, role:creator, verified` | Buat kampanye |
| PUT | `/api/campaigns/{slug}` | `auth:sanctum, role:creator, verified` | Perbarui kampanye (hanya DRAFT) |
| POST | `/api/campaigns/{slug}/submit-review` | `auth:sanctum, role:creator, verified` | Ajukan untuk tinjauan |
| DELETE | `/api/campaigns/{slug}` | `auth:sanctum, role:creator, verified` | Hapus kampanye (hanya DRAFT) |

### Admin Kampanye

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| PUT | `/api/admin/campaigns/{slug}/approve` | `auth:sanctum, role:admin` | Setujui kampanye (REVIEW → ACTIVE) |
| PUT | `/api/admin/campaigns/{slug}/reject` | `auth:sanctum, role:admin` | Tolak kampanye (REVIEW → DRAFT) |
| PUT | `/api/admin/campaigns/{slug}/force-fail` | `auth:sanctum, role:admin` | Gugurkan paksa kampanye |

### Sumber Daya Kampanye

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| POST | `/api/campaigns/{slug}/tiers` | `auth:sanctum, role:creator, verified` | Buat tier |
| PUT | `/api/campaigns/{slug}/tiers/{tier}` | `auth:sanctum, role:creator, verified` | Perbarui tier |
| DELETE | `/api/campaigns/{slug}/tiers` | `auth:sanctum, role:creator, verified` | Hapus tier (massal) |
| POST | `/api/campaigns/{slug}/images` | `auth:sanctum, role:creator, verified` | Unggah gambar |
| DELETE | `/api/campaigns/{slug}/images` | `auth:sanctum, role:creator, verified` | Hapus gambar (massal) |
| GET | `/api/campaigns/{slug}/updates` | public | Daftar pembaruan kampanye |
| POST | `/api/campaigns/{slug}/updates` | `auth:sanctum, role:creator, verified` | Buat pembaruan |
| PUT | `/api/campaigns/{slug}/updates/{update}` | `auth:sanctum, role:creator, verified` | Perbarui postingan |
| DELETE | `/api/campaigns/{slug}/updates/{update}` | `auth:sanctum, role:creator, verified` | Hapus pembaruan |

### Backing

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| GET | `/api/backings` | `auth:sanctum, verified` | Daftar backing saya |
| GET | `/api/campaigns/{slug}/backings` | `auth:sanctum, verified` | Daftar backer kampanye |
| POST | `/api/campaigns/{slug}/back` | `auth:sanctum, verified` | Buat backing |

### Dompet

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| POST | `/api/wallet/deposit` | `auth:sanctum, verified` | Deposito ke dompet |
| POST | `/api/wallet/withdraw` | `auth:sanctum, verified` | Tarik dari dompet |

### Transaksi

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| GET | `/api/transactions` | `auth:sanctum` | Daftar riwayat transaksi pengguna |

### Admin

| Method | Endpoint | Middleware | Deskripsi |
|--------|----------|------------|-------------|
| GET | `/api/admin/users` | `auth:sanctum, role:admin` | Daftar pengguna |
| GET | `/api/admin/users/{user}` | `auth:sanctum, role:admin` | Dapatkan detail pengguna |
| PUT | `/api/admin/users/{user}/suspend` | `auth:sanctum, role:admin` | Suspend pengguna |
| PUT | `/api/admin/users/{user}/unsuspend` | `auth:sanctum, role:admin` | Unsuspend pengguna |
| GET | `/api/admin/statistics` | `auth:sanctum, role:admin` | Statistik platform |

---

## 9. Sistem Event

Aplikasi menggunakan sistem event Laravel untuk mendekupulkan aksi bisnis penting dari side-effect-nya.

### Gambaran Event

| Event | Dipicu Oleh | Listener | Tujuan |
|-------|----------|-----------|---------|
| `CampaignApproved` | `CampaignService::approve()` | `HandleCampaignApproved` | Notifikasi ke creator + kirim email |
| `CampaignRejected` | `CampaignService::reject()` | `HandleCampaignRejected` | Notifikasi ke creator + kirim email |
| `CampaignFunded` | `BackingService::checkCampaignReachedTarget()` | `HandleCampaignFunded` | Memicu pekerjaan pencairan |
| `BackingCreated` | `BackingService::create()` | `HandleBackingCreated` | Notifikasi ke backer + creator, kirim email |
| `DepositProcessed` | `WalletService::deposit()` | `HandleWalletTransaction::handleDeposit` | Buat notifikasi dalam aplikasi |
| `WithdrawalProcessed` | `WalletService::withdraw()` | `HandleWalletTransaction::handleWithdrawal` | Buat notifikasi dalam aplikasi |
| `UserSuspended` | `UserService::suspend()` | ❌ TIDAK TERTDAFTAR | Saat ini tidak ada listener |
| `UserUnsuspended` | `UserService::unsuspend()` | ❌ TIDAK TERTDAFTAR | Saat ini tidak ada listener |

### Listener Event

Semua listener terdaftar di `app/Providers/EventServiceProvider.php`. Auto-discovery dinonaktifkan (`shouldDiscoverEvents() = false`).

| Listener | Menangani | Aksi |
|----------|---------|--------|
| `HandleCampaignApproved` | `CampaignApproved` | Membuat notifikasi dalam aplikasi `Notification` untuk creator; mengirim email `CampaignApproved` jika diverifikasi |
| `HandleCampaignRejected` | `CampaignRejected` | Membuat notifikasi dalam aplikasi `Notification` untuk creator; mengirim email `CampaignRejected` jika diverifikasi |
| `HandleCampaignFunded` | `CampaignFunded` | Memanggil `DisburseCampaignJob` untuk pemrosesan berbasis antrian |
| `HandleBackingCreated` | `BackingCreated` | Membuat 2 notifikasi dalam aplikasi `Notification` (backer + creator); mengirim email `BackingConfirmation` ke backer jika diverifikasi |
| `HandleWalletTransaction` | `DepositProcessed`, `WithdrawalProcessed` | Membuat notifikasi dalam aplikasi untuk deposit/penarikan |

### Event Yang Tidak Terdaftar

Event `UserSuspended` dan `UserUnsuspended` dipicu tetapi **tidak terdaftar** di `EventServiceProvider`. Tidak ada listener yang akan beraksi untuk event-event ini.

### Keamanan Transaksional Event

Beberapa event menggunakan `DB::afterCommit()` untuk memastikan hanya berjalan setelah transaksi database berhasil:

- `CampaignApproved` — dipicu di `CampaignService::approve()` dalam `DB::transaction()`
- `CampaignRejected` — dipicu di `CampaignService::reject()` dalam `DB::transaction()`
- `BackingCreated` — dipicu di `BackingService::create()` dalam `DB::transaction()`
- `CampaignFunded` — dipicu melalui `DB::afterCommit()` di `BackingService::checkCampaignReachedTarget()`
- `DepositProcessed` — dipicu melalui `DB::afterCommit()` di `WalletService::deposit()`
- `WithdrawalProcessed` — dipicu melalui `DB::afterCommit()` di `WalletService::withdraw()`

> Dengan menggunakan `DB::afterCommit()`, event dipicu setelah transaksi dilakukan, tetapi berjalan **secara sinkron**. Jika worker antrian dikonfigurasi, listener `ShouldQueue` akan berjalan secara asinkron.

---

## 10. Pekerjaan Latar Belakang

Aplikasi menggunakan sistem antrian Laravel untuk tugas yang berjalan lama. Secara default, koneksi antrian adalah `sync` (berjalan segera dalam proses yang sama).

### Pekerjaan

| Job | Koneksi Antrian | Dipicu Oleh | Tujuan |
|-----|-----------------|---------------|--------|
| `DisburseCampaignJob` | `sync` (default) | Listener `HandleCampaignFunded` | Mencairkan dana (95%) ke creator, mengambil 5% biaya platform |
| `RefundBackersJob` | `sync` (default) | Perintah `CheckExpiredCampaigns` | Mengembalikan semua dana backer kampanye yang gagal |

### Implementasi Pekerjaan

Kedua job mengimplementasikan `ShouldQueue` tetapi karena `QUEUE_CONNECTION=sync`, sebenarnya berjalan secara sinkron. Ini berarti:
- Tidak diperlukan proses worker antrian terpisah
- Job berjalan inline selama siklus permintaan
- Jika antrian diubah ke `database` atau `redis`, job akan diantre dan memerlukan worker

### Jadwal Cron

`app/Console/Kernel.php` mendefinisikan jadwal melalui `schedule()`:

| Perintah | Jadwal | Deskripsi |
|---------|----------|-------------|
| `campaign:check-expired` | Harian pukul 00:05 | Memeriksa kampanye yang melewati deadline, success/fail + refund/disburse |
| `campaign:notify-deadline` | Harian pukul 09:00 | Mengirim notifikasi deadline H-3 dan H-1 ke backer |

### Menjalankan Penjadwal

Penjadwal Laravel memerlukan satu entri cron pada server:

```bash
* * * * * cd /path/to/cofund/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Menjalankan Worker Antrian (Jika Koneksi Antrian Diubah)

Jika `QUEUE_CONNECTION` diubah dari `sync` ke `database` atau `redis`:

```bash
# Mulai worker
php artisan queue:work

# Untuk produksi, gunakan supervisor
# /etc/supervisor/conf.d/cofund-worker.conf
[program:cofund-worker]
command=php /path/to/cofund/backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
```

### Tabel Antrian

Driver antrian database menggunakan tabel berikut:
- `jobs` — job yang diantre
- `failed_jobs` — catatan job yang gagal (berbasis UUID)

Jalankan migrasi jika beralih ke antrian database:
```bash
php artisan queue:table
php artisan migrate
```

---

## 11. Modul

Dokumentasi detail tersedia di file-file berikut:

| Modul | File | Deskripsi |
|--------|------|-------------|
| Otentikasi | [`api/auth.md`](api/auth.md) | Register, login, logout, reset password, verifikasi |
| Kampanye | [`api/campaigns.md`](api/campaigns.md) | Buat, edit, daftar, detail, ajukan tinjauan |
| Aksi Admin Kampanye | [`api/admin.md`](api/admin.md) | Setujui, tolak, gugurkan paksa |
| Backing | [`api/backing.md`](api/backing.md) | Buat backing, daftar backing |
| Tier | [`api/tier.md`](api/tier.md) | Buat, perbarui, hapus tier hadiah |
| Gambar Kampanye | [`api/campaign-image.md`](api/campaign-image.md) | Unggah, hapus gambar kampanye |
| Pembaruan Kampanye | [`api/campaign-update.md`](api/campaign-update.md) | Buat, edit, hapus pembaruan kampanye |
| Dompet | [`api/wallet.md`](api/wallet.md) | Deposito, tarik dana |
| Transaksi | [`api/transaction.md`](api/transaction.md) | Daftar riwayat transaksi |
| Admin | [`api/admin.md`](api/admin.md) | Manajemen pengguna, statistik platform |

---

## 12. Masalah yang Diketahui

### 1. Tabel Transaksi Hilang Nilai Enum (Kritis)

Migrasi `transactions` mendefinisikan kolom `type` enum sebagai:
```sql
ENUM('payment', 'refund', 'disbursement', 'platform_fee')
```

Namun, enum `TransactionType` PHP mencakup `deposit` dan `withdrawal`. Di bawah mode strict MySQL, memasukkan transaksi `deposit` atau `withdrawal` akan **gagal**.

**Perbaikan yang Diperlukan:** Tambahkan nilai ini ke database enum:
```sql
ALTER TABLE transactions MODIFY COLUMN type ENUM('payment', 'refund', 'disbursement', 'platform_fee', 'deposit', 'withdrawal');
```

### 2. UserSuspended/UserUnsuspended Tidak Terdaftar (Sedang)

Metode `UserService::suspend()` dan `UserService::unsuspend()` memicu event `UserSuspended` dan `UserUnsuspended`, tetapi event-event ini **tidak terdaftar** di `EventServiceProvider::$listen`. Karena `shouldDiscoverEvents()` mengembalikan `false`, tidak ada listener yang akan beraksi.

**Perbaikan yang Diperlukan:** Daftarkan event dan buat listener yang sesuai (mis., untuk efek kunci akun, notifikasi admin, dll.).

### 3. Config Cofund Tidak Didefinisikan (Sedang)

`Admin\StatisticsController` memanggil `config('cofund.platform_fee', 0.1)` tetapi tidak ada file `config/cofund.php`. Fallback `0.1` (10%) tidak konsisten dengan `0.05` (5%) yang di-hardcode di `TransactionService::disburseCampaign()` dan `DisburseCampaignJob`.

**Perbaikan yang Diperlukan:** Buat `config/cofund.php`:
```php
return ['platform_fee' => 0.05];
```

### 4. Bug Perintah NotifyDeadlineApproaching (Sedang)

Perintah `NotifyDeadlineApproaching` mereferensikan variabel yang tidak terdefinisi `$countH3` dan `$countH1` pada baris 73-74. Ini akan menyebabkan error runtime ketika perintah dijalankan.

**Perbaikan yang Diperlukan:** Definisikan variabel ini atau hapus referensinya.

### 5. .env Di-commit di Repositori (Kritis)

File `.env` (berisi `APP_KEY`, kredensial database, dll.) dilacak di kontrol versi. Ini adalah risiko keamanan.

**Perbaikan yang Diperlukan:** Tambahkan `.env` ke `.gitignore` dan hapus dari kontrol versi.

### 6. URL Frontend untuk Reset Password Tidak Dapat Dikonfigurasi (Rendah)

`AuthServiceProvider::boot()` mengganti `ResetPassword::createUrlUsing()` untuk menghasilkan URL frontend menggunakan `config('app.frontend_url', config('app.url'))`. Jika kunci konfigurasi ini tidak diatur, URL reset akan mengarah ke backend API, bukan aplikasi frontend.

### 7. Email Tidak Di-antrikan (Sedang)

Semua email dikirim **secara sinkron** selama siklus permintaan:

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

### 8. Indeks FULLTEXT Tidak Digunakan (Rendah)

Indeks FULLTEXT ditambahkan ke tabel `campaigns` untuk `title` dan `description`, tetapi pencarian di `CampaignController::index()` menggunakan kueri `LIKE` sebaliknya `MATCH...AGAINST`. Indeks ini tidak dimanfaatkan.

**Perbaikan (Opsional):** Refaktor pencarian untuk menggunakan `MATCH...AGAINST` atau hapus indeks yang tidak digunakan.

### 9. Bug Atomisitas Penghapusan Gambar Kampanye (Rendah)

Di `CampaignImageService::deleteMany()`:
1. Berkas fisik dihapus dari penyimpanan
2. Kemudian sistem memeriksa apakah ada gambar yang tersisa
3. Jika hanya 1 gambar tersisa, `ValidationException` dilempar

Ini berarti berkas dihapus tetapi catatan database tetap ada (soft delete tidak dilakukan) karena pengecualian dilempar sebelum `$image->delete()` dijalankan untuk semua item.

**Perbaikan (Disarankan):** Bungkus dalam transaksi DB dan validasi jumlah **sebelum** menghapus berkas fisik apa pun.

### 10. Middleware Sanctum Stateful Dinonaktifkan (Rendah)

Middleware `EnsureFrontendRequestsAreStateful` dikomentar di grup `api` di `routes/api.php`. Ini berarti otentikasi berbasis sesi cookie untuk SPA **tidak aktif** — hanya token bearer yang bekerja.

**Perbaikan (Opsional):** Hapus komentar middleware jika membangun frontend SPA berbasis cookie.

### 11. Tidak Ada Versi API (Rendah)

Semua rute berada di bawah `/api` tanpa prefix versi (mis., `/api/v1`). Ini membuat perubahan breaking yang akan datang lebih sulit dikelola.

### 12. Tidak Ada Pembatasan Laju pada Endpoint Sensitif

Meskipun endpoint otentikasi memiliki pembatasan laju, deposit/penarikan dompet dan pembuatan kampanye tidak memiliki limiter khusus di luar `throttle:api` global 60 req/menit.

### 13. Tidak Ada Validasi Input untuk `per_page`

Paginasi mengizinkan nilai `per_page` arbitrer (mis., `per_page=99999`), yang dapat menyebabkan masalah memori. Pertimbangkan untuk memberlakukan batas maksimum.

### 14. Tidak Ada Batas Paginasi pada Kampanye Teratas Statistik (Rendah)

`StatisticsController::index()` selalu mengembalikan 5 kampanye teratas tanpa batas yang dapat dikonfigurasi.

### 15. Transisi Status Kampanye Tidak Ditegakkan (Rendah)

Tidak ada batasan mesin status yang memaksa transisi status yang valid. Mis., admin dapat menyetujui kampanye yang dalam status FAILED (status akan berubah menjadi ACTIVE).

---

## 13. Pengaturan Postman

### Variabel Lingkungan

Buat lingkungan Postman dengan variabel berikut:

| Variabel | Nilai | Deskripsi |
|----------|-------|-------------|
| `base_url` | `http://localhost:8000/api` | URL dasar API |
| `admin_email` | `admin@example.com` | Email pengguna admin (dari seeder) |
| `admin_password` | `password` | Password pengguna admin |
| `creator_email` | `creator1@example.com` | Email pengguna creator |
| `creator_password` | `password` | Password pengguna creator |
| `backer_email` | `backer@example.com` | Email pengguna backer |
| `backer_password` | `password` | Password pengguna backer |
| `admin_token` | *(disimpan otomatis)* | Disimpan setelah login |
| `creator_token` | *(disimpan otomatis)* | Disimpan setelah login |
| `backer_token` | *(disimpan otomatis)* | Disimpan setelah login |

### Alur Otentikasi (Postman)

1. **Login sebagai Admin:**
   - `POST {{base_url}}/login`
   - Body: `{ "email": "{{admin_email}}", "password": "{{admin_password}}" }`
   - Di tab "Tests", tambahkan:
     ```js
     pm.environment.set("admin_token", pm.response.json().token)
     ```

2. **Login sebagai Creator:**
   - `POST {{base_url}}/login`
   - Body: `{ "email": "{{creator_email}}", "password": "{{creator_password}}" }`
   - Di tab "Tests", tambahkan:
     ```js
     pm.environment.set("creator_token", pm.response.json().token)
     ```

3. **Login sebagai Backer:**
   - Sama seperti di atas, tetapi dengan kredensial backer dan variabel `backer_token`.

4. **Gunakan token** pada permintaan berikutnya:
   - Header: `Authorization: Bearer {{admin_token}}`

### Koleksi Permintaan

Ekspor koleksi Postman lengkap tersedia di: `docs/CoFund-API.postman_collection.json`

---

## 14. Perintah Pengembangan

### Menjalankan Migrasi

```bash
# Jalankan semua migrasi
php artisan migrate

# Rollback batch terakhir
php artisan migrate:rollback

# Reset semua migrasi
php artisan migrate:reset

# Refresh (rollback + migrate)
php artisan migrate:refresh

# Seed setelah migrasi
php artisan db:seed
```

### Menjalankan Seeder

```bash
# Jalankan semua seeder
php artisan db:seed

# Jalankan seeder tertentu
php artisan db:seed --class=CategorySeeder
```

### Perintah Artisan

| Perintah | Deskripsi |
|---------|-------------|
| `php artisan serve` | Jalankan server dev |
| `php artisan tinker` | REPL interaktif |
| `php artisan migrate` | Jalankan migrasi |
| `php artisan db:seed` | Jalankan seeder |
| `php artisan queue:work` | Mulai worker antrian |
| `php artisan schedule:run` | Jalankan tugas yang dijadwalkan |
| `php artisan route:list` | Daftar semua rute |
| `php artisan test` | Jalankan PHPUnit tests |

### Perintah Konsol (Khusus)

| Perintah | Deskripsi |
|---------|-------------|
| `php artisan campaign:check-expired` | Periksa dan proses kampanye yang kadaluarsa |
| `php artisan campaign:notify-deadline` | Kirim notifikasi deadline yang mendekat |

### Pengujian

```bash
# Jalankan semua tes
php artisan test

# Jalankan tes tertentu
php artisan test --filter=ExampleTest

# Jalankan dengan cakupan
php artisan test --coverage
```

### Debugging

```bash
# Bersihkan cache konfigurasi
php artisan config:clear

# Bersihkan cache rute
php artisan route:clear

# Bersihkan semua cache
php artisan optimize:clear

# Tampilkan kueri SQL terakhir
php artisan tinker
>>> DB::enableQueryLog();
>>> // ... picu aksi ...
>>> dd(DB::getQueryLog());
```

### Symlink Penyimpanan

Pastikan berkas penyimpanan dapat diakses:

```bash
php artisan storage:link
```

### Debugging Penjadwal

```bash
# Periksa jadwal
php artisan schedule:list

# Jalankan perintah tertentu pada jadwal
php artisan campaign:check-expired

# Simulasikan tanggal tertentu untuk pengujian
php artisan schedule:run --date="2026-08-31 00:05:00"
```

---

## Konvensi Respons API

### Respons Sukses

Respons sukses standar menggunakan konvensi kode status HTTP:

- `200 OK` — Untuk GET, PUT, DELETE yang berhasil
- `201 Created` — Untuk POST yang berhasil
- `204 No Content` — (saat ini tidak digunakan)

### Paginasi

Semua endpoint yang mencantumkan mengembalikan respons terpaginasi dengan struktur berikut:

```json
{
  "data": [...],
  "links": {
    "first": "http://localhost/api/endpoint?page=1",
    "last": "http://localhost/api/endpoint?page=10",
    "prev": null,
    "next": "http://localhost/api/endpoint?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "http://localhost/api/endpoint",
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

### Timestamp

Semua timestamp dikembalikan dalam format ISO 8601 (UTC):
```
2026-08-26T10:00:00.000000Z
```

### Field Desimal

Semua nilai moneter dikembalikan sebagai string dengan 2 desimal:
```json
"amount": "100000.00"
```

Ini mencegah masalah presisi floating-point di sisi klien.
