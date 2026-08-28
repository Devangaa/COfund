# CoFund API - Modul Kampanye (Campaigns Module)

## 1. Judul & Deskripsi Modul

Modul kampanye adalah inti utama platform CoFund. Pengguna dengan peran `creator` dapat membuat, mengupdate, menghapus, dan mengirimkan kampanye untuk direview. Pengguna dengan peran `admin` dapat menyetujui atau menolak kampanye. Semua pengguna dapat melihat daftar kampanye aktif dan detail kampanye.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/CampaignController.php` | Mengelola semua endpoint kampanye |
| **Service** | `app/Services/CampaignService.php` | Logika bisnis kampanye (create, update, delete, approve, reject, forceFail) |
| **Form Request** | `app/Http/Requests/StoreCampaignRequest.php` | Validasi pembuatan kampanye |
| | `app/Http/Requests/UpdateCampaignRequest.php` | Validasi update kampanye |
| | `app/Http/Requests/SubmitCampaignReviewRequest.php` | Validasi submit review |
| | `app/Http/Requests/DeleteCampaignRequest.php` | Validasi hapus kampanye |
| **Resource** | `app/Http/Resources/CampaignResource.php` | Serialisasi data kampanye |
| **Model** | `app/Models/Campaign.php` | Model Campaign dengan relasi creator, category, images, tiers, updates |
| **Enums** | `app/Enums/CampaignStatus.php` | `draft`, `review`, `active`, `success`, `failed` |
| **Middleware** | `auth:sanctum`, `role:creator`, `role:admin`, `verified` | Kontrol akses berbasis peran |
| **Jobs** | `app/Jobs/DisburseCampaignJob.php` | Job pencairan dana (trigger ketika kampanye berhasil) |
| | `app/Jobs/RefundBackersJob.php` | Job refund backer (trigger ketika kampanye gagal) |
| **Commands** | `app/Console/Commands/CheckExpiredCampaigns.php` | Command cron pengecekan kampanye kadaluarsa |

### Alur Proses Logika Bisnis

```
                    PENGRUH
                        |
                        v
  +-----------+   +----------------+   +----------------+
  |   LIST    |   |   DETAIL       |   |   CREATE       |
  | Campaigns |   |   Campaign     |   |   Campaign     |
  +-----------+   +----------------+   +----------------+
                        |                    |
                        |                    v
                        |         StoreCampaignRequest
                        |                    |
                        |                    v
                        |           CampaignService::create()
                        |                    |
                        |         +----------+----------+
                        |         |                     |
                        |         v                     v
                        |   Create Campaign         Create Tiers
                        |   (status=DRAFT)        (from payload)
                        |         |                     |
                        |         v                     v
                        |   Store Images           Store Tiers
                        |   via Storage::disk      via CampaignTier
                        |
                        |         v
                        |   Return CampaignResource
                        |
                        |
        KREATOR         v
  +-----------+   +----------------+
  |  UPDATE   |   | SUBMIT REVIEW  |
  +-----------+   +----------------+
        |               |
        v               v
  UpdateCampaignRequest   SubmitCampaignReviewRequest
        |               |
        v               v
  CampaignService::update()  CampaignService::submitForReview()
        |               |
        v               v
  Campaign.status = DRAFT  Campaign.status = REVIEW
        |               |
        v               v
  Return updated    Return status=REVIEW
  CampaignResource
        |
        v
  +-----------+
  | DELETE    |
  +-----------+
        |
        v
  DeleteCampaignRequest
        |
        v
  CampaignService::destroy()
  - Delete images (Storage::disk)
  - Delete tiers
  - Delete updates
  - Soft delete campaign

        ADMIN
         |
    +----+----+----+
    |         |    |
    v         v    v
  APPROVE   REJECT  FORCE-FAIL
    |         |    |
    v         v    v
  Set status=ACTIVE   Set status=DRAFT   Set status=FAILED
  + reviewed_by/at    + rejection_note    + dispatch RefundBackersJob
  + fire CampaignApproved   + fire CampaignRejected
  + email to creator        + email to creator
```

### Siklus Status Kampanye

```
DRAFT --> REVIEW --> ACTIVE --> SUCCESS --> (DisburseCampaignJob)
                    |   ^
                    |   |
                    v   |
                  FAILED --> (RefundBackersJob)
                    ^
                    |
               [forceFail by admin]
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── CampaignController.php
│   │   │       ├── CampaignImageController.php
│   │   │       ├── CampaignUpdateController.php
│   │   │       └── TierController.php
│   │   ├── Requests/
│   │   │   ├── StoreCampaignRequest.php
│   │   │   ├── UpdateCampaignRequest.php
│   │   │   ├── SubmitCampaignReviewRequest.php
│   │   │   └── DeleteCampaignRequest.php
│   │   └── Resources/
│   │       ├── CampaignResource.php
│   │       ├── CampaignImageResource.php
│   │       ├── CampaignTierResource.php
│   │       └── CampaignUpdateResource.php
│   ├── Models/
│   │   ├── Campaign.php
│   │   ├── CampaignImage.php
│   │   ├── CampaignTier.php
│   │   └── CampaignUpdate.php
│   ├── Enums/
│   │   ├── CampaignStatus.php
│   │   └── Category.php
│   ├── Services/
│   │   ├── CampaignService.php
│   │   ├── CampaignImageService.php
│   │   ├── CampaignUpdateService.php
│   │   └── TierService.php
│   ├── Jobs/
│   │   ├── DisburseCampaignJob.php
│   │   └── RefundBackersJob.php
│   └── Console/
│       └── Commands/
│           └── CheckExpiredCampaigns.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: List Campaigns (Index)

