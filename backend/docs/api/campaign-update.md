# CoFund API - Modul Update Kampanye (Campaign Update Module)

## 1. Judul & Deskripsi Modul

Modul Update Kampanye mengelola penerbitan artikel kabar perkembangan proyek (*milestone updates*) oleh inisiator kampanye agar donatur dan publik dapat memantau transparansi progres realisasi ide, penggunaan dana, dan tahapan produksi.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/CampaignUpdateController.php` | Method `index`, `store`, `update`, `destroy` |
| **Service Layer** | `backend/app/Services/CampaignUpdateService.php` | Enkapsulasi logika CRUD artikel kabar proyek |
| **Form Requests** | `backend/app/Http/Requests/StoreCampaignUpdateRequest.php` | Validasi artikel update baru |
| | `backend/app/Http/Requests/UpdateCampaignUpdateRequest.php` | Validasi pembaruan artikel |
| **Resource** | `backend/app/Http/Resources/CampaignUpdateResource.php` | Serialisasi format output update |
| **Model** | `backend/app/Models/CampaignUpdate.php` | Model berita kemajuan proyek dengan SoftDeletes |

### Diagram Alur Publikasi Kabar Proyek

```
Creator Tulis Kabar Baru
        │
        ▼
[ StoreCampaignUpdateRequest::validate ]
  - title (required, max:255)
  - content (required, max:10000)
        │
        ▼
[ CampaignUpdateService::create ]
  - Pastikan kampanye berstatus ACTIVE
  - Pastikan creator adalah pemilik kampanye
  - Simpan ke Database
        │
        ▼
Return CampaignUpdateResource (HTTP 201 Created)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── CampaignUpdateController.php
│   │   ├── Requests/
│   │   │   ├── StoreCampaignUpdateRequest.php
│   │   │   └── UpdateCampaignUpdateRequest.php
│   │   └── Resources/
│   │       └── CampaignUpdateResource.php
│   ├── Models/
│   │   └── CampaignUpdate.php
│   └── Services/
│       └── CampaignUpdateService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Daftar Kabar Proyek (`GET /api/v1/campaigns/{campaign:slug}/updates`)
- **Middleware:** Guest (Publik)

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Produksi PCB Batch 1 Selesai",
      "content": "Kami telah menyelesaikan fabrikasi 50 unit casing prototype.",
      "created_at": "2026-08-31T10:00:00Z"
    }
  ]
}
```

---

### 4.2 Endpoint: Terbitkan Kabar Baru (`POST /api/v1/campaigns/{campaign:slug}/updates`)
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Parameter Body
| Field | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `title` | string | Ya | Judul kabar proyek (Max: 255) |
| `content` | string | Ya | Isi artikel pembaruan |

#### Contoh Response (`201 Created`):
```json
{
  "success": true,
  "message": "Campaign update created successfully",
  "data": {
    "id": 2,
    "title": "Uji Lapangan Sensor",
    "content": "Pengujian sensor di 5 stasiun pemantau kota.",
    "created_at": "2026-08-31T12:00:00Z"
  }
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### CampaignUpdateResource
```json
{
  "id": 1,
  "title": "Produksi PCB Batch 1 Selesai",
  "content": "Kami telah menyelesaikan fabrikasi 50 unit casing prototype.",
  "created_at": "2026-08-31T10:00:00Z"
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Update created with title", function () {
    var data = pm.response.json().data;
    pm.expect(data.title).to.exist;
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Creator posting update valid | Judul & Konten terisi | `201 Created` + Artikel tersimpan |
| 2 | Creator posting tanpa judul | `title: null` | `422 Unprocessable Content` |
| 3 | Non-owner posting update | Token creator lain | `403 Forbidden` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Gagal posting kabar proyek | Kampanye belum berstatus `active` | Kabar proyek hanya dapat diposting pada kampanye yang telah disetujui dan aktif. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /campaigns/{slug}/updates` | ✓ | ✓ | ✓ | ✓ |
| `POST /campaigns/{slug}/updates` | ✗ | ✗ | ✓ (Owner) | ✗ |
| `PUT /campaigns/{slug}/updates/{id}` | ✗ | ✗ | ✓ (Owner) | ✗ |
| `DELETE /campaigns/{slug}/updates/{id}` | ✗ | ✗ | ✓ (Owner) | ✗ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-UPD-001` | Posting kabar valid | Positive | Form valid | `201 Created` | Artikel kabar tersimpan |
| `TC-UPD-002` | Posting tanpa content | Negative | `content: null` | `422 Unprocessable` | Error "The content field is required." |
| `TC-UPD-003` | Non-owner ubah kabar | Security | Token creator lain | `403 Forbidden` | Error unauthorized |
