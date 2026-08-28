# CoFund API - Modul Otentikasi (Authentication Module)

## 1. Judul & Deskripsi Modul

Modul otentikasi mengelola siklus hidup pengguna secara lengkap mulai dari pendaftaran akun, login, logout, pengiriman dan penggunaan ulang tautan reset kata sandi, hingga verifikasi email. Seluruh endpoint didukung oleh bearer token Sanctum untuk sesi yang terautentikasi.

**Base Path:** `/api/v1` (kecuali route verifikasi email yang tetap di root)

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/AuthController.php` | Menangani register, login, logout, me, forgot-password, reset-password |
| **Service** | `app/Services/AuthService.php` | Logika bisnis register, login (token generation), logout, password reset |
| **Form Request** | `app/Http/Requests/RegisterRequest.php` | Validasi pendaftaran |
| | `app/Http/Requests/LoginRequest.php` | Validasi login |
| | `app/Http/Requests/ForgotPasswordRequest.php` | Validasi permintaan reset password |
| | `app/Http/Requests/ResetPasswordRequest.php` | Validasi reset password |
| **Resource** | `app/Http/Resources/UserResource.php` | Serialisasi data pengguna |
| **Model** | `app/Models/User.php` | Model User dengan trait `MustVerifyEmail`, `HasApiTokens` |
| **Middleware** | `auth:sanctum` | Otentikasi bearer token |
| | `verified` | Verifikasi email wajib |
| | `throttle:password.request` | Rate limiting reset password (5/menit/email+IP) |
| **Event** | `Illuminate\Auth\Events\Registered` | Trigger notifikasi verifikasi email |
| | `Illuminate\Auth\Events\PasswordReset` | Trigger notifikasi reset password berhasil |

### Alur Proses Logika Bisnis

```
                    +-----------------+
                    |   Pengguna    |
                    +--------+--------+
                             |
    +------------------------+------------------------+
    |                                                 |
    v                                                 v
REGISTER                                      LOGIN
  |                                             |
  v                                             v
AuthService::register()                    AuthService::login()
  |                                             |
  v                                             v
Hash::make(password)                     Auth::attempt(credentials)
  |                                             |
  v                                             v
User::create()                         Auth::user()
  |                                             |
  v                                             v
event(Registered)                     user->createToken()
  |                                             |
  v                                             v
SendEmailVerificationNotification      Return {user, token}
  |
  v
UserResource + 201

    +------------------------+------------------------+
    |                        |                        |
    v                        v                        v
FORGOT PASSWORD          RESET PASSWORD          LOGOUT
  |                        |                        |
  v                        v                        v
AuthService                 AuthService               AuthService::logout()
::sendResetLink()           ::resetPassword()          |
  |                        |                        v
  v                        v                currentAccessToken()->delete()
Password::                  Password::       |
sendResetLink()             reset()          |
  |                        |                v
  v                        v            return {success, message}
Return {success,          Return {success,
  message}                  message}
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── AuthController.php
│   │   ├── Requests/
│   │   │   ├── RegisterRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   ├── ForgotPasswordRequest.php
│   │   │   └── ResetPasswordRequest.php
│   │   └── Resources/
│   │       └── UserResource.php
│   ├── Models/
│   │   └── User.php
│   ├── Services/
│   │   └── AuthService.php
│   └── Middleware/
│       ├── RoleMiddleware.php
│       └── Authenticate.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Register

- **Deskripsi:** Mendaftarkan pengguna baru dan mengirimkan notifikasi verifikasi email.
- **HTTP Method & URL Path:** `POST /api/v1/register`
- **Middleware:** None
- **Autentikasi:** None (public)
- **Content-Type:** `application/json`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `name` | Body | string | Ya | `max:255` | Nama lengkap pengguna |
| `email` | Body | string | Ya | `email`, `unique:users,email` | Alamat email unik |
| `password` | Body | string | Ya | `min:8`, `confirmed` | Kata sandi minimal 8 karakter, harus cocok dengan `password_confirmation` |
| `password_confirmation` | Body | string | Ya | - | Konfirmasi kata sandi |

