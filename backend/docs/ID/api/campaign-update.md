# API Modul Pembaruan Kampanye

Postingan pembaruan kampanye untuk komunikasi antara creator dan backer.

## Arsitektur

Modul Pembaruan Kampanye memungkinkan creator memposting pembaruan pada kampanyenya. Saat pembaruan baru diposting, semua backer kampanye tersebut menerima notifikasi dalam aplikasi. Pembaruan hanya dapat dibuat untuk kampanye yang AKTIF.

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/CampaignUpdateController.php` | Menangani operasi CRUD pembaruan |
| Service | `app/Services/CampaignUpdateService.php` | Logika bisnis untuk pembaruan |
| Requests | `app/Http/Requests/{StoreCampaignUpdateRequest, UpdateCampaignUpdateRequest, DeleteCampaignUpdateRequest}.php` | Aturan validasi |
| Resource | `app/Http/Resources/CampaignUpdateResource.php` | Pemformatan respons JSON |
| Model | `app/Models/CampaignUpdate.php` | Entitas pembaruan |
| Notifikasi | (melalui model, bukan Laravel Notifications) | Membuat notifikasi dalam aplikasi untuk semua backer |

### Alur

```
Creator → StoreCampaignUpdateRequest → CampaignUpdateService::create()
       → Periksa status kampanye = ACTIVE
       → Buat pembaruan
       → notifyBackers() — mendapatkan ID backer yang unik
       → Mass insert catatan Notification
       → Kembalikan sumber daya pembaruan

Backer → GET /campaigns/{slug}/updates (public)
       → Koleksi CampaignUpdateResource
```

## Struktur File

```
app/
├── Http/Controllers/Api/CampaignUpdateController.php
├── Services/CampaignUpdateService.php
├── Http/Requests/
│   ├── StoreCampaignUpdateRequest.php
│   ├── UpdateCampaignUpdateRequest.php
│   └── DeleteCampaignUpdateRequest.php
├── Http/Resources/CampaignUpdateResource.php
└── Models/CampaignUpdate.php
```

## API Endpoints

### 1. Daftar Pembaruan (Public)

Mengembalikan semua pembaruan untuk sebuah kampanye. Dapat diakses publik (tidak memerlukan autentikasi).

**Endpoint:** `GET /api/campaigns/{slug}/updates`  
**Middleware:** `public`  
**Deskripsi:** Mengembalikan pembaruan kampanye. Tidak memerlukan otentikasi.

#### Respons (Sukses: 200)

```json
[
  {
    "id": 1,
    "title": "First Update",
    "content": "We've reached 50% of our goal! Thank you to everyone who supported us.",
    "created_at": "2026-08-25T10:00:00.000000Z"
  },
  {
    "id": 2,
    "title": "Milestone Reached",
    "content": "We hit our target! Production will begin next month.",
    "created_at": "2026-08-26T10:00:00.000000Z"
  }
]
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 404 | Campaign not found | Slug tidak valid |

---

### 2. Buat Pembaruan

Membuat pembaruan baru untuk kampanye yang AKTIF. Mengirimkan notifikasi ke semua backer.

**Endpoint:** `POST /api/campaigns/{slug}/updates`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Memosting pembaruan dan memberi tahu semua backer.

#### Otorisasi

Pengguna harus memiliki kampanye.

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `title` | string | Ya | `required, string, max:255` | Judul pembaruan |
| `content` | string | Ya | `required, string` | Konten pembaruan (body) |

#### Contoh Request

```json
{
  "title": "Production Update",
  "content": "We've placed the order for materials. Expected delivery in 2 weeks."
}
```

#### Respons (Sukses: 201)

```json
{
  "id": 3,
  "title": "Production Update",
  "content": "We've placed the order for materials. Expected delivery in 2 weeks.",
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

#### Efek Samping

- Membuat catatan `CampaignUpdate`
- Mengumpulkan semua ID backer yang unik untuk kampanye
- Mass insert catatan `Notification` (type=`campaign_update`) untuk setiap backer
- **Tidak ada antrian** — `notifyBackers()` berjalan secara sinkron

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan pembuat kampanye |
| 409 | Campaign has not been approved yet | Status kampanye ≠ ACTIVE |
| 422 | Validation error | Judul atau konten hilang |

> **Catatan tentang kode error:** `CampaignUpdateService::create()` melempar `ConflictHttpException('Campaign has not been approved yet')` ketika kampanye tidak aktif, yang menghasilkan respons **409**. Namun, pengontrolan eksepsi kustom mengembalikan ini sebagai HTTP 409 (Konflik), **bukan** 422.

---

### 3. Perbarui Pembaruan

Memperbarui postingan pembaruan yang ada.

**Endpoint:** `PUT /api/campaigns/{slug}/updates/{update}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Mengedit pembaruan kampanye yang ada.

#### Otorisasi

- Pengguna harus memiliki kampanye
- Pembaruan harus milik kampanye

#### Body Permintaan

| Parameter | Tipe | Validasi | Deskripsi |
|-----------|------|------------|-------------|
| `title` | string | `sometimes, string, max:255` | Judul baru |
| `content` | string | `sometimes, string` | Konten baru |

#### Contoh Request

```json
{
  "title": "Production Update (Revised)",
  "content": "Updated content..."
}
```

#### Respons (Sukses: 200)

```json
{
  "id": 3,
  "title": "Production Update (Revised)",
  "content": "Updated content...",
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan pembuat kampanye |
| 404 | Campaign update not found | ID pembaruan tidak valid |

---

### 4. Hapus Pembaruan

Menghapus postingan pembaruan.

**Endpoint:** `DELETE /api/campaigns/{slug}/updates/{update}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Menghapus pembaruan secara lembut.

#### Otorisasi

- Pengguna harus memiliki kampanye
- Pembaruan harus milik kampanye

#### Respons (Sukses: 200)

