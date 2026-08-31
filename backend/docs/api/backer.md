# CoFund API - Modul Donatur (Backer Module)

## 1. Judul & Deskripsi Modul

Modul Donatur (*Backer Module*) menyediakan endpoint analitik dan pengelolaan data aktivitas pendanaan untuk pengguna dengan peran donatur, mencakup total dana yang didonasikan, akumulasi dana yang dikembalikan (*refunded*), jumlah proyek yang didanai, dan daftar seluruh riwayat dukungan.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/Backer/BackerStatisticsController.php` | Endpoint metrik analitik donatur |
| | `backend/app/Http/Controllers/Api/BackingController.php` | Pengambilan daftar donasi pengguna |
| **Service Layer** | `backend/app/Services/StatisticsService.php` | Agregasi data kontribusi donatur |
| **Resource** | `backend/app/Http/Resources/BackingResource.php` | Format output data donasi |
| **Model** | `backend/app/Models/Backing.php` | Model relasi donasi dan kampanye |
| **Enums** | `backend/app/Enums/BackingStatus.php` | `pending`, `completed`, `refunded` |
| **Middleware** | `auth:sanctum` | Otentikasi sesi pengguna |

### Diagram Alur Agregasi Metrik Donatur

```
Backer Membuka Dasbor
        │
        ▼
[ BackerStatisticsController::index ]
        │
        ▼
[ StatisticsService::getBackerStats(user) ]
        │
        ├─► Hitung Total Dana Berstatus COMPLETED
        ├─► Hitung Total Dana Berstatus REFUNDED
        ├─► Hitung Jumlah Transaksi Backing
        ├─► Hitung Jumlah Kampanye Unik yang Didukung
        │
        ▼
Return Standard JSON Response (HTTP 200)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Enums/
│   │   └── BackingStatus.php
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── Backer/
│   │   │   │   └── BackerStatisticsController.php
│   │   │   └── BackingController.php
│   │   └── Resources/
│   │       └── BackingResource.php
│   ├── Models/
│   │   └── Backing.php
│   └── Services/
│       └── StatisticsService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Statistik Donatur (`GET /api/v1/backer/statistics`)
- **Middleware:** `auth:sanctum`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "total_backed": 2500000,
    "total_refunded": 500000,
    "total_backings": 15,
    "total_campaigns_backed": 8
  }
}
```

---

### 4.2 Endpoint: Daftar Riwayat Backing (`GET /api/v1/backings`)
- **Middleware:** `auth:sanctum`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "campaign": {
        "id": 5,
        "slug": "sensor-udara-pintar",
        "title": "Sensor Udara Pintar",
        "status": "active"
      },
      "tier": {
        "id": 1,
        "name": "Early Bird",
        "min_amount": 50000
      },
      "amount": 50000,
      "status": "completed",
      "created_at": "2026-08-31T10:30:00Z"
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

### BackerStatistics Schema
```json
{
  "total_backed": 2500000,
  "total_refunded": 500000,
  "total_backings": 15,
  "total_campaigns_backed": 8
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Required fields exist", function () {
    var data = pm.response.json().data;
    pm.expect(data.total_backed).to.be.a("number");
    pm.expect(data.total_backings).to.be.a("number");
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Donatur baru ambil statistik | Token user baru | `200 OK` + Nilai metrik 0 |
| 2 | Donatur setelah melakukan backing | Token backer aktif | `200 OK` + `total_backed` terakumulasi |
| 3 | Akses tanpa token auth | No token | `401 Unauthorized` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Nilai `total_backed` tetap 0 setelah donasi | Transaksi donasi belum berstatus `completed` | Pastikan transaksi donasi telah sukses diproses. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /backer/statistics` | ✗ | ✓ | ✓ | ✓ |
| `GET /backings` | ✗ | ✓ (Milik sendiri) | ✓ (Milik sendiri) | ✓ (Semua) |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-BAC-001` | Get statistik backer | Positive | Auth valid | `200 OK` | Metrik statistik donatur |
| `TC-BAC-002` | Get statistik tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
