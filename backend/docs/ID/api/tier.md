# API Modul Tier

Pengelolaan tier hadiah untuk kampanye.

## Arsitektur

Modul Tier mengelola tier hadiah yang dikaitkan dengan sebuah kampanye. Setiap tier menentukan jumlah dukungan minimum, kuota opsional, dan deskripsi hadiah. Tier dibuat, diperbarui, dan dihapus sebagai bagian dari pengelolaan kampanye.

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/TierController.php` | Menangani operasi CRUD tier |
| Service | `app/Services/TierService.php` | Logika bisnis untuk pengelolaan tier |
| Requests | `app/Http/Requests/{StoreTierRequest, UpdateTierRequest, DeleteTierRequest}.php` | Aturan validasi |
| Resource | `app/Http/Resources/CampaignTierResource.php` | Pemformatan respons JSON |
| Model | `app/Models/CampaignTier.php` | Entitas tier dengan logika kuota |

### Alur

```
Creator → Validasi permintaan (pemeriksaan kepemilikan)
       → TierService::create/update/deleteMany()
       → CampaignService::ensureEditable() check
       → DB::transaction() (untuk multi-hapus)
       → Perbarui catatan CampaignTier
```

## Struktur File

```
app/
├── Http/Controllers/Api/TierController.php
├── Services/TierService.php
├── Http/Requests/
│   ├── StoreTierRequest.php
│   ├── UpdateTierRequest.php
│   └── DeleteTierRequest.php
├── Http/Resources/CampaignTierResource.php
└── Models/CampaignTier.php
```

## API Endpoints

### 1. Buat Tier

Membuat tier hadiah baru untuk sebuah kampanye.

**Endpoint:** `POST /api/campaigns/{slug}/tiers`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Menambahkan tier hadiah ke kampanye yang dapat diedit.

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `name` | string | Ya | `required, string, max:255` | Nama tier |
| `min_amount` | decimal | Ya | `required, numeric, min:0` | Jumlah dukungan minimum |
| `quota` | integer | Ya | `required, integer, min:0` | Kuota (0 = tak terbatas) |
| `reward_description` | string | Tidak | `nullable, string` | Deskripsi hadiah |

#### Contoh Request

```json
{
  "name": "Early Bird",
  "min_amount": 100000,
  "quota": 10,
  "reward_description": "Special thank you + exclusive sticker pack"
}
```

#### Respons (Sukses: 201)

```json
{
  "id": 4,
  "name": "Early Bird",
  "min_amount": "100000.00",
  "quota": 10,
  "remaining_quota": 10,
  "is_unlimited": false,
  "has_availability": true,
  "reward_description": "Special thank you + exclusive sticker pack"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan creator / bukan pemilik kampanye |
| 409 | Campaign is not in editable state | Kampanye adalah DRAFT... wait, DRAFT IS editable |

> **Catatan bug:** `ensureEditable()` dipanggil sebelum membuat tier, namun hanya mengizinkan status DRAFT (bukan REVIEW). Jika kampanye dalam status REVIEW, tier tidak dapat ditambahkan. Ini memang memang disengaja — kampanye harus dalam status DRAFT sebelum menambahkan tier.

---

### 2. Perbarui Tier

Memperbarui tier yang ada.

**Endpoint:** `PUT /api/campaigns/{slug}/tiers/{tier}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Memperbarui detail tier.

#### Otorisasi

- Pengguna harus memiliki kampanye (`$campaign->user_id === auth()->id()`)
- Tier harus milik kampanye

#### Body Permintaan (Semua field opsional)

| Parameter | Tipe | Validasi | Deskripsi |
|-----------|------|------------|-------------|
| `name` | string | `sometimes, string, max:255` | Nama tier |
| `min_amount` | decimal | `sometimes, numeric, min:0` | Jumlah dukungan minimum |
| `quota` | integer | `sometimes, integer, min:0` | Kuota (0 = tak terbatas) |
| `reward_description` | string | `nullable, string` | Deskripsi hadiah |

#### Respons (Sukses: 200)

```json
{
  "id": 4,
  "name": "Early Bird (Updated)",
  "min_amount": "150000.00",
  "quota": 15,
  "remaining_quota": 10,
  "is_unlimited": false,
  "has_availability": true,
  "reward_description": "Updated reward"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan pemilik |
| 409 | Campaign is not in editable state | Bukan DRAFT |

---

### 3. Hapus Tier (Massal)

Menghapus beberapa tier dari sebuah kampanye.

**Endpoint:** `DELETE /api/campaigns/{slug}/tiers`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Deskripsi:** Menghapus beberapa tier dalam satu permintaan.

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `ids` | array | Ya | `required, array, min:1` | Array ID tier |
| `ids.*` | integer | Ya | `integer, exists:campaign_tiers,id` | Harus ada |

#### Contoh Request

```json
{
  "ids": [4, 5]
}
```

#### Respons (Sukses: 200)

```json
{
  "message": "Tiers deleted successfully"
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You do not have permission to access this resource. | Bukan pemilik |
| 409 | A campaign must have at least one tier | Mencoba menghapus semua tier |
| 422 | Validation error | ID tier tidak valid |

## Skema Sumber Daya Tier

```json
{
  "id": 4,
  "name": "Early Bird",
  "min_amount": "100000.00",
  "quota": 10,
  "remaining_quota": 5,
  "is_unlimited": false,
  "has_availability": true,
  "reward_description": "Special thank you + exclusive sticker pack"
}
```

### Referensi Kolom

| Kolom | Tipe | Deskripsi |
|-------|------|-------------|
| `id` | integer | ID tier |
| `name` | string | Nama tier |
| `min_amount` | decimal | Jumlah dukungan minimum untuk tier ini |
| `quota` | integer\|null | Jumlah maksimal backing (null = tak terbatas) |
| `remaining_quota` | integer\|null | Slot yang tersisa (null = tak terbatas) |
| `is_unlimited` | boolean | Benar jika quota = 0 |
| `has_availability` | boolean | Benar jika masih tersedia untuk backing |
| `reward_description` | string\|null | Deskripsi hadiah |

## Aturan Bisnis

### 1. Kuota Tak Terbatas

Saat `quota = 0`, tier dianggap tak terbatas:
- `is_unlimited` → `true`
- `remaining_quota` → `null`
- `has_availability` → `true` (selalu, terlepas dari berapa banyak backing)

### 2. Pengelolaan Kuota

Setiap kali backing dibuat menggunakan tier:
- `remaining_quota` dikurangi 1
- Jika `remaining_quota` mencapai 0, `has_availability` menjadi `false`

### 3. Minimum Tier

Validasi `StoreCampaignRequest` menerapkan `tiers.*.min_amount` dengan aturan `min:0`, tetapi logika bisnis tidak mencegah `min_amount = 0`. Namun, `BackingService` mengharuskan jumlah backing ≥ `tier.min_amount`.

### 4. Hanya Status yang Dapat Diedit

Tier hanya dapat dibuat, diperbarui, atau dihapus ketika kampanye dalam status DRAFT. Setelah diajukan untuk tinjauan (REVIEW) atau aktif (ACTIVE), modifikasi tier diblokir.

## Pengujian Postman

### Pengaturan: Login sebagai Creator

```
POST {{base_url}}/login
{ "email": "creator1@example.com", "password": "password123" }
→ Simpan token ke {{creator_token}}
```

### Pengujian 1: Buat Tier

1. `POST {{base_url}}/campaigns/{draft-slug}/tiers`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Body:
   ```json
   {
     "name": "Early Bird",
     "min_amount": 100000,
     "quota": 10,
     "reward_description": "Exclusive sticker pack"
   }
   ```
4. Diperkirakan: `201 Created`.

### Pengujian 2: Perbarui Tier

1. `PUT {{base_url}}/campaigns/{draft-slug}/tiers/4`
2. Body:
   ```json
   { "name": "Early Bird Updated", "min_amount": 150000 }
   ```
3. Diperkirakan: `200 OK`.

### Pengujian 3: Hapus Tier

1. `DELETE {{base_url}}/campaigns/{draft-slug}/tiers`
2. Body:
   ```json
   { "ids": [5, 6] }
   ```
3. Diperkirakan: `200 OK`.

### Pengujian 4: Coba Hapus Semua Tier

1. Hapus semua tier kecuali satu.
2. `DELETE {{base_url}}/campaigns/{draft-slug}/tiers`
3. Body:
   ```json
   { "ids": [remaining_tier_id] }
   ```
4. Diperkirakan: `409 Conflict` — "A campaign must have at least one tier".

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Buat tier pada kampanye yang dapat diedit | Data tier valid | 201 + skema tier |
| 2 | Buat tier pada kampanye yang tidak dapat diedit | Kampanye aktif/REVIEW | 409 konflik |
| 3 | Buat tier sebagai non-pemilik | Kampanye creator lain | 403 dilarang |
| 4 | Perbarui tier (pemilik) | Data tier valid | 200 + tier yang diperbarui |
| 5 | Perbarui tier (non-pemilik) | Tier creator lain | 403 dilarang |
| 6 | Hapus beberapa tier | Array ID valid | 200 + berhasil dihapus |
| 7 | Hapus semua tier (sisakan 1) | Tier terakhir yang tersisa | 409 konflik |
| 8 | Hapus tier tidak pada kampanye | Tier dari kampanye berbeda | 403/422 error |
| 9 | Buat tier tak terbatas | quota=0, min_amount=50000 | 201 + is_unlimited=true |
| 10 | Backing pada tier tak terbatas | Beberapa backing | remaining_quota tetap null |

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Buat tier | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Perbarui tier | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
| Hapus tier | Creator (pemilik) | `auth:sanctum, role:creator, verified` |
