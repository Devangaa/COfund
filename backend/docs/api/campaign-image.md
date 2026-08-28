# CoFund API - Modul Gambar Kampanye (Campaign Image Module)

## 1. Judul & Deskripsi Modul

Modul gambar kampanye mengelola unggahan gamber untuk kampanye yang masih dalam status `draft`. Gambar disimpan di storage `public/campaigns/` dan URL publik dikembalikan melalui resource.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/CampaignImageController.php` | Store dan destroyMany |
| **Service** | `app/Services/CampaignImageService.php` | Logika bisnis upload dan delete |
| **Form Request** | `app/Http/Requests/StoreCampaignImageRequest.php` | Validasi upload |
| | `app/Http/Requests/DeleteCampaignImageRequest.php` | Validasi bulk delete |
| **Resource** | `app/Http/Resources/CampaignImageResource.php` | Serialisasi data gambar |
| **Model** | `app/Models/CampaignImage.php` | Model dengan SoftDeletes |
| **Middleware** | `auth:sanctum`, `role:creator`, `verified` | Otentikasi dan otorisasi |
| **Dependencies** | `CampaignService::ensureEditable()` | Pastikan kampanye dalam status draft |

### Alur Proses Logika Bisnis

```
Creator upload gambar
        |
        v
CampaignImageController::store()
        |
        v
StoreCampaignImageRequest
  - authorize: campaign.user_id === auth->id()
  - rules: image required, mimes, max 2048kb
        |
        v
CampaignImageService::create()
        |
        +---> CampaignService::ensureEditable() // draft only
        |       throw ConflictHttpException(422) if not draft
        |
        +---> Check count < 5 // max 5 images
        |       throw HttpException(422) "Maximum 5 images per campaign"
        |
        +---> $file->store('campaigns', 'public')
        |       -> Storage::disk('public')->url($path)
        |
        +---> CampaignImage::create()
        |
        v
Return CampaignImageResource
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── CampaignImageController.php
│   │   ├── Requests/
│   │   │   ├── StoreCampaignImageRequest.php
│   │   │   └── DeleteCampaignImageRequest.php
│   │   └── Resources/
│   │       └── CampaignImageResource.php
│   ├── Models/
│   │   └── CampaignImage.php
│   ├── Services/
│   │   ├── CampaignImageService.php
│   │   └── CampaignService.php
│   └── Enums/
│       └── CampaignStatus.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Store Campaign Image

- **Deskripsi:** Mengunggah satu gambar untuk kampanye. Maksimal 5 gambar per kampagne.
- **HTTP Method & URL Path:** `POST /api/v1/campaigns/{campaign:slug}/images`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `image` | Body | file | Ya | `image`, `mimes:jpeg,png,jpg,gif`, `max:2048` | File gambar (multipart) |

#### Contoh Request (Multer Form Data)

```
POST /api/v1/campaigns/my-campaign/images
Authorization: Bearer {token}
Content-Type: multipart/form-data

image=@./gambar.jpg
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "message": "Image uploaded successfully",
    "data": {
        "id": 3,
        "url": "http://localhost/storage/campaigns/XYZ123abc.jpg",
        "is_primary": false
    }
}
```

#### Efek Samping

- File disimpan di `storage/app/public/campaigns/{filename}`
- Membuat entri `CampaignImage`
- `is_primary` otomatis `false` untuk gambar tambahan

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik kampanye |
| 422 | `{"success":false,"message":"Campaign can only be edited in draft status"}` | Status kampanye bukan draft |
| 422 | `{"success":false,"message":"Maximum 5 images per campaign"}` | Sudah ada 5 gambar |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Validasi file gagal |

---

### 4.2 Endpoint: Delete Many Images

- **Deskripsi:** Menghapus beberapa gambar sekaligus. kampanye harus memiliki minimal 1 gambar.
- **HTTP Method & URL Path:** `DELETE /api/v1/campaigns/{campaign:slug}/images`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `ids` | Body | array | Ya | `min:1`, `exists:campaign_images,id` | Array ID gambar yang akan dihapus |

#### Contoh Request Payload

```json
{
    "ids": [3, 4]
}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Selected images deleted successfully"
}
```

#### Efek Samping

- File gambar dihapus dari storage
- Entri `CampaignImage` dihapus
- Operasi dalam transaksi database

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator |
| 422 | `{"success":false,"message":"Campaign must have at least 1 image"}` | Hanya tersisa 1 gambar |
| 403 | `{"success":false,"message":"This action is unauthorized."}` | ID gambar tidak valid |

---

## 5. Skema Sumber Daya (Resource Schema)

### CampaignImageResource

```json
{
    "id": 3,
    "url": "http://localhost/storage/campaigns/XYZ123abc.jpg",
    "is_primary": false
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key |
| `url` | string | URL publik gambar |
| `is_primary` | boolean | Apakah gambar ini primary/cover |

---

## 6. Pengujian Postman

### Store Campaign Image

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/campaigns/qa-draft-campaign/images`
3. Headers: `Authorization: Bearer {{auth_token}}`
4. Body: `form-data` → Key: `image`, Type: File

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Image uploaded", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.url).to.include("http");
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | Upload gambar valid | File JPG/PNG < 2MB | HTTP 201, URL gambar |
| 2 | Upload file non-image | File .pdf | HTTP 422, validation error |
| 3 | Upload file > 2MB | File JPG 3MB | HTTP 422, "The image attribute has a maximum size" |
| 4 | Upload ketika sudah 5 gambar | File baru | HTTP 422, "Maximum 5 images per campaign" |
| 5 | Upload di kampanye yang sudah active | Form upload | HTTP 422, "Campaign can only be edited in draft status" |
| 6 | Delete banyak gambar | `{"ids": [3,4]}` | HTTP 200, gambar dan file dihapus |
| 7 | Delete hanya 1 gambar tersisa | `{"ids": [1]}` | HTTP 422, "Campaign must have at least 1 image" |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| Gambar tidak dapat diakses di storage | Jalankan `php artisan storage:link` untuk membuat symbolic link. |
| Upload gagal dengan error 422 | Pastikan file valid (jpeg, png, jpg, gif) dan ukuran < 2MB. |
| "Campaign can only be edited in draft status" | Gambar hanya dapat dikelola ketika kampanye masih draft. |
| URL gambar broken | Pastikan storage sudah dilink dan file benar-benar tersimpan. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /api/v1/campaigns/{slug}/images` | - | - | ✓ (draft only) | - |
| `DELETE /api/v1/campaigns/{slug}/images` | - | - | ✓ (draft only) | - |

---

## 10. Matriks Kasus Pengujian (Test Case)

### 10.1 Upload Campaign Image

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-IMG-UPLOAD-001` | Upload image valid (JPG) | Positive | `image` berupa file JPG valid | `201 Created` | ImageResource terbuat, URL disimpan |
| `TC-IMG-UPLOAD-002` | Upload image valid (PNG) | Positive | `image` berupa file PNG valid | `201 Created` | ImageResource terbuat |
| `TC-IMG-UPLOAD-003` | Upload image tanpa token | Security | No Authorization | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-IMG-UPLOAD-004` | Backer upload image | Security | Role backer | `403 Forbidden` | Error "Creator role required" |
| `TC-IMG-UPLOAD-005` | Upload image pada campaign review | Business Logic | Status `review` | `409 Conflict` | Error "Only draft campaigns can be modified" |
| `TC-IMG-UPLOAD-006` | Upload format PDF | Negative | `image: file.pdf` | `422 Unprocessable` | Error "Image must be jpg, jpeg, png, or webp" |
| `TC-IMG-UPLOAD-007` | Upload format EXE | Negative | `image: file.exe` | `422 Unprocessable` | Error mime type tidak valid |
| `TC-IMG-UPLOAD-008` | Upload file > 2MB | Negative | `image` ukuran 3MB | `422 Unprocessable` | Error "Image may not be greater than 2048 kilobytes" |
| `TC-IMG-UPLOAD-009` | Upload tanpa file | Negative | `image: null` | `422 Unprocessable` | Error "The image field is required" |
| `TC-IMG-UPLOAD-010` | Upload pada campaign yang tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |
| `TC-IMG-UPLOAD-011` | Creator A upload di campaign Creator B | Security | BOPA | `404 Not Found` | Not Found |
| `TC-IMG-UPLOAD-012` | Spam upload 20x | Throttling | Rapid requests | `429 Too Many Requests` | Rate limited |

### 10.2 Delete Many Images

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-IMG-DELETE-001` | Hapus banyak image yang ada | Positive | `{"ids": [1,2]}` | `200 OK` | Images dihapus (soft delete) |
| `TC-IMG-DELETE-002` | Hapus image pada campaign review | Business Logic | Status `review` | `409 Conflict` | Error tidak dapat dihapus |
| `TC-IMG-DELETE-003` | Hapus image tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-IMG-DELETE-004` | Hapus dengan array ids kosong | Negative | `{"ids": []}` | `422 Unprocessable` | Error "The ids field is required" |
| `TC-IMG-DELETE-005` | Hapus image yang tidak ada | Negative | `{"ids": [9999]}` | `422 Unprocessable` | Error "Selected ids are invalid" |
| `TC-IMG-DELETE-006` | Hapus image Creator A oleh Creator B | Security | BOPA | `404 Not Found` | Not Found |
