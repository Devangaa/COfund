# CoFund API - Modul Backing (Backing Module)

## 1. Judul & Deskripsi Modul

Modul backing mengelola proses dukungan (backing) dari pengguna (backer) terhadap kampanye yang sedang berlangsung (status `active`). Backing menciptakan transaksi pembayaran, menambahkan jumlah terkumpul kampanye, dan memicu event notifikasi ke backer dan kreator.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/BackingController.php` | Index dan store backing |
| **Service** | `app/Services/BackingService.php` | Logika bisnis pembuatan backing, pengecekan kuota tier, pencapaian target |
| **Form Request** | `app/Http/Requests/StoreBackingRequest.php` | Validasi pembuatan backing |
| **Resource** | `app/Http/Resources/BackingResource.php` | Serialisasi data backing |
| **Model** | `app/Models/Backing.php` | Model Backing dengan relasi campaign, tier, backer |
| **Enums** | `app/Enums/BackingStatus.php` | `pending`, `completed`, `refunded` |
| | `app/Enums/TransactionType.php` | `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal` |
| | `app/Enums/TransactionStatus.php` | `pending`, `success`, `failed` |
| | `app/Enums/CampaignStatus.php` | `draft`, `review`, `active`, `success`, `failed` |
| **Event** | `app/Events/BackingCreated.php` | Event backing berhasil dibuat |
| | `app/Events/CampaignFunded.php` | Event kampanye mencapai target |
| **Listener** | `app/Listeners/HandleBackingCreated.php` | Kirim notifikasi & email setelah backing dibuat |
| | `app/Listeners/HandleCampaignFunded.php` | Dispatch `DisburseCampaignJob` |
| **Middleware** | `auth:sanctum`, `verified`, `role:` | Kontrol akses |

### Alur Proses Logika Bisnis

```
Backer klik dukung
        |
        v
StoreBackingRequest (validate)
        |
        v
BackingService::create()
        |
        +---> Ensure campaign is ACTIVE
        |       (AuthorizationException if creator)
        |
        +---> Check tier availability (if tier selected)
        |       - LockForUpdate tier
        |       - Check hasAvailability()
        |       - Check amount >= min_amount
        |
        +---> Check minimum amount 10.000 (if no tier)
        |
        v
    DB::transaction()
        |
        +---> Create Backing (status=COMPLETED)
        |
        +---> Create Transaction (type=PAYMENT, status=SUCCESS)
        |
        +---> Decrement tier remaining_quota (if applicable)
        |
        +---> Increment campaign collected_amount
        |
        +---> Check campaign reached target
        |       - If collected >= target AND status=ACTIVE
        |       - Update status=SUCCESS
        |       - DB::afterCommit -> event(CampaignFunded)
        |
        +---> event(BackingCreated)
        |
        v
Return BackingResource + HTTP 201


Event: BackingCreated
        |
        v
HandleBackingCreated::handle()
        |
        +---> Create notification for backer
        +---> Create notification for creator
        +---> Send email to backer (if verified)

Event: CampaignFunded
        |
        v
HandleCampaignFunded::handle()
        |
        v
DisburseCampaignJob::dispatch()
        |
        v
TransactionService::disburseCampaign()
        |
        +---> Add 95% collected to creator balance
        +---> Create disbursement transaction
        +---> Create platform_fee transaction (5%)
        +---> Create notification + email to creator
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── BackingController.php
│   │   ├── Requests/
│   │   │   └── StoreBackingRequest.php
│   │   └── Resources/
│   │       └── BackingResource.php
│   ├── Models/
│   │   ├── Backing.php
│   │   ├── Campaign.php
│   │   └── CampaignTier.php
│   ├── Enums/
│   │   ├── BackingStatus.php
│   │   ├── CampaignStatus.php
│   │   └── TransactionType.php
│   ├── Events/
│   │   ├── BackingCreated.php
│   │   └── CampaignFunded.php
│   ├── Listeners/
│   │   ├── HandleBackingCreated.php
│   │   └── HandleCampaignFunded.php
│   ├── Jobs/
│   │   ├── DisburseCampaignJob.php
│   │   └── RefundBackersJob.php
│   └── Services/
│       ├── BackingService.php
│       └── TransactionService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: List Backings

- **Deskripsi:** Mendapatkan daftar backing milik pengguna yang terautentikasi. Admin dapat melihat semua backing.
- **HTTP Method & URL Path:** `GET /api/v1/backings`
- **Middleware:** `auth:sanctum`, `verified`

#### Tabel Parameter (Query)

| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `page` | integer | Tidak | `min:1` | Halaman pagination |
| `per_page` | integer | Tidak | `min:1`, `max:50` | Item per halaman (default: 10, maksimal: 50) |

#### Contoh Request

```
GET /api/v1/backings?page=1
Authorization: Bearer {token}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "campaign": {
                "id": 5,
                "slug": "platform-donasi",
                "title": "Platform Donasi Terdesentralisasi",
                "status": "active",
                "target_amount": 50000000,
                "collected_amount": 25000000,
                "progress_percentage": 50.0,
                "deadline": "2024-12-31",
                "creator_name": "Jane Creator"
            },
            "tier": {
                "id": 1,
                "name": "Early Bird",
                "min_amount": 50000
            },
            "amount": 50000,
            "status": "completed",
            "created_at": "2024-02-01T10:30:00Z"
        }
    ],
    "meta": {
        "pagination": {
            "current_page": 1,
             "last_page": 3,
             "per_page": 10,
             "total": 25
        }
    }
}
```

#### Efek Samping

- Backer hanya melihat backing miliknya sendiri
- Admin melihat semua backing

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 403 | `{"success":false,"message":"The user must verify their email."}` | Email belum diverifikasi |

---

### 4.2 Endpoint: Store Backing

- **Deskripsi:** Membuat dukungan baru untuk kampanye yang sedang berlangsung.
- **HTTP Method & URL Path:** `POST /api/v1/campaigns/{campaign:slug}/back`
- **Middleware:** `auth:sanctum`, `verified`

#### Tabel Parameter (Path + Body)

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `tier_id` | Body | integer | Tidak | `exists:campaign_tiers,id` | ID tier yang dipilih |
| `amount` | Body | numeric | Ya | `min:10000` | Jumlah dukungan (Rp10.000 minimum) |

#### Contoh Request (Tanpa Tier)

```json
{
    "amount": 100000
}
```

#### Contoh Request (Dengan Tier)

```json
{
    "tier_id": 1,
    "amount": 50000
}
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "message": "Backing created successfully",
    "data": {
        "id": 1,
        "campaign": {
            "id": 5,
            "slug": "platform-donasi",
            "title": "Platform Donasi Terdesentralisasi",
            "status": "active",
            "target_amount": 50000000,
            "collected_amount": 25050000,
            "progress_percentage": 50.1,
            "deadline": "2024-12-31",
            "creator_name": "Jane Creator"
        },
        "tier": {
            "id": 1,
            "name": "Early Bird",
            "min_amount": 50000
        },
        "amount": 50000,
        "status": "completed",
        "created_at": "2024-02-01T10:30:00Z"
    }
}
```

#### Efek Samping

- Membuat backing dengan status `completed`
- Membuat transaksi type `payment`
- Jika tier dipilih: mengurangi `remaining_quota` tier
- Menambah `collected_amount` di kampanye
- Jika kampanye mencapai target: status berubah ke `success`, dispatch `DisburseCampaignJob`
- Membuat notifikasi in-app ke backer dan kreator
- Mengirim email ke backer (jika terverifikasi)

#### Error Handling

| Kode HTTP | Pesan Error JSON | Konditi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"Creator cannot back their own campaign"}` | Kreator mencoba backing kampanye sendiri |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"campaign":["Campaign must be active to receive backing"]}}` | Kampanye tidak dalam status `active` |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"tier_id":["This tier is full"]}}` | Kuota tier habis |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"amount":["Backing amount must be at least tier minimum"]}}` | Jumlah kurang dari `min_amount` tier |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"amount":["Minimum backing amount is 10,000"]}}` | Jumlah kurang dari Rp10.000 |
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |

---

### 4.3 Endpoint: List Backings by Campaign

- **Deskripsi:** Mendapatkan daftar backing untuk kampanye tertentu. Hanya admin dan kreator kampanye yang dapat mengakses endpoint ini.
- **HTTP Method & URL Path:** `GET /api/v1/campaigns/{campaign:slug}/backings`
- **Middleware:** `auth:sanctum`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Deskripsi |
|---|---|---|---|---|
| `campaign` | Path | string | Ya | Slug kampanye |

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "backer": {
                "id": 3,
                "name": "Backer User",
                "email": "backer@example.com",
                "role": "backer",
                "balance": 500000,
                "email_verified_at": "2024-01-15T10:30:00Z",
                "is_suspended": false
            },
            "tier": {
                "id": 1,
                "name": "Early Bird",
                "min_amount": 50000
            },
            "amount": 50000,
            "status": "completed",
            "created_at": "2024-02-01T10:30:00Z"
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

#### Efek Samping

- Admin dapat melihat backing untuk kampanye apa saja
- Creator hanya dapat melihat backing untuk kampanye miliknya sendiri
- Backer tidak dapat mengakses endpoint ini

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"You can only view backings for your own campaigns."}` | Creator bukan pemilik kampanye |
| 403 | `{"success":false,"message":"Unauthorized. Only admin and creator roles can view campaign backings."}` | Role backer mencoba mengakses |

