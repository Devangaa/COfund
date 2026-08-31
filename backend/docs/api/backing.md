# CoFund API - Modul Dukungan Pendanaan (Backing Module)

## 1. Judul & Deskripsi Modul

Modul Dukungan Pendanaan (*Backing Module*) mengelola proses transaksi pendanaan kampanye dari donatur ke proyek yang sedang aktif (`status: active`), validasi batasan nominal minimal dan kuota reward tier, penguncian dana ke dalam Virtual Escrow, penambahan dana terkumpul (*collected amount*), serta emisi event notifikasi dan pemicu pencairan otomatis saat target pendanaan tercapai.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/BackingController.php` | Endpoint `store`, `index`, `indexByCampaign` |
| **Service Layer** | `backend/app/Services/BackingService.php` | Logika bisnis backing, validasi kuota tier, pencapaian target |
| **Form Request** | `backend/app/Http/Requests/StoreBackingRequest.php` | Validasi nominal dan reward tier |
| **Resource** | `backend/app/Http/Resources/BackingResource.php` | Serialisasi format output backing |
| **Model** | `backend/app/Models/Backing.php` | Model Backing dengan relasi campaign, tier, dan user |
| **Enums** | `backend/app/Enums/BackingStatus.php` | `pending`, `completed`, `refunded` |
| | `backend/app/Enums/TransactionType.php` | `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal` |
| | `backend/app/Enums/CampaignStatus.php` | `draft`, `review`, `active`, `success`, `failed` |
| **Events** | `backend/app/Events/BackingCreated.php` | Event pemicu mutasi saldo & notifikasi |
| | `backend/app/Events/CampaignFunded.php` | Event pemicu pencairan kampanye |

### Diagram Alur Transaksi Backing & Virtual Escrow

```
Backer Klik Dukung Kampanye
        │
        ▼
[ StoreBackingRequest::validate ]
  - Nominal min Rp 10.000 / sesuai min_amount tier
  - Kuota tier masih tersedia (remaining_quota > 0)
        │
        ▼
[ BackingService::create ]
  - Pastikan status kampanye ACTIVE
  - Cegah Self-Backing (kreator mendanai proyek sendiri)
  - Pastikan user.balance >= amount
        │
        ▼
[ DB::transaction ]
  - Potong saldo donatur ($user->withdraw($amount))
  - Simpan record Backing (status: COMPLETED)
  - Catat transaksi buku besar (type: PAYMENT)
  - Kurangi sisa kuota tier (remaining_quota--)
  - Tambahkan collected_amount pada kampanye
  - Jika collected >= target ──► Update status kampanye = SUCCESS
        │
        ▼
Return BackingResource (HTTP 201 Created)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Enums/
│   │   ├── BackingStatus.php
│   │   └── CampaignStatus.php
│   ├── Events/
│   │   ├── BackingCreated.php
│   │   └── CampaignFunded.php
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── BackingController.php
│   │   ├── Requests/
│   │   │   └── StoreBackingRequest.php
│   │   └── Resources/
│   │       └── BackingResource.php
│   ├── Models/
│   │   └── Backing.php
│   └── Services/
│       └── BackingService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Buat Dukungan Pendanaan (`POST /api/v1/campaigns/{campaign:slug}/back`)
- **Middleware:** `auth:sanctum`, `verified`

#### Parameter Body
| Field | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `amount` | numeric | Ya | `required, numeric, min:10000` | Nominal donasi (Min: 10.000) |
| `tier_id` | integer | Tidak | `nullable, exists:campaign_tiers,id` | ID paket reward yang dipilih |

#### Contoh Request:
```json
{
  "amount": 500000,
  "tier_id": 21
}
```

#### Contoh Response (`201 Created`):
```json
{
  "success": true,
  "message": "Backing created successfully",
  "data": {
    "id": 1,
    "amount": 500000,
    "status": "completed",
    "campaign": {
      "id": 10,
      "slug": "sensor-udara-pintar",
      "title": "Sensor Udara Pintar",
      "status": "active"
    },
    "tier": {
      "id": 21,
      "name": "Early Bird"
    },
    "created_at": "2026-08-31T11:00:00Z"
  }
}
```

---

### 4.2 Endpoint: Daftar Donatur Kampanye (`GET /api/v1/campaigns/{campaign:slug}/backings`)
- **Middleware:** `auth:sanctum`, `verified` (Khusus Creator pemilik & Admin)

---

## 5. Skema Sumber Daya (Resource Schema)

### BackingResource
```json
{
  "id": 1,
  "amount": 500000,
  "status": "completed",
  "campaign": { "id": 10, "slug": "sensor-udara-pintar", "title": "Sensor Udara Pintar" },
  "tier": { "id": 21, "name": "Early Bird" },
  "created_at": "2026-08-31T11:00:00Z"
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Backing is completed", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.status).to.eql("completed");
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Backing dengan saldo cukup | `amount: 100000` | `201 Created` + Backing sukses |
| 2 | Backing saldo tidak cukup | `amount: 99999999` | `422 Unprocessable Content` |
| 3 | Kreator backing proyek sendiri | Slug kampanye milik sendiri | `403 Forbidden` |
| 4 | Backing pada kampanye draft | Slug kampanye draft | `422 Unprocessable Content` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| `Creator cannot back their own campaign` | Inisiator mencoba mendanai proyeknya sendiri | Gunakan akun donatur lain untuk melakukan pengujian donasi. |
| `This tier is full` | Kuota paket reward telah habis | Pilih paket reward lain atau lakukan donasi tanpa paket tier. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /campaigns/{slug}/back` | ✗ | ✓ | ✓ (Bukan owner) | ✗ |
| `GET /campaigns/{slug}/backings` | ✗ | ✗ | ✓ (Owner) | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-BCK-001` | Backing normal sukses | Positive | Saldo mencukupi | `201 Created` | Status `completed` |
| `TC-BCK-002` | Self-backing dicegah | Security | Akun creator owner | `403 Forbidden` | Error self backing |
| `TC-BCK-003` | Backing email unverified | Security | Email belum verified | `403 Forbidden` | Error verification |
