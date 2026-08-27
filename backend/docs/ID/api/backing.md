# API Modul Backing

Pembuatan, pencantuman, dan pengelolaan status backing (dukungan keuangani) untuk kampanye.

## Arsitektur

Modul backing menangani proses pengguna yang mendanai sebuah kampanye. Mendukung tier hadiah opsional dan memvalidasi jumlah dukungan minimum, ketersediaan tier, dan mencegah creator mendukung kampanye sendiri.

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/BackingController.php` | Mencantumkan backing dan menangani dukungan kampanye |
| Service | `app/Services/BackingService.php` | Logika bisnis untuk membuat backing, validasi tier, cek target |
| Request | `app/Http/Requests/StoreBackingRequest.php` | Validasi untuk backing baru |
| Resource | `app/Http/Resources/BackingResource.php` | Pemformatan respons JSON |
| Model | `app/Models/Backing.php` | Entitas backing dengan relasi |
| Enum | `app/Enums/BackingStatus.php` | Keadaan status backing |
| Enum | `app/Enums/TransactionType.php` | Tipe transaksi termasuk `PAYMENT` |
| Job (terkait) | `app/Jobs/RefundBackersJob.php` | Dipanggil untuk backing yang dikembalikan pada kampanye yang gagal |
| Event | `app/Events/BackingCreated.php` | Dipicu setelah backing berhasil |
| Listener | `app/Listeners/HandleBackingCreated.php` | Membuat notifikasi dalam aplikasi + email |

### Alur

```
Backer → StoreBackingRequest → BackingService::create() → DB Transaction
       → Validasi creator ≠ backer
       → Validasi kampanye is ACTIVE
       → Validasi ketersediaan tier atau jumlah minimum
       → Buat Backing (status=completed)
       → Buat Transaction (type=payment, status=success)
       → Kurangi kuota tier jika berlaku
       → Tambahkan collected_amount pada kampanye
       → Cek jika target tercapai → Trigger event CampaignFunded
       → Trigger event BackingCreated → Listener HandleBackingCreated
