# CoFund API - Modul Admin (Admin Module)

## 1. Judul & Deskripsi Modul

Modul admin mencakup dua sub-modul:
1. **User Management** — Kelola daftar pengguna, suspend/unsuspend akun, dan lihat detail pengguna.
2. **Platform Statistics** — Statistik platform lengkap untuk admin.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/Admin/UserController.php` | CRUD & suspend/unsuspend user |
| | `app/Http/Controllers/Api/Admin/StatisticsController.php` | Statistik platform |
| **Service** | `app/Services/UserService.php` | Logika bisnis suspend/unsuspend, getUserStats |
| **Resource** | `app/Http/Resources/UserResource.php` | Serialisasi data user |
| **Model** | `app/Models/User.php`, `app/Models/Campaign.php`, `app/Models/Backing.php`, `app/Models/Transaction.php` | Model untuk query statistik |
| **Enums** | `app/Enums/CampaignStatus.php` | Status kampanye |
| **Event** | `app/Events\UserSuspended.php`, `app/Events\UserUnsuspended.php` | Event suspend/unsuspend |
| **Listener** | `app/Listeners/HandleUserSuspended.php`, `app/Listeners/HandleUserUnsuspended.php` | Notifikasi & email |
| **Middleware** | `auth:sanctum`, `role:admin` | Hanya admin yang dapat mengakses |
| **Config** | `config/cofund.php` (expected) | `platform_fee` rate |

### Alur Proses Logika Bisnis

```
Admin login
        |
        v
GET /api/v1/admin/users
GET /api/v1/admin/users/{id}
PUT /api/v1/admin/users/{id}/suspend
PUT /api/v1/admin/users/{id}/unsuspend
GET /api/v1/admin/statistics
        |
        v
AuthServiceProvider + RouteServiceProvider
  - role:admin middleware
        |
        v
UserService / StatisticsController
  - suspend: check self-suspend prevention
  - unsuspend: toggle is_suspended
  - statistics: aggregate queries
        |
        v
Event: UserSuspended / UserUnsuspended
        |
        v
HandleUserSuspended / HandleUserUnsuspended
  - Create in-app notification
  - Send email (if verified)
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── Admin/
│   │   │       │   ├── UserController.php
│   │   │       │   └── StatisticsController.php
│   │   ├── Resources/
│   │   │   └── UserResource.php
│   │   └── Middleware/
│   │       ├── RoleMiddleware.php
│   │       └── Authenticate.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Campaign.php
│   │   ├── Backing.php
│   │   └── Transaction.php
│   ├── Enums/
│   │   └── CampaignStatus.php
│   ├── Events/
│   │   ├── UserSuspended.php
│   │   └── UserUnsuspended.php
│   ├── Listeners/
│   │   ├── HandleUserSuspended.php
│   │   └── HandleUserUnsuspended.php
│   ├── Services/
│   │   └── UserService.php
│   └── Jobs/
│       ├── DisburseCampaignJob.php
│       └── RefundBackersJob.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: List Users

- **Deskripsi:** Mendapatkan daftar semua pengguna dengan filter peran, status penangguhan, dan pencarian.
- **HTTP Method & URL Path:** `GET /api/v1/admin/users`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Tabel Parameter (Query)

| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `role` | string | Tidak | `in:backer,creator,admin` | Filter berdasarkan peran |
| `is_suspended` | boolean | Tidak | boolean | Filter berdasarkan status penangguhan |
| `search` | string | Tidak | `max:255` | Cari berdasarkan nama/email |
| `per_page` | integer | Tidak | `min:1`, `max:50` | Item per halaman (default: 10, maksimal: 50) |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "backer",
            "balance": 500000,
            "email_verified_at": "2024-01-15T10:30:00Z",
            "is_suspended": false
        }
    ],
    "meta": {
        "pagination": {
            "current_page": 1,
            "last_page": 3,
            "per_page": 10,
            "total": 30
        }
    }
}
```

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 403 | `{"success":false,"message":"Unauthorized. This action requires Admin role."}` | Bukan peran admin |

---

### 4.2 Endpoint: Show User

- **Deskripsi:** Mendapatkan detail pengguna termasuk statistik campaign dan backing.
- **HTTP Method & URL Path:** `GET /api/v1/admin/users/{user}`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": {
        "user": {
            "id": 2,
            "name": "Jane Creator",
            "email": "jane@example.com",
            "role": "creator",
            "balance": 25000000,
            "email_verified_at": "2024-01-10T08:00:00Z",
            "is_suspended": false
        },
        "stats": {
            "total_backings": 5,
            "total_campaigns_created": 2,
            "total_amount_backed": 0
        }
    }
}
```

