# CoFund API

> **Enterprise-Grade Crowdfunding API**  
> Dokumentasi resmi API CoFund — berbasis Laravel 10 & PHP 8.1+

> **Status kontrak:** Revisi ini mengikuti route, migration, FormRequest, service, seeder, dan Postman collection yang ada di repository per 2026-08-28. Dokumentasi ini tidak mengklaim fitur yang belum tersedia.

## Ringkasan Evaluasi Arsitektur

| Area | Status | Catatan |
|---|---|---|
| REST method | Sesuai | `GET` untuk read, `POST` untuk create/action, `PUT` untuk update/action, `DELETE` untuk delete/soft-delete. |
| Prefix route | Sesuai setelah revisi | API berada di `/api/v1`; route verifikasi berada di `/api/email/...`, bukan root `/email/...`. |
| RBAC | Sesuai dengan middleware saat ini | Creator/admin dibatasi `role:*`; backing/wallet/statistics backer memakai `auth:sanctum, verified`. |
| Validation | Sebagian sesuai | Campaign create wajib multipart `images[]` dan minimal satu `tiers[]`; field file kosong akan menghasilkan 422. |
| Queue/mail | Bersyarat | Job memakai Redis; email memakai SMTP Mailpit. Redis worker dan Mailpit harus aktif agar side effect tidak gagal. |
| Payment webhook | Belum tersedia | Tidak ada route/controller/signature verification webhook; payment saat ini memakai reference `mock_payment_*`. |
| Postman coverage | Parsial | Collection aktif berisi 64 request, sedangkan matriks modul mendokumentasikan lebih banyak skenario. Jangan menyebutnya full 100% coverage. |

### Temuan Prioritas

| Endpoint/komponen | Temuan | Perbaikan atau keputusan |
|---|---|---|
| `POST /api/v1/register`, `POST /api/v1/login` | Dedicated rate limit sudah dihapus. | Jangan mendokumentasikan `throttle:register` atau `throttle:login`; rate limit global API tetap berlaku. |
| `POST /api/v1/campaigns` | Wajib `multipart/form-data`, `images[]` file valid, dan `tiers[]`. | Raw JSON atau file kosong memang harus menghasilkan 422. |
| Campaign CRUD tier/image/delete | Hanya campaign `draft` yang editable. | Gunakan fixture `qa-draft-campaign` pada testing; jangan memakai campaign yang sudah active/review. |
| Campaign updates | Create hanya menerima campaign `active`. | Gunakan `bantu-anak-pedalaman-tepukan` untuk positive create update. |
| Wallet withdrawal | Saldo kurang dikembalikan 422 dengan error field `amount`. | Dokumentasi tidak boleh menyebut 409 untuk kondisi ini. |
| Email verification | Route aktual `/api/email/verify/notice` dan `/api/email/verify/{id}/{hash}`. | Sesuaikan URL Postman dan contoh dokumentasi. |
| Payment | Belum ada gateway/webhook nyata. | Tandai mock payment sebagai test-only sampai webhook ditambahkan. |

### Kontrak Response

Success response memakai `success: true`, `message` bila relevan, dan `data` untuk resource tunggal atau array resource. Error API memakai `success: false` dan `message`; validation error menambahkan `errors` berupa array pesan per field. Status yang benar-benar dipakai implementasi adalah 400 (email sudah verified), 401 (unauthenticated), 403 (role/email/suspended), 404 (implicit model binding), 409 (business conflict tertentu), 422 (validation/business validation), dan 500 (unexpected infrastructure/application error).

---

## 1. Pendahuluan

### Deskripsi

CoFund API adalah RESTful API berbasis Laravel 10 yang mendukung platform crowdfunding berbasis web. API ini menyediakan seluruh functionalitas untuk otentikasi pengguna, manajemen kampanye, dukungan (backing), dompet digital, transaksi, notifikasi, dan administrasi platform.

### Base URL

```
http://localhost:8000/api/v1
```

Email verifikasi route (di luar prefix v1 tetapi tetap berada di bawah prefix aplikasi `/api`):

```
http://localhost:8000/api/email/verify/{id}/{hash}
http://localhost:8000/api/email/verify/notice
```

### Protokol & Format