#### Contoh Request Payload

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
}
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "backer",
        "balance": 0,
        "email_verified_at": null,
        "is_suspended": false
    },
    "message": "Registration successful"
}
```

#### Efek Samping

- Membuat entri baru di tabel `users`
- Memicu event `Registered` yang secara otomatis mengirimkan notifikasi verifikasi email via `SendEmailVerificationNotification`
- Role default `backer` dan `balance` default `0`

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Validasi gagal (email duplikat, password tidak cocong, dsb.) |
| 429 | `{"success":false,"message":"Too many registration attempts. Please try again later."}` | Melebihi rate limit `register` (3 permintaan/menit/IP) |

---

### 4.2 Endpoint: Login

- **Deskripsi:** Mengautentikasi pengguna dan mengembalikan bearer token Sanctum.
- **HTTP Method & URL Path:** `POST /api/v1/login`
- **Middleware:** None
- **Autentikasi:** None (public)

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `email` | Body | string | Ya | `required`, `email` | Email pengguna |
| `password` | Body | string | Ya | `required` | Kata sandi |

#### Contoh Request Payload

```json
{
    "email": "john@example.com",
    "password": "secret123"
}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "backer",
        "balance": 500000,
        "email_verified_at": "2024-01-15T10:30:00Z",
        "is_suspended": false
    },
    "token": "1|abcdef1234567890...",
    "message": "Login successful"
}
```

#### Efek Samping

- Memverifikasi kredensial melalui `Auth::attempt()`
- Membuat token Sanctum baru via `user->createToken("auth-token")`
- Token disimpan di tabel `personal_access_tokens`

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"email":["These credentials do not match our records."]}}}` | Email/password tidak cocok |
| 403 | `{"success":false,"message":"The given data was invalid.","errors":{"email":["This account has been suspended."]}}` | User `is_suspended: true` |
| 429 | `{"success":false,"message":"Too many login attempts. Please try again in 1 minute."}` | Melebihi rate limit `login` (5/menit/email+IP) |

---

### 4.3 Endpoint: Logout

- **Deskripsi:** Mencabut (revoke) token Sanctum yang sedang digunakan.
- **HTTP Method & URL Path:** `POST /api/v1/logout`
- **Middleware:** `auth:sanctum`
- **Autentikasi:** Bearer Token

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Deskripsi |
|---|---|---|---|---|
| `Authorization` | Header | string | Ya | `Bearer {token}` |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

#### Efek Samping

- Menghapus token Sanctum saat ini dari tabel `personal_access_tokens`
- Token tidak dapat digunakan lagi untuk request berikutnya

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid atau tidak dikirim |

---

### 4.4 Endpoint: Get Current User (Me)

- **Deskripsi:** Mendapatkan profil pengguna yang sedang terautentikasi.
- **HTTP Method & URL Path:** `GET /api/v1/me`
- **Middleware:** `auth:sanctum`
- **Autentikasi:** Bearer Token

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "backer",
        "balance": 500000,
        "email_verified_at": "2024-01-15T10:30:00Z",
        "is_suspended": false
    }
}
```

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |

---

### 4.5 Endpoint: Resend Email Verification

- **Deskripsi:** Mengirimkan ulang notifikasi verifikasi email ke pengguna yang belum memverifikasi.
- **HTTP Method & URL Path:** `POST /api/v1/email/resend`
- **Middleware:** `auth:sanctum`

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Verification email sent"
}
```

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 400 | `{"success":false,"message":"Email already verified"}` | Email sudah diverifikasi |
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |

---

### 4.6 Endpoint: Forgot Password

- **Deskripsi:** Mengirimkan tautan reset kata sandi ke email pengguna.
- **HTTP Method & URL Path:** `POST /api/v1/forgot-password`
- **Middleware:** `throttle:password.request`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `email` | Body | string | Ya | `required`, `email` | Email pengguna yang ingin mereset password |

#### Contoh Request Payload

```json
{
    "email": "john@example.com"
}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "If the email exists, a password reset link has been sent."
}
```

#### Efek Samping

- Mengirimkan email reset password melalui broker password Laravel
- Pesan response selalu sama terlepas dari apakah email terdaftar atau tidak (keamanan informasi)

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 429 | `{"success":false,"message":"Too many password reset requests. Please try again in 1 minute."}` | Melebihi rate limit (5/menit/email+IP) |

