# CoFund API - Modul Tier (Tier Module)

## 1. Judul & Deskripsi Modul

Modul tier mengelola opsi dukungan (reward tier) yang ditawarkan oleh kreator dalam kampanye mereka. Tier adalah opsi yang dapat dipilih backer saat membuat backing, masing-masing dengan jumlah minimum dukungan, kuota, dan deskripsi reward.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/TierController.php` | Store, update, deleteMany |
| **Service** | `app/Services/TierService.php` | Logika bisnis tier CRUD |
| **Form Request** | `app/Http/Requests/StoreTierRequest.php` | Validasi store tier |
| | `app/Http/Requests/UpdateTierRequest.php` | Validasi update tier |
| | `app/Http/Requests/DeleteTierRequest.php` | Validasi bulk delete |
| **Resource** | `app/Http/Resources/CampaignTierResource.php` | Serialisasi data tier |
| **Model** | `app/Models/CampaignTier.php` | Model Tier dengan relasi campaign, backings |
| **Middleware** | `auth:sanctum`, `role:creator`, `verified` | Otentikasi dan role |
| **Dependencies** | `CampaignService::ensureEditable()` | Memastikan kampanye dalam status draft |

### Alur Proses Logika Bisnis

```
Creator kelola tier
        |
        v
TierController::store() / update() / destroyMany()
        |
        v
StoreTierRequest / UpdateTierRequest / DeleteTierRequest
        |
        v
TierService
        |
        +---> CampaignService::ensureEditable()
        |       - Jika status != draft: THROW ConflictHttpException(422)
        |
  For Store:
        |
        v
    CampaignTier::create()
        |
        v
    Return CampaignTierResource

  For Update:
        |
        v
    unset(remaining_quota) // cannot update remaining_quota
        |
        v
    Tier::update()
        |
        v
    Return CampaignTierResource

  For Delete Many:
        |
        v
  DB::transaction()
        |
        +---> Count total tiers
        +---> Ensure (total - deleteCount) >= 1
        +---> Validate tier IDs belong to campaign
        +---> Delete tiers
        |
        v
    Return success
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── TierController.php
│   │   ├── Requests/
│   │   │   ├── StoreTierRequest.php
│   │   │   ├── UpdateTierRequest.php
│   │   │   └── DeleteTierRequest.php
│   │   └── Resources/
│   │       └── CampaignTierResource.php
│   ├── Models/
│   │   └── CampaignTier.php
│   ├── Services/
│   │   ├── TierService.php
│   │   └── CampaignService.php
│   └── Enums/
│       └── CampaignStatus.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Store Tier

- **Deskripsi:** Membuat tier dukungan baru untuk kampanye yang masih dalam status `draft`.
- **HTTP Method & URL Path:** `POST /api/v1/campaigns/{campaign:slug}/tiers`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `name` | Body | string | Ya | `max:255` | Nama tier |
| `min_amount` | Body | numeric | Ya | `min:0` | Jumlah minimum dukungan |
| `quota` | Body | integer | Ya | `min:0` | Kuota maksimum (0 = tak terbatas) |
| `reward_description` | Body | string | Tidak | - | Deskripsi reward untuk backer |

#### Contoh Request Payload

```json
{
    "name": "Early Bird",
    "min_amount": 50000,
    "quota": 100,
    "reward_description": "Akses eksklusif konten dan pengakuan di website"
}
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "message": "Tier created successfully",
    "data": {
        "id": 2,
        "name": "Early Bird",
        "min_amount": 50000,
        "quota": 100,
        "remaining_quota": 100,
        "is_unlimited": false,
        "has_availability": true,
        "reward_description": "Akses eksklusif konten dan pengakuan di website"
    }
}
```

#### Efek Samping