```json
{
  "message": "Update deleted successfully"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan pembuat kampanye |
| 404 | Campaign update not found | ID pembaruan tidak valid |

## Skema Sumber Daya Pembaruan Kampanye

```json
{
  "id": 3,
  "title": "Production Update",
  "content": "We've placed the order for materials...",
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

### Referensi Kolom

| Kolom | Tipe | Deskripsi |
|-------|------|-------------|
| `id` | integer | ID pembaruan |
| `title` | string | Judul pembaruan |
| `content` | string | Konten pembaruan (teks yang aman dari HTML) |
| `created_at` | datetime | Format `Y-m-d H:i:s` |

## Aturan Bisnis

### 1. Persyaratan Kampanye Aktif

Pembaruan kampanye hanya dapat dibuat untuk kampanye dengan `status = 'active'`. Mencoba membuat pembaruan pada kampanye DRAFT, REVIEW, SUCCESS, atau FAILED menghasilkan error `409 Conflict` dengan pesan "Campaign has not been approved yet".

### 2. Notifikasi Backer

Saat pembaruan baru diposting:
1. Semua **unik** ID backer pengguna dikumpulkan dari backing kampanye
2. Catatan `Notification` dimasukkan massal (menggunakan `Notification::insert()`) untuk efisiensi
3. Setiap notifikasi memiliki `type = 'campaign_update'`, `title`, dan `body` berisi konten pembaruan
4. Notifikasi dibuat secara sinkron (tidak menggunakan antrian)

### 3. Pemeriksaan Kepemilikan

Hanya pembuat kampanye yang dapat membuat, mengedit, atau menghapus pembaruan. Pemeriksaan dilakukan di level Form Request (`authorize()` method) dan di dalam service.

## Pengujian Postman

### Skrip Pengujian (Pembaruan Kampanye)

#### Pengujian 1: Daftar Pembaruan (Public)

1. `GET {{base_url}}/campaigns/test-campaign/updates`
2. Diperkirakan: `200 OK` dengan array pembaruan.

#### Pengujian 2: Buat Pembaruan (Creator)

1. `POST {{base_url}}/campaigns/{active-sluggy}/updates`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Body:
   ```json
   {
     "title": "New Update",
     "content": "Campaign is going well!"
   }
   ```
4. Diperkirakan: `201 Created` + pembaruan dibuat.

#### Pengujian 3: Buat Pembaruan pada Kampanye Non-Aktif

1. Gunakan slug kampanye DRAFT atau REVIEW.
2. Sama seperti Pengujian 2.
3. Diperkirakan: `409 Conflict`.

#### Pengujian 4: Perbarui Pembaruan yang Ada

1. `PUT {{base_url}}/campaigns/{slug}/updates/{id}`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Body:
   ```json
   { "title": "Updated Title" }
   ```
4. Diperkirakan: `200 OK`.

#### Pengujian 5: Hapus Pembaruan

1. `DELETE {{base_url}}/campaigns/{slug}/updates/{id}`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Diperkirakan: `200 OK` + "Update deleted successfully".

#### Pengujian 6: Akses sebagai Non-Creator

1. Gunakan token creator yang berbeda.
2. Coba buat/perbarui/hapus pembaruan pada kampanye orang lain.
3. Diperkirakan: `403 Forbidden`.

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Daftar pembaruan (public) | Slug valid | 200 + array pembaruan |
| 2 | Buat pembaruan pada kampanye aktif | Judul + konten valid | 201 + pembaruan dibuat |
| 3 | Buat pembaruan pada kampanye draft | Data valid | 409 konflik |
| 4 | Buat pembaruan pada kampanye review | Data valid | 409 konflik |
| 5 | Buat pembaruan pada kampanye success/failed | Data valid | 409 konflik |
| 6 | Buat pembaruan sebagai non-pemilik | Kampanye creator lain | 403 dilarang |
| 7 | Buat pembaruan tanpa judul | Judul hilang | 422 error validasi |
| 8 | Buat pembaruan tanpa konten | Konten hilang | 422 error validasi |
| 9 | Perbarui pembaruan yang ada (pemilik) | ID valid + data | 200 + diperbarui |
| 10 | Perbarui pembaruan (non-pemilik) | Pembaruan creator lain | 403 dilarang |
| 11 | Hapus pembaruan (pemilik) | ID valid | 200 + pesan terhapus |
| 12 | Hapus pembaruan (non-pemilik) | Pembaruan creator lain | 403 dilarang |
| 13 | Backer menerima notifikasi | Setelah creator membuat pembaruan | Catatan notifikasi dibuat |
| 14 | Daftar pembaruan pada slug tidak valid | Slug tidak ada | 404 tidak ditemukan |

## Pemecahan Masalah

### 1. "Campaign has not been approved yet" (409)

Error ini terjadi ketika mencoba membuat pembaruan pada kampanye yang statusnya bukan `ACTIVE`. Metode `CampaignUpdateService::create()` melempar `ConflictHttpException`.

**Perbaikan:** Pastikan kampanye telah disetujui oleh admin dan memiliki `status = 'active'`.

---

### 2. Notifikasi tidak dibuat untuk backer

Jika backer tidak menerima notifikasi dalam aplikasi setelah pembaruan:

1. Periksa apakah kampanye memiliki setidaknya satu backing COMPLETED
2. Metode `notifyBackers()` mendapatkan ID pengguna backer yang **unik** — pastikan tabel `backings` memiliki entri dengan `status = 'completed'`
3. Notifikasi dibuat secara sinkron menggunakan `Notification::insert()` — tidak diperlukan pemrosesan antrian

> **Peringatan:** Pembuatan notifikasi ini **tidak diantri**. Untuk kampanye dengan ribuan backer, ini dapat menyebabkan timeout. Pertimbangkan penggunaan pengiriman notifikasi berbasis antrian untuk skenario bervolume tinggi.

---

### 3. Pembaruan dihapus tetapi backer tidak diberi tahu

Menghapus pembaruan **tidak** mengirim notifikasi ke backer. Hanya **membuat** pembaruan baru yang memicu notifikasi. Ini memang dimaksudkan.

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Daftar pembaruan kampanye | Public | — |
| Buat pembaruan | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Perbarui pembaruan | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Hapus pembaruan | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