---

### 4.7 Endpoint: Reset Password

- **Deskripsi:** Mengatur ulang kata sandi pengguna menggunakan token reset.
- **HTTP Method & URL Path:** `POST /api/v1/reset-password`
- **Middleware:** `throttle:password.request`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `email` | Body | string | Ya | `required`, `email` | Email pengguna |
| `token` | Body | string | Ya | `required` | Token reset yang dikirim ke email |
| `password` | Body | string | Ya | `required`, `min:8`, `confirmed` | Password baru |
| `password_confirmation` | Body | string | Ya | - | Konfirmasi password baru |

#### Contoh Request Payload

```json
{
    "email": "john@example.com",
    "token": "a1b2c3d4e5f6...",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

#### Contoh Response Sukses (HTTP 200)

```json
{
    "success": true,
    "message": "Password reset successfully"
}
```

#### Contoh Response Gagal (HTTP 422)

```json
{
    "success": false,
    "message": "invalid or expired token",
    "errors": {
        "email": ["invalid or expired token"]
    }
}
```

#### Efek Samping

- Memperbarui password pengguna di database
- Memicu event `PasswordReset`

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Token tidak valid, kadaluarsa, atau validasi gagal |
| 429 | `{"success":false,"message":"Too many password reset requests. Please try again in 1 minute."}` | Rate limit |

---

### 4.8 Endpoint: Email Verification Notice

- **Deskripsi:** Memberikan pesan bahwa verifikasi email diperlukan.
- **HTTP Method & URL Path:** `GET /email/verify/notice`
- **Middleware:** None
- **Catatan:** Route ini tidak termasuk dalam prefix `/api/v1` karena mematuhi konvensi Laravel.

#### Contoh Response (HTTP 403)

```json
{
    "success": false,
    "message": "Email verification required."
}
```

---

### 4.9 Endpoint: Verify Email

- **Deskripsi:** Memverifikasi alamat email pengguna melalui tautan yang ditandatangani.
- **HTTP Method & URL Path:** `GET /email/verify/{id}/{hash}`
- **Middleware:** `signed`, `throttle:60,1`
- **Catatan:** Route ini tidak termasuk dalam prefix `/api/v1`.

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Deskripsi |
|---|---|---|---|---|
| `id` | Path | integer | Ya | ID pengguna |
| `hash` | Path | string | Ya | Hash verifikasi email |

#### Contoh Response Sukses (HTTP 200)

```json
{
    "success": true,
    "message": "Email verified successfully"
}
```

#### Contoh Response sudah terverifikasi (HTTP 200)

```json
{
    "success": true,
    "message": "Email already verified"
}
```

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"Invalid signature"}` | Tanda tangan tidak valid |
| 404 | `{"success":false,"message":"User not found"}` | ID pengguna tidak ditemukan |
| 429 | (standard rate limit) | Melebihi 60 permintaan/menit |

---

## 5. Skema Sumber Daya (Resource Schema)

### UserResource

```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "backer",
    "balance": 500000,
    "email_verified_at": "2024-01-15T10:30:00Z",
    "is_suspended": false
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key pengguna |
| `name` | string | Nama lengkap |
| `email` | string | Alamat email (unik) |
| `role` | enum | Peran: `backer`, `creator`, `admin` |
| `balance` | decimal | Saldo dompet pengguna |
| `email_verified_at` | datetime|null | Timestamp verifikasi email |
| `is_suspended` | boolean | Status penangguhan akun |

---

## 6. Pengujian Postman

### Setup Environment Variables

| Variable | Value |
|---|---|
| `base_url` | `http://localhost:8000` |
| `api_version` | `v1` |
| `auth_token` | (di-set otomatis setelah login) |

### Register

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/register`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
}
```

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Registration successful", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.user.email).to.eql("john@example.com");
});
```

### Login

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/login`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "email": "john@example.com",
    "password": "secret123"
}
```

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Login returns token", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.token).to.be.a("string");
    pm.environment.set("auth_token", jsonData.token);
});
```

### Logout

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/logout`
3. Headers: `Authorization: Bearer {{auth_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Logout successful", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
});
```

### Me

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/me`
3. Headers: `Authorization: Bearer {{auth_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Returns user data", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.user.email).to.exist;
});
```