- **Deskripsi:** Mendapatkan daftar kampanye dengan filter, pencarian, dan pagination. Perilaku yang berbeda tergantung peran pengguna.
- **HTTP Method & URL Path:** `GET /api/v1/campaigns`
- **Middleware:** *(opsional)* `auth:sanctum`, `role:creator`, `role:admin`
- **Autentikasi:** Bearer Token (opsional, tanpa token hanya kampanye `active` yang ditampilkan)

#### Tabel Parameter (Query)

| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `category` | string | Tidak | `exists:categories,slug` | Filter berdasarkan slug kategori |
| `status` | string | Tidak | `in:draft,review,active,success,failed` | Filter berdasarkan status (hanya berlaku `scope=mine` untuk creator) |
| `scope` | string | Tidak | `in:mine,public` | `mine` untuk melihat kampanye sendiri |
| `sort` | string | Tidak | `latest`, `oldest`, `popular` | Urutkan berdasarkan tanggal dibuat atau terpopuler |
| `search` | string | Tidak | `max:255` | Pencarian berdasarkan judul, deskripsi, kreator, atau kategori |
| `min_amount` | numeric | Tidak | `min:0` | Filter kampanye dengan `target_amount` minimal |
| `max_amount` | numeric | Tidak | `min:0` | Filter kampanye dengan `collected_amount` maksimal |
| `start_date` | date | Tidak | - | Filter berdasarkan tanggal pembuatan minimum |
| `end_date` | date | Tidak | `after_or_equal:start_date` | Filter berdasarkan tanggal pembuatan maksimal |
| `page` | integer | Tidak | `min:1` | Halaman pagination |
| `per_page` | integer | Tidak | `min:1`, `max:50` | Item per halaman (default: 10, maksimal: 50) |

#### Contoh Request

```
GET /api/v1/campaigns?status=active&sort=popular&category=teknologi&search=donasi&page=1
Authorization: Bearer {token}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "creator": {
                "id": 5,
                "name": "Jane Creator",
                "email": "jane@example.com",
                "role": "creator",
                "balance": 0,
                "email_verified_at": "2024-01-10T08:00:00Z",
                "is_suspended": false
            },
            "category": {
                "id": 3,
                "name": "Teknologi",
                "slug": "teknologi"
            },
            "title": "Platform Donasi Terdesentralisasi",
            "slug": "platform-donasi-terdesentralisasi",
            "description": "Kami ingin membangun platform donasi berbasis blockchain...",
            "description_html": "<p>Kami ingin membangun platform donasi berbasis blockchain...</p>",
            "target_amount": 50000000,
            "collected_amount": 25000000,
            "progress_percentage": 50.0,
            "deadline": "2024-12-31",
            "status": "active",
            "video_url": "https://youtube.com/watch?v=xxxx",
            "rejection_note": null,
            "reviewed_at": "2024-01-15T14:30:00Z",
            "images": [
                {
                    "id": 1,
                    "url": "http://localhost/storage/campaigns/abc.jpg",
                    "is_primary": true
                }
            ],
            "tiers": [
                {
                    "id": 1,
                    "name": "Early Bird",
                    "min_amount": 50000,
                    "quota": 100,
                    "remaining_quota": 50,
                    "is_unlimited": false,
                    "has_availability": true,
                    "reward_description": "Akses eksklusif konten"
                }
            ],
            "updates": [],
            "updates_count": 0
        }
    ],
    "meta": {
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 10,
            "total": 55
        }
    }
}
```

#### Efek Samping

- Query yang berbeda untuk tiap peran pengguna
- Creator dengan `scope=mine` melihat semua kampanye milik sendiri
- Tanpa autentikasi, hanya kampanye `active` yang ditampilkan

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Parameter query tidak valid |

---

### 4.2 Endpoint: Show Campaign (Detail)

- **Deskripsi:** Mendapatkan detail lengkap satu kampanye termasuk images, tiers, dan updates.
- **HTTP Method & URL Path:** `GET /api/v1/campaigns/{campaign:slug}`
- **Middleware:** Tidak ada (public)
- **Autentikasi:** Opsional