#### Efek Samping

- Memuat relasi campaigns dan backings
- Menghitung statistik sederhana

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 404 | `{"message":"No query results for model ..."}` | User tidak ditemukan |

---

### 4.3 Endpoint: Suspend User

- **Deskripsi:** Menonaktifkan akun pengguna. Mencecek pencegahan self-suspension.
- **HTTP Method & URL Path:** `PUT /api/v1/admin/users/{user}/suspend`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "User suspended successfully",
    "data": {
        "id": 2,
        "name": "Jane Creator",
        "email": "jane@example.com",
        "role": "creator",
        "balance": 25000000,
        "email_verified_at": "2024-01-10T08:00:00Z",
        "is_suspended": true
    }
}
```

#### Efek Samping

- User `is_suspended` diubah ke `true`
- `suspended_at` di-set
- Trigger event `UserSuspended`
- Kirim notifikasi in-app & email ke user

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"user":["You cannot suspend yourself"]}}` | Admin mencoba suspend diri sendiri |

---

### 4.4 Endpoint: Unsuspend User

- **Deskripsi:** Mengaktifkan kembali akun pengguna yang disuspend.
- **HTTP Method & URL Path:** `PUT /api/v1/admin/users/{user}/unsuspend`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "User unsuspended successfully",
    "data": {
        "id": 2,
        "is_suspended": false
    }
}
```

#### Efek Samping

- User `is_suspended` diubah ke `false`
- `suspended_at` di-set ke `null`
- Trigger event `UserUnsuspended`
- Kirim notifikasi in-app & email ke user

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 404 | (not found) | User tidak ditemukan |

---

### 4.5 Endpoint: Platform Statistics

- **Deskripsi:** Statistik platform lengkap untuk admin.
- **HTTP Method & URL Path:** `GET /api/v1/admin/statistics`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Tabel Parameter (Query)

| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `period` | string | Tidak | `in:daily,weekly,monthly,yearly` | Periode grafik (default: `daily`) |
| `start_date` | date | Tidak | - | Tanggal mulai |
| `end_date` | date | Tidak | - | Tanggal akhir |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": {
        "total_users": 150,
        "total_campaigns": 25,
        "total_backings": 350,
        "total_collected": 150000000,
        "total_target": 500000000,
        "total_fee": 35000000,
        "platform_fee_rate": 0.05,
        "completion_rate": 30.0,
        "status_distribution": {
            "draft": 5,
            "review": 3,
            "active": 12,
            "success": 3,
            "failed": 2
        },
        "top_campaigns": [
            {
                "id": 1,
                "title": "Platform Donasi",
                "slug": "platform-donasi",
                "collected_amount": 50000000,
                "target_amount": 100000000,
                "backings_count": 150,
                "status": "success"
            }
        ],
        "chart": [
            {
                "period": "2024-01",
                "campaigns": 5,
                "collected": 75000000
            }
        ]
    }
}
```

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
| `id` | integer | Primary key |
| `name` | string | Nama lengkap |
| `email` | string | Email |
| `role` | enum | `backer`, `creator`, `admin` |
| `balance` | decimal | Saldo dompet |
| `email_verified_at` | datetime\|null | Timestamp verifikasi |
| `is_suspended` | boolean | Status penangguhan |

---

## 6. Pengujian Postman

