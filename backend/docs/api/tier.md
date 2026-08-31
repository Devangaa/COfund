# CoFund API - Modul Paket Reward (Tier Module)

## 1. Judul & Deskripsi Modul

Modul Paket Reward mengelola penawaran paket apresiasi donasi (*reward tiers*) pada kampanye proyek, mencakup penetapan nominal minimum donasi, pembatasan kuota slot donatur, kalkulasi ketersediaan slot tersisa (*remaining quota*), dan deskripsi benefit reward.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/TierController.php` | Method `store`, `update`, `destroyMany` |
| **Service Layer** | `backend/app/Services/TierService.php` | Enkapsulasi logika tier dan validasi status `draft` |
| **Form Requests** | `backend/app/Http/Requests/StoreTierRequest.php` | Validasi penambahan tier baru |
| | `backend/app/Http/Requests/UpdateTierRequest.php` | Validasi pembaruan tier |
| | `backend/app/Http/Requests/DeleteTierRequest.php` | Validasi batch ID tier untuk dihapus |
| **Resource** | `backend/app/Http/Resources/TierResource.php` | Serialisasi format output tier |
| **Model** | `backend/app/Models/CampaignTier.php` | Model tier dengan accessor `remaining_quota` |

### Diagram Alur Manipulasi & Validasi Kuota Tier

```
Creator Tambah / Edit Tier
        │
        ▼
[ FormRequest Validation ]
  - min_amount >= 10.000
  - quota >= 0 (0 = Unlimited)
        │
        ▼
[ TierService ]
  - Pastikan kampanye berstatus DRAFT
  - Simpan / Perbarui Tier di Database
        │
        ▼
Saat Backer Melakukan Backing
        │
        ▼
[ Sisa Kuota Dihitung Otomatis ]
  remaining_quota = quota - total_completed_backings
  Jika remaining_quota <= 0 ──► Status: Sold Out
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── TierController.php
│   │   ├── Requests/
│   │   │   ├── DeleteTierRequest.php
│   │   │   ├── StoreTierRequest.php
│   │   │   └── UpdateTierRequest.php
│   │   └── Resources/
│   │       └── TierResource.php
│   ├── Models/
│   │   └── CampaignTier.php
│   └── Services/
│       └── TierService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Tambah Paket Reward Baru (`POST /api/v1/campaigns/{campaign:slug}/tiers`)
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Parameter Body
| Field | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `name` | string | Ya | `required, string, max:255` | Nama paket reward |
| `min_amount` | numeric | Ya | `required, numeric, min:10000` | Batas minimal donasi (Min: 10.000) |
| `quota` | integer | Ya | `required, integer, min:0` | Kuota slot (`0` = Unlimited) |
| `reward_description` | string | Tidak | `nullable, string, max:5000` | Rincian fasilitas hadiah |

#### Contoh Response (`201 Created`):
```json
{
  "success": true,
  "message": "Tier created successfully",
  "data": {
    "id": 21,
    "name": "Paket Early Adopter Device",
    "min_amount": 500000,
    "quota": 50,
    "remaining_quota": 50,
    "reward_description": "Mendapatkan 1 unit alat prototipe siap pakai."
  }
}
```

---

### 4.2 Endpoint: Hapus Batch Tier (`DELETE /api/v1/campaigns/{campaign:slug}/tiers`)
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Contoh Request:
```json
{
  "ids": [21, 22]
}
```

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "message": "Selected tiers deleted successfully"
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### TierResource
```json
{
  "id": 21,
  "name": "Paket Early Adopter Device",
  "min_amount": 500000,
  "quota": 50,
  "remaining_quota": 50,
  "reward_description": "Mendapatkan 1 unit alat prototipe siap pakai."
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Tier created with valid remaining quota", function () {
    var data = pm.response.json().data;
    pm.expect(data.remaining_quota).to.eql(data.quota);
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Tambah tier data valid | Nama, Min 50.000, Kuota 20 | `201 Created` + Data tier |
| 2 | Tambah tier nominal < 10.000 | `min_amount: 5000` | `422 Unprocessable Content` |
| 3 | Edit tier pada kampanye active | Update saat kampanye active | `400 Bad Request` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Gagal edit tier pada kampanye | Kampanye sudah berstatus `active` | Paket tier hanya dapat dimodifikasi saat kampanye masih berstatus `draft`. |
| Sisa kuota tidak berkurang | Status backing belum `completed` | Sisa kuota otomatis berkurang ketika transaksi donasi berstatus sukses. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /campaigns/{slug}/tiers` | ✗ | ✗ | ✓ (Owner) | ✗ |
| `PUT /campaigns/{slug}/tiers/{tier}` | ✗ | ✗ | ✓ (Owner) | ✗ |
| `DELETE /campaigns/{slug}/tiers` | ✗ | ✗ | ✓ (Owner) | ✗ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-TIER-001` | Tambah tier sukses | Positive | Data valid | `201 Created` | Tier tersimpan |
| `TC-TIER-002` | Tambah tier kuota negatif | Negative | `quota: -5` | `422 Unprocessable` | Error "The quota must be at least 0." |
| `TC-TIER-003` | Non-owner ubah tier | Security | Token creator lain | `403 Forbidden` | Error unauthorized |
