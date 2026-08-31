# CoFund API - Modul Dasbor Inisiator (Creator Module)

## 1. Judul & Deskripsi Modul

Modul Inisiator (*Creator Module*) menyediakan antarmuka analitik performa proyek yang dikelola oleh pembuat kampanye, meliputi total dana terhimpun, rasio proyek sukses, proyeksi dana bersih setelah potongan fee platform (5%), serta query daftar kampanye milik pribadi (`scope=mine`).

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/Creator/CreatorStatisticsController.php` | Endpoint metrik analitik kreator |
| | `backend/app/Http/Controllers/Api/CampaignController.php` | Query kampanye milik sendiri (`scope=mine`) |
| **Service Layer** | `backend/app/Services/StatisticsService.php` | Kalkulasi agregasi performa kampanye inisiator |
| **Form Requests** | `backend/app/Http/Requests/IndexStatisticsRequest.php` | Validasi rentang tanggal statistik |
| | `backend/app/Http/Requests/IndexCampaignRequest.php` | Validasi query parameter filter kampanye |
| **Middleware** | `auth:sanctum`, `role:creator`, `verified` | Proteksi otentikasi dan hak akses kreator |

### Diagram Alur Agregasi Metrik Kreator

```
Creator Request Dashboard Stats
        │
        ▼
[ CreatorStatisticsController::index ]
        │
        ▼
[ StatisticsService::getCreatorStats(user) ]
        │
        ├─► Hitung Total Kampanye Dibuat
        ├─► Hitung Kampanye Active, Success, Failed
        ├─► Jumlahkan Total Dana Terkumpul
        ├─► Hitung Estimasi Dana Bersih (95%)
        │
        ▼
Return Standard JSON Response (HTTP 200)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── CampaignController.php
│   │   │   └── Creator/
│   │   │       └── CreatorStatisticsController.php
│   │   └── Requests/
│   │       ├── IndexCampaignRequest.php
│   │       └── IndexStatisticsRequest.php
│   └── Services/
│       └── StatisticsService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Statistik Analitik Kreator (`GET /api/v1/creator/statistics`)
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Parameter Query
| Parameter | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `start_date` | date | Tidak | Filter tanggal mulai (YYYY-MM-DD) |
| `end_date` | date | Tidak | Filter tanggal akhir (YYYY-MM-DD) |

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "total_campaigns": 3,
    "active_campaigns": 1,
    "successful_campaigns": 2,
    "failed_campaigns": 0,
    "total_funds_raised": 75000000,
    "total_backers": 85,
    "platform_fee_rate": 0.05,
    "estimated_net_funds": 71250000
  }
}
```

---

### 4.2 Endpoint: Kampanye Saya (`GET /api/v1/campaigns?scope=mine`)
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "title": "Perangkat IoT Pendeteksi Udara",
      "slug": "perangkat-iot-pendeteksi-udara",
      "status": "active",
      "target_amount": 50000000,
      "collected_amount": 32500000,
      "progress_percentage": 65,
      "deadline": "2026-10-31"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 1
    }
  }
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### CreatorStatistics Schema
```json
{
  "total_campaigns": 3,
  "active_campaigns": 1,
  "successful_campaigns": 2,
  "failed_campaigns": 0,
  "total_funds_raised": 75000000,
  "total_backers": 85,
  "platform_fee_rate": 0.05,
  "estimated_net_funds": 71250000
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Has valid creator statistics metrics", function () {
    var data = pm.response.json().data;
    pm.expect(data.total_campaigns).to.be.a("number");
    pm.expect(data.platform_fee_rate).to.eql(0.05);
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Creator baru akses statistik | Token creator tanpa kampanye | `200 OK` + Seluruh nilai bernilai 0 |
| 2 | Backer akses statistik creator | Token backer | `403 Forbidden` |
| 3 | Akses tanpa login | No token | `401 Unauthorized` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| `403 Creator role required` | Akun masih berstatus donatur (backer) | Lakukan upgrade role via `POST /api/v1/upgrade-to-creator`. |
| Nilai dana bersih tidak sesuai | Potongan fee belum dihitung | Sistem otomatis mengenakan `platform_fee_rate = 0.05` (5%). |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /creator/statistics` | ✗ | ✗ | ✓ | ✗ |
| `GET /campaigns?scope=mine` | ✗ | ✗ | ✓ | ✗ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-CRE-001` | Get statistik creator valid | Positive | Token creator terverifikasi | `200 OK` | Metrik analitik lengkap |
| `TC-CRE-002` | Get statistik tanpa email verify | Security | Email belum verified | `403 Forbidden` | Error "Email verification required." |
| `TC-CRE-003` | Get statistik via token backer | Security | Role backer | `403 Forbidden` | Error "Creator role required." |
