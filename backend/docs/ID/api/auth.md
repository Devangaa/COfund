# API Modul Auth

API autentikasi, registrasi, manajemen kata sandi, dan sesi pengguna.

## Arsitektur

Modul auth dibangun di atas Laravel Sanctum untuk otentikasi token API. Menggunakan `AuthService` khusus untuk memusatkan logika bisnis, dengan validasi yang ditangani oleh kelas Form Request yang ddedikasikan. Pembatasan laju (rate limiting) diterapkan per-endpoint melalui middleware yang dikonfigurasi di `RouteServiceProvider`.

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/AuthController.php` | Menangani registrasi, login, logout, reset kata sandi |
| Service | `app/Services/AuthService.php` | Logika bisnis untuk pembuatan user, otentikasi, manajemen sesi |
| Requests | `app/Http/Requests/{LoginRequest, RegisterRequest, ForgotPasswordRequest, ResetPasswordRequest}.php` | Aturan validasi per endpoint |
| Model | `app/Models/User.php` | Entitas pengguna dengan `HasApiTokens`, `MustVerifyEmail` |
| Middleware | `app/Http/Middleware/Authenticate.php`, `VerifyEmail.php` | Pintu gerbang otentikasi & verifikasi email |

### Alur

```
User → Validasi Form Request → Metode AuthService → Event/Token → Respons
```

- **Register**: Validasi input → Hash kata sandi → Buat user (role=backer, balance=0) → Trigger event `Registered` → Kembalikan user + token.
- **Login**: Validasi kredensial → `Auth::attempt()` → Buat token Sanctum → Kembalikan user + token.
- **Logout**: Hapus token akses saat ini → Kembalikan pesan sukses.

## Struktur File

```
app/
├── Http/Controllers/Api/AuthController.php
├── Services/AuthService.php
├── Http/Requests/
│   ├── LoginRequest.php
│   ├── RegisterRequest.php
│   ├── ForgotPasswordRequest.php
│   └── ResetPasswordRequest.php
└── Models/User.php
```

## Batas Rate (Rate Limiting)

Dikonfigurasi di `RouteServiceProvider::configureRateLimiting()`:

| Endpoint | Limiter | Kecepatan | Lingkup |
|----------|---------|------|-------|
| `POST /api/register` | `register` | 3 permintaan / menit | Per IP |
| `POST /api/login` | `login` | 5 permintaan / menit | Per email + IP |
| `POST /api/forgot-password` | `password.request` | 5 permintaan / menit | Per email + IP |
| `POST /api/reset-password` | `password.request` | 5 permintaan / menit | Per email + IP |

Saat batas rate melebihi, respons `429 Too Many Requests` dikembalikan dengan body JSON terstruktur:

```json
{
  "message": "Too many registration attempts. Please try again in 60 seconds."
}
```

## API Endpoints

### 1. Register

Membuat akun pengguna baru (default role `backer`, `balance = 0`).

**Endpoint:** `POST /api/register`  
**Middleware:** `public` + `throttle:register`  
**Deskripsi:** Mendaftarkan pengguna baru dan mengembalikan token otentikasi. Membutuhkan verifikasi email.

#### Request

```
POST /api/register
Content-Type: application/json
Accept: application/json
```

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `name` | string | Ya | `required, string, max:255` | Nama lengkap pengguna |
| `email` | string | Ya | `required, email, unique:users` | Alamat email pengguna |
| `password` | string | Ya | `required, string, min:8, confirmed` | Kata sandi (harus cocok dengan `password_confirmation`) |
| `password_confirmation` | string | Ya | — | Harus cocok dengan `password` |

#### Contoh Request

```json
{
  "name": "Ahmad Fauzi",
  "email": "fauzi@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Respons (Sukses: 201)

```json
{
  "user": {
    "id": 3,
    "name": "Ahmad Fauzi",
    "email": "fauzi@example.com",
    "role": "backer",
    "balance": "0.00",
    "email_verified_at": null,
    "is_suspended": false
  },
  "token": "5|9e7a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 400 | `Validation error` | Email sudah terdaftar / kata sandi < 8 karakter / tidak cocok |
| 429 | `Too many registration attempts...` | Rate limit tercapai |

---

### 2. Login

Mengotentikasi pengguna dan mengeluarkan token API Sanctum.

**Endpoint:** `POST /api/login`  
**Middleware:** `public` + `throttle:login`  
**Deskripsi:** Mengeluarkan token bearer untuk sesi yang terotentikasi.

#### Request

```
POST /api/login
Content-Type: application/json
Accept: application/json
```

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `email` | string | Ya | `required, email` | Email pengguna |
| `password` | string | Ya | `required, string` | Kata sandi pengguna |

#### Contoh Request

```json
{
  "email": "fauzi@example.com",
  "password": "password123"
}
```

#### Respons (Sukses: 200)

```json
{
  "user": {
    "id": 3,
    "name": "Ahmad Fauzi",
    "email": "fauzi@example.com",
    "role": "backer",
    "balance": "500000.00",
    "email_verified_at": "2026-08-24T10:00:00.000000Z",
    "is_suspended": false
  },
  "token": "5|9e7a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | `Email or password is incorrect` | Kredensial tidak valid |
| 429 | `Too many login attempts...` | Rate limit tercapai |

---

### 3. Logout

Mencabulkan token akses saat ini.

**Endpoint:** `POST /api/logout`  
**Middleware:** `auth:sanctum`  
**Deskripsi:** Membatalkan validitas token bearer saat ini.

#### Request

```
POST /api/logout
Authorization: Bearer {token}
Accept: application/json
```

#### Respons (Sukses: 200)

```json
{
  "message": "Logged out successfully"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | `Unauthenticated` | Token tidak ada atau tidak valid |

---

### 4. Dapatkan Pengguna Terotentikasi

Mengembalikan data pengguna yang saat ini terotentikasi.

**Endpoint:** `GET /api/me`  
**Middleware:** `auth:sanctum`  
**Deskripsi:** Mengambil profil pengguna yang sedang login.

#### Respons (Sukses: 200)

```json
{
  "id": 3,
  "name": "Ahmad Fauzi",
  "email": "fauzi@example.com",
  "role": "backer",
  "balance": "500000.00",
  "email_verified_at": "2026-08-24T10:00:00.000000Z",
  "is_suspended": false
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | `Unauthenticated` | Token tidak ada atau tidak valid |

---

### 5. Kirim Tautan Reset Kata Sandi

Mengirimkan tautan reset kata sandi ke email pengguna.

**Endpoint:** `POST /api/forgot-password`  
**Middleware:** `public` + `throttle:password.request`  
**Deskripsi:** Mengirimkan tautan reset kata sandi melalui email.

#### Request

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `email` | string | Ya | `required, email` | Alamat email pengguna |

#### Contoh Request

```json
{
  "email": "fauzi@example.com"
}
```

#### Respons (Sukses: 200)

```json
{
  "message": "If the email exists in our system, a reset link has been sent."
}
```

> **Catatan:** Respons sama apakah email ada atau tidak, untuk mencegah pencacahan pengguna (user enumeration).

---

### 6. Reset Kata Sandi

Mereset kata sandi pengguna menggunakan token yang valid.

**Endpoint:** `POST /api/reset-password`  
**Middleware:** `public` + `throttle:password.request`  
**Deskripsi:** Mereset kata sandi menggunakan token valid.

#### Request

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `email` | string | Ya | `required, email` | Alamat email pengguna |
| `token` | string | Ya | — | Token reset (dari tautan email) |
| `password` | string | Ya | `required, string, min:8, confirmed` | Kata sandi baru |
| `password_confirmation` | string | Ya | — | Harus cocmpa dengan `password` |

#### Contoh Request

```json
{
  "email": "fauzi@example.com",
  "token": "aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

#### Respons (Sukses: 200)

```json
{
  "message": "Password has been reset successfully"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 400 | `Validation error` | Token tidak valid atau kadaluarsa / kata sandi < 8 karakter |
| 429 | `Too many password reset attempts...` | Rate limit tercapai |

---

### 7. Kirim Ulang Verifikasi Email

Mengirimkan notifikasi verifikasi email baru.

**Endpoint:** `POST /api/email/resend`  
**Middleware:** `auth:sanctum`  
**Deskripsi:** Mengirim kembali notifikasi verifikasi email.

#### Respons (Sukses: 200)

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 200 | `Verification email resent.` | Berhasil |
| 400 | `Already verified` | Email sudah diverifikasi |

---

### 8. Verifikasi Email

Menandai email pengguna sebagai sudah diverifikasi.

**Endpoint:** `GET /api/email/verify/{id}/{hash}`  
**Middleware:** `signed` + `throttle:6,1`  
**Deskripsi:** Memverifikasi email pengguna menggunakan URL yang ditandatangani.

#### Respons (Sukses: 200)

```json
{
  "message": "Email successfully verified."
}
```

---

## Skema Sumber Daya Pengguna

```json
{
  "id": 3,
  "name": "Ahmad Fauzi",
  "email": "fauzi@example.com",
  "role": "backer",
  "balance": "500000.00",
  "email_verified_at": "2026-08-24T10:00:00.000000Z",
  "is_suspended": false
}
```

### Referensi Kolom

| Kolom | Tipe | Deskripsi |
|-------|------|-------------|
| `id` | integer | Pengenal unik |
| `name` | string | Nama lengkap |
| `email` | string | Alamat email (unik) |
| `role` | enum | Salah satu dari `backer`, `creator`, `admin` |
| `balance` | decimal | Saldo akun (format: decimal:2) |
| `email_verified_at` | datetime\|null | Cap waktu verifikasi email (null jika belum diverifikasi) |
| `is_suspended` | boolean | Apakah pengguna disuspend |

## Pengujian Postman

Impor koleksi Postman yang disediakan `CoFund-API.postman_collection.json` dan gunakan variabel lingkungan berikut:

| Variabel | Nilai |
|----------|-------|
| `base_url` | `http://localhost:8000/api` |

### Skrip Pengujian (Auth)

#### Pengujian 1: Daftar Pengguna Baru

1. Atur permintaan: `POST {{base_url}}/register`
2. Body (raw JSON):
   ```json
   {
     "name": "Test User",
     "email": "testuser@example.com",
     "password": "password123",
     "password_confirmation": "password123"
   }
   ```
3. Diperkirakan: `201 Created` dengan user + token.
4. Simpan `token` dari respons ke variabel lingkungan `{{auth_token}}`.

#### Pengujian 2: Login dengan Pengguna Terdaftar

1. Atur permintaan: `POST {{base_url}}/login`
2. Body (raw JSON):
   ```json
   {
     "email": "testuser@example.com",
     "password": "password123"
   }
   ```
3. Diperkirakan: `200 OK` dengan user + token.
4. Perbarui `{{auth_token}}` dengan token baru.

#### Pengujian 3: Akses Sumber Daya yang Dilindungi

1. Atur permintaan: `GET {{base_url}}/me`
2. Header: `Authorization: Bearer {{auth_token}}`
3. Diperkirakan: `200 OK` dengan data pengguna.

#### Pengujian 4: Batas Rate pada Login

1. Kirim `POST {{base_url}}/login` dengan kredensial salah 6 kali dengan cepat.
2. Diperkirakan: `429 Too Many Requests` setelah 5 percobaan dalam 1 menit.

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Daftar dengan data valid | Nama, email, kata sandi valid | 201 + user + token |
| 2 | Daftar dengan email duplikat | Email yang sudah ada | 400 error validasi |
| 3 | Daftar dengan kata sandi pendek | Kata sandi < 8 karakter | 400 error validasi |
| 4 | Daftar dengan kata sandi tidak cocok | password != password_confirmation | 400 error validasi |
| 5 | Login dengan kredensial valid | Email + kata sandi benar | 200 + user + token |
| 6 | Login dengan kredensial tidak valid | Kata sandi salah | 401 tidak terotentikasi |
| 7 | Logout dengan token valid | Bearer token | 200 pesan sukses |
| 8 | Akses /me tanpa token | Tidak ada header Authorization | 401 tidak terotentikasi |
| 9 | Lupa kata sandi dengan email valid | Email valid | 200 pesan generik |
| 10 | Lupa kata sandi dengan email tidak valid | Email tidak ada | 200 pesan generik (sama dengan yang valid) |
| 11 | Reset kata sandi dengan token tidak valid | Token buruk/kadaluarsa | 400 error validasi |
| 12 | Reset kata sandi dengan kata sandi tidak cocok | password != konfirmasi | 400 error validasi |

## Pemecahan Masalah

### 1. Mendapatkan 401 "Unauthenticated" pada Setiap Permintaan

Ini karena Sanctum bergantung pada salah satu:
- **Bearer Token**: Klien mengirimkan header `Authorization: Bearer {token}`.
- **Berbasis Cookie**: Frontend yang berjalan pada *domain stateful* (lihat array `stateful` di `config/sanctum.php`).

Karena middleware `EnsureFrontendRequestsAreStateful` **dikomentar** di `routes/api.php`, hanya token bearer yang diterima. Pastikan header `Authorization` dikirim.

### 2. Batas Rate Terlampaui (429)

Tunggu sampai periode cooldown selesai. Kunci pembatas rate berbeda per endpoint:
- Register: per alamat IP.
- Login: per `email + IP`.
- Password: per `email + IP`.

### 3. Verifikasi Email Tidak Berfungsi

Pastikan `MAIL_MAILER` diatur di `.env` (default `smtp` dengan mailpit di port 1025). Periksa `MAIL_FROM_ADDRESS` valid. Email verifikasi hanya dikirimkan saat pendaftaran melalui event `Registered` → listener `SendEmailVerificationNotification`.

### 4. Token Reset Kata Sandi Tidak Valid/Kadaluarsa

Token reset kata sandi kadaluarsa setelah `env('PASSWORD_RESET_TTL', 60)` menit (default 60). Pastikan token digunakan dalam jangka waktu ini.

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Register | Public | `throttle:register` |
| Login | Public | `throttle:login` |
| Logout | Terotentikasi | `auth:sanctum` |
| Lihat Profil (`/me`) | Terotentikasi | `auth:sanctum` |
| Kirim Ulang Verifikasi | Terotentikasi + Tidak Terverifikasi | `auth:sanctum` |
| Verifikasi Email | Public (ditandatangani) | `signed, throttle:6,1` |
| Lupa Password | Public | `throttle:password.request` |
| Reset Password | Public | `throttle:password.request` |