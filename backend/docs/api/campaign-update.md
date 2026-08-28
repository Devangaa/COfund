# CoFund API - Modul Update Kampanye (Campaign Update Module)

## 1. Judul & Deskripsi Modul

Modul update kampanye memungkinkan kreator untuk memposting, memperbarui, menghapus, dan menampilkan pembaruan (update) untuk kampanye. Setiap pembaruan dilengkapi dengan notifikasi otomatis ke semua backer yang mendukung kampanye tersebut.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/CampaignUpdateController.php` | Index, store, update, destroy |
| **Service** | `app/Services/CampaignUpdateService.php` | Logika bisnis CRUD + notifikasi |
| **Form Request** | `app/Http/Requests/StoreCampaignUpdateRequest.php` | Validasi store |
| | `app/Http/Requests/UpdateCampaignUpdateRequest.php` | Validasi update |
| | `app/Http/Requests/DeleteCampaignUpdateRequest.php` | Validasi delete |
| **Resource** | `app/Http/Resources/CampaignUpdateResource.php` | Serialisasi data update |
| **Model** | `app/Models/CampaignUpdate.php` | Model dengan SoftDeletes, Markdown rendering |
| **Enums** | `app/Enums/CampaignStatus.php` | `active` — update hanya bisa diposting ketika kampanye aktif |
| **Job** | `app/Jobs/NotifyBackersJob.php` | Job async notifikasi ke semua backer |
| **Mail** | `app/Mail/CampaignUpdatePosted.php` | Template email notifikasi update |
| **Middleware** | `auth:sanctum`, `role:creator`, `verified` | Otorisasi akses |

### Alur Proses Logika Bisnis

```
Creator posting update
        |
        v
CampaignUpdateController::store()
        |
        v
StoreCampaignUpdateRequest
  - authorize: campaign.user_id === auth->id()
  - rules: title (required, max:255), content (required, max:10000)
        |
        v
CampaignUpdateService::create()
        |
        +---> Check campaign->status === ACTIVE
        |       throw ConflictHttpException "Campaign update can only be posted when campaign is active"
        |
        +---> DB::transaction()
              +---> CampaignUpdate::create()
              +---> NotifyBackersJob::dispatch($update)
        |
        v
Return CampaignUpdateResource + HTTP 201

Event: NotifyBackersJob (queued)
        |
        v
  - Create in-app notifications for all backers
  - Send email to backers (if verified)
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── CampaignUpdateController.php
│   │   ├── Requests/
│   │   │   ├── StoreCampaignUpdateRequest.php
│   │   │   ├── UpdateCampaignUpdateRequest.php
│   │   │   └── DeleteCampaignUpdateRequest.php
│   │   └── Resources/
│   │       └── CampaignUpdateResource.php
│   ├── Models/
│   │   └── CampaignUpdate.php
│   ├── Enums/
│   │   └── CampaignStatus.php
│   ├── Services/
│   │   └── CampaignUpdateService.php
│   ├── Jobs/
│   │   └── NotifyBackersJob.php
│   └── Mail/
│       └── CampaignUpdatePosted.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Index Updates by Campaign

- **Deskripsi:** Menampilkan daftar update dari satu kampanye, diurutkan terbaru dulu, dengan pagination.
- **HTTP Method & URL Path:** `GET /api/v1/campaigns/{campaign:slug}/updates`
- **Middleware:** *(opsional)* `auth:sanctum`
- **Autentikasi:** Opsional (public)