---

## 5. Skema Sumber Daya (Resource Schema)

### BackingResource

```json
{
    "id": 1,
    "campaign": {
        "id": 5,
        "slug": "platform-donasi",
        "title": "Platform Donasi Terdesentralisasi",
        "status": "active",
        "target_amount": 50000000,
        "collected_amount": 25000000,
        "progress_percentage": 50.0,
        "deadline": "2024-12-31",
        "creator_name": "Jane Creator"
    },
    "tier": {
        "id": 1,
        "name": "Early Bird",
        "min_amount": 50000
    },
    "amount": 50000,
    "status": "completed",
    "created_at": "2024-02-01T10:30:00Z"
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key backing |
| `campaign` | object | Data kampanye (slug, title, status, progress) |
| `tier` | object\|null | Data tier (jika dipilih) |
| `amount` | decimal | Jumlah backing |
| `status` | enum | `pending`, `completed`, `refunded` |
| `created_at` | datetime | Timestamp pembuatan |

---

## 6. Pengujian Postman

### Store Backing

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/campaigns/bantu-anak-pedalaman-tepukan/back`
3. Headers: `Authorization: Bearer {{auth_token}}`, `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "amount": 100000
}
```

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Backing created", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.data.status).to.eql("completed");
});
```

### List Backings

1. Method: `GET`
2. URL: `{{base_url}}/api/v1/backings`
3. Headers: `Authorization: Bearer {{auth_token}}`

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
| 1 | Backer menciptakan backing tanpa tier | `{"amount": 100000}` | HTTP 201, backing berhasil |
| 2 | Backer menciptakan backing dengan tier | `{"tier_id": 1, "amount": 50000}` | HTTP 201, tier dikurangi, transaction terbuat |
| 3 | Creator mencoba backing kampanye sendiri | `{"amount": 100000}` | HTTP 403, "Creator cannot back their own campaign" |
| 4 | Backing di bawah minimum (10.000) | `{"amount": 5000}` | HTTP 422, "Minimum backing amount is 10,000" |
| 5 | Backing kurang dari minimum tier | `{"tier_id": 1, "amount": 10000}` (tier min=50000) | HTTP 422, "Backing amount must be at least tier minimum" |
| 6 | Backing pada tier yang habis | `{"tier_id": 1, "amount": 50000}` (remaining_quota=0) | HTTP 422, "This tier is full" |
| 7 | Backing pada kampanye tidak aktif | POST /campaigns/draft-campaign/back | HTTP 422, "Campaign must be active to receive backing" |
| 8 | Backing melebihi target kampanye | Backing ketika collected + amount >= target | Status campaign berubah ke `success`, DisburseCampaignJob di-dispatch |
| 9 | List backing tanpa token | GET /backings | HTTP 401, "Unauthenticated" |
| 10 | Admin list backing kampanye | GET /campaigns/{slug}/backings | HTTP 200, data lengkap |
| 11 | Backer list backing milik creator | GET /campaigns/{slug-lain}/backings | HTTP 403, "Unauthorized" |
| 12 | Backing pada kampanye dengan status success | POST /campaigns/success-campaign/back | HTTP 422, campaign tidak aktif |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| Backing gagal dengan error "This tier is full" | Periksa `remaining_quota` tier. Set `quota=0` untuk tier tak terbatas. |
| Backing gagal dengan error "Creator cannot back their own campaign" | Ini perilaku yang disengaja. Creator tidak dapat backing kampanye sendiri. |
| Notifikasi tidak muncul setelah backing | Pastikan queue worker berjalan (`php artisan queue:work`). Notifikasi dibuat secara synchronous, tapi email menggunakan queue. |
| DisburseCampaignJob tidak berjalan | Pastikan `QUEUE_CONNECTION` di `.env` diatur ke `database` atau `redis`, dan worker berjalan. |
| collected_amount tidak bertambah | Pastikan backing berhasil (status `completed`). Peningkatan dilakukan dalam transaksi database. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /api/v1/backings` | - | ✓ (sendiri) | ✓ (sendiri) | ✓ (all) |
| `POST /api/v1/campaigns/{slug}/back` | - | ✓ (active only) | ✗ (own campaign) | ✓ |

---

## 9. Matriks Kasus Pengujian (Test Case)

### 9.1 Store Backing

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-BACK-STORE-001` | Buat backing pada campaign active | Positive | `amount` valid, campaign `active` | `201 Created` | Backing disimpan, `collected_amount` bertambah, transaksi payment |
| `TC-BACK-STORE-002` | Buat backing dengan tier yang tersedia | Positive | `tier_id` valid, `amount >= min_amount` | `201 Created` | Backing + `remaining_quota` berkurang |
| `TC-BACK-STORE-003` | Buat backing tanpa token | Security | Tidak ada Authorization | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-BACK-STORE-004` | Creator backing campaign sendiri | Security | Kreator campaign melakukan backing | `403 Forbidden` | Error "Creator cannot back their own campaign" |
| `TC-BACK-STORE-005` | Backing pada campaign draft | Business Logic | Status `draft` | `409 Conflict` | Error "Cannot back a campaign that is not active" |
| `TC-BACK-STORE-006` | Backing pada campaign yang sudah gagal | Business Logic | Status `failed` | `409 Conflict` | Error "Campaign is no longer active" |
| `TC-BACK-STORE-007` | Backing pada campaign yang sudah success | Business Logic | Status `success` | `409 Conflict` | Error "Campaign goal already reached" |
| `TC-BACK-STORE-008` | Backing dengan tier yang stok habis | Business Logic | Tier `remaining_quota = 0` | `409 Conflict` | Error "This tier is full" |
| `TC-BACK-STORE-009` | Backing dengan tier_id tidak ada | Negative | `tier_id: 9999` | `422 Unprocessable` | Error "Selected tier is invalid" |
| `TC-BACK-STORE-010` | Backing dengan amount di bawah min_tier | Negative | `amount < tier.min_amount` | `409 Conflict` | Error "Amount must be at least tier minimum" |
| `TC-BACK-STORE-011` | Backing dengan amount kurang dari 10000 | Negative | `amount: 100` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-BACK-STORE-012` | Backing dengan amount negatif | Negative | `amount: -500` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-BACK-STORE-013` | Backing dengan amount = 0 | Negative | `amount: 0` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-BACK-STORE-014` | Backing email belum terverifikasi | Security | Email belum verified | `403 Forbidden` | Error "Email verification required" |
| `TC-BACK-STORE-015` | Backing campaign yang tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |
| `TC-BACK-STORE-016` | Backing dengan tipe data amount string | Negative | `amount: "abc"` | `422 Unprocessable` | Error tipe data |
| `TC-BACK-STORE-017` | Spam backing 10x cepat | Throttling | Rapid requests | `429 Too Many Requests` | Rate limited (60/menit global) |

### 9.2 List Backings (User)

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-BACK-INDEX-001` | List backing sendiri | Positive | Auth user biasa | `200 OK` | Daftar backing user, meta pagination |
| `TC-BACK-INDEX-002` | List backing dengan pagination | Positive | `?page=2&per_page=25` | `200 OK` | Halaman ke-2, 25 item (max 50) |
| `TC-BACK-INDEX-003` | List backing tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-BACK-INDEX-004` | Admin list semua backing | Positive | Login admin | `200 OK` | Semua backing di sistem |
| `TC-BACK-INDEX-005` | List backing dengan per_page > 50 | Positive | `?per_page=999` | `200 OK` | Dibatasi ke 50 |

### 9.3 List Campaign Backings

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-BACK-CAM-001` | Creator lihat backing campaign sendiri | Positive | Login creator, campaign milik sendiri | `200 OK` | Daftar backing, meta pagination |
| `TC-BACK-CAM-002` | Backer mencoba lihat backing campaign orang lain | Security | Role backer, campaign bukan milik sendiri | `403 Forbidden` | Error "Only admin and creator roles" |
| `TC-BACK-CAM-003` | Creator A lihat backing campaign Creator B | Security | BOPA | `403 Forbidden` | Error hak akses |
| `TC-BACK-CAM-004` | Admin lihat backing semua campaign | Positive | Login admin | `200 OK` | Semua backing campaign |
| `TC-BACK-CAM-005` | Lihat backing campaign yang tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |
| `GET /api/v1/campaigns/{slug}/backings` | - | ✗ | ✓ (own campaign) | ✓ (any) |