#### Tabel Parameter (Path)

| Nama | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `campaign` | string | Ya | Slug kampanye |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": {
        "id": 1,
        "creator": {
            "id": 5,
            "name": "Jane Creator",
            "email": "jane@example.com",
            "role": "creator",
            "balance": 0,
            "email_verified_at": "2024-01-10T08:00:00Z",
            "is_suspended": false
        },
        "category": {
            "id": 3,
            "name": "Teknologi",
            "slug": "teknologi"
        },
        "title": "Platform Donasi Terdesentralisasi",
        "slug": "platform-donasi-terdesentralisasi",
        "description": "Kami ingin membangun platform donasi berbasis blockchain...",
        "description_html": "<p>Kami ingin membangun platform donasi berbasis blockchain...</p>",
        "target_amount": 50000000,
        "collected_amount": 25000000,
        "progress_percentage": 50.0,
        "deadline": "2024-12-31",
        "status": "active",
        "video_url": "https://youtube.com/watch?v=xxxx",
        "rejection_note": null,
        "reviewed_at": "2024-01-15T14:30:00Z",
        "images": [
            {
                "id": 1,
                "url": "http://localhost/storage/campaigns/abc.jpg",
                "is_primary": true
            }
        ],
        "tiers": [
            {
                "id": 1,
                "name": "Early Bird",
                "min_amount": 50000,
                "quota": 100,
                "remaining_quota": 50,
                "is_unlimited": false,
                "has_availability": true,
                "reward_description": "Akses eksklusif konten"
            }
        ],
        "updates": [
            {
                "id": 1,
                "title": "Update Mingguan #1",
                "content": "Kami telah mencapai 25% target...",
                "content_html": "<p>Kami telah mencapai 25% target...</p>",
                "created_at": "2024-02-01T10:00:00Z"
            }
        ],
        "updates_count": 1
    }
}
```

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 404 | `{"message":"No query results for model [App\\Models\\Campaign]"}`. | Slug kampanye tidak ditemukan |

---

### 4.3 Endpoint: Create Campaign

- **Deskripsi:** Membuat kampanye baru dalam status `draft`. Termasuk upload gambar dan pembuatan tier sekaligus.
- **HTTP Method & URL Path:** `POST /api/v1/campaigns`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter (Body)

| Nama | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|
| `category_id` | integer | Ya | `exists:categories,id` | ID kategori |
| `title` | string | Ya | `max:100` | Judul kampanye |
| `slug` | string | Tidak | `unique:campaigns,slug` | Slug khusus (jika dikosongkan, otomatis dari title) |
| `description` | string | Ya | `max:10000` | Deskripsi dalam format Markdown |
| `target_amount` | numeric | Ya | `min:100000` | Target dana minimal Rp100.000 |
| `deadline` | date | Ya | `after:+7 days` | Deadline minimum 7 hari dari sekarang |
| `video_url` | string | Tidak | `url` | URL video (misal: YouTube) |
| `images` | array | Ya | `min:1`, `max:5` | File gambar (multipart form) |
| `images.*` | file | Ya | `image`, `mimes:jpeg,png,jpg,gif`, `max:2048` | Setiap file gambar |
| `tiers` | array | Ya | `min:1` | Array tier |
| `tiers.*.name` | string | Ya | `max:255` | Nama tier |
| `tiers.*.min_amount` | numeric | Ya | `min:0` | Jumlah minimum backing |
| `tiers.*.quota` | integer | Ya | `min:0` | Kuota maksimum (0 = unlimited) |
| `tiers.*.reward_description` | string | Tidak | - | Deskripsi reward |

#### Contoh Request (Multipart Form Data)

```
POST /api/v1/campaigns
Authorization: Bearer {token}
Content-Type: multipart/form-data

category_id=3
title=Platform Donasi Terdesentralisasi
slug=platform-donasi-terdesentralisasi
description=Kami ingin membangun platform donasi berbasis blockchain...
target_amount=50000000
deadline=2024-12-31
video_url=https://youtube.com/watch?v=xxxx

tiers[0][name]=Early Bird
tiers[0][min_amount]=50000
tiers[0][quota]=100
tiers[0][reward_description]=Akses eksklusif konten

tiers[1][name]=Standard
tiers[1][min_amount]=100000
tiers[1][quota]=0
tiers[1][reward_description]=Semua fitur Early Bird + merchandise