### Forgot Password

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/forgot-password`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "email": "john@example.com"
}
```

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Forgot password response", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | Register dengan data valid | Name, email, password, password_confirmation | HTTP 201, data user (email verifikasi dikirim) |
| 2 | Register dengan email duplikat | Email yang sudah terdaftar | HTTP 422, error validasi "email has already been taken" |
| 3 | Register dengan password tidak cocong | Password ≠ password_confirmation | HTTP 422, error validasi |
| 4 | Login dengan kredensial valid | Email + password benar | HTTP 200, token Sanctum |
| 5 | Login dengan password salah | Email benar, password salah | HTTP 422, error "credentials do not match" |
| 6 | Login dengan akun belum diverifikasi | Email + password valid, email_verified_at null | HTTP 200 (login berhasil, tapi endpoint terproteksi `verified` akan gagal) |
| 7 | Logout dengan token valid | Bearer token | HTTP 200, token dicabut |
| 8 | Akses `/me` tanpa token | Tidak ada header Authorization | HTTP 401, "Unauthenticated" |
| 9 | Resend verifikasi email sudah diverifikasi | Token valid | HTTP 400, "Email already verified" |
| 10 | Resend verifikasi email belum diverifikasi | Token valid | HTTP 200, "Verification email sent" |
| 11 | Request reset password | Email valid | HTTP 200, pesan sukses (samar) |
| 12 | Reset password dengan token valid | Email, token, password, password_confirmation | HTTP 200, "Password reset successfully" |
| 13 | Reset password dengan token tidak valid | Token tidak valid | HTTP 422, "invalid or expired token" |
| 14 | Verifikasi email dengan signature valid | URL dengan {id} dan {hash} yang valid | HTTP 200, "Email verified successfully" |
| 15 | Verifikasi email dengan signature tidak valid | URL ditamper | HTTP 403, "Invalid signature" |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| Email verifikasi tidak terkirim | Pastikan `QUEUE_CONNECTION=redis`, Redis worker berjalan, konfigurasi SMTP benar, dan Mailpit aktif pada `127.0.0.1:1025` (atau hostname Docker `mailpit`). |
| Login gagal meskipun password benar | Pastikan `Auth::attempt()` menggunakan guard default. Cek apakah password di-hash dengan `Hash::make()` saat register. |
| Token Sanctum tidak valid | Pastikan `config/sanctum.php` `stateful` dikonfigurasi. Pastikan `SESSION_DOMAIN` dan `SANCTUM_STATEFUL_DOMAINS` sesuai. |
| Rate limiter register/login menghit blocking | Login dan register tidak memiliki limiter khusus. Hanya limiter global API dan password reset yang aktif. |
| Verifikasi email tidak ditemukan | Route aktual adalah `/api/email/verify/notice` dan `/api/email/verify/{id}/{hash}` karena `routes/api.php` dimuat dengan prefix `/api`. |
| Password reset token kadaluarsa | Token reset password Laravel kadaluarsa setelah 60 menit secara default. Sesuaikan di `config/auth.php`. |

---

## 9. Matriks RBAC

| Endpoint | Role yang Diperlukan |
|---|---|
| `POST /api/v1/register` | *Public* |
| `POST /api/v1/login` | *Public* |
| `POST /api/v1/logout` | Authenticated |
| `GET /api/v1/me` | Authenticated |
| `POST /api/v1/email/resend` | Authenticated |
| `POST /api/v1/forgot-password` | *Public* |
| `POST /api/v1/reset-password` | *Public* |
| `GET /email/verify/notice` | *Public* |
| `GET /email/verify/{id}/{hash}` | *Public* |

---

## 10. Matriks Kasus Pengujian (Test Case)

### 10.1 Register

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-REG-001` | Register dengan input lengkap valid | Positive | Semua field terisi sesuai aturan | `201 Created` | User terdaftar, email verifikasi dikirim, respons `{success, message, data}` |
| `TC-AUTH-REG-002` | Register dengan role opsional (default backer) | Positive | Tanpa field `role` (auto backer) | `201 Created` | Role otomatis `backer` |
| `TC-AUTH-REG-003` | Register dengan role creator | Positive | `role: "creator"` valid | `201 Created` | User memiliki role creator |
| `TC-AUTH-REG-004` | Register dengan role admin | Negative | `role: "admin"` (role terlarang untuk registrasi publik) | `422 Unprocessable` | Error "Invalid role" |
| `TC-AUTH-REG-005` | Register tanpa email | Negative | `email: null` | `422 Unprocessable` | Error "The email field is required" |
| `TC-AUTH-REG-006` | Register dengan format email tidak valid | Negative | `email: "not-an-email"` | `422 Unprocessable` | Error "The email field must be a valid email address" |
| `TC-AUTH-REG-007` | Register dengan password terlalu pendek | Negative | `password: "123"` | `422 Unprocessable` | Error "The password field must be at least 8 characters" |
| `TC-AUTH-REG-008` | Register dengan password tidak cocok | Negative | `password ≠ password_confirmation` | `422 Unprocessable` | Error "Password confirmation does not match" |
| `TC-AUTH-REG-009` | Register dengan email duplikat | Negative | `email` sudah terdaftar | `422 Unprocessable` | Error "The email has already been taken" |
| `TC-AUTH-REG-010` | Register tanpa password_confirmation | Negative | Field confirmation hilang | `422 Unprocessable` | Error "The password confirmation field is required" |
| `TC-AUTH-REG-011` | Spam register 4x dalam 1 menit | Throttling | Rapid requests | `429 Too Many Requests` | Header `Retry-After` |
| `TC-AUTH-REG-012` | Register dengan nama lebih dari 255 karakter | Negative | `name > 255 chars` | `422 Unprocessable` | Error "The name field must not be greater than 255 characters" |

### 10.2 Login

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-LOG-001` | Login dengan kredensial valid | Positive | `email`, `password` benar | `200 OK` | Mengembalikan Sanctum Token & User Resource |
| `TC-AUTH-LOG-002` | Login dengan password salah | Negative | `password` salah | `422 Unprocessable` | Error "Invalid credentials" |
| `TC-AUTH-LOG-003` | Login dengan email tidak terdaftar | Negative | `email` belum terdaftar | `422 Unprocessable` | Error "Invalid credentials" |
| `TC-AUTH-LOG-004` | Login tanpa email | Negative | `email: null` | `422 Unprocessable` | Error "The email field is required" |
| `TC-AUTH-LOG-005` | Login dengan format email tidak valid | Negative | `email: "invalid"` | `422 Unprocessable` | Error validasi email |
| `TC-AUTH-LOG-006` | Login dengan akun yang disuspended | Negative | User `is_suspended: true` | `403 Forbidden` | Error "This account has been suspended" |
| `TC-AUTH-LOG-007` | Login akun yang belum verifikasi email | Positive | Login sukses tapi email belum verified | `200 OK` | Token dikembalikan (degraded state) |
| `TC-AUTH-LOG-008` | Spam login 6x dalam 1 menit | Throttling | Rapid requests | `429 Too Many Requests` | Header `Retry-After` |
| `TC-AUTH-LOG-009` | Login dengan tipe data password string kosong | Negative | `password: ""` | `422 Unprocessable` | Error "The password field is required" |
| `TC-AUTH-LOG-010` | Login dengan integer di field password | Negative | `password: 12345678` (bukan string) | `422 Unprocessable` | Error tipe data |

### 10.3 Logout

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-LOG-011` | Logout dengan token valid | Positive | `Authorization: Bearer valid_token` | `200 OK` | Token dihapus, respons `{success, message}` |
| `TC-AUTH-LOG-012` | Logout tanpa token | Security | Tidak ada header Authorization | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-AUTH-LOG-013` | Logout dengan token kadaluarsa | Security | `Bearer expired_token` | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-AUTH-LOG-014` | Double logout | Positive | Logout 2x dengan token yang sama | `200 OK` / `401` | Request pertama: 200. Request kedua: 401 (token sudah invalid) |

### 10.4 Me (Get Current User)

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-ME-001` | Get user dengan token valid | Positive | `Authorization: Bearer valid_token` | `200 OK` | UserResource lengkap |
| `TC-AUTH-ME-002` | Get user tanpa token | Security | Tidak ada Authorization | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-AUTH-ME-003` | Get user dengan token invalid | Security | `Bearer invalid_token` | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-AUTH-ME-004` | Get user dengan token kadaluarsa | Security | Token expired | `401 Unauthorized` | Error "Unauthenticated" |

### 10.5 Resend Email Verification

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-RES-001` | Resend verification dengan email belum terverifikasi | Positive | Authenticated, email belum verified | `200 OK` | Email verifikasi dikirim, respons `{success, message}` |
| `TC-AUTH-RES-002` | Resend verification pada email yang sudah terverifikasi | Negative | Email sudah verified | `400 Bad Request` | Error "Email already verified" |
| `TC-AUTH-RES-003` | Resend verification tanpa autentikasi | Security | Tidak ada token | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-AUTH-RES-004` | Resend verification berulang (spam) | Positive | Kirim 3x berturut-turut | `200 OK` | Email terkirim berulang (tidak dibatasi rate di level ini) |

### 10.6 Forgot Password

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-FP-001` | Forget password dengan email terdaftar | Positive | `email` valid & terdaftar | `200 OK` | Link reset dikirim, respons `{success, message}` |
| `TC-AUTH-FP-002` | Forget password dengan email tidak terdaftar | Positive | `email` tidak terdaftar | `200 OK` | Respons sukses (tanpa mengungkap keberadaan email) |
| `TC-AUTH-FP-003` | Forget password tanpa email | Negative | `email: null` | `422 Unprocessable` | Error "The email field is required" |
| `TC-AUTH-FP-004` | Forget password dengan format email tidak valid | Negative | `email: "invalid"` | `422 Unprocessable` | Error validasi email |
| `TC-AUTH-FP-005` | Spam forget password 6x dalam 1 menit | Throttling | Rapid requests | `429 Too Many Requests` | Header `Retry-After` |

### 10.7 Reset Password

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-RP-001` | Reset password dengan kredensial valid | Positive | `email`, `token` (dari email), `password`, `password_confirmation` valid | `200 OK` | Password berubah, respons `{success, message}` |
| `TC-AUTH-RP-002` | Reset password dengan token salah | Negative | `token` tidak valid | `422 Unprocessable` / `400 Bad Request` | Error "Invalid token" |
| `TC-AUTH-RP-003` | Reset password dengan token kadaluarsa | Negative | Token expired (> 60 menit) | `422 Unprocessable` | Error token kadaluarsa |
| `TC-AUTH-RP-004` | Reset password tanpa email | Negative | `email: null` | `422 Unprocessable` | Error "The email field is required" |
| `TC-AUTH-RP-005` | Reset password dengan password terlalu pendek | Negative | `password: "123"` | `422 Unprocessable` | Error "The password field must be at least 8 characters" |
| `TC-AUTH-RP-006` | Reset password dengan password tidak cocok | Negative | `password ≠ password_confirmation` | `422 Unprocessable` | Error "Password confirmation does not match" |
| `TC-AUTH-RP-007` | Reset password dengan token yang hilang | Negative | `token: null` | `422 Unprocessable` | Error "The token field is required" |
| `TC-AUTH-RP-008` | Spam reset password 6x dalam 1 menit | Throttling | Rapid requests | `429 Too Many Requests` | Header `Retry-After` |

### 10.8 Email Verification

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-AUTH-EV-001` | Akses verification notice | Positive | GET /email/verify/notice | `403 Forbidden` | Error "Email verification required" |
| `TC-AUTH-EV-002` | Verifikasi email dengan signature valid | Positive | URL dari email, signature valid | `200 OK` | Email terverifikasi, event `Verified` dipicu |
| `TC-AUTH-EV-003` | Verifikasi email dengan signature tidak valid | Negative | Signature dimanipulasi | `403 Forbidden` | Error "Invalid signature" |
| `TC-AUTH-EV-004` | Verifikasi email untuk user yang tidak ada | Negative | `id` user tidak ada di DB | `404 Not Found` | Error "User not found" |
| `TC-AUTH-EV-005` | Verifikasi email yang sudah diverifikasi | Positive | Email sudah verified | `200 OK` | Pesan "Email already verified" |