- Membuat entri baru di tabel `campaign_tiers`
- `remaining_quota` diinisialisasi dari `quota`
- Hanya dapat dilakukan ketika kampanye dalam status `draft`

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik kampanye |
| 422 | `{"success":false,"message":"Campaign can only be edited in draft status"}` | Status kampanye bukan draft |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{...}}` | Validasi gagal |

---

### 4.2 Endpoint: Update Tier

- **Deskripsi:** Memperbarui tier kampanye yang ada. Field `remaining_quota` tidak dapat diupdate secara manual.
- **HTTP Method & URL Path:** `PUT /api/v1/campaigns/{campaign:slug}/tiers/{tier}`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `tier` | Path | integer | Ya | - | ID tier |
| `name` | Body | string | Tidak | `max:255` | Nama baru |
| `min_amount` | Body | numeric | Tidak | `min:0` | Minimum dukungan baru |
| `quota` | Body | integer | Tidak | `min:0` | Kuota baru |
| `reward_description` | Body | string | Tidak | - | Deskripsi reward |

#### Contoh Request Payload

```json
{
    "name": "Early Bird V2",
    "min_amount": 60000,
    "reward_description": "Akses eksklusif konten, pengakuan, dan merchandise"
}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "message": "Tier updated successfully",
    "data": {
        "id": 2,
        "name": "Early Bird V2",
        "min_amount": 60000,
        "quota": 100,
        "remaining_quota": 75,
        "is_unlimited": false,
        "has_availability": true,
        "reward_description": "Akses eksklusif konten, pengakuan, dan merchandise"
    }
}
```

#### Efek Samping

- Memperbarui tier yang sudah ada
- `remaining_quota` akan tetap (hanya berkurang dari backing)
- Hanya dapat dilakukan ketika kampanye dalam status `draft`

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik kampanye atau tier tidak milik kampanye |
| 422 | `{"success":false,"message":"Campaign can only be edited in draft status"}` | Status kampanye bukan draft |

---

### 4.3 Endpoint: Delete Many Tiers

- **Deskripsi:** Menghapus beberapa tier sekaligus. kampanye harus memiliki minimal 1 tier yang tersisa.
- **HTTP Method & URL Path:** `DELETE /api/v1/campaigns/{campaign:slug}/tiers`
- **Middleware:** `auth:sanctum`, `role:creator`, `verified`

#### Tabel Parameter

| Nama | Lokasi | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|---|
| `campaign` | Path | string | Ya | - | Slug kampanye |
| `ids` | Body | array | Ya | `min:1`, `exists:campaign_tiers,id` | Array ID tier yang akan dihapus |

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
    "message": "Selected tiers deleted successfully"
}
```

#### Efek Samping

- Menghapus tier dari database
- Operasi dalam transaksi database
- Menerima error jika:
  - Hanya tersisa 1 tier ( tidak boleh mengahpus semua)
  - ID tier tidak valid
  - Tier tidak milik kampanye tersebut

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 403 | `{"success":false,"message":"This action is unauthorized."}` | Bukan creator pemilik kampanye |
| 422 | `{"success":false,"message":"Campaign must have at least 1 tier"}` | Hanya tersisa 1 tier |
| 403 | `{"success":false,"message":"This action is unauthorized."}` | ID tier tidak valid atau tidak milik kampanye |

---

## 5. Skema Sumber Daya (Resource Schema)

### CampaignTierResource

```json
{
    "id": 2,
    "name": "Early Bird",
    "min_amount": 50000,
    "quota": 100,
    "remaining_quota": 75,
    "is_unlimited": false,
    "has_availability": true,
    "reward_description": "Akses eksklusif konten dan pengakuan di website"
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key |
| `name` | string | Nama tier |
| `min_amount` | decimal | Jumlah minimum dukungan |
| `quota` | integer\|null | Kuota maksimum (null jika unlimited) |
| `remaining_quota` | integer\|null | Kuota tersisa (null jika unlimited) |
| `is_unlimited` | boolean | Apakah kuota tak terbatas (quota=0) |
| `has_availability` | boolean | Apakah masih tersedia |
| `reward_description` | string\|null | Deskripsi reward |

---

## 6. Pengujian Postman

### Store Tier

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/campaigns/qa-draft-campaign/tiers`
3. Headers: `Authorization: Bearer {{auth_token}}`, `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "name": "Early Bird",
    "min_amount": 50000,
    "quota": 100,
    "reward_description": "Akses eksklusif"
}
```

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Tier created", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data.name).to.eql("Early Bird");
    pm.expect(jsonData.data.has_availability).to.be.true;
});
```

### Delete Many Tiers

1. Method: `DELETE`
2. URL: `{{base_url}}/api/v1/campaigns/qa-draft-campaign/tiers`
3. Headers: `Authorization: Bearer {{auth_token}}`, `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "ids": [3, 4]
}
```

**Tests Script:**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Tiers deleted", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | Store tier dengan data valid | `{"name":"Early Bird","min_amount":50000,"quota":100}` | HTTP 201, tier terbuat |
| 2 | Store tier dengan quota 0 (unlimited) | `{"name":"Standard","min_amount":100000,"quota":0}` | `is_unlimited=true`, `has_availability=true` |
| 3 | Store tier di kampanye yang sudah active | POST /campaigns/active-slug/tiers | HTTP 422, "Campaign can only be edited in draft status" |
| 4 | Update tier dengan name baru | PUT /.../{id} `{"name":"VIP"}` | HTTP 200, tier terupdate |
| 5 | Update tier remaining_quota secara manual | PUT /.../{id} `{"remaining_quota":0}` | `remaining_quota` tidak berubah (di-unset) |
| 6 | Delete many hanya 1 tier tersisa | DELETE `{"ids":[1]}` (hanya ada 1 tier) | HTTP 422, "Campaign must have at least 1 tier" |
| 7 | Delete tier yang tidak milik kampanye | DELETE `{"ids":[999]}` | HTTP 403, unauthorized |
| 8 | Store tier dengan min_amount negatif | `{"name":"Invalid","min_amount":-100}` | HTTP 422, validation error |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| "Campaign can only be edited in draft status" | Pastikan status kampanye masih `draft`. Tier hanya bisa dikelola ketika kampanye belum disubmit untuk review. |
| "Campaign must have at least 1 tier" | Pastikan setidaknya 1 tier tidak termasuk dalam daftar penghapusan. |
| "This action is unauthorized." saat update tier | Pastikan tier milik kampanye yang benar dan Anda adalah creator pemilik kampanye. |
| `is_unlimited=true` tapi ingin kuota terbatas | Set `quota=0` berarti unlimited. Untuk quota terbatas, gunakan angka positif. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /api/v1/campaigns/{slug}/tiers` | - | - | ✓ (draft only) | - |
| `PUT /api/v1/campaigns/{slug}/tiers/{tier}` | - | - | ✓ (draft only) | - |
| `DELETE /api/v1/campaigns/{slug}/tiers` | - | - | ✓ (draft only) | - |