#### Tabel Parameter (Path + Query)

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `page` | Query | integer | Tidak | `min:1` | Halaman |
| `per_page` | Query | integer | Tidak | `min:1`, `max:50` | Item per halaman (default: 10, maksimal: 50) |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Update Mingguan #1",
            "content": "Kami telah mencapai 25% target...",
            "content_html": "<p>Kami telah mencapai 25% target...</p>",
            "created_at": "2024-02-01T10:00:00Z"
        }
    ],
    "meta": {
        "pagination": {
            "current_page": 1,
            "last_page": 2,
            "per_page": 10,
            "total": 12
        }
    }
}
```

#### Efek Samping

- Hanya kampanye yang memiliki update yang menampilkan data
- Pagination dengan per_page default 10

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 404 | `{"message":"No query results for model ..."}` | Slug tidak ditemukan |

---

### 4.2 Endpoint: Store Campaign Update

- **Deskripsi:** Memposting update baru untuk kampanye yang statusnya `active`.
- **HTTP Method & URL Path:** `POST /api/v1/campaigns/{campaign:slug}/updates`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `title` | Body | string | Ya | `max:255` | Judul update |
| `content` | Body | string | Ya | `max:10000` | Konten (Markdown) |

#### Contoh Request Payload

```json
{
    "title": "Update Mingguan #1",
    "content": "Kami telah mencapai 25% target pendanaan! Terima kasih kepada semua backer."
}
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "message": "Update posted successfully",
    "data": {
        "id": 2,
        "title": "Update Mingguan #1",
        "content": "Kami telah mencapai 25% target pendanaian! Terima kasih kepada semua backer.",
        "content_html": "<p>Kami telah mencapai 25% target pendanaian! Terima kasih kepada semua backer.</p>",
        "created_at": "2024-02-01T10:00:00Z"
    }
}
```

#### Efek Samping

- Memosting update (hanya saat status kampanye `active`)
- Dispatch `NotifyBackersJob` via queue
- Notifikasi ke semua backer kampanye
- Email ke backer yang terverifikasi

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik |
| 409 | `{"success":false,"message":"Campaign update can only be posted when campaign is active"}` | Status kampanye bukan `active` |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Validasi gagal |

---

### 4.3 Endpoint: Update Campaign Update

- **Deskripsi:** Memperbarui update yang sudah diposting.
- **HTTP Method & URL Path:** `PUT /api/v1/campaigns/{campaign:slug}/updates/{update}`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `update` | Path | integer | Ya | - | ID update |
| `title` | Body | string | Tidak | `max:255` | Judul baru |
| `content` | Body | string | Tidak | `max:10000` | Konten baru |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Update updated successfully",
    "data": {
        "id": 2,
        "title": "Update Mingguan #1 - Revisi",
        "content": "Kami telah mencapai 30% target...",
        "content_html": "<p>Kami telah mencapai 30% target...</p>",
        "created_at": "2024-02-01T10:00:00Z"
    }
}
```

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik |
| 422 | `{"success":false,"message":"The given data was invalid."}` | Validasi gagal |

---

### 4.4 Endpoint: Delete Campaign Update

- **Deskripsi:** Menghapus update kampanye.
- **HTTP Method & URL Path:** `DELETE /api/v1/campaigns/{campaign:slug}/updates/{update}`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Update deleted successfully"
}
```

#### Efek Samping

- Soft delete update dari database

#### Error Handling

| Kode HTTP | Pesam Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik |
| 422 | `{"success":false,"message":"The given data was invalid."}` | Validasi gagal |

---

## 5. Skema Sumber Daya (Resource Schema)

### CampaignUpdateResource

```json
{
    "id": 1,
    "title": "Update Mingguan #1",
    "content": "Kami telah mencapai 25% target...",
    "content_html": "<p>Kami telah mencapai 25% target...</p>",
    "created_at": "2024-02-01T10:00:00Z"
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key |
| `title` | string | Judul update |
| `content` | string | Konten asli (Markdown) |
| `content_html` | string | Konten yang sudah di-render HTML |
| `created_at` | datetime | Timestamp pembuatan |

---

## 6. Pengujian Postman

### Store Campaign Update

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/campaigns/bantu-anak-pedalaman-tepukan/updates`
3. Headers: `Authorization: Bearer {{auth_token}}`, `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "title": "Update Pertama",
    "content": "Kami baru saja meluncurkan kampanye ini!"
}
```

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Content rendered as HTML", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.content_html).to.include("<p>");
});
```

