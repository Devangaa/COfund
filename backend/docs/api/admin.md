# CoFund API - Modul Administrasi Platform (Admin Module)

## 1. Judul & Deskripsi Modul

Modul Administrasi Platform digunakan secara eksklusif oleh Administrator untuk mengelola seluruh data pengguna (termasuk aksi penangguhan/*suspend* dan pemulihan/*unsuspend* akun), memproses moderasi persetujuan (*approve*), penolakan (*reject*), dan pembatalan paksa (*force-fail*) kampanye proyek, serta memantau ringkasan analitik keuangan dan akumulasi biaya platform (5% *platform fee*).

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/Admin/UserController.php` | CRUD dan suspend/unsuspend akun user |
| | `backend/app/Http/Controllers/Api/Admin/StatisticsController.php` | Analitik performa platform & fee |
| | `backend/app/Http/Controllers/Api/CampaignController.php` | Method `approve`, `reject`, `forceFail` |
| **Service Layer** | `backend/app/Services/UserService.php` | Logika suspend, unsuspend, dan agregasi data user |
| | `backend/app/Services/StatisticsService.php` | Logika kalkulasi metrik platform, kampanye, dan fee |
| | `backend/app/Services/CampaignService.php` | Logika transisi status kampanye |
| **Form Requests** | `backend/app/Http/Requests/IndexUserRequest.php` | Validasi filter & paginasi list user |
| | `backend/app/Http/Requests/IndexStatisticsRequest.php` | Validasi rentang tanggal statistik |
| | `backend/app/Http/Requests/RejectCampaignRequest.php` | Validasi alasan penolakan kampanye |
| **Resource** | `backend/app/Http/Resources/UserResource.php` | Transformasi serialisasi JSON data user |
| **Middleware** | `auth:sanctum`, `role:admin` | Pembatasan hak akses khusus administrator |

### Diagram Alur Moderasi Kampanye oleh Admin

```
Admin Login (Role: Admin)
        │
        ▼
[ Admin Melakukan Tinjauan Kampanye ]
        │
        ├─► [ Approve: PUT /api/v1/admin/campaigns/{slug}/approve ]
        │         └─► Set status = 'active', catat approved_by & approved_at
        │
        ├─► [ Reject: PUT /api/v1/admin/campaigns/{slug}/reject ]
        │         └─► Set status = 'draft', simpan rejection_note, notifikasi kreator
        │
        └─► [ Force-Fail: PUT /api/v1/admin/campaigns/{slug}/force-fail ]
                  └─► Set status = 'failed', dispatch RefundBackersJob (100% refund)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── Admin/
│   │   │   │   ├── StatisticsController.php
│   │   │   │   └── UserController.php
│   │   │   └── CampaignController.php
│   │   ├── Requests/
│   │   │   ├── IndexStatisticsRequest.php
│   │   │   ├── IndexUserRequest.php
│   │   │   └── RejectCampaignRequest.php
│   │   └── Resources/
│   │       └── UserResource.php
│   └── Services/
│       ├── CampaignService.php
│       ├── StatisticsService.php
│       └── UserService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Daftar Pengguna (`GET /api/v1/admin/users`)
- **Middleware:** `auth:sanctum`, `role:admin`

#### Parameter Query
| Parameter | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `role` | string | Tidak | Filter role (`backer`, `creator`, `admin`) |
| `is_suspended` | boolean | Tidak | Filter status penangguhan (`true`, `false`, `1`, `0`) |
| `search` | string | Tidak | Pencarian nama atau email pengguna |
| `page` & `per_page` | integer | Tidak | Pagination (Default per_page: 10) |

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "name": "Siti Rahma",
      "email": "siti@example.com",
      "role": "creator",
      "balance": "15000000.00",
      "email_verified_at": "2026-08-20T10:00:00.000000Z",
      "is_suspended": false
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 10,
      "total": 48
    }
  }
}
```

---

### 4.2 Endpoint: Analitik Platform Global (`GET /api/v1/admin/statistics`)
- **Middleware:** `auth:sanctum`, `role:admin`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "total_users": 150,
    "total_backers": 120,
    "total_creators": 28,
    "total_campaigns": 35,
    "active_campaigns": 12,
    "successful_campaigns": 18,
    "failed_campaigns": 5,
    "total_funds_collected": 450000000,
    "total_platform_fees": 22500000,
    "platform_fee_rate": 0.05
  }
}
```

---

### 4.3 Endpoint: Penangguhan Akun (`PUT /api/v1/admin/users/{user}/suspend`)
- **Deskripsi:** Menangguhkan akun pengguna (terdapat pencegahan self-suspend).
- **Middleware:** `auth:sanctum`, `role:admin`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "message": "User suspended successfully",
  "data": {
    "id": 2,
    "name": "Siti Rahma",
    "is_suspended": true
  }
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### AdminStatistics Schema
```json
{
  "total_users": 150,
  "total_backers": 120,
  "total_creators": 28,
  "total_campaigns": 35,
  "active_campaigns": 12,
  "successful_campaigns": 18,
  "failed_campaigns": 5,
  "total_funds_collected": 450000000,
  "total_platform_fees": 22500000,
  "platform_fee_rate": 0.05
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Platform fee calculation is correct", function () {
    var data = pm.response.json().data;
    pm.expect(data.platform_fee_rate).to.eql(0.05);
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Admin get list user | Token admin valid | `200 OK` + Data array user |
| 2 | Admin suspend diri sendiri | Suspend ID akun admin yang sedang login | `400 Bad Request` / Error pencegahan |
| 3 | Non-admin akses rute admin | Token backer / creator | `403 Forbidden` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Admin tidak bisa approve kampanye | Kampanye tidak dalam status `review` | Kampanye harus diajukan review oleh kreator terlebih dahulu (`status: review`). |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /admin/users` | ✗ | ✗ | ✗ | ✓ |
| `PUT /admin/users/{id}/suspend` | ✗ | ✗ | ✗ | ✓ |
| `GET /admin/statistics` | ✗ | ✗ | ✗ | ✓ |
| `PUT /admin/campaigns/{slug}/approve` | ✗ | ✗ | ✗ | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-ADM-001` | Get platform stats | Positive | Token admin | `200 OK` | Metrik platform lengkap |
| `TC-ADM-002` | Suspend user valid | Positive | ID user backer | `200 OK` | `is_suspended: true` |
| `TC-ADM-003` | Akses tanpa role admin | Security | Token creator | `403 Forbidden` | Error unauthorized |