images[]=file:./cover.jpg
images[]=file:./banner.png
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "message": "Campaign created successfully",
    "data": {
        "id": 1,
        "creator": {
            "id": 5,
            "name": "Jane Creator",
            "email": "jane@example.com",
            "role": "creator",
            "balance": 0,
            "email_verified_at": "2024-01-10T08:00:00Z",
            "is_suspended": false
        },
        "category": { ... },
        "title": "Platform Donasi Terdesentralisasi",
        "slug": "platform-donasi-terdesentralisasi",
        "description": "...",
        "description_html": "...",
        "target_amount": 50000000,
        "collected_amount": 0,
        "progress_percentage": 0,
        "deadline": "2024-12-31",
        "status": "draft",
        "video_url": "https://youtube.com/watch?v=xxxx",
        "rejection_note": null,
        "reviewed_at": null,
        "images": [ ... ],
        "tiers": [ ... ],
        "updates": [],
        "updates_count": 0
    }
}
```

#### Efek Samping

- Membuat entri di tabel `campaigns` dengan status `draft`
- Mengunggah gambar ke storage `public/campaigns/`
- Membuat entri di tabel `campaign_tiers`
- Slug otomatis dibuat dari title jika tidak disediakan (dengan deduplikasi)
- Operasi dilakukan dalam transaksi database (`DB::transaction`)

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Pengguna bukan peran `creator` |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Validasi gagal (file terlalu besar, tier kosong, dst.) |

---

### 4.4 Endpoint: Update Campaign

- **Deskripsi:** Memperbarui data kampagne yang masih dalam status `draft`.
- **HTTP Method & URL Path:** `PUT /api/v1/campaigns/{campaign:slug}`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter (Path + Body)

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `category_id` | Body | integer | Tidak | `exists:categories,id` | ID kategori baru |
| `title` | Body | string | Tidak | `max:100` | Judul baru |
| `slug` | Body | string | Tidak | `unique:campaigns,slug,{id}` | Slug baru |
| `description` | Body | string | Tidak | `max:10000` | Deskripsi baru |
| `target_amount` | Body | numeric | Tidak | `min:100000` | Target dana baru |
| `deadline` | Body | date | Tidak | `after:+7 days` | Deadline baru |
| `video_url` | Body | string | Tidak | `url` | URL video |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Campaign updated successfully",
    "data": {
        "id": 1,
        "title": "Platform Donasi V2",
        "status": "draft",
        ...
    }
}
```

#### Efek Samping

- Memperbarui data kampanye jika status masih `draft`
- Membatalkan operasi jika kampanye sudah tidak dalam status `draft` (ConflictHttpException)

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik kampanye |
| 409 | `{"success":false,"message":"Campaign can only be edited in draft status"}` | Kampanye tidak dalam status draft |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Validasi gagal |

---

### 4.5 Endpoint: Submit Campaign for Review

- **Deskripsi:** Mengirimkan kampanye dari status `draft` ke `review` untuk ditinjau admin.
- **HTTP Method & URL Path:** `POST /api/v1/campaigns/{campaign:slug}/submit-review`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter (Path)

| Nama | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `campaign` | string | Ya | Slug kampanye |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Campaign submitted for review",
    "data": {
        "id": 1,
        "status": "review",
        ...
    }
}
```

#### Efek Samping

- Status kampanye berubah dari `draft` menjadi `review`
- Hanya bisa dilakukan ketika status masih `draft`

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik |
| 409 | `{"success":false,"message":"Campaign can only be edited in draft status"}` | Status bukan draft |

---

### 4.6 Endpoint: Delete Campaign

- **Deskripsi:** Menghapus kampanye secara permanen (berserta gambar, tier, dan update terkait).
- **HTTP Method & URL Path:** `DELETE /api/v1/campaigns/{campaign:slug}`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Efek Samping

- Menghapus semua gambar dari storage (`public/campaigns/`)
- Menghapus semua tier dan update terkait
- Soft delete kampanye (jika kampanye sudah pernah dilihat/backing)

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Campaign deleted successfully"
}
```

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik |
| 409 | `{"success":false,"message":"Campaign can only be edited in draft status"}` | Status bukan draft |

---

### 4.7 Endpoint: Approve Campaign (Admin)

- **Deskripsi:** Menyetujui kampanye yang sudah disubmit untuk review. Kampanye otomatis berubah ke status `active`.
- **HTTP Method & URL Path:** `PUT /api/v1/admin/campaigns/{campaign:slug}/approve`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Tabel Parameter (Path)

| Nama | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `campaign` | string | Ya | Slug kampanye |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Campaign approved and is now active",
    "data": {
        "id": 1,
        "status": "active",
        "reviewed_by": 10,
        "reviewed_at": "2024-02-01T12:00:00Z",
        ...
    }
}
```

#### Efek Samping

- Status berubah ke `active`
- `reviewed_by` dan `reviewed_at` di-set
- Trigger event `CampaignApproved`
- Kirim notifikasi in-app dan email ke creator

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"Unauthorized. This action requires Admin role."}` | Bukan admin |

---

### 4.8 Endpoint: Reject Campaign (Admin)

- **Deskripsi:** Menolak kampanye yang sudah disubmit untuk review. Kampanye kembali ke status `draft` dengan catatan penolakan.
- **HTTP Method & URL Path:** `PUT /api/v1/admin/campaigns/{campaign:slug}/reject`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Tabel Parameter (Path + Body)

