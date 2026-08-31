# CoFund API - Modul Galeri Foto Kampanye (Campaign Image Module)

## 1. Judul & Deskripsi Modul

Modul Galeri Foto mengelola berkas visual pendukung kampanye proyek, termasuk pengunggahan batch foto baru (maksimal 5 foto per kampanye), penentuan foto utama (*primary cover image*), dan penghapusan foto secara massal (*batch deletion*).

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/CampaignImageController.php` | Method `store` dan `destroyMany` |
| **Service Layer** | `backend/app/Services/CampaignImageService.php` | Manajemen file disk storage dan validasi batas 5 foto |
| **Form Requests** | `backend/app/Http/Requests/StoreCampaignImageRequest.php` | Validasi array file gambar |
| | `backend/app/Http/Requests/DeleteCampaignImageRequest.php` | Validasi array ID foto untuk dihapus |
| **Resource** | `backend/app/Http/Resources/CampaignImageResource.php` | Serialisasi format output gambar |
| **Model** | `backend/app/Models/CampaignImage.php` | Model relasi foto kampanye |

### Diagram Alur Upload & Deletion

```
Creator Upload Gambar
        │
        ▼
[ StoreCampaignImageRequest ]
  - Validasi: Array file, format JPG/PNG/WEBP, Max 2MB/file
        │
        ▼
[ CampaignImageService ]
  - Cek total foto existing + new <= 5
  - Simpan file ke Storage (public/campaigns)
  - Set is_primary = true jika foto pertama
        │
        ▼
Return CampaignImageResource (HTTP 201)
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── CampaignImageController.php
│   │   ├── Requests/
│   │   │   ├── DeleteCampaignImageRequest.php
│   │   │   └── StoreCampaignImageRequest.php
│   │   └── Resources/
│   │       └── CampaignImageResource.php
│   ├── Models/
│   │   └── CampaignImage.php
│   └── Services/
│       └── CampaignImageService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Unggah Foto Kampanye (`POST /api/v1/campaigns/{campaign:slug}/images`)
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`
- **Content-Type:** `multipart/form-data`

#### Parameter Form
| Field | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `images[]` | file array | Ya | File gambar binary (JPG/PNG/WEBP, Max: 2048 KB) |

#### Contoh Response (`201 Created`):
```json
{
  "success": true,
  "message": "Images uploaded successfully",
  "data": [
    {
      "id": 15,
      "url": "/storage/campaigns/sensor-prototype.jpg",
      "is_primary": false
    }
  ]
}
```

---

### 4.2 Endpoint: Hapus Batch Foto (`DELETE /api/v1/campaigns/{campaign:slug}/images`)
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Contoh Request:
```json
{
  "ids": [15, 16]
}
```

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "message": "Selected images deleted successfully"
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### CampaignImageResource
```json
{
  "id": 15,
  "url": "/storage/campaigns/sensor-prototype.jpg",
  "is_primary": false
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Images uploaded correctly", function () {
    var data = pm.response.json().data;
    pm.expect(data).to.be.an("array");
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Upload 2 foto valid | Form-data 2 file JPG | `201 Created` + Array data foto |
| 2 | Upload melebihi batas 5 foto | Form-data 6 file | `422 Unprocessable Content` |
| 3 | Hapus foto milik kreator lain | IDs foto orang lain | `403 Forbidden` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Gambar 404 saat dibuka di browser | Simlink storage belum terpasang | Jalankan perintah `php artisan storage:link` pada backend. |
| Maximum upload size exceeded | Konfigurasi `upload_max_filesize` di php.ini rendah | Naikkan `upload_max_filesize` dan `post_max_size` di file `php.ini`. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /campaigns/{slug}/images` | ✗ | ✗ | ✓ (Owner) | ✗ |
| `DELETE /campaigns/{slug}/images` | ✗ | ✗ | ✓ (Owner) | ✗ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-IMG-001` | Upload foto valid | Positive | 1 file PNG < 2MB | `201 Created` | Foto tersimpan |
| `TC-IMG-002` | Upload file format PDF | Negative | File `.pdf` | `422 Unprocessable` | Error "The images must be an image." |
| `TC-IMG-003` | Delete batch IDs valid | Positive | Array IDs `[15]` | `200 OK` | Pesan sukses hapus |
| `TC-IMG-004` | Non-owner delete image | Security | Token creator lain | `403 Forbidden` | Error unauthorized |
