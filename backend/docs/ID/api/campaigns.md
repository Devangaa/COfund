# API Modul Kampanye

Pembuatan, pengelolaan, pencantuman, tinjauan, dan aksi administratif kampanye.

## Arsitektur

Modul kampanye menerapkan siklus hidup multi-tahap (DRAFT → REVIEW → ACTIVE → SUCCESS/FAILED). Menggunakan `CampaignService` khusus untuk logika bisnis, Form Request untuk validasi, dan kelas Resource untuk serialisasi JSON. Gambar, tier, dan pembaruan dikelola melalui controller terpisah namun dikaitkan dengan sebuah kampanye.

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/CampaignController.php` | Semua CRUD kampanye + aksi tinjauan admin |
| Service | `app/Services/CampaignService.php` | Logika bisnis untuk buat, perbarui, tinjau, setuji/tolak |
| Requests | `app/Http/Requests/{StoreCampaignRequest, UpdateCampaignRequest, SubmitCampaignReviewRequest, DeleteCampaignRequest}.php` | Aturan validasi per aksi |
| Resource | `app/Http/Resources/CampaignResource.php`, `CampaignTierResource.php`, `CampaignImageResource.php`, `CampaignUpdateResource.php`, `CategoryResource.php` | Pemformatan respons JSON |
| Model | `app/Models/Campaign.php`, `CampaignTier.php`, `CampaignImage.php`, `CampaignUpdate.php`, `Category.php` | Model Eloquent dengan relasi |
| Enum | `app/Enums/CampaignStatus.php` | Keadaan status kampanye |
| Job (terkait) | `app/Jobs/DisburseCampaignJob.php` | Dipanggil ketika kampanye mencapai target pendanaan |

### Alur

```
Creator → StoreCampaignRequest → CampaignService::create() → DB Transaction
         → Upload gambar ke disk campaigns → Atur pertama sebagai primary
         → Buat tier → Atur status=DRAFT

Creator → SubmitCampaignReviewRequest → CampaignService::submitForReview() → Atur status=REVIEW

Admin → setuji/tolak → CampaignService::approve()/reject() → Trigger Event

System → BackingService cek target → Trigger CampaignFunded
        → HandleCampaignFunded listener → DisburseCampaignJob (antrian)

System → ExpireScheduler (cron) → Perintah CheckExpiredCampaigns
        → Auto-success/failed + panggil job
```

## Struktur File

```
app/
├── Http/Controllers/Api/CampaignController.php
├── Services/CampaignService.php
├── Http/Requests/
│   ├── StoreCampaignRequest.php
│   ├── UpdateCampaignRequest.php
│   ├── SubmitCampaignReviewRequest.php
│   └── DeleteCampaignRequest.php
├── Http/Resources/
│   ├── CampaignResource.php
│   ├── CampaignTierResource.php
│   ├── CampaignImageResource.php
│   ├── CampaignUpdateResource.php
│   └── CategoryResource.php
├── Models/
│   ├── Campaign.php
│   ├── CampaignTier.php
│   ├── CampaignImage.php
│   ├── CampaignUpdate.php
│   └── Category.php
└── Enums/CampaignStatus.php
```

## Siklus Hidup Kampanye

```
DRAFT → (submit for review) → REVIEW → (admin approve) → ACTIVE → (funded) → SUCCESS
                                → (admin reject) →  ↖       → (expired, unfunded) → FAILED