| Nama | Lokasi | Tipe | Wajib | Deskripsi |
|---|---|---|---|---|
| `campaign` | Path | string | Ya | Slug kampanye |
| `rejection_note` | Body | string | Tidak | Catatan penolakan |

#### Contoh Request

```json
{
    "rejection_note": "Deskripsi terlalu singkat dan kurang detail"
}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Campaign rejected",
    "data": {
        "id": 1,
        "status": "draft",
        "rejection_note": "Deskripsi terlalu singkat dan kurang detail",
        "reviewed_by": 10,
        "reviewed_at": "2024-02-01T12:00:00Z",
        ...
    }
}
```

#### Efek Samping

- Status berubah ke `draft`
- `rejection_note` di-set
- Trigger event `CampaignRejected`
- Kirim notifikasi in-app dan email ke creator

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"Unauthorized. This action requires Admin role."}` | Bukan admin |

---

### 4.9 Endpoint: Force Fail Campaign (Admin)

- **Deskripsi:** Memaksa kampanye ke status `failed` dan memicu proses refund ke semua backer.
- **HTTP Method & URL Path:** `PUT /api/v1/admin/campaigns/{campaign:slug}/force-fail`
- **Middleware:** `auth:sanctum`, `role:admin`

#### Tabel Parameter (Path)

| Nama | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `campaign` | string | Ya | Slug kampanye |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Campaign force-failed",
    "data": {
        "id": 1,
        "status": "failed",
        ...
    }
}
```

#### Efek Samping

