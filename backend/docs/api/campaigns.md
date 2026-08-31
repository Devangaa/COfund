# CoFund API - Modul Manajemen Kampanye (Campaigns Module)

## 1. Judul & Deskripsi Modul

Modul Kampanye menangani seluruh siklus hidup proyek penggalangan dana (*crowdfunding campaigns*), mencakup eksplorasi katalog publik dengan filter multikriteria dan pengurutan, penampilan detail kampanye, pembuatan draf kampanye oleh kreator, pengunggahan proposal, pengajuan review, serta aksi peninjauan admin (*approve*, *reject*, *force-fail*).

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/CampaignController.php` | CRUD, submit review, approve, reject, force-fail |
| **Service Layer** | `backend/app/Services/CampaignService.php` | Enkapsulasi logika transisi status & filter query |
| **Form Requests** | `backend/app/Http/Requests/IndexCampaignRequest.php` | Validasi parameter filter, pencarian, dan paginasi |
| | `backend/app/Http/Requests/StoreCampaignRequest.php` | Validasi data pembuatan kampanye baru |
| | `backend/app/Http/Requests/UpdateCampaignRequest.php` | Validasi pembaruan draf kampanye |
| | `backend/app/Http/Requests/RejectCampaignRequest.php` | Validasi alasan penolakan kampanye oleh admin |
| **Resource** | `backend/app/Http/Resources/CampaignResource.php` | Format serialisasi JSON data kampanye |
| **Model** | `backend/app/Models/Campaign.php` | Model Eloquent kampanye dengan SoftDeletes |
| **Enums** | `backend/app/Enums/CampaignStatus.php` | Status: `draft`, `review`, `active`, `success`, `failed` |

### Diagram Siklus Status Kampanye (State Lifecycle)

```
[ Creator ]                     [ Admin ]                      [ System / Deadline ]
     │                              │                                    │
     ▼                              ▼                                    ▼
+---------+  Submit Review    +---------+       Approve         +---------+
|  DRAFT  | ────────────────► | REVIEW  | ────────────────────► | ACTIVE  |
+---------+                   +---------+                       +---------+
     ▲                             │                                 │
     │         Reject              │                                 │
     └─────────────────────────────┘                                 ├─► Target Tercapai ──► [ SUCCESS ]
                                                                     │   (Disburse 95% + Fee 5%)
                                                                     │
                                                                     ├─► Deadline Habis / Force Fail ──► [ FAILED ]
                                                                         (Auto-Refund 100%)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Enums/
│   │   └── CampaignStatus.php
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── CampaignController.php
│   │   ├── Requests/
│   │   │   ├── IndexCampaignRequest.php
│   │   │   ├── RejectCampaignRequest.php
│   │   │   ├── StoreCampaignRequest.php
│   │   │   └── UpdateCampaignRequest.php
│   │   └── Resources/
│   │       └── CampaignResource.php
│   ├── Models/
│   │   └── Campaign.php
│   └── Services/
│       └── CampaignService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Katalog Kampanye Publik (`GET /api/v1/campaigns`)
- **Deskripsi:** Menampilkan daftar kampanye aktif terpaginasi dengan dukungan filter kategori, pencarian kata kunci, dan pengurutan.
- **Middleware:** Guest (Publik)

#### Parameter Query
| Parameter | Tipe | Wajib | Nilai yang Diizinkan | Deskripsi |
|---|---|---|---|---|
| `category` | string | Tidak | Slug kategori (misal: `teknologi`) | Filter berdasarkan kategori |
| `status` | string | Tidak | `draft`, `review`, `active`, `success`, `failed` | Filter status (khusus admin/creator) |
| `scope` | string | Tidak | `mine` | Khusus creator melihat proyek miliknya |
| `sort` | string | Tidak | `latest`, `popular`, `oldest` | Pengurutan data |
| `search` | string | Tidak | Bebas | Pencarian judul, deskripsi, inisiator |
| `page` | integer | Tidak | Min 1 | Nomor halaman |
| `per_page` | integer | Tidak | Min 1, Max 50 | Jumlah item per halaman (Default: 10) |

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "title": "Perangkat IoT Pendeteksi Udara",
      "slug": "perangkat-iot-pendeteksi-udara",
      "description": "Inovasi sensor modular untuk memantau kualitas udara.",
      "target_amount": 50000000,
      "collected_amount": 32500000,
      "progress_percentage": 65,
      "deadline": "2026-10-31",
      "status": "active",
      "category": {
        "id": 1,
        "name": "Teknologi & Inovasi",
        "slug": "teknologi"
      },
      "creator": {
        "id": 5,
        "name": "Arya Wijaya",
        "role": "creator"
      },
      "images": [
        {
          "id": 12,
          "url": "/storage/campaigns/iot-sensor.jpg",
          "is_primary": true
        }
      ],
      "tiers": [
        {
          "id": 21,
          "name": "Early Bird Device",
          "min_amount": 500000,
          "quota": 50,
          "remaining_quota": 18
        }
      ]
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

### 4.2 Endpoint: Buat Kampanye Baru (`POST /api/v1/campaigns`)
- **Deskripsi:** Membuat draf kampanye baru beserta foto visual dan reward tiers.
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`
- **Content-Type:** `multipart/form-data`

#### Parameter Form Payload
| Field | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `title` | string | Ya | Judul kampanye (Min: 5, Max: 255) |
| `category_id` | integer | Ya | ID kategori valid di database |
| `description` | string | Ya | Deskripsi lengkap proposal |
| `target_amount` | numeric | Ya | Minimal Rp 100.000 |
| `deadline` | date | Ya | Format YYYY-MM-DD (Minimal besok) |
| `video_url` | string | Tidak | Tautan video YouTube |
| `images[]` | file array | Ya | 1 hingga 5 file gambar (JPG/PNG) |
| `tiers[0][name]` | string | Ya | Nama paket tier |
| `tiers[0][min_amount]` | numeric | Ya | Batas minimal donasi tier |
| `tiers[0][quota]` | integer | Ya | Kuota backer (`0` = Unlimited) |
| `tiers[0][reward_description]` | string | Tidak | Fasilitas hadiah reward |

---

### 4.3 Endpoint: Ajukan Peninjauan Admin (`POST /api/v1/campaigns/{campaign:slug}/submit-review`)
- **Deskripsi:** Mengubah status kampanye dari `draft` menjadi `review`.
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "message": "Campaign submitted for review",
  "data": {
    "id": 10,
    "status": "review"
  }
}
```

---

### 4.4 Endpoint: Persetujuan Admin (`PUT /api/v1/admin/campaigns/{campaign:slug}/approve`)
- **Deskripsi:** Menyetujui kampanye berstatus `review` menjadi `active`.
- **Middleware:** `auth:sanctum`, `role:admin`

---

## 5. Skema Sumber Daya (Resource Schema)

### CampaignResource
```json
{
  "id": 10,
  "title": "Perangkat IoT Pendeteksi Udara",
  "slug": "perangkat-iot-pendeteksi-udara",
  "description": "Deskripsi lengkap...",
  "target_amount": 50000000,
  "collected_amount": 32500000,
  "progress_percentage": 65,
  "deadline": "2026-10-31",
  "status": "active",
  "category": { "id": 1, "name": "Teknologi", "slug": "teknologi" },
  "creator": { "id": 5, "name": "Arya Wijaya", "role": "creator" },
  "images": [],
  "tiers": []
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Campaign data structure is valid", function () {
    var data = pm.response.json().data;
    pm.expect(data.title).to.exist;
    pm.expect(data.target_amount).to.be.a("number");
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | List kampanye publik | GET `/campaigns` | `200 OK` + Data array kampanye |
| 2 | Buat kampanye tanpa foto | `images: []` | `422 Unprocessable Content` |
| 3 | Submit review kampanye bukan milik sendiri | Slug kampanye user lain | `403 Forbidden` |
| 4 | Approve kampanye status bukan review | Approve pada kampanye draft | `400 Bad Request` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Kampanye baru tidak muncul di list publik | Status kampanye masih `draft` atau `review` | Ajukan peninjauan dan setujui melalui akun admin agar status menjadi `active`. |
| Upload foto gagal | Ukuran foto melebihi 2MB | Kompres ukuran foto sebelum diunggah. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /campaigns` | ✓ | ✓ | ✓ | ✓ |
| `GET /campaigns/{slug}` | ✓ | ✓ | ✓ | ✓ |
| `POST /campaigns` | ✗ | ✗ | ✓ | ✗ |
| `PUT /campaigns/{slug}` | ✗ | ✗ | ✓ (Owner) | ✗ |
| `POST /campaigns/{slug}/submit-review` | ✗ | ✗ | ✓ (Owner) | ✗ |
| `PUT /admin/campaigns/{slug}/approve` | ✗ | ✗ | ✗ | ✓ |
| `PUT /admin/campaigns/{slug}/reject` | ✗ | ✗ | ✗ | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-CAMP-001` | Get katalog publik | Positive | Query standard | `200 OK` | Array data kampanye active |
| `TC-CAMP-002` | Buat kampanye data lengkap | Positive | Form valid + Foto | `201 Created` | Status `draft` |
| `TC-CAMP-003` | Buat kampanye target < 100.000 | Negative | `target_amount: 50000` | `422 Unprocessable` | Error "The target amount must be at least 100000." |
| `TC-CAMP-004` | Non-admin approve kampanye | Security | Token creator | `403 Forbidden` | Error unauthorized |