### List Users (Admin)

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/admin/users?role=creator&is_suspended=false`
3. Headers: `Authorization: Bearer {{admin_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("All roles are creator", function () {
    var jsonData = pm.response.json();
    jsonData.data.forEach(user => {
        pm.expect(user.role).to.eql("creator");
    });
});
```

### Suspend User

1. Method: `PUT`
2. URL: `{{base_url}}/api/v1/admin/users/2/suspend`
3. Headers: `Authorization: Bearer {{admin_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("User suspended", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.is_suspended).to.be.true;
});
```

### Platform Statistics

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/admin/statistics?period=daily`
3. Headers: `Authorization: Bearer {{admin_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Has all required fields", function () {
    var data = pm.response.json().data;
    pm.expect(data.total_users).to.be.a("number");
    pm.expect(data.total_campaigns).to.be.a("number");
    pm.expect(data.top_campaigns).to.be.an("array");
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | List all users | GET /admin/users | Semua user dengan pagination |
| 2 | Filter role creator | `?role=creator` | Hanya user dengan role creator |
| 3 | Filter suspended | `?is_suspended=true` | Hanya user yang disuspend |
| 4 | Search by email | `?search=john` | User dengan email/nama mengandung "john" |
| 5 | Show user detail | GET /admin/users/1 | Detail user + stats |
| 6 | Suspend user | PUT /admin/users/2/suspend | User disuspend + notifikasi terkirim |
| 7 | Unsuspend user | PUT /admin/users/2/unsuspend | User diaktifkan kembali + notifikasi |
| 8 | Self-suspend prevention | Admin suspend diri sendiri | HTTP 422, "You cannot suspend yourself" |
| 9 | Suspend user yang sudah disuspend | PUT /admin/users/2/suspend | Tidak error, hanya return (idempotent) |
| 10 | Get platform statistics | GET /admin/statistics | Data statistik platform |
| 11 | Non-admin mengakses | Login sebagai backer | HTTP 403, "Admin role required" |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| "You cannot suspend yourself" | Admin tidak dapat menonaktifkan akunnya sendiri. Ini adalah fitur keamanan preventif. |
| User tidak bisa login setelah disuspend | Ini perilaku yang benar. Akun yang disuspend tidak dapat melakukan login aktif. |
| Email tidak terkirim saat suspend | Pastikan email user terverifikasi dan queue worker berjalan. Email hanya dikirim ke user yang emailnya terverifikasi. |
| Platform fee rate inconsistency | **RESOLVED** — `config/cofund.php` dibuat dengan default 5% (`PLATFORM_FEE_RATE=0.05`). `StatisticsController` dan `CreatorStatisticsController` sekarang menggunakan `config('cofund.platform_fee', 0.05)`. `DisburseCampaignJob` juga menggunakan config yang sama. |
| `top_campaigns` kosong | Pastikan ada kampanye yang memiliki backing. Query `withCount('backings')` dan `orderByDesc('collected_amount')`. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /api/v1/admin/users` | - | - | - | ✓ |
| `GET /api/v1/admin/users/{user}` | - | - | - | ✓ |
| `PUT /api/v1/admin/users/{user}/suspend` | - | - | - | ✓ |
| `PUT /api/v1/admin/users/{user}/unsuspend` | - | - | - | ✓ |
| `GET /api/v1/admin/statistics` | - | - | - | ✓ |

---

## 10. Matriks Kasus Pengujian (Test Case)

### 10.1 List Users

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-ADM-USER-001` | List semua user | Positive | Admin login | `200 OK` | Semua user, meta pagination |
| `TC-ADM-USER-002` | List user dengan filter role | Positive | `?role=creator` | `200 OK` | Hanya user dengan role creator |
| `TC-ADM-USER-003` | List user dengan filter is_suspended | Positive | `?is_suspended=true` | `200 OK` | Hanya user yang disuspend |
| `TC-ADM-USER-004` | List user dengan search | Positive | `?search=john` | `200 OK` | User dengan nama/email mengandung "john" |
| `TC-ADM-USER-005` | List user dengan pagination | Positive | `?page=2&per_page=15` | `200 OK` | Halaman 2, 15 item |
| `TC-ADM-USER-006` | List user tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-ADM-USER-007` | List user sebagai creator | Security | Role creator | `403 Forbidden` | Error "Admin role required" |
| `TC-ADM-USER-008` | List user sebagai backer | Security | Role backer | `403 Forbidden` | Error "Admin role required" |
| `TC-ADM-USER-009` | List user dengan role invalid | Negative | `?role=superadmin` | `422 Unprocessable` | Error enum tidak valid |
| `TC-ADM-USER-010` | List user dengan per_page > 50 | Positive | `?per_page=999` | `200 OK` | Dibatasi ke 50 |
| `TC-ADM-USER-011` | List user dengan is_suspended non-boolean | Negative | `?is_suspended=yes` | `422 Unprocessable` | Error boolean |

### 10.2 Show User

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-ADM-USER-SHOW-001` | Get detail user | Positive | Admin login | `200 OK` | UserResource + stats |
| `TC-ADM-USER-SHOW-002` | Get detail user tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-ADM-USER-SHOW-003` | Get detail user sebagai non-admin | Security | Role tidak admin | `403 Forbidden` | Error "Admin role required" |
| `TC-ADM-USER-SHOW-004` | Get detail user yang tidak ada | Negative | `user_id: 99999` | `404 Not Found` | Error "User not found" |

### 10.3 Suspend User

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-ADM-SUSPEND-001` | Suspend user yang valid | Positive | PUT suspend, user ada | `200 OK` | `is_suspended: true`, notifikasi & email terkirim |
| `TC-ADM-SUSPEND-002` | Suspend user yang tidak ada | Negative | `user_id: 99999` | `404 Not Found` | Error "User not found" |
| `TC-ADM-SUSPEND-003` | Suspend user sendiri (admin) | Business Logic | Suspend diri sendiri | `409 Conflict` | Error "You cannot suspend yourself" |
| `TC-ADM-SUSPEND-004` | Suspend user yang sudah disuspend | Business Logic | User sudah `is_suspended: true` | `409 Conflict` | Error "User is already suspended" |
| `TC-ADM-SUSPEND-005` | Suspend user tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-ADM-SUSPEND-006` | Suspend user sebagai backer | Security | Role backer | `403 Forbidden` | Error "Admin role required" |
| `TC-ADM-SUSPEND-007` | Suspend user yang sedang login | Positive | Suspend user aktif | `200 OK` | Token tidak langsung invalid (sampai token dicabut) |

### 10.4 Unsuspend User

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-ADM-UNSUSPEND-001` | Unsuspend user yang valid | Positive | PUT unsuspend | `200 OK` | `is_suspended: false`, notifikasi & email terkirim |
| `TC-ADM-UNSUSPEND-002` | Unsuspend user yang tidak disuspend | Business Logic | `is_suspended: false` | `409 Conflict` | Error "User is not suspended" |
| `TC-ADM-UNSUSPEND-003` | Unsuspend user yang tidak ada | Negative | `user_id: 99999` | `404 Not Found` | Error "User not found" |
| `TC-ADM-UNSUSPEND-004` | Unsuspend user tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-ADM-UNSUSPEND-005` | Unsuspend user sebagai creator | Security | Role creator | `403 Forbidden` | Error "Admin role required" |

### 10.5 Platform Statistics

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-ADM-STAT-001` | Get statistik platform default | Positive | Admin login | `200 OK` | Semua statistik lengkap |
| `TC-ADM-STAT-002` | Get statistik dengan period | Positive | `?period=monthly` | `200 OK` | Chart data bulanan |
| `TC-ADM-STAT-003` | Get statistik dengan date filter | Positive | `?start_date=2024-01-01&end_date=2024-12-31` | `200 OK` | Data dalam rentang |
| `TC-ADM-STAT-004` | Get statistik tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-ADM-STAT-005` | Get statistik sebagai creator | Security | Role creator | `403 Forbidden` | Error "Admin role required" |
| `TC-ADM-STAT-006` | Get statistik sebagai backer | Security | Role backer | `403 Forbidden` | Error "Admin role required" |
| `TC-ADM-STAT-007` | Get statistik dengan period invalid | Negative | `?period=invalid` | `422 Unprocessable` | Error validasi |
| `TC-ADM-STAT-008` | Get statistik dengan date range tidak valid | Negative | `start_date > end_date` | `422 Unprocessable` | Error validasi tanggal |
| `TC-ADM-STAT-009` | Spam statistik request | Throttling | Rapid requests | `429 Too Many Requests` | Rate limited |
