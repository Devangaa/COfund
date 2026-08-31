# CoFund API - Modul Autentikasi & Akun Pengguna (Auth Module)

## 1. Judul & Deskripsi Modul

Modul Autentikasi mengelola seluruh siklus akun pengguna di platform CoFund, meliputi pendaftaran akun baru (*registration*), login dan penerbitan token sesi Sanctum, pemutusan sesi (*logout*), pengambilan data profil terkini (`/me`), pengiriman ulang dan verifikasi email (*email verification*), pengaturan ulang kata sandi (*forgot & reset password*), serta peningkatan hak akses donatur menjadi inisiator (*Upgrade to Creator*).

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/AuthController.php` | Controller utama penanganan request auth |
| **Service Layer** | `backend/app/Services/AuthService.php` | Enkapsulasi logika autentikasi, token, dan upgrade |
| **Form Requests** | `backend/app/Http/Requests/RegisterRequest.php` | Validasi data registrasi user baru |
| | `backend/app/Http/Requests/LoginRequest.php` | Validasi kredensial login |
| | `backend/app/Http/Requests/ForgotPasswordRequest.php` | Validasi permintaan email reset password |
| | `backend/app/Http/Requests/ResetPasswordRequest.php` | Validasi token dan password baru |
| | `backend/app/Http/Requests/UpgradeToCreatorRequest.php` | Validasi alasan upgrade ke creator |
| **Resource** | `backend/app/Http/Resources/UserResource.php` | Serialisasi format objek user |
| **Model** | `backend/app/Models/User.php` | Model Eloquent pengguna (Authenticatable) |
| **Enums** | `backend/app/Models/User.php` (Constants) | Definisi `ROLE_BACKER`, `ROLE_CREATOR`, `ROLE_ADMIN` |

### Diagram Alur Proses Logika Bisnis

```
User Registrasi / Login
        │
        ▼
[ AuthController::register / login ]
        │
        ▼
[ FormRequest Validation ]
        │
        ▼
[ AuthService ]
        │
        ├─► Pengecekan Hash Password & Akun Suspended
        │         │
        │         ├─► Suspended / Salah ──► HTTP 403 / 422
        │         ▼
        ├─► Terbitkan Sanctum PlainTextToken
        │
        ▼
Return UserResource + Token (HTTP 200 / 201)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── AuthController.php
│   │   ├── Requests/
│   │   │   ├── ForgotPasswordRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   ├── RegisterRequest.php
│   │   │   ├── ResetPasswordRequest.php
│   │   │   └── UpgradeToCreatorRequest.php
│   │   └── Resources/
│   │       └── UserResource.php
│   ├── Models/
│   │   └── User.php
│   └── Services/
│       └── AuthService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Register Akun Baru
- **Deskripsi:** Mendaftarkan akun donatur baru dan mengirimkan email verifikasi.
- **HTTP Method & Path:** `POST /api/v1/register`
- **Middleware:** Guest (Publik)

#### Tabel Parameter Body
| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `name` | string | Ya | `required, string, max:255` | Nama lengkap pengguna |
| `email` | string | Ya | `required, string, email, max:255, unique:users` | Email aktif unik |
| `password` | string | Ya | `required, string, min:8, confirmed` | Kata sandi akun |
| `password_confirmation` | string | Ya | `required, string` | Konfirmasi kata sandi |

#### Contoh Response (`201 Created`):
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 10,
      "name": "Ahmad Fauzi",
      "email": "ahmad@example.com",
      "role": "backer",
      "balance": "0.00",
      "email_verified_at": null,
      "is_suspended": false
    }
  }
}
```

---

### 4.2 Endpoint: Login Akun
- **Deskripsi:** Mengautentikasi kredensial dan menerbitkan Bearer Token.
- **HTTP Method & Path:** `POST /api/v1/login`

#### Contoh Request:
```json
{
  "email": "ahmad@example.com",
  "password": "password123"
}
```

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 10,
      "name": "Ahmad Fauzi",
      "email": "ahmad@example.com",
      "role": "backer",
      "balance": "0.00",
      "email_verified_at": null,
      "is_suspended": false
    },
    "token": "1|abc123xyz789..."
  }
}
```