---

## 10. Matriks Kasus Pengujian (Test Case)

### 10.1 Store Tier

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-TIER-STORE-001` | Buat tier dengan data valid | Positive | `name`, `min_amount`, `quota`, `reward_description` | `201 Created` | TierResource terbuat |
| `TC-TIER-STORE-002` | Buat tier dengan quota 0 (unlimited) | Positive | `quota: 0` | `201 Created` | Tier tak terbatas |
| `TC-TIER-STORE-003` | Buat tier tanpa autentikasi | Security | No token | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-TIER-STORE-004` | Buat tier sebagai backer | Security | Role backer | `403 Forbidden` | Error "Creator role required" |
| `TC-TIER-STORE-005` | Buat tier pada campaign yang sedang review | Business Logic | Status `review` | `409 Conflict` | Error "Only draft campaigns can be modified" |
| `TC-TIER-STORE-006` | Buat tier pada campaign yang sudah published | Business Logic | Status `active` | `409 Conflict` | Error "Only draft campaigns can be modified" |
| `TC-TIER-STORE-007` | Buat tier tanpa min_amount | Negative | `min_amount: null` | `422 Unprocessable` | Error "The min_amount field is required" |
| `TC-TIER-STORE-008` | Buat tier dengan min_amount negatif | Negative | `min_amount: -100` | `422 Unprocessable` | Error "The min_amount must be at least 10000" |
| `TC-TIER-STORE-009` | Buat tier dengan min_amount > collected_amount | Business Logic | `min_amount > current backing` | `422 Unprocessable` | Error "min_amount cannot exceed campaign target" |
| `TC-TIER-STORE-010` | Buat tier dengan campaign slug tidak ada | Negative | Slug tidak ditemukan | `404 Not Found` | Error "Campaign not found" |
| `TC-TIER-STORE-011` | Creator A buat tier di campaign Creator B | Security | BOPA | `404 Not Found` | Not Found |

### 10.2 Update Tier

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-TIER-UPDATE-001` | Update tier yang ada | Positive | `PUT /tiers/{tier}` dengan data valid | `200 OK` | TierResource terbarui |
| `TC-TIER-UPDATE-002` | Update tier pada campaign yang sudah review | Business Logic | Status `review` | `409 Conflict` | Error "Only draft campaigns can be modified" |
| `TC-TIER-UPDATE-003` | Creator A update tier di campaign Creator B | Security | BOPA | `404 Not Found` | Not Found |
| `TC-TIER-UPDATE-004` | Update tier tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-TIER-UPDATE-005` | Update tier dengan min_amount negatif | Negative | `min_amount: -500` | `422 Unprocessable` | Error validasi |
| `TC-TIER-UPDATE-006` | Update tier yang tidak ada | Negative | `tier_id: 9999` | `404 Not Found` | Error "Tier not found" |

### 10.3 Delete Many Tiers

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-TIER-DELETE-001` | Hapus banyak tier yang ada | Positive | `{"ids": [1,2,3]}` | `200 OK` | Tier dihapus (soft delete) |
| `TC-TIER-DELETE-002` | Hapus tier pada campaign review | Business Logic | Status `review` | `409 Conflict` | Error tidak dapat dihapus |
| `TC-TIER-DELETE-003` | Hapus tier tanpa token | Security | No auth | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-TIER-DELETE-004` | Hapus tier yang sedang dipakai backing | Business Logic | Tier dengan backing aktif | `409 Conflict` | Error "Cannot delete tier with active backings" |
| `TC-TIER-DELETE-005` | Hapus dengan array ids kosong | Negative | `{"ids": []}` | `422 Unprocessable` | Error "The ids field is required" |
| `TC-TIER-DELETE-006` | Hapus dengan id yang tidak ada | Negative | `{"ids": [9999]}` | `422 Unprocessable` | Error "Selected ids are invalid" |
| `TC-TIER-DELETE-007` | Hapus tier Creator A oleh Creator B | Security | BOPA | `404 Not Found` | Not Found |