### Index Updates

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/campaigns/bantu-anak-pedalaman-tepukan/updates`
3. Headers: `Authorization: Bearer {{auth_token}}` (opsional)

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Has pagination", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.meta.pagination).to.exist;
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | Post update pada kampanye active | `{"title":"Update 1","content":"..."}` | HTTP 201, NotifyBackersJob dispatch |
| 2 | Post update pada kampanye draft | POST /campaigns/draft-slug/updates | HTTP 409, "Campaign update can only be posted when campaign is active" |
| 3 | Post update pada kampanye yang bukan milik sendiri | POST /campaigns/lain-slug/updates | HTTP 403, unauthorized |
| 4 | Update postingan | PUT /.../{id} `{"title":"Revised"}` | HTTP 200, data terupdate |
| 5 | Delete postingan | DELETE /.../{id} | HTTP 200, "Update deleted successfully" |
| 6 | Index update dengan pagination | GET /.../{slug}/updates?page=2 | Halaman 2 dengan data |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| "Campaign update can only be posted when campaign is active" | Pastikan kampanye sudah disetujui oleh admin (status `active`). |
| Notifikasi backer tidak terkirim | Pastikan `QUEUE_CONNECTION` bukan `sync` dan queue worker berjalan (`php artisan queue:work`). |
| `content_html` kosong | Pastikan `content` diisi. Rendering dilakukan oleh `Parsedown` dalam accessor model. |
| Email tidak terkirim ke backer | Pastikan queue worker berjalan dan konfigurasi mail (`.env`) benar. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /api/v1/campaigns/{slug}/updates` | ✓ | ✓ | ✓ | ✓ |
| `POST /api/v1/campaigns/{slug}/updates` | - | - | ✓ (active only) | - |
| `PUT /api/v1/campaigns/{slug}/updates/{id}` | - | - | ✓ (owner) | - |
| `DELETE /api/v1/campaigns/{slug}/updates/{id}` | - | - | ✓ (owner) | - |

---

## 10. Matriks Kasus Pengujian (Test Case)

### 10.1 Index Updates

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-UPDATE-INDEX-001` | Get updates kampanye yang ada | Positive | `GET /campaigns/{slug}/updates` | `200 OK` | Daftar update, meta pagination |
| `TC-UPDATE-INDEX-002` | Get updates dengan pagination | Positive | `?page=2&per_page=15` | `200 OK` | Halaman 2, 15 item (max 50) |
| `TC-UPDATE-INDEX-003` | Get updates kampanye tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |
| `TC-UPDATE-INDEX-004` | Get updates dengan per_page > 50 | Positive | `?per_page=999` | `200 OK` | Dibatasi ke 50 |

### 10.2 Store Update

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-UPDATE-STORE-001` | Buat update pada campaign active | Positive | `title`, `content` valid | `201 Created` | CampaignUpdateResource |
| `TC-UPDATE-STORE-002` | Buat update pada campaign draft | Positive | Campaign berstatus draft | `201 Created` | Update terbuat |
| `TC-UPDATE-STORE-003` | Buat update tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-UPDATE-STORE-004` | Buat update sebagai backer | Security | Role backer | `403 Forbidden` | Error "Creator role required" |
| `TC-UPDATE-STORE-005` | Buat update pada campaign review | Business Logic | Status `review` | `409 Conflict` | Error "Campaign must be draft or active" |
| `TC-UPDATE-STORE-006` | Buat update tanpa title | Negative | `title: null` | `422 Unprocessable` | Error "The title field is required" |
| `TC-UPDATE-STORE-007` | Buat update dengan title > 255 karakter | Negative | `title > 255` | `422 Unprocessable` | Error panjang karakter |
| `TC-UPDATE-STORE-008` | Buat update tanpa content | Negative | `content: null` | `422 Unprocessable` | Error "The content field is required" |
| `TC-UPDATE-STORE-009` | Creator A update di campaign Creator B | Security | BOPA | `404 Not Found` | Not Found |
| `TC-UPDATE-STORE-010` | Buat update pada campaign yang tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |

### 10.3 Update Existing Update

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-UPDATE-UPDATE-001` | Update update yang ada | Positive | PUT dengan data valid | `200 OK` | CampaignUpdateResource terbarui |
| `TC-UPDATE-UPDATE-002` | Update update milik creator lain | Security | BOPA | `404 Not Found` | Not Found |
| `TC-UPDATE-UPDATE-003` | Update update tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-UPDATE-UPDATE-004` | Update dengan title > 255 karakter | Negative | `title > 255` | `422 Unprocessable` | Error validasi |
| `TC-UPDATE-UPDATE-005` | Update update yang tidak ada | Negative | `update_id: 9999` | `404 Not Found` | Error "Update not found" |

### 10.4 Delete Update

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-UPDATE-DELETE-001` | Hapus update yang ada | Positive | DELETE pada id valid | `200 OK` | Update dihapus (soft delete) |
| `TC-UPDATE-DELETE-002` | Hapus update milik creator lain | Security | BOPA | `404 Not Found` | Not Found |
| `TC-UPDATE-DELETE-003` | Hapus update tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-UPDATE-DELETE-004` | Hapus update yang tidak ada | Negative | `update_id: 9999` | `404 Not Found` | Error "Update not found" |