---

### 4.3 Endpoint: Profil Saya (`GET /api/v1/me`)
- **Deskripsi:** Mengambil data profil dan saldo terkini pengguna login.
- **Middleware:** `auth:sanctum`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "id": 10,
    "name": "Ahmad Fauzi",
    "email": "ahmad@example.com",
    "role": "backer",
    "balance": "500000.00",
    "email_verified_at": "2026-08-31T08:00:00.000000Z",
    "is_suspended": false
  }
}
```

---

### 4.4 Endpoint: Upgrade to Creator
- **Deskripsi:** Meningkatkan peran akun dari `backer` menjadi `creator`.
- **HTTP Method & Path:** `POST /api/v1/upgrade-to-creator`
- **Middleware:** `auth:sanctum`

#### Contoh Request:
```json
{
  "reason": "Ingin menggalang dana untuk proyek perangkat pemantau lingkungan pintar."
}
```

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "message": "Upgraded to creator successfully",
  "data": {
    "user": {
      "id": 10,
      "name": "Ahmad Fauzi",
      "email": "ahmad@example.com",
      "role": "creator",
      "balance": "500000.00",
      "email_verified_at": "2026-08-31T08:00:00.000000Z",
      "is_suspended": false
    }
  }
}
```

---

### 4.5 Endpoint: Logout (`POST /api/v1/logout`)
- **Deskripsi:** Menghapus token sesi aktif.
- **Middleware:** `auth:sanctum`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### UserResource
```json
{
  "id": 10,
  "name": "Ahmad Fauzi",
  "email": "ahmad@example.com",
  "role": "backer",
  "balance": "500000.00",
  "email_verified_at": "2026-08-31T08:00:00.000000Z",
  "is_suspended": false
}
```

---

## 6. Pengujian Postman

### Script Uji Login:
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Has Bearer Token and User Data", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.data.token).to.be.a("string");
    pm.expect(jsonData.data.user.id).to.be.a("number");
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Registrasi dengan data valid | Name, Email baru, Password valid | `201 Created` + UserResource |
| 2 | Registrasi email duplikat | Email yang sudah terdaftar | `422 Unprocessable Content` |
| 3 | Login dengan kredensial valid | Email & Password cocok | `200 OK` + Token Sanctum |
| 4 | Login akun yang ditangguhkan (suspended) | Kredensial akun suspended | `403 Forbidden` |
| 5 | Ambil profil tanpa token | Header Authorization null | `401 Unauthorized` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| `Your account has been suspended` | Akun dinonaktifkan oleh administrator | Hubungi admin platform untuk membuka penangguhan akun. |
| `Unauthenticated` pada `/me` | Token telah expired atau logout | Lakukan login ulang untuk memperoleh token Sanctum baru. |
| Email verifikasi tidak masuk | Konfigurasi SMTP belum aktif | Pastikan `QUEUE_CONNECTION` berjalan dan driver mail tervalidasi. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /register` | ✓ | - | - | - |
| `POST /login` | ✓ | - | - | - |
| `POST /logout` | ✗ | ✓ | ✓ | ✓ |
| `GET /me` | ✗ | ✓ | ✓ | ✓ |
| `POST /upgrade-to-creator` | ✗ | ✓ | - | - |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-AUTH-REG-001` | Registrasi berhasil | Positive | Data valid | `201 Created` | User tersimpan di database |
| `TC-AUTH-REG-002` | Registrasi password konfirmasi beda | Negative | Password mismatch | `422 Unprocessable` | Error "The password field confirmation does not match." |
| `TC-AUTH-LOG-001` | Login sukses | Positive | Email & Password valid | `200 OK` | Return PlainTextToken |
| `TC-AUTH-LOG-002` | Login password salah | Negative | Password salah | `422 Unprocessable` | Error "These credentials do not match our records." |
| `TC-AUTH-UPG-001` | Upgrade role sukses | Positive | Alasan valid | `200 OK` | Role berubah menjadi `creator` |