```

### Status States

| Status | Label | Deskripsi |
|--------|-------|-------------|
| `DASHBOARD` | `draft` | Keadaan awal setelah pembuatan |
| `REVIEW` | `review` | Diajukan untuk tinjauan admin |
| `ACTIVE` | `active` | Diterbitkan dan menerima dukungan |
| `SUCCESS` | `success` | Mencapai target pendanaan sebelum deadline |
| `FAILED` | `failed` | Deadline kadaluarsa sebelum mencapai target |

## API Endpoints

### 1. Daftar Kampanye

Mengembalikan daftar kampanye yang dipaginasi dengan filter, pengurutan, dan pencarian opsional.

**Endpoint:** `GET /api/campaigns`  
**Middleware:** `public`  
**Deskripsi:** Mencantumkan kampanye dengan filter, pengurutan, dan pencarian.

#### Parameter Kueri

| Parameter | Tipe | Wajib | Default | Deskripsi |
|-----------|------|----------|---------|-------------|
| `page` | integer | Tidak | 1 | Nomor halaman untuk paginasi |
| `per_page` | integer | Tidak | 12 | Jumlah item per halaman |
| `search` | string | Tidak | — | Cari di judul, deskripsi, dan nama creator |
| `category` | string | Tidak | — | Filter berdasarkan slug kategori |
| `min_amount` | decimal | Tidak | — | Filter kampanye dengan target ≥ jumlah ini |
| `max_amount` | decimal | Tidak | — | Filter kampanye dengan jumlah terkumpul ≤ jumlah ini |
| `status` | string | Tidak | `active` | Filter berdasarkan status. Hanya admin dan creator (?scope=mine) yang menghormati ini. Guest/backer selalu dapat yang aktif saja |
| `scope` | string | Tidak | `public` | Untuk creator: `mine` menampilkan semua kampanye milik creator (status apa saja). Publik hanya menampilkan yang aktif |
| `sort` | string | Tidak | `latest` | Mode pengurutan: `latest` (berdasarkan created_at desc), `oldest` (berdasarkan created_at asc), `popular` (berdasarkan collected_amount desc) |
| `start_date` | date | Tidak | — | Filter kampanye yang dibuat pada atau setelah tanggal ini (format: YYYY-MM-DD) |
| `end_date` | date | Tidak | — | Filter kampanye yang dibuat pada atau sebelum tanggal ini (format: YYYY-MM-DD). Harus ≥ start_date |

#### Respons (Sukses: 200)

```json
{
  "data": [
    {
      "id": 1,
      "creator": {
        "id": 2,
        "name": "Zaki Creator 1",
        "email": "creator1@example.com",
        "role": "creator",
        "balance": "0.00"
      },
      "category": {
        "id": 1,
        "name": "Teknologi",
        "slug": "teknologi"
      },
      "title": "Kampanye Teknologi Gojek",
      "slug": "kampanye-teknologi-gojek",
      "description": "Deskripsi kampanye...",
      "description_html": "<p>Deskripsi kampanye...</p>",
      "target_amount": "5000000.00",
      "collected_amount": "3000000.00",
      "progress_percentage": 60,
      "deadline": "2026-08-31",
      "status": "active",
      "video_url": "https://www.youtube.com/watch?v=example",
      "rejection_note": null,
      "reviewed_at": "2026-08-20T10:00:00.000000Z",
      "images": [
        {
          "id": 1,
          "url": "http://localhost/storage/campaigns/IMG-abc123.jpg",
          "is_primary": true
        }
      ],
      "tiers": [
        {
          "id": 1,
          "name": "Early Bird",
          "min_amount": "100000.00",
          "quota": 10,
          "remaining_quota": 5,
          "has_availability": true,
          "is_unlimited": false,
          "reward_description": "Reward for early birds"
        }
      ],
      "updates_count": 2
    }
  ],
  "links": {
    "first": "http://localhost/api/campaigns?page=1",
    "last": "http://localhost/api/campaigns?page=5",
    "prev": null,
    "next": "http://localhost/api/campaigns?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "links": [...],
    "path": "http://localhost/api/campaigns",
    "per_page": 12,
    "to": 12,
    "total": 60
  }
}
```

#### Catatan

- Secara default, **hanya kampanye yang AKTIF** yang dikembalikan. Untuk melihat kampanye dalam status lain, kirim parameter `status` secara eksplisit.
- Parameter `search` menggunakan indeks `FULLTEXT` MySQL (ditambahkan melalui migrasi `2026_08_26_165000_add_fulltext_search_to_campaigns_table.php`) — namun pencarian dengan kurang dari 4 karakter mungkin tidak cocok.
- Pengurutan berdasarkan `deadline` akan menempatkan kampanye yang berakhir lebih dulu pertama (jika `order=asc`).

---

### 2. Dapatkan Detail Kampanye

Mengembalikan detail lengkap dari sebuah kampanye termasuk semua relasinya.

**Endpoint:** `GET /api/campaigns/{slug}`  
**Middleware:** `public`  
**Deskripsi:** Mengembalikan satu kampanye berdasarkan slug dengan semua sumber daya terkait.

#### Respons (Sukses: 200)

```json
{
  "id": 1,
  "creator": {
    "id": 2,
    "name": "Zaki Creator 1",
    "email": "creator1@example.com",
    "role": "creator",
    "balance": "0.00",
    "email_verified_at": "2026-08-24T10:00:00.000000Z",
    "is_suspended": false
  },
  "category": {
    "id": 1,
    "name": "Teknologi",
    "slug": "teknologi"
  },
  "title": "Kampanye Teknologi Gojek",
  "slug": "kampanye-teknologi-gojek",
  "description": "Deskripsi kampanye...",
  "target_amount": "5000000.00",
  "collected_amount": "3000000.00",
  "progress_percentage": 60,
  "deadline": "2026-08-31",
  "status": "active",
  "video_url": "https://www.youtube.com/watch?v=example",
  "rejection_note": null,
  "reviewed_at": "2026-08-20T10:00:00.000000Z",
  "images": [...],
  "tiers": [...],
  "updates": [...],
  "updates_count": 2
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 404 | `Campaign not found` | Slug tidak ada |

---

### 3. Buat Kampanye

Membuat kampanye baru dalam status DRAFT.

**Endpoint:** `POST /api/campaigns`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Membuat kampanye baru dengan gambar dan tier.

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `category_id` | integer | Ya | `required, exists:categories,id` | Kategori kampanye |
| `title` | string | Ya | `required, string, max:100` | Judul kampanye |
| `description` | string | Ya | `required, string, max:2000` | Deskripsi detail |
| `target_amount` | decimal | Ya | `required, numeric, min:100000` | Target pendanaan (min 100.000) |
| `deadline` | date | Ya | `required, date, after:+7 days` | Deadline (harus minimal 7 hari dari sekarang) |
| `video_url` | string | Tidak | `nullable, string, url` | URL video YouTube/video |
| `images` | array | Ya | `required, array, min:1, max:5` | Gambar kampanye |
| `images.*.file` | file | Ya | `required, image, mimes:jpeg,png,jpg,gif, max:2048` | Berkas gambar |
| `tiers` | array | Ya | `required, array, min:1` | Tier hadiah |
| `tiers.*.name` | string | Ya | `required, string, max:255` | Nama tier |
| `tiers.*.min_amount` | decimal | Ya | `required, numeric, min:0` | Jumlah dukungan minimum |
| `tiers.*.quota` | integer | Ya | `required, integer, min:0` | Kuota (0 = tak terbatas) |
| `tiers.*.reward_description` | string | Tidak | `nullable, string` | Deskripsi hadiah |

#### Contoh Request

```json
{
  "category_id": 1,
  "title": "Kampanye Teknologi Gojek",
  "description": "Deskripsi kampanye...",
  "target_amount": 5000000,
  "deadline": "2026-09-15",
  "video_url": "https://www.youtube.com/watch?v=example",
  "tiers": [
    {
      "name": "Early Bird",
      "min_amount": 100000,
      "quota": 10,
      "reward_description": "Special reward for early birds"
    },
    {
      "name": "Supporter",
      "min_amount": 250000,
      "quota": 20,
      "reward_description": "Standard supporter reward"
    }
  ]
}
```

> **Catatan:** Gambar diunggah sebagai multipart form-data, bukan JSON. Setiap berkas gambar dikaitkan sebagai `images[]`.

#### Contoh Multipart Form Data

```
POST /api/campaigns
Content-Type: multipart/form-data
Authorization: Bearer {token}

Form data:
- category_id: 1
- title: Kampanye Teknologi Gojek
- description: Deskripsi kampanye...
- target_amount: 5000000
- deadline: 2026-09-15
- video_url: https://www.youtube.com/watch?v=example
- images[]: (file upload 1)
- images[]: (file upload 2)
- tiers: [{"name": "Early Bird", "min_amount": 100000, "quota": 10, "reward_description": "..."}]
```

#### Respons (Sukses: 201)

```json
{
  "id": 1,
  "creator": {
    "id": 2,
    "name": "Zaki Creator 1",
    "email": "creator1@example.com",
    "role": "creator",
    "balance": "0.00"
  },
  "category": {
    "id": 1,
    "name": "Teknologi",
    "slug": "teknologi"
  },
  "title": "Kampanye Teknologi Gojek",
  "slug": "kampanye-teknologi-gojek",
  "description": "Deskripsi kampanye...",
  "target_amount": "5000000.00",
  "collected_amount": "0.00",
  "progress_percentage": 0,
  "deadline": "2026-09-15",
  "status": "draft",
  "video_url": "https://www.youtube.com/watch?v=example",
  "rejection_note": null,
  "images": [...],
  "tiers": [...],
  "updates_count": 0
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 403 | `You do not have permission to access this resource.` | Pengguna bukan creator |
| 422 | `Validation error` | Field yang hilang/tidak valid |

---

### 4. Perbarui Kampanye

Memperbarui kampanye yang ada dalam status DRAFT atau REVIEW.

**Endpoint:** `PUT /api/campaigns/{slug}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Memperbarui informasi kampanye. Hanya bisa diedit dalam keadaan DRAFT atau REVIEW.

#### Otorisasi

Pengguna harus memiliki kepemilikan kampanye (`$campaign->user_id === auth()->id()`).

#### Body Permintaan

Semua field bersifat opsional (validasi `sometimes`):

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `title` | string | Tidak | `sometimes, string, max:100` | Judul baru |
| `description` | string | Tidak | `sometimes, string, max:2000` | Deskripsi baru |
| `target_amount` | decimal | Tidak | `sometimes, numeric, min:100000` | Target baru |
| `deadline` | date | Tidak | `sometimes, date, after:+7 days` | Deadline baru |
| `video_url` | string | Tidak | `sometimes, string, url` | URL video baru |
| `category_id` | integer | Tidak | `sometimes, exists:categories,id` | Kategori baru |

#### Respons (Sukses: 200)

Mengembalikan `CampaignResource` yang diperbarui.

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 403 | `You do not have permission to access this resource.` | Bukan pemilik kampanye |
| 409 | `Campaign is not in editable state` | Status ACTIVE/SUCCESS/FAILED |
| 422 | `Validation error` | Field tidak valid |

---

### 5. Ajukan Kampanye untuk Tinjauan

Mentransisikan kampanye dari DRAFT/REVIEW ke status REVIEW.

**Endpoint:** `POST /api/campaigns/{slug}/submit-review`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Mengajukan kampanye untuk tinjauan admin.

#### Respons (Sukses: 200)

```json
{
  "message": "Campaign submitted for review",
  "campaign": { ... full CampaignResource ... }
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 403 | You do not have permission to access this resource. | Bukan pemilik kampanye |
| 409 | Campaign is not in editable state | Status ACTIVE/SUCCESS/FAILED |

---

### 6. Hapus Kampanye

Menghapus kampanye secara lembut (soft-delete) dalam status DRAFT.

**Endpoint:** `DELETE /api/campaigns/{slug}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Menghapus kampanye secara lembut beserta gambar, tier, dan pembaruan yang terkait.

#### Respons (Sukses: 200)

```json
{
  "message": "Campaign deleted successfully"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 403 | You do not have permission to access this resource. | Bukan pemilik kampanye |
| 409 | Campaign is not in editable state | Status ACTIVE/SUCCESS/FAILED |

---

### 7. Setujui Kampanye (Admin)

Mentransisikan kampanye dari REVIEW ke status ACTIVE.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/approve`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Menyetujui kampanye yang sedang ditinjau. Memicu event `CampaignApproved`.

#### Respons (Sukses: 200)

```json
{
  "message": "Campaign approved",
  "campaign": { ... full CampaignResource ... }
}
```

#### Efek Samping

- `status` → `active`
- `reviewed_by` → ID admin
- `reviewed_at` → timestamp saat ini
- Memicu event `CampaignApproved` → Membuat notifikasi dalam aplikasi + email ke pembuat

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 403 | You do not have permission to access this resource. | Bukan admin |
| 404 | Campaign not found | Slug tidak valid |

---

### 8. Tolak Kampanye (Admin)

Mengembalikan kampanye dari REVIEW ke DRAFT dengan catatan penolakan.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/reject`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Menolak kampanye. Memicu event `CampaignRejected`.

#### Body Permintaan

| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|----------|-------------|
| `rejection_note` | string | Ya | Alasan penolakan |

#### Contoh Request

```json
{
  "rejection_note": "Campaign description is too vague."
}
```

#### Respons (Sukses: 200)

```json
{
  "message": "Campaign rejected",
  "campaign": { ... }
}
```

#### Efek Samping

- `status` → `draft`
- `rejection_note` → diatur
- `reviewed_by` → ID admin
- `reviewed_at` → timestamp saat ini
- Memicu event `CampaignRejected` → Membuat notifikasi dalam aplikasi + email ke pembuat

---

### 9. Gugurkan Paksa Kampanye (Admin)

Memaksakan kampanye ke status FAILED.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/force-fail`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Deskripsi:** Menandai kampanye sebagai gagal secara manual.

#### Respons (Sukses: 200)

```json
{
  "message": "Campaign marked as failed"
}
```

---

## Skema Sumber Daya Kampanye

```json
{
  "id": 1,
  "creator": {
    "id": 2,
    "name": "Zaki Creator 1",
    "email": "creator1@example.com",
    "role": "creator",
    "balance": "0.00"
  },
  "category": {
    "id": 1,
    "name": "Teknologi",
    "slug": "teknologi"
  },
  "title": "Kampanye Teknologi Gojek",
  "slug": "kampanye-teknologi-gojek",
  "description": "...",
  "target_amount": "5000000.00",
  "collected_amount": "3000000.00",
  "progress_percentage": 60,
  "deadline": "2026-08-31",
  "status": "active",
  "video_url": "...",
  "rejection_note": null,
  "reviewed_at": "2026-08-20T10:00:00.000000Z",
  "images": [...],
  "tiers": [...],
  "updates": [...],
  "updates_count": 2
}
```

## Pengujian Postman

### Skrip Pengujian (Kampanye)

#### Pengujian 1: Daftar Kampanye Aktif

1. Atur permintaan: `GET {{base_url}}/campaigns`
2. Diperkirakan: `200 OK` dengan data terpaginasi (hanya kampanye AKTIF secara default).

#### Pengujian 2: Filter Berdasarkan Kategori

1. Atur permintaan: `GET {{base_url}}/campaigns?category_id=1`
2. Diperkirakan: `200 OK` dengan kampanye yang disaring berdasarkan kategori.

#### Pengujian 3: Cari Kampanye

1. Atur permintaan: `GET {{base_url}}/campaigns?search=teknologi`
2. Diperkirakan: `200 OK` dengan kampanye yang cocok dengan istilah pencarian.

#### Pengujian 4: Urutkan Berdasarkan Jumlah Terkumpul

1. Atur permintaan: `GET {{base_url}}/campaigns?sort_by=collected_amount&order=desc`
2. Diperkirakan: `200 OK` dengan kampanye yang diurutkan berdasarkan jumlah terkumpul (menurun).

#### Pengujian 5: Dapatkan Detail Kampanye

1. Atur permintaan: `GET {{base_url}}/campaigns/kampanye-teknologi-gojek`
2. Diperkirakan: `200 OK` dengan detail kampanye lengkap.

#### Pengujian 6: Buat Kampanye (Creator)

1. Atur permintaan: `POST {{base_url}}/campaigns`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Body (multipart form-data dengan gambar + field JSON).
4. Diperkirakan: `201 Created` dengan kampanye dalam status DRAFT.

#### Pengujian 7: Ajukan Kampanye untuk Tinjauan

1. Atur permintaan: `POST {{base_url}}/campaigns/{slug}/submit-review`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Diperkirakan: `200 OK` dengan status=REVIEW.

#### Pengujian 8: Setujui Kampanye (Admin)

1. Atur permintaan: `PUT {{base_url}}/admin/campaigns/{slug}/approve`
2. Header: `Authorization: Bearer {{admin_token}}`
3. Diperkirakan: `200 OK` dengan status=ACTIVE.

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Daftar kampanye (tanpa parameter) | Tidak ada | 200 + hanya kampanye aktif |
| 2 | Filter berdasarkan kategori | `category_id=1` | 200 + daftar yang disaring |
| 3 | Filter berdasarkan target minimum | `min_amount=1000000` | 200 + kampanye ≥ 1M |
| 4 | Filter berdasarkan target maksimum | `max_amount=10000000` | 200 + kampanye ≤ 10M |
| 5 | Urutkan berdasarkan terkumpul | `sort_by=collected_amount&order=desc` | 200 + daftar yang diurutkan |
| 6 | Cari berdasarkan kata kunci | `search=teknologi` | 200 + kampanye yang cocok |
| 7 | Dapatkan kampanye berdasarkan slug | Slug valid | 200 + detail lengkap |
| 8 | Dapatkan kampanye dengan slug tidak valid | Slug tidak valid | 404 tidak ditemukan |
| 9 | Buat kampanye (creator) | Data valid + gambar | 201 + kampanye draft |
| 10 | Buat kampanye (backer) | Data apa saja | 403 dilarang |
| 11 | Buat kampanye tanpa gambar | Tidak ada gambar | 422 error validasi |
| 12 | Buat kampanye dengan deadline pendek | deadline ≤ 7 hari | 422 error validasi |
| 13 | Perbarui kampanye sendiri (draft) | Field apa saja | 200 + diperbarui |
| 14 | Perbarui kampanye orang lain | Field apa saja | 403 dilarang |
| 15 | Perbarui kampanye aktif | Field apa saja | 409 tidak bisa diedit |
| 16 | Ajukan untuk tinjauan (pemilik) | Slug valid | 200 + status=REVIEW |
| 17 | Ajukan untuk tinjauan (bukan pemilik) | Slug valid | 403 dilarang |
| 18 | Hapus kampanye (pemilik, draft) | Slug valid | 200 + dihapus |
| 19 | Hapus kampanye aktif | Slug valid | 409 tidak bisa diedit |
| 20 | Setujui kampanye (admin) | Slug valid dalam REVIEW | 200 + status=ACTIVE |
| 21 | Setujui kampanye (bukan admin) | Slug valid | 403 dilarang |
| 22 | Tolak kampanye (admin) | slug + rejection_note | 200 + status=DRAFT |
| 23 | Gugurkan paksa kampanye (admin) | Slug valid | 200 + status=FAILED |

## Pemecahan Masalah

### 1. "Campaign is not in editable state" (409)

Error ini terjadi ketika mencoba memperbarui, mengajukan, atau menghapus kampanye yang statusnya `active`, `success`, atau `failed`. Hanya kampanye dalam status `draft` atau `review` yang dapat diedit.

**Perbaikan:** Periksa status saat ini kampanye. Kampanye tidak boleh dimodifikasi setelah mulai menerima dukungan atau mencapai status sukses/gagal.

---

### 2. Unggah Gambar Gagal

Server mengharapkan gambar sebagai **multipart form-data**, bukan JSON. Setiap berkas gambar harus disertakan sebagai unggahan terpisah dengan kunci `images[]`.

**Perbaikan:** Gunakan enkode multipart yang sesuai. Di Postman, pilih "form-data" dan tambahkan berkas di bawah kunci `images[]`.

---

### 3. Pencarian Tidak Mengembalikan Hasil

Pencarian menggunakan indeks `FULLTEXT` MySQL pada kolom `title` dan `description`. MySQL memerlukan istilah pencarian minimal 4 karakter secara default.

**Perbaikan:** Pastikan istilah pencarian Anda ≥ 4 karakter.

---

### 4. Konflik Slug

Jika dua kampanye memiliki judul yang sama, model secara otomatis menambahkan `-1`, `-2`, dll. ke slug untuk memastikan keunikan.

---

### 5. Error Validasi Deadline

Field `deadline` harus minimal **7 hari setelah tanggal saat ini** (aturan `after:+7 days`).

**Perbaikan:** Atur deadline minimal satu minggu ke depan.

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Daftar kampanye | Public | — |
| Dapatkan detail kampanye | Public | — |
| Buat kampanye | Creator | `auth:sanctum, role:creator, verified` |
| Perbarui kampanye | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Ajukan untuk tinjauan | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Hapus kampanye | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Setujui kampanye | Admin | `auth:sanctum, role:admin` |
| Tolak kampanye | Admin | `auth:sanctum, role:admin` |
| Gugurkan paksa | Admin | `auth:sanctum, role:admin` |