```

## Struktur File

```
app/
├── Http/Controllers/Api/BackingController.php
├── Services/BackingService.php
├── Http/Requests/StoreBackingRequest.php
├── Http/Resources/BackingResource.php
└── Models/Backing.php
```

## Status States Backing

| Status | Label | Deskripsi |
|--------|-------|-------------|
| `PENDING` | `pending` | Backing dibuat namun pembayaran belum dikonfirmasi |
| `COMPLETED` | `completed` | Pembayaran berhasil dan backing aktif |
| `REFUNDED` | `refunded` | Backing dikembalikan (kampanye gagal) |

## API Endpoints

### 1. Daftar Backing Saya

Mengembalikan daftar backing yang telah dibuat oleh pengguna yang terotentikasi. Admin dapat melihat semua backing.

**Endpoint:** `GET /api/backings`  
**Middleware:** `auth:sanctum` + `verified`  
**Deskripsi:** Mencantumkan semua backing untuk pengguna saat ini. Admin melihat semua backing di seluruh sistem.

#### Parameter Kueri

| Parameter | Tipe | Wajib | Default | Deskripsi |
|-----------|------|----------|---------|-------------|
| `page` | integer | Tidak | 1 | Nomor halaman |
| `per_page` | integer | Tidak | 15 | Jumlah item per halaman |

#### Respons (Sukses: 200)

```json
{
  "data": [
    {
      "id": 1,
      "campaign": {
        "id": 1,
        "slug": "kampanye-teknologi-gojek",
        "title": "Kampanye Teknologi Gojek",
        "status": "active",
        "target_amount": "5000000.00",
        "collected_amount": "3000000.00",
        "progress_percentage": 60,
        "deadline": "2026-08-31",
        "creator_name": "Zaki Creator 1"
      },
      "tier": {
        "id": 1,
        "name": "Early Bird",
        "min_amount": "100000.00"
      },
      "amount": "100000.00",
      "status": "completed",
      "created_at": "2026-08-25T10:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

> **Untuk admin:** Respons yang sama namun mencakup backing dari semua pengguna.

---

### 2. Dapatkan Backing Kampanye (Daftar Backer untuk Detail Kampanye)

Mengembalikan daftar backing yang dipaginasi untuk sebuah kampanye tertentu.

**Endpoint:** `GET /api/campaigns/{slug}/backings`  
**Middleware:** `auth:sanctum` + `verified`  
**Deskripsi:** Mencantumkan semua backer untuk sebuah kampanye. Digunakan untuk menampilkan daftar backer di halaman detail kampanye.

#### Parameter Kueri

| Parameter | Tipe | Wajib | Default | Deskripsi |
|-----------|------|----------|---------|-------------|
| `page` | integer | Tidak | 1 | Nomor halaman |
| `per_page` | integer | Tidak | 15 | Jumlah item per halaman |

#### Respons (Sukses: 200)

Struktur yang sama dengan "Daftar Backing Saya", namun disaring berdasarkan kampanye yang ditentukan.

---

### 3. Buat Backing

Mendanai sebuah kampanye. Mendukung pemilihan tier opsional.

**Endpoint:** `POST /api/campaigns/{slug}/back`  
**Middleware:** `auth:sanctum` + `verified`  
**Deskripsi:** Membuat backing baru untuk pengguna yang terotentikasi pada kampanye yang ditentukan.

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Deskripsi |
|-----------|------|----------|------------|-------------|
| `tier_id` | integer | Tidak | `nullable, integer, exists:campaign_tiers,id` | Tier hadiah yang diklaim (jika ada) |
| `amount` | decimal | Ya | `required, numeric, min:10000` | Jumlah backing (min 10.000) |

#### Contoh Request

```json
{
  "tier_id": 1,
  "amount": 100000
}
```

> **Atau tanpa tier:**
> ```json
> {
>   "amount": 50000
> }
> ```

#### Respons (Sukses: 201)

```json
{
  "id": 1,
  "campaign": {
    "id": 1,
    "slug": "kampanye-teknologi-gojek",
    "title": "Kampanye Teknologi Gojek",
    "status": "active",
    "target_amount": "5000000.00",
    "collected_amount": "3100000.00",
    "progress_percentage": 62,
    "deadline": "2026-08-31",
    "creator_name": "Zaki Creator 1"
  },
  "tier": {
    "id": 1,
    "name": "Early Bird",
    "min_amount": "100000.00"
  },
  "amount": "100000.00",
  "status": "completed",
  "created_at": "2026-08-25T10:00:00.000000Z"
}
```

#### Efek Samping

- Membuat catatan `Backing` (status=completed)
- Membuat catatan `Transaction` (type=payment, status=success, reference=`mock_payment_*`)
- Mengurangi `CampaignTier.remaining_quota` jika tier digunakan
- Meningkatkan `Campaign.collected_amount`
- Jika target tercapai → status kampanye berubah menjadi `success`, memicu event `CampaignFunded` → memanggil `DisburseCampaignJob`
- Memicu event `BackingCreated` → membuat 2 notifikasi dalam aplikasi (ke backer dan creator) + mengirim email konfirmasi backing

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 403 | You cannot back your own campaign | Creator mendukung kampunya sendiri |
| 422 | Validation error | Jumlah < 10.000 / Tier tidak tersedia / Kuota tier habis / Kampanye tidak aktif |

## Skema Sumber Daya Backing

```json
{
  "id": 1,
  "campaign": {
    "id": 1,
    "slug": "kampanye-teknologi-gojek",
    "title": "Kampanye Teknologi Gojek",
    "status": "active",
    "target_amount": "5000000.00",
    "collected_amount": "3000000.00",
    "progress_percentage": 60,
    "deadline": "2026-08-31",
    "creator_name": "Zaki Creator 1"
  },
  "tier": {
    "id": 1,
    "name": "Early Bird",
    "min_amount": "100000.00"
  },
  "amount": "100000.00",
  "status": "completed",
  "created_at": "2026-08-25T10:00:00.000000Z"
}
```

## Aturan Bisnis

### 1. Jumlah Dukungan Minimum

Jumlah dukungan minimum (tanpa tier) adalah **10.000** (10k VND). Diperlakukan di `BackingService::ensureMinimumAmount()`.

### 2. Validasi Tier

Saat menggunakan tier:
- Tier harus ada dan milik kampanye
- `remaining_quota` harus > 0 ATAU tier harus tak terbatas (`quota = 0`)
- Jumlah backing harus ≥ `tier.min_amount`

Saat **tidak** menggunakan tier:
- Jumlah backing harus ≥ 10.000

### 3. Pencegahan Creator Mendukung Kampanyenya Sendiri

Creator tidak dapat mendukung kampanyanya sendiri. Diperlakukan di `BackingService::ensureCanBack()`.

### 4. Pemeriksaan Status Kampanye

Backing hanya dapat dibuat pada kampanye dengan status `ACTIVE`. Kampanye dalam status DRAFT, REVIEW, SUCCESS, atau FAILED akan menolak backing baru.

### 5. Pemeriksaan Pencapaian Target

Setelah setiap backing berhasil, sistem memeriksa apakah `collected_amount >= target_amount`. Jika demikian:
1. Status kampanye → `success`
2. Event `CampaignFunded` dipicu
3. Listener `HandleCampaignFunded` memanggil `DisburseCampaignJob`
4. `DisburseCampaignJob` mentransfer dana (95%) ke creator, mengambil 5% biaya platform

## Pengujian Postman

### Skrip Pengujian (Backing)

#### Pengujian 1: Daftar Backing Saya

1. Atur permintaan: `GET {{base_url}}/backings`
2. Header: `Authorization: Bearer {{backer_token}}`
3. Diperkirakan: `200 OK` dengan daftar backing terpaginasi.

#### Pengujian 2: Daftar Backing Kampanye (Admin atau Creator)

1. Atur permintaan: `GET {{base_url}}/campaigns/kampanye-teknologi-gojek/backings`
2. Header: `Authorization: Bearer {{admin_token or creator_token}}`
3. Diperkirakan: `200 OK` dengan daftar backer terpaginasi.

#### Pengujian 3: Buat Backing dengan Tier

1. Atur permintaan: `POST {{base_url}}/campaigns/{slug}/back`
2. Header: `Authorization: Bearer {{backer_token}}`
3. Body: `{"tier_id": 1, "amount": 100000}`
4. Diperkirakan: `201 Created` dengan backing + progres kampanye diperbarui.

#### Pengujian 4: Buat Backing tanpa Tier

1. Atur permintaan: `POST {{base_url}}/campaigns/{slug}/back`
2. Header: `Authorization: Bearer {{backer_token}}`
3. Body: `{"amount": 50000}`
4. Diperkirakan: `201 Created`.

#### Pengujian 5: Upaya Self-Backing

1. Atur permintaan: `POST {{base_url}}/campaigns/{creator_slug}/back`
2. Header: `Authorization: Bearer {{creator_token}}`
3. Body: `{"amount": 100000}`
4. Diperkirakan: `403 Forbidden`.

#### Pengujian 6: Jumlah Tidak Mencukupi

1. Atur permintaan: `POST {{base_url}}/campaigns/{slug}/back`
2. Body: `{"amount": 5000}`
3. Diperkirakan: `422 Validation error`.

#### Pengujian 7: Kuota Tier Habis

1. Atur permintaan pada kampanye di mana semua tier penuh.
2. Body: `{"tier_id": 1, "amount": 100000}`
3. Diperkirakan: `422 Validation error` — kuota tier habis.

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Daftar backing saya (backer) | Token valid | 200 + daftar terpaginasi |
| 2 | Daftar semua backing (admin) | Token admin | 200 + semua backing |
| 3 | Daftar backing kampanye | Slug kampanye valid | 200 + daftar backer terpaginasi |
| 4 | Buat backing dengan tier valid | tier_id=1, amount=100000 | 201 + backing dibuat |
| 5 | Buat backing tanpa tier | amount=50000 | 201 + backing dibuat |
| 6 | Upaya self-backing | Creator mendukung kampunya sendiri | 403 dilarang |
| 7 | Backing di bawah minimum | amount=5000 | 422 error validasi |
| 8 | Backing dengan tier habis | tier_id dengan remaining_quota=0 | 422 error validasi |
| 9 | Backing dengan tier tidak valid | tier_id yang tidak ada | 422 error validasi |
| 10 | Backing pada kampanye tidak aktif | slug kampanye DRAFT/REVIEW/FAILED | 422 error validasi |
| 11 | Backing tepat di ambang minimum tier | min_amount tier = 100000, amount=100000 | 201 berhasil |
| 12 | Backing di atas minimum tier | min_amount=100000, amount=150000 | 201 berhasil |
| 13 | Backing pada tier kuota 0 (tak terbatas) | tier dengan quota=0 | 201 berhasil |
| 14 | Backing mencapai target tepat | collected + amount = target | 201 + status=success + pencairan dipicu |

## Pemecahan Masalah

### 1. "You cannot back your own campaign"

Error ini terjadi ketika seorang creator mencoba mendukung kampunya sendiri. Metode `BackingService::ensureCanBack()` melempar `AuthorizationException` (403) untuk kasus ini.

**Perbaikan:** Gunakan akun pengguna yang berbeda (backer) untuk membuat backing.

---

### 2. "Tier quota exhausted"

Saat sebuah tier memiliki `remaining_quota = 0` dan `quota != 0` (kuota terbatas), sistem mencegah backing tambahan pada tier tersebut.

**Perbaikan:** Gunakan tier yang berbeda atau dukung tanpa tier.

---

### 3. "Campaign is not active"

Backing hanya dapat dibuat pada kampanye dengan `status = 'active'`. Kampanye dalam status DRAFT, REVIEW, SUCCESS, atau FAILED menolak backing baru.

**Perbaikan:** Kampanye harus disetujui oleh admin terlebih dahulu (status → active).

---

### 4. "Amount must be at least 10000"

Jumlah dukungan minimum adalah 10.000 (10k) ketika tidak ada tier yang dipilih.

**Perbaikan:** Pastikan `amount >= 10000`.

---

### 5. Backing berhasil tetapi status kampanye tidak berubah menjadi SUCCESS

Ini ditangani di `BackingService::checkCampaignReachedTarget()`. Metode ini menggunakan `DB::afterCommit()` untuk memicu event `CampaignFunded` setelah transaksi komited. Pastikan:
1. Total `collected_amount` sama dengan atau melebihi `target_amount`
2. Tidak terjadi error transaksi database

---

### 6. Notifikasi email tidak diterima

Listener `HandleBackingCreated` hanya mengirim email jika penerima (`email_verified_at` tidak null). Pastikan pengguna sudah memverifikasi emailnya sebelum melakukan backing.

---

### 7. Referensi transaksi adalah "mock"

Implementasi saat ini menggunakan referensi transaksi mock (`mock_payment_*`). Di produksi, ini harus diganti dengan integrasi gerbang pembayaran nyata (mis., Midtrans, Doku, dll.).

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Daftar backing saya | Terotentikasi | `auth:sanctum, verified` |
| Daftar backing kampanye | Terotentikasi | `auth:sanctum, verified` |
| Buat backing | Terotentikasi (backer) | `auth:sanctum, verified` |