| Properti | Nilai |
|---|---|
| Protokol | HTTPS (direkomendasikan untuk produksi) |
| Format Request | JSON / Multipart Form Data |
| Format Response | JSON |
| Encoding | UTF-8 |
| Authentication | Bearer Token (Sanctum) |

### Otentikasi Bearer Token

Semua endpoint yang dilindungi membutuhkan header `Authorization` dengan format bearer token:

```
Authorization: Bearer {your-sanctum-token}
```

Token diperoleh melalui endpoint `POST /api/v1/login`.

---

## 2. Ikhtisar Arsitektur

### Diagram Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (React/Vite)                    │
│                                                                 │
│  - User Interface                                               │
│  - State Management (Context/Zustand)                           │
│  - API Client (Axios/Fetch)                                     │
└───────────────┬─────────────────────────────────────────────────┘
                │  HTTPS (Bearer Token)
                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    CoFund API (Laravel 10)                       │
│                                                                  │
│  ┌────────────────┐  ┌─────────────────┐  ┌─────────────────┐  │
│  │  Controller     │  │   Form Request   │  │   Resource      │  │
│  │  (API)          │  │  (Validation)    │  │  (JSON Output)  │  │
│  └────────┬────────┘  └────────┬─────────┘  └────────┬────────┘  │
│           │                    │                       │           │
│           ▼                    ▼                       ▼           │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │                 Service Layer                               │ │
│  │  Auth | Campaign | Backing | Wallet | Tier | Update | User   │ │
│  └──────────────────────┬──────────────────────────────────────┘ │
│                         │                                         │
│           +─────────────┼─────────────+                           │
│           │             │             │                           │
│           ▼             ▼             ▼                           │
│     ┌────────┐    ┌────────┐   ┌──────────┐                      │
│     │ Events │    │Listeners│  │   Models  │                      │
│     └────┬───┘    └────┬────┘  └──────────┘                      │
│          │            │                                          │
│          ▼            ▼                                          │
│     ┌──────────────────────────────────┐                         │
│     │       Queue Workers (Redis/DB)   │                         │
│     │  DisburseCampaignJob            │                         │
│     │  RefundBackersJob               │                         │
│     │  NotifyBackersJob               │                         │
│     └──────────────────────────────────┘                         │
└─────────────────────────────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────────────────────┐
│  Database (MySQL 8)     &    Storage (public/campaigns/)      │
│                                                                 │
│  - users, campaigns, backings,                                   │
│    transactions, tiers, images,                                   │
│    notifications, categories                                    │
└─────────────────────────────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────────────────────┐
│       Email Service (Mailpit)                                    │
│       Notifikasi & Verifikasi Email                              │
└─────────────────────────────────────────────────────────────────┘
```

### Tabel Pola Desain

| Pattern | Implementasi | File/Path |
|---|---|---|
| **Service Layer** | Logika bisnis terpisah dari controller | `app/Services/*.php` |
| **Form Request Validation** | Validasi request terpusat | `app/Http/Requests/*.php` |
| **Resource Transformers** | Format output JSON konsisten | `app/Http/Resources/*.php` |
| **Event-Driven** | Decoupling via events/listeners | `app/Events/`, `app/Listeners/` |
| **Repository Pattern (Eloquent)** | Query builder berbasis model | `app/Models/*.php` |
| **Middleware Pipeline** | Otentikasi & otorisasi | `app/Http/Middleware/` |

### Technology Stack

| Layer | Teknologi | Versi |
|---|---|---|
| **Backend Framework** | Laravel | 10.x |
| **Language** | PHP | ^8.1 |
| **Database** | MySQL | 8.x |
| **Authentication** | Laravel Sanctum | ^3.2 |
| **Queue Driver** | Database / Redis | (configurable) |
| **Mail Service** | Mailpit | (SMTP localhost:1025) |
| **Markdown Parser** | erusev/parsedown | ^1.8 |
| **Testing** | PHPUnit / Pint | ^10.0 / ^1.0 |

---

## 3. Memulai (Getting Started)

### Prasyarat Sistem

| Komponen | Versi Minimum |
|---|---|
| PHP | 8.1 |
| Composer | 2.x |
| MySQL | 8.0 |
| Redis (pilihan, untuk queue) | 7.x |
| Mailpit (untuk testing email) | latest |

### Langkah Instalasi

1. **Clone repository**

```bash
git clone {repo-url} cofund-backend
cd cofund-backend/backend
```

2. **Install dependencies**

```bash
composer install
```

3. **Copy environment file**

```bash
cp .env.example .env
```

4. **Generate application key**

```bash
php artisan key:generate
```

5. **Konfigurasi database**

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coFund
DB_USERNAME=root
DB_PASSWORD=
```

6. **Jalankan migrasi & seeder**

```bash
php artisan migrate --seed
```

7. **Storage link**

```bash
php artisan storage:link
```

8. **Start development server**

```bash
php artisan serve
```

### Konfigurasi Environment (.env)

```env
APP_NAME=CoFund
APP_ENV=local
APP_KEY=base64:{generated-key}
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coFund
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
SESSION_DOMAIN=localhost

PLATFORM_FEE_RATE=0.05
```

---

## 4. Otentikasi & Keamanan

### Sanctum Bearer Token

CoFund API menggunakan **Laravel Sanctum** untuk otentikasi berbasis token. Setiap token valid:

- Disimpan di tabel `personal_access_tokens`
- Dapat dicabut melalui endpoint `/api/v1/logout`
- Memiliki lifespan tak terbatas (hingga dicabut)

### Token Lifecycle

```
1. User login (POST /api/v1/login)
       |
       v
2. Server: user->createToken("auth-token")
       |
       v
3. Return plainTextToken ke client
       |
       v
4. Client menyimpan token (localStorage/cookies)
       |
       v
5. Semua request API: Authorization: Bearer {token}
       |
       v
6. Server: auth:sanctum middleware memverifikasi token
       |
       v
7. User dapat melakukan request terautentikasi
       |
       v
8. Logout (POST /api/v1/logout) -> token dihapus
```

### Scope

Saat ini, API tidak menggunakan scope khusus. Semua token yang valid memberikan akses penuh berdasarkan role pengguna.

### Verifikasi Email

CoFund API mewajibkan verifikasi email untuk:

- Membuat backing
- Deposit/withdrawal dompet
- Menerima notifikasi email

Middleware `verified` dilindungi pada route yang relevan.

### Alur Reset Password

1. **Request Reset Link:** `POST /api/v1/forgot-password` — Mengirimkan email dengan token reset
2. **Reset Password:** `POST /api/v1/reset-password` — Memasukkan token, email, dan password baru
3. Laravel Password Broker otomatis memverifikasi token dan memperbarui password
4. Event `PasswordReset` dipicu setelah berhasil

---

## 5. Peran Pengguna & Matriks Akses Global

### Definisi Peran

| Role | Deskripsi | Warna |
|---|---|---|
| `backer` | Pengguna yang mendukung kampanye dengan dana | Biru |
| `creator` | Pengguna yang membuat dan mengelola kampanye | Oranye |
| `admin` | Pengguna dengan hak penuh mengelola platform | Merah |

### Matriks Otorisasi Fitur

| Fitur | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| **Otentikasi** | ✓ | ✓ | ✓ | ✓ |
| Register | ✓ | ✓ | ✓ | ✓ |
| Login | ✓ | ✓ | ✓ | ✓ |
| Logout | ✓ | ✓ | ✓ | ✓ |
| Forgot/Reset Password | ✓ | ✓ | ✓ | ✓ |
| **Kampanye** | | | | |
| List kampanye publik | ✓ | ✓ | ✓ | ✓ |
| Detail kampanye | ✓ | ✓ | ✓ | ✓ |
| Buat kampanye | - | - | ✓ | - |
| Update/ Delete kampanye (draft) | - | - | ✓ | - |
| Submit untuk review | - | - | ✓ | - |
| Approve kampanye | - | - | - | ✓ |
| Reject kampanye | - | - | - | ✓ |
| Force-fail kampanye | - | - | - | ✓ |
| **Tier & Gambar** | | | | |
| CRUD tier & gambar | - | - | ✓ | - |
| **Backing & Dompet** | | | | |
| Backing kampanye | - | ✓ | ✗* | ✓ |
| List backing sendiri | - | ✓ | ✓ | ✓ |
| Deposit dompet | - | ✓ | ✓ | ✓ |
| Withdraw dompet | - | ✓ | ✓ | ✓ |
| Lihat transaksi | - | ✓ | ✓ | ✓ (semua) |
| **Update Kampanye** | | | | |
| Post update | - | - | ✓ | - |
| **Statistik** | | | | |
| Creator statistik | - | - | ✓ | - |
| Backer statistik | - | ✓ | ✓* | ✓* |
| Admin statistik | - | - | - | ✓ |
| **Admin Panel** | | | | |
| Kelola user | - | - | - | ✓ |
| Suspend/unsuspend user | - | - | - | ✓ |

> * Creator tidak dapat backing kampanye sendiri (dikaiti di `BackingService`)

---

## 6. Pembatasan Laju (Rate Limiting)

### Tabel Batasan Rate Limit

| Endpoint | Middleware | Limit | Reset | Dibatasi Berdasarkan |
|---|---|---|---|---|
| Forgot Password | `throttle:password.request` | 5/menit | 60 detik | email + IP |
| Reset Password | `throttle:password.request` | 5/menit | 60 detik | email + IP |
| Semua API lain | `throttle:api` | 60/menit | 60 detik | user_id atau IP |
| Email Verify | `throttle:60,1` | 60/menit | 1 menit | IP |
| Global | `api` group | 60/menit | 60 detik | user_id atau IP |

### Format Respons HTTP 429

```json
{
    "success": false,
    "message": "Too many login attempts. Please try again in 1 minute."
}
```

Header tambahan:

```
Retry-After: 32
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0
```

---

## 7. Dynamic Pagination

### Standar Pagination API

Semua endpoint yang mengembalikan koleksi data (list/collection) menggunakan **Dynamic Pagination** dengan mekanisme berikut:

1. **Parameter `per_page`** — Dari query string (misal: `GET /api/v1/campaigns?per_page=15`)
2. **Default:** `10` — Jika tidak dikirim, gunakan 10 item per halaman
3. **Safety Limit:** Maksimal `50` — Diterapkan via `min(max($perPage, 1), 50)` untuk mencegah beban query berlebih
4. **Query Preservation:** Semua parameter query (seperti `search`, `category`, `sort`, `type`, `status`) otomatis dipertahankan di pagination links melalui `->appends($request->query())`

### Format Respons Pagination

```json
{
    "success": true,
    "data": [...],
    "meta": {
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 10,
            "total": 45
        }
    }
}
```

### Endpoint dengan Pagination

| Endpoint | Default Sebelumnya | Sekarang |
|---|---|---|
| `GET /api/v1/campaigns` | 12 | 10 (per_page param, max 50) |
| `GET /api/v1/backings` | 12 | 10 (per_page param, max 50) |
| `GET /api/v1/campaigns/{slug}/backings` | 12 | 10 (per_page param, max 50) |
| `GET /api/v1/campaigns/{slug}/updates` | 10 | 10 (per_page param, max 50) |
| `GET /api/v1/transactions` | 12 | 10 (per_page param, max 50) |
| `GET /api/v1/admin/users` | 15 | 10 (per_page param, max 50) |

### Contoh Request

```
GET /api/v1/campaigns?page=2&per_page=25&category=teknologi&sort=popular
Authorization: Bearer {token}
```

Response akan tetap mempertahankan parameter `category=teknologi` dan `sort=popular` di semua link pagination.

---

## 8. Penanganan Error (Standardized Error Handling)

### Format JSON Error Standar

Semua error pada route API mengembalikan struktur JSON yang konsisten:

```json
{
    "success": false,
    "message": "Deskripsi singkat error",
    "errors": {
        "field_name": ["Error detail"]
    }
}
```

### Tabel Kode Error

| Kode HTTP | Nama | Deskripsi | Format Respons |
|---|---|---|---|
| **401** | Unauthenticated | Token tidak valid, tidak ada, atau kadaluarsa | `{"success":false,"message":"Unauthenticated."}` |
| **403** | Forbidden | Pengguna tidak memiliki hak akses | `{"success":false,"message":"This action is unauthorized."}` atau role-specific message |
| **404** | Not Found | Resource tidak ditemukan | `{"message":"No query results for model [App\\Models\\Campaign] .id = ..."}` |
| **409** | Conflict | Konflik status bisnis | `{"success":false,"message":"Campaign can only be edited in draft status"}` |
| **422** | Validation Error | Validasi gagal | `{"success":false,"message":"The given data was invalid.","errors":{...}}` |
| **429** | Too Many Requests | Rate limit password reset atau verifikasi terlampaui | `{"success":false,"message":"Too many ... requests."}` |
| **500** | Server Error | Kesalahan server tak terduga | `{"success":false,"message":"Server Error"}` |

### Handler Khusus (`app/Exceptions/Handler.php`)

Handler khusul ini memastikan semua exception pada route API mengembalikan response JSON yang konsisten, bukan redirect ke halaman web.

---

## 9. Ringkasan Seluruh Endpoint API

### Endpoint Master

| Method | Endpoint | Middleware | Deskripsi | Module |
|---|---|---|---|---|
| POST | `/api/v1/register` | *(none)* | Registrasi pengguna baru | Auth |
| POST | `/api/v1/login` | *(none)* | Login dan dapatkan token | Auth |
| POST | `/api/v1/logout` | `auth:sanctum` | Cabut token | Auth |
| GET | `/api/v1/me` | `auth:sanctum` | Dapatkan profil pengguna | Auth |
| POST | `/api/v1/email/resend` | `auth:sanctum` | Kirim ulang verifikasi email | Auth |
| POST | `/api/v1/forgot-password` | `throttle:password.request` | Kirim link reset password | Auth |
| POST | `/api/v1/reset-password` | `throttle:password.request` | Reset password dengan token | Auth |
| GET | `/api/email/verify/notice` | - | Notifikasi verifikasi | Auth* |
| GET | `/api/email/verify/{id}/{hash}` | `signed`,`throttle:60,1` | Verifikasi email | Auth* |
| GET | `/api/v1/campaigns` | *(opsional)* | List kampanye | Campaigns |
| GET | `/api/v1/campaigns/{slug}` | - | Detail kampanye | Campaigns |
| POST | `/api/v1/campaigns` | `auth:sanctum`, `role:creator`, `verified` | Buat kampanye | Campaigns |
| PUT | `/api/v1/campaigns/{slug}` | `auth:sanctum`, `role:creator`, `verified` | Update kampanye | Campaigns |
| POST | `/api/v1/campaigns/{slug}/submit-review` | `auth:sanctum`, `role:creator`, `verified` | Submit ke review | Campaigns |
| DELETE | `/api/v1/campaigns/{slug}` | `auth:sanctum`, `role:creator`, `verified` | Hapus kampanye | Campaigns |
| PUT | `/api/v1/admin/campaigns/{slug}/approve` | `auth:sanctum`, `role:admin` | Setujui kampanye | Campaigns |
| PUT | `/api/v1/admin/campaigns/{slug}/reject` | `auth:sanctum`, `role:admin` | Tolak kampanye | Campaigns |
| PUT | `/api/v1/admin/campaigns/{slug}/force-fail` | `auth:sanctum`, `role:admin` | Gagalkan paksa | Campaigns |
| POST | `/api/v1/campaigns/{slug}/tiers` | `auth:sanctum`, `role:creator`, `verified` | Buat tier | Tier |
| PUT | `/api/v1/campaigns/{slug}/tiers/{tier}` | `auth:sanctum`, `role:creator`, `verified` | Update tier | Tier |
| DELETE | `/api/v1/campaigns/{slug}/tiers` | `auth:sanctum`, `role:creator`, `verified` | Hapus banyak tier | Tier |
| POST | `/api/v1/campaigns/{slug}/images` | `auth:sanctum`, `role:creator`, `verified` | Upload gambar | Image |
| DELETE | `/api/v1/campaigns/{slug}/images` | `auth:sanctum`, `role:creator`, `verified` | Hapus banyak gambar | Image |
| POST | `/api/v1/campaigns/{slug}/updates` | `auth:sanctum`, `role:creator`, `verified` | Post update | Update |
| PUT | `/api/v1/campaigns/{slug}/updates/{update}` | `auth:sanctum`, `role:creator`, `verified` | Update post | Update |
| DELETE | `/api/v1/campaigns/{slug}/updates/{update}` | `auth:sanctum`, `role:creator`, `verified` | Hapus update | Update |
| GET | `/api/v1/campaigns/{slug}/updates` | *(opsional)* | List update kampanye | Update |
| POST | `/api/v1/campaigns/{slug}/back` | `auth:sanctum`, `verified` | Buat backing | Backing |
| GET | `/api/v1/backings` | `auth:sanctum`, `verified` | List backing pribadi | Backing |
| GET | `/api/v1/campaigns/{slug}/backings` | `auth:sanctum`, `verified` | List backing per kampanye | Backing |
| POST | `/api/v1/wallet/deposit` | `auth:sanctum`, `verified` | Deposit dana | Wallet |
| POST | `/api/v1/wallet/withdraw` | `auth:sanctum`, `verified` | Tarik dana | Wallet |
| GET | `/api/v1/transactions` | `auth:sanctum`, `verified` | List transaksi | Transaction |
| GET | `/api/v1/admin/users` | `auth:sanctum`, `role:admin` | List pengguna | Admin |
| GET | `/api/v1/admin/users/{user}` | `auth:sanctum`, `role:admin` | Detail pengguna | Admin |
| PUT | `/api/v1/admin/users/{user}/suspend` | `auth:sanctum`, `role:admin` | Suspend pengguna | Admin |
| PUT | `/api/v1/admin/users/{user}/unsuspend` | `auth:sanctum`, `role:admin` | Unsuspend pengguna | Admin |
| GET | `/api/v1/admin/statistics` | `auth:sanctum`, `role:admin` | Statistik platform | Admin |
| GET | `/api/v1/creator/statistics` | `auth:sanctum`, `role:creator`, `verified` | Statistik kreator | Creator |
| GET | `/api/v1/backer/statistics` | `auth:sanctum`, `verified` | Statistik backer | Backer |

> *Catatan:* Route verifikasi email berada di luar prefix `/api/v1`, tetapi `routes/api.php` tetap dimuat di bawah prefix aplikasi `/api`; path aktualnya `/api/email/...`.

---

## 10. Sistem Event & Listener

### Tabel Pemetaan Event ↔ Listener

| Event | Listener | Aksi | Async? |
|---|---|---|---|
| `Illuminate\Auth\Events\Registered` | `SendEmailVerificationNotification` | Kirim email verifikasi saat registrasi | Ya |
| `Illuminate\Auth\Events\PasswordReset` | (Laravel built-in) | Kirim email notifikasi reset password | Ya |
| `App\Events\CampaignApproved` | `HandleCampaignApproved` | Notifikasi in-app + email ke creator | Ya |
| `App\Events\CampaignRejected` | `HandleCampaignRejected` | Notifikasi in-app + email ke creator | Ya |
| `App\Events\CampaignFunded` | `HandleCampaignFunded` | Dispatch `DisburseCampaignJob` | Ya (via queue) |
| `App\Events\BackingCreated` | `HandleBackingCreated` | Notifikasi ke backer & creator + email | Ya |
| `App\Events\DepositProcessed` | `HandleWalletTransaction::handleDeposit` | Notifikasi in-app deposit | Ya |
| `App\Events\WithdrawalProcessed` | `HandleWalletTransaction::handleWithdrawal` | Notifikasi in-app withdrawal | Ya |
| `App\Events\UserSuspended` | `HandleUserSuspended` | Notifikasi + email akun disuspend | Ya |
| `App\Events\UserUnsuspended` | `HandleUserUnsuspended` | Notifikasi + email akun diaktifkan | Ya |
| `Illuminate\Auth\Events\Verified` | (Laravel built-in) | (Jika terdaftar) | - |

### Keamanan Transaksional Event

CoFund API menggunakan `DB::afterCommit()` untuk memastikan event hanya dipicu setelah transaksi database berhasil:

```php
DB::transaction(function () {
    // ... operasi database ...
    
    DB::afterCommit(fn () => event(new CampaignFunded($campaign)));
});
```

Penggunaan `DB::afterCommit()` terdapat pada:
- `BackingService::create()` — event `CampaignFunded`
- `WalletService::deposit()` — event `DepositProcessed`
- `WalletService::withdraw()` — event `WithdrawalProcessed`

---

## 11. Pekerjaan Latar Belakang (Queue & Cron Jobs)

### Queue Workers

Konfigurasi lokal yang direkomendasikan adalah `QUEUE_CONNECTION=redis` dengan `REDIS_CLIENT=predis`. Jalankan Redis dan worker sebelum menguji fitur yang mengirim job. Mail dikirim melalui SMTP Mailpit (`127.0.0.1:1025`); bila Mailpit tidak aktif, operasi yang mengirim notifikasi email dapat mengembalikan HTTP 500.

#### DisburseCampaignJob

| Properti | Nilai |
|---|---|
| **Deskripsi** | Mencairkan dana kampanye yang berhasil (status `success`) |
| **Trigger** | Event `CampaignFunded` |
| **Logika** | 1. Ambil 95% collected_amount<br>2. Deposit ke saldo creator<br>3. Buat transaksi `disbursement`<br>4. Buat transaksi `platform_fee` (5%)<br>5. Notifikasi + email ke creator |
| **Dependencies** | `TransactionService` |

#### RefundBackersJob

| Properti | Nilai |
|---|---|
| **Deskripsi** | Refund semua backer kampanye yang gagal |
| **Trigger** | Endpoint `forceFail` atau command `campaign:check-expired` |
| **Logika** | 1. Ambil backing yang belum refunded<br>2. Deposit kembali ke saldo backer<br>3. Buat transaksi `refund`<br>4. Update backing status ke `refunded`<br>5. Notifikasi + email ke backer |
| **Dependencies** | `TransactionService` |

#### NotifyBackersJob

| Properti | Nilai |
|---|---|
| **Deskripsi** | Memberi tahu semua backer tentang update baru di kampanyenya |
| **Trigger** | `CampaignUpdateService::create()` |
| **Logika** | 1. Bulk insert notifikasi ke semua backer<br>2. Kirim email ke backer yang terverifikasi |

### Cron Jobs (Scheduler)

Dikonfigurasi pada `app/Console/Kernel.php`:

| Command | Jadwal | Deskripsi |
|---|---|---|
| `campaign:check-expired` | Daily pukul 00:05 | Memeriksa kampanye yang deadline-nya lewat, menandainya sebagai `success` atau `failed`, dan memicu disbursement/refund |
| `campaign:notify-deadline` | Daily pukul 09:00 | Mengirim notifikasi pengingat deadline ke backer H-3 dan H-1 |

### Panduan Supervisor & Worker Antrian

1. **Setup queue worker:**

```bash
php artisan queue:work --tries=3
```

2. **Setup supervisor (Linux):**

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/cofund/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/laravel-worker.log
```

3. **Cek status queue:**

```bash
php artisan queue:work --status
```

---

## 12. Navigasi Modul

| Modul | File Dokumentasi | Fokus |
|---|---|---|
| **Otentikasi** | [`api/auth.md`](api/auth.md) | Register, login, logout, password, verifikasi email |
| **Kampanye** | [`api/campaigns.md`](api/campaigns.md) | CRUD kampanye, approve/reject, force-fail |
| **Backing** | [`api/backing.md`](api/backing.md) | Dukung kampanye, list backing |
| **Dompet** | [`api/wallet.md`](api/wallet.md) | Deposit, withdrawal, transaksi |
| **Tier** | [`api/tier.md`](api/tier.md) | Kelola reward tier |
| **Gambar** | [`api/campaign-image.md`](api/campaign-image.md) | Upload/hapus gambar kampanye |
| **Update** | [`api/campaign-update.md`](api/campaign-update.md) | Post/update/delete kampanye |
| **Transaksi** | [`api/transaction.md`](api/transaction.md) | Riwayat transaksi |
| **Creator** | [`api/creator.md`](api/creator.md) | Statistik kreator |
| **Backer** | [`api/backer.md`](api/backer.md) | Statistik backer |
| **Admin** | [`api/admin.md`](api/admin.md) | User management, platform statistik |

---

## 13. Masalah yang Diketahui & Defisit Arsitektur

| No | Masalah | Tingkat | Solusi |
|---|---|---|---|
| 1 | **Reference payment hardcoded `mock_payment_`** | Minor | Perlu integrasi payment gateway |
| 2 | **Backup/restore database** | Minor | Tidak ada dokumentasi atau tool backup otomatis. |

---

## 14. Pengaturan Postman & Environment Auto-Sync

### Import Collection

File `CoFund-API.testing.postman_collection.postman_collection.json` adalah collection yang digunakan runner saat ini dan dapat langsung diimport ke Postman:

1. Buka Postman
2. Klik "Import" → "Upload Files"
3. Pilih file `CoFund-API.testing.postman_collection.postman_collection.json`

### Environment Variables

| Variable | Value | Deskripsi |
|---|---|---|
| `base_url` | `http://localhost:8000/api/v1` | Base URL API lengkap |
| `base_url_no_v1` | `http://localhost:8000/api` | Prefix untuk verifikasi email |
| `token_admin` | (auto-set) | Token admin |
| `token_creator` | (auto-set) | Token creator |
| `token_backer` | (auto-set) | Token backer |

### Auto-Set Variable pada Login Response

Di dalam collection, endpoint login memiliki skrip tests berikut:

```javascript
var jsonData = pm.response.json();
pm.collectionVariables.set("token_creator", jsonData.token);
```

---

## 15. Perintah Pengembangan & Debugging

### Migrasi & Database

| Perintah | Deskripsi |
|---|---|
| `php artisan migrate` | Jalankan migrasi database |
| `php artisan migrate:fresh --seed` | Reset database, migrate, & seed |
| `php artisan migrate:fresh --seed --seeder=CategorySeeder` | Reset & seed hanya kategori |
| `php artisan db:seed` | Jalankan seeder tanpa migrasi |
| `php artisan migrate:rollback` | Rollback migrasi terakhir |
| `php artisan migrate:status` | Lihat status migrasi |

### Queue & Jobs

| Perintah | Deskripsi |
|---|---|
| `php artisan queue:work` | Jalankan queue worker |
| `php artisan queue:work --tries=3` | Worker dengan retry 3x |
| `php artisan queue:listen` | Monitor dan restart otomatis |
| `php artisan queue:work --stop-when-empty` | Hentikan worker saat queue kosong |
| `php artisan tinker` | Evaluasi kode secara interaktif |

### Cache & Config

| Perintah | Deskripsi |
|---|---|
| `php artisan config:clear` | Bersihkan cache config |
| `php artisan cache:clear` | Bersihkan cache aplikasi |
| `php artisan route:clear` | Bersihkan cache route |
| `php artisan config:cache` | Cache config untuk performa |
| `php artisan optimize` | Optimasi performa |
| `php artisan view:clear` | Bersihkan cache view |

### Cron & Scheduling

| Perintah | Deskripsi |
|---|---|
| `php artisan schedule:run` | Jalankan scheduled task |
| `php artisan schedule:finish` | Tandai task selesai |
| `php artisan make:command CheckExpiredCampaigns` | Buat command kustom |
| `php artisan campaign:check-expired` | Jalankan manual cek kampanye kadaluarsa |
| `php artisan campaign:notify-deadline` | Jalankan manual notifikasi deadline |

### Testing & Debugging

| Perintah | Deskripsi |
|---|---|
| `php artisan tinker` | Interaktif PHP shell |
| `php artisan test` | Jalankan PHPUnit tests |
| `php artisan serve` | Jalankan development server |
| `php artisan route:list` | List semua route |
| `php artisan route:list --middleware=role:admin` | Filter route admin |
| `php artisan san:token` (via Tinker) | Generate token manual |

### Storage & File

| Perintah | Deskripsi |
|---|---|
| `php artisan storage:link` | Buat symbolic link storage |
| `php artisan down` | Maintenance mode |
| `php artisan up` | Keluar maintenance mode |

---

## Lampiran A: Dokumentasi Database & Skema ERD

Dokumentasi database lengkap termasuk ERD (Mermaid.js), kamus data, enum state machine, dan integrity rules tersedia di [`docs/database.md`](database.md).

## Lampiran B: Konvensi Penamaan

| Layer | Konvensi | Contoh |
|---|---|---|
| Controller | `{Modul}Controller` | `CampaignController` |
| Service | `{Modul}Service` | `CampaignService` |
| Request | `{Aksi}{Modul}Request` | `StoreCampaignRequest`, `UpdateCampaignRequest` |
| Resource | `{Modul}Resource` | `CampaignResource` |
| Model | `{Modul}` | `Campaign` |
| Event | `{Aksi}Event` | `CampaignApproved` |
| Listener | `Handle{Aksi}` | `HandleCampaignApproved` |
| Job | `{Aksi}Job` | `DisburseCampaignJob` |
| Middleware | `{Nama}Middleware` | `RoleMiddleware` |
| Enum | `{Modul}{Property}Enum` | `CampaignStatus` |