- Status berubah ke `failed`
- Jika ada backing yang belum refunded, dispatch `RefundBackersJob` ke queue
- RefundBackersJob akan:
  - Membalik saldo ke backer
  - Membuat transaksi refund di database
  - Mengirim notifikasi dan email ke backer

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"Unauthorized. This action requires Admin role."}` | Bukan admin |

---

## 5. Skema Sumber Daya (Resource Schema)

### CampaignResource

```json
{
    "id": 1,
    "creator": {
        "id": 5,
        "name": "Jane Creator",
        "email": "jane@example.com",
        "role": "creator",
        "balance": 0,
        "email_verified_at": "2024-01-10T08:00:00Z",
        "is_suspended": false
    },
    "category": {
        "id": 3,
        "name": "Teknologi",
        "slug": "teknologi"
    },
    "title": "Platform Donasi",
    "slug": "platform-donasi",
    "description": "...",
    "description_html": "...",
    "target_amount": 50000000,
    "collected_amount": 0,
    "progress_percentage": 0.0,
    "deadline": "2024-12-31",
    "status": "active|draft|review|success|failed",
    "video_url": "https://youtube.com/...",
    "rejection_note": null,
    "reviewed_at": "2024-02-01T12:00:00Z",
    "images": [...],
    "tiers": [...],
    "updates": [...],
    "updates_count": 1
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key |
| `creator` | object (UserResource) | Kreator kampanye |
| `category` | object (CategoryResource) | Kategori kampanye |
| `title` | string | Judul kampanye (max 100) |
| `slug` | string | URL-friendly identifier |
| `description` | string | Deskripsi dalam Markdown |
| `description_html` | string | Deskripsi yang sudah di-render HTML |
| `target_amount` | decimal | Target dana |
| `collected_amount` | decimal | Dana terkumpul |
| `progress_percentage` | decimal | Persentase pencapaian |
| `deadline` | date | Tanggal akhir kampanye |
| `status` | enum | Status kampanye |
| `video_url` | string|null | URL video pendukung |
| `rejection_note` | string|null | Catatan penolakan (jika ditolak) |
| `reviewed_at` | datetime|null | Timestamp review |
| `images` | array (CampaignImageResource) | Gambar kampanye |
| `tiers` | array (CampaignTierResource) | Tier dukungan |
| `updates` | array (CampaignUpdateResource) | Pembaruan kampanye |
| `updates_count` | integer | Jumlah total update |

---

## 6. Pengujian Postman

### Index Campaigns

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/campaigns?status=active&sort=popular`
3. Headers: `Authorization: Bearer {{auth_token}}` (opsional)

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Response has pagination", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.meta.pagination).to.exist;
});
```

### Show Campaign

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/campaigns/bantu-anak-pedalaman-tepukan`
3. Headers: `Authorization: Bearer {{auth_token}}` (opsional)

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Campaign data returned", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.slug).to.eql("platform-donasi");
});
```

### Create Campaign

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/campaigns`
3. Headers: `Authorization: Bearer {{auth_token}}`
4. Body: `form-data` (multipart)

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Campaign created with DRAFT status", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.status).to.eql("draft");
});
```

### Approve Campaign (Admin)

1. Method: `PUT`
2. URL: `{{base_url}}/api/v1/admin/campaigns/kitabisa-ayo/approve`
3. Headers: `Authorization: Bearer {{admin_token}}`

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Campaign approved", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.status).to.eql("active");
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | List campaign tanpa auth | GET /campaigns | Hanya kampanye `active` yang ditampilkan |
| 2 | List campaign sebagai creator | `scope=mine` | Semua kampanye milik sendiri |
| 3 | List campaign sebagai admin | Tanpa filter | Semua kampanye (semua status) |
| 4 | Search campaign | `search=donasi` | Kampanye yang cocok dengan kata kunci |
| 5 | Buat kampanye dengan data valid | Multipart form | HTTP 201, status `draft` |
| 6 | Buat kampanye tanpa gambar | Request tanpa `images` | HTTP 422, error validasi |
| 7 | Update kampanye dalam status `draft` | PUT dengan data baru | Berhasil |
| 8 | Update kampanye dalam status `active` | PUT | HTTP 409, "Campaign can only be edited in draft status" |
| 9 | Submit campaign untuk review | POST submit-review | Status berubah ke `review` |
| 10 | Admin approve campaign | PUT approve | Status berubah ke `active`, email terkirim |
| 11 | Admin reject campaign | PUT reject + note | Status kembali ke `draft`, note tersimpan |
| 12 | Admin force-fail campaign | PUT force-fail | Status `failed`, RefundBackersJob di-dispatch |
| 13 | Backer mencoba buat kampanye | POST /campaigns | HTTP 403, unauthorized |
| 14 | Slug kampanye duplikat | POST dengan slug yang ada | Slug otomatis ditambahkan dengan `-1`, `-2`, dst. |
| 15 | Deadline kurang dari 7 hari | POST campaign | HTTP 422, error validasi |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| Gambar tidak muncul di response | Pastikan `php artisan storage:link` telah dijalankan. Gambar disimpan di `storage/app/public/campaigns/`. |
| Slug duplikat | Model Campaign secara otomatis menambahkan counter (`-1`, `-2`) pada slug yang sudah ada. |
| `description_html` kosong | Pastikan deskripsi diisi saat create. `description_html` di-render dari Markdown menggunakan `Parsedown`. |
| Status kampanye tidak berubah setelah disubmit | Pastikan hanya creator yang berhak mengakses endpoint ini. |
| Force fail tidak memicu refund | Pastikan queue worker berjalan (`php artisan queue:work`). Job dispatch secara asynchronous. |
| Campaign tidak muncul di daftar | Periksa status kampanye — hanya `active` yang ditampilkan untuk publik/backer. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /api/v1/campaigns` | ✓ (active only) | ✓ (active only) | ✓ (active atau `scope=mine`) | ✓ (all) |
| `GET /api/v1/campaigns/{slug}` | ✓ | ✓ | ✓ | ✓ |
| `POST /api/v1/campaigns` | - | - | ✓ (draft only) | - |
| `PUT /api/v1/campaigns/{slug}` | - | - | ✓ (draft only) | - |
| `POST /api/v1/campaigns/{slug}/submit-review` | - | - | ✓ (draft only) | - |
| `DELETE /api/v1/campaigns/{slug}` | - | - | ✓ (draft only) | - |
| `PUT /api/v1/admin/campaigns/{slug}/approve` | - | - | - | ✓ |
| `PUT /api/v1/admin/campaigns/{slug}/reject` | - | - | - | ✓ |
| `PUT /api/v1/admin/campaigns/{slug}/force-fail` | - | - | - | ✓ |

---

## 9. Matriks Kasus Pengujian (Test Case)

### 9.1 Index Campaigns

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CAM-INDEX-001` | Get semua campaign publik | Positive | `GET /api/v1/campaigns` (tanpa auth) | `200 OK` | Daftar campaign dengan status `active`, meta pagination |
| `TC-CAM-INDEX-002` | Get campaign dengan filter kategori | Positive | `?category=teknologi` | `200 OK` | Hanya campaign di kategori teknologi |
| `TC-CAM-INDEX-003` | Get campaign dengan search | Positive | `?search=kata+kunci` | `200 OK` | Campaign yang cocok dengan pencarian |
| `TC-CAM-INDEX-004` | Get campaign dengan sort populer | Positive | `?sort=popular` | `200 OK` | Diurutkan berdasarkan `collected_amount` desc |
| `TC-CAM-INDEX-005` | Get campaign dengan pagination custom | Positive | `?page=2&per_page=25` | `200 OK` | Halaman ke-2, 25 item (max 50) |
| `TC-CAM-INDEX-006` | Get campaign dengan per_page melebihi 50 | Positive | `?per_page=100` | `200 OK` | Secara otomatis dibatasi ke 50 |
| `TC-CAM-INDEX-007` | Get campaign dengan per_page tidak valid | Negative | `?per_page=-5` | `422 Unprocessable` | Error "The per_page must be at least 1" |
| `TC-CAM-INDEX-008` | Get campaign dengan page tidak valid | Negative | `?page=abc` | `422 Unprocessable` | Error validasi tipe integer |
| `TC-CAM-INDEX-009` | Get campaign dengan category tidak ada | Negative | `?category=nonexistent` | `422 Unprocessable` | Error "Selected category is invalid" |
| `TC-CAM-INDEX-010` | Creator melihat campaign milik sendiri | Positive | `?scope=mine` login sebagai creator | `200 OK` | Hanya campaign milik creator (semua status) |
| `TC-CAM-INDEX-011` | Creator melihat campaign publik (tanpa scope) | Positive | Login sebagai creator, tidak ada scope | `200 OK` | Hanya campaign dengan status `active` |
| `TC-CAM-INDEX-012` | Filter campaign dengan status valid | Positive | `?status=active` | `200 OK` | Campaign dengan status sesuai |
| `TC-CAM-INDEX-013` | Filter campaign dengan status tidak valid | Negative | `?status=invalid` | `422 Unprocessable` | Error enum tidak valid |

### 9.2 Show Campaign (Detail)

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CAM-SHOW-001` | Get detail campaign yang ada | Positive | `GET /api/v1/campaigns/{valid_slug}` | `200 OK` | CampaignResource lengkap |
| `TC-CAM-SHOW-002` | Get detail campaign dengan slug tidak ada | Negative | `GET /api/v1/campaigns/nonexistent-slug` | `404 Not Found` | Error "Campaign not found" |
| `TC-CAM-SHOW-003` | Get detail campaign draft oleh publik | Negative | Publik mengakses campaign draft | `404 Not Found` | Campaign tidak muncul |
| `TC-CAM-SHOW-004` | Creator melihat detail campaign draft miliknya | Positive | Login sebagai creator, akses campaign draft sendiri | `200 OK` | CampaignResource lengkap |
| `TC-CAM-SHOW-005` | Creator melihat campaign milik creator lain | Negative | Akses campaign draft creator lain | `404 Not Found` | Error (draft tidak publik) |

### 9.3 Create Campaign

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CAM-STORE-001` | Buat campaign lengkap dengan tier & image | Positive | Semua field + multipart images + tiers | `201 Created` | CampaignResource, status `draft` |
| `TC-CAM-STORE-002` | Buat campaign dengan field wajib saja | Positive | Hanya `category_id`, `title`, `description`, `target_amount`, `deadline` | `201 Created` | Campaign tanpa tiers/images |
| `TC-CAM-STORE-003` | Buat campaign tanpa autentikasi | Security | Tidak ada token | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-CAM-STORE-004` | Buat campaign sebagai backer | Security | Role backer | `403 Forbidden` | Error "Creator role required" |
| `TC-CAM-STORE-005` | Buat campaign dengan email belum terverifikasi | Security | Email belum verified | `403 Forbidden` | Error "Email verification required" |
| `TC-CAM-STORE-006` | Buat campaign tanpa category_id | Negative | `category_id: null` | `422 Unprocessable` | Error "The category_id field is required" |
| `TC-CAM-STORE-007` | Buat campaign dengan category_id tidak ada | Negative | `category_id: 9999` | `422 Unprocessable` | Error "Selected category is invalid" |
| `TC-CAM-STORE-008` | Buat campaign tanpa title | Negative | `title: null` | `422 Unprocessable` | Error "The title field is required" |
| `TC-CAM-STORE-009` | Buat campaign dengan title > 100 karakter | Negative | `title > 100 chars` | `422 Unprocessable` | Error "The title may not be greater than 100 characters" |
| `TC-CAM-STORE-010` | Buat campaign tanpa description | Negative | `description: null` | `422 Unprocessable` | Error "The description field is required" |
| `TC-CAM-STORE-011` | Buat campaign dengan target_amount 0 | Negative | `target_amount: 0` | `422 Unprocessable` | Error "The target_amount must be at least 100000" |
| `TC-CAM-STORE-012` | Buat campaign dengan target_amount negatif | Negative | `target_amount: -100` | `422 Unprocessable` | Error "The target_amount must be at least 100000" |
| `TC-CAM-STORE-013` | Buat campaign dengan deadline di masa lalu | Negative | `deadline: "2020-01-01"` | `422 Unprocessable` | Error tanggal harus di masa depan |
| `TC-CAM-STORE-014` | Buat campaign dengan tier min_amount > target_amount | Negative | `tier min_amount > target_amount` | `422 Unprocessable` | Error validasi tier |
| `TC-CAM-STORE-015` | Upload image format tidak valid | Negative | `image: file.pdf` | `422 Unprocessable` | Error "Image must be jpg, jpeg, png, or webp" |

### 9.4 Update Campaign

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CAM-UPDATE-001` | Update campaign dalam status draft | Positive | PUT campaign berstatus draft, field valid | `200 OK` | CampaignResource terbarui |
| `TC-CAM-UPDATE-002` | Update campaign tanpa autentikasi | Security | Tidak ada token | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-CAM-UPDATE-003` | Backer mencoba update campaign | Security | Role backer | `403 Forbidden` | Error "Creator role required" |
| `TC-CAM-UPDATE-004` | Creator A update campaign Creator B | Security | BOPA attack | `404 Not Found` | Error (hanya pemilik yang bisa) |
| `TC-CAM-UPDATE-005` | Update campaign yang sedang review | Business Logic | Status `review` | `409 Conflict` | Error "Cannot modify campaign in review status" |
| `TC-CAM-UPDATE-006` | Update campaign yang sudah published | Business Logic | Status `active` | `409 Conflict` | Error tidak dapat mengubah kampanye live |
| `TC-CAM-UPDATE-007` | Update campaign yang sudah berhasil | Business Logic | Status `success` | `409 Conflict` | Error tidak dapat mengubah kampanye yang sudah selesai |
| `TC-CAM-UPDATE-008` | Update campaign dengan title > 100 karakter | Negative | `title > 100` | `422 Unprocessable` | Error panjang karakter |
| `TC-CAM-UPDATE-009` | Update campaign dengan target_amount < 100000 | Negative | `target_amount: 100` | `422 Unprocessable` | Error minimum |

### 9.5 Submit Campaign for Review

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CAM-REVIEW-001` | Submit campaign draft untuk review | Positive | POST submit-review pada status draft | `200 OK` | Status berubah ke `review` |
| `TC-CAM-REVIEW-002` | Submit campaign yang sudah di review | Negative | Status `review` | `409 Conflict` | Error "Already in review" |
| `TC-CAM-REVIEW-003` | Submit campaign yang sudah published | Negative | Status `active` | `409 Conflict` | Error tidak dapat submit ulang |
| `TC-CAM-REVIEW-004` | Creator A submit campaign Creator B | Security | BOPA | `404 Not Found` | Campaign tidak ditemukan |
| `TC-CAM-REVIEW-005` | Submit campaign tanpa autentikasi | Security | No token | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-CAM-REVIEW-006` | Submit campaign dengan image kurang | Negative | Image belum diupload | `422 Unprocessable` | Error "Campaign must have at least 1 primary image" |
| `TC-CAM-REVIEW-007` | Submit campaign tanpa tier | Negative | Tidak ada tier | `422 Unprocessable` | Error "Campaign must have at least 1 tier" |

### 9.6 Delete Campaign

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CAM-DELETE-001` | Hapus campaign draft | Positive | DELETE campaign berstatus draft | `200 OK` | Campaign & relasinya dihapus |
| `TC-CAM-DELETE-002` | Hapus campaign draft oleh creator lain | Security | Creator B hapus campaign A | `404 Not Found` | Not Found |
| `TC-CAM-DELETE-003` | Hapus campaign yang sedang review | Business Logic | Status `review` | `409 Conflict` | Error tidak dapat menghapus kampanye dalam review |
| `TC-CAM-DELETE-004` | Hapus campaign yang sudah published | Business Logic | Status `active` | `409 Conflict` | Error tidak dapat menghapus kampanye live |
| `TC-CAM-DELETE-005` | Hapus campaign tanpa autentikasi | Security | No token | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-CAM-DELETE-006` | Hapus campaign dengan slug tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |

### 9.7 Admin Campaign Actions

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-CAM-ADM-001` | Approve campaign yang valid | Positive | PUT approve pada status `review` | `200 OK` | Status berubah ke `active`, `DisburseCampaignJob` tidak dipicu |
| `TC-CAM-ADM-002` | Approve campaign yang sudah active | Business Logic | Status `active` | `409 Conflict` | Error "Campaign already active" |
| `TC-CAM-ADM-003` | Approve campaign draft | Business Logic | Status `draft` | `409 Conflict` | Error "Only campaigns in review can be approved" |
| `TC-CAM-ADM-004` | Approve campaign dengan non-admin | Security | Role backer/creator | `403 Forbidden` | Error "Admin role required" |
| `TC-CAM-ADM-005` | Reject campaign dengan rejection_note | Positive | PUT reject + `rejection_note` | `200 OK` | Status kembali ke `draft`, note disimpan |
| `TC-CAM-ADM-006` | Reject campaign tanpa rejection_note | Positive | PUT reject tanpa note | `200 OK` | Status `draft`, note kosong/null |
| `TC-CAM-ADM-007` | Force-fail campaign yang sedang berjalan | Positive | PUT force-fail pada status `active` | `200 OK` | Status `failed`, `RefundBackersJob` dispatch |
| `TC-CAM-ADM-008` | Force-fail campaign yang belum review | Business Logic | Status `draft` | `409 Conflict` | Error "Only active/review campaigns can be force-failed" |
| `TC-CAM-ADM-009` | Approve/reject/force-fail campaign yang tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |
