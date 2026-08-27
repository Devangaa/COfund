# API Modul Transaksi

Riwayat transaksi hanya-baca untuk pengguna yang terotentikasi.

## Arsitektur

Modul transaksi menyediakan endpoint untuk melihat semua transaksi yang terkait dengan akun pengguna. Transaksi dibuat secara otomatis oleh sistem (backing, pencairan, pengembalian, biaya, deposit, penarikan) dan bersifat hanya-baca dari perspektif pengguna.

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/TransactionController.php` | Mencantumkan transaksi pengguna |
| Model | `app/Models/Transaction.php` | Entitas transaksi |
| Enum | `app/Enums/TransactionType.php`, `TransactionStatus.php` | Enum tipe dan status |
| Resource | `app/Http/Resources/TransactionResource.php` | Pemformatan respons JSON |
| Services | `app/Services/WalletService.php`, `app/Services/BackingService.php`, `app/Services/TransactionService.php` | Membuat transaksi untuk berbagai operasi |

### Alur

```
User → GET /api/transactions
     → Auth::user()
     → User->transactions (hasMany)
     → Filter berdasarkan tipe / urutkan
     → Paginasi
     → Koleksi TransactionResource
```

## Struktur File

```
app/
├── Http/Controllers/Api/TransactionController.php
├── Models/Transaction.php
├── Enums/
│   ├── TransactionType.php
│   └── TransactionStatus.php
├── Http/Resources/TransactionResource.php
└── Services/
    ├── WalletService.php
    ├── BackingService.php
    └── TransactionService.php
```

## Tipe Transaksi

| Tipe | Nilai Enum | Arah | Trigger |
|------|------------|-----------|---------|
| `PAYMENT` | `payment` | Keluar | Backing dibuat |
| `DISBURSEMENT` | `disbursement` | Masuk | Kampanye berhasil didanai |
| `REFUND` | `refund` | Masuk | Kampanye gagal, backer dikembalikan |
| `PLATFORM_FEE` | `platform_fee` | Keluar | Biaya platform (5%) yang dikurangkan |
| `DEPOSIT` | `deposit` | Masuk | Pengguna mendepositokan ke dompet |
| `WITHDRAWAL` | `withdrawal` | Keluar | Pengguna menarik dari dompet |

## Status Transaksi

| Status | Nilai Enum | Deskripsi |
|--------|------------|-------------|
| `PENDING` | `pending` | Transaksi dibuat namun belum final |
| `SUCCESS` | `success` | Transaksi selesai |
| `FAILED` | `failed` | Transaksi gagal |

## API Endpoints

### 1. Daftar Transaksi

Mengembalikan daftar transaksi yang dipaginasi untuk pengguna yang terotentikasi.

**Endpoint:** `GET /api/transactions`  
**Middleware:** `auth:sanctum`  
**Deskripsi:** Mengembalikan riwayat transaksi untuk pengguna saat ini.

#### Parameter Kueri

| Parameter | Tipe | Wajib | Default | Deskripsi |
|-----------|------|----------|---------|-------------|
| `page` | integer | Tidak | 1 | Nomor halaman |
| `per_page` | integer | Tidak | 15 | Jumlah item per halaman |
| `type` | string | Tidak | — | Filter berdasarkan tipe: `payment`, `disbursement`, `refund`, `platform_fee`, `deposit`, `withdrawal` |
| `status` | string | Tidak | — | Filter berdasarkan status: `pending`, `success`, `failed` |
| `sort_by` | string | Tidak | `created_at` | Bidang pengurutan |
| `order` | string | Tidak | `desc` | Arah pengurutan: `asc`, `desc` |

#### Contoh Request

```
GET /api/transactions?type=payment&status=success&sort_by=created_at&order=desc
```

#### Respons (Sukses: 200)

```json
{
  "data": [
    {
      "id": 1,
      "type": "payment",
      "amount": "100000.00",
      "status": "success",
      "reference": "mock_payment_1724694400_abc123",
      "backing_id": 1,
      "campaign_id": 1,
      "created_at": "2026-08-26T10:00:00.000000Z"
    },
    {
      "id": 10,
      "type": "deposit",
      "amount": "500000.00",
      "status": "success",
      "reference": "deposit_20260826_abc123",
      "backing_id": null,
      "campaign_id": null,
      "created_at": "2026-08-26T15:30:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/transactions?page=1",
    "last": "http://localhost/api/transactions?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [...],
    "path": "http://localhost/api/transactions",
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |

## Skema Sumber Daya Transaksi

```json
{
  "id": 1,
  "type": "payment",
  "amount": "100000.00",
  "status": "success",
  "reference": "mock_payment_1724694400_abc123",
  "backing_id": 1,
  "campaign_id": 1,
  "created_at": "2026-08-26T10:00:00.000000Z"
}
```

### Referensi Kolom

| Kolom | Tipe | Deskripsi |
|-------|------|-------------|
| `id` | integer | ID transaksi |
| `type` | enum | Tipe transaksi (lihat tabel di atas) |
| `amount` | decimal | Jumlah transaksi |
| `status` | enum | Status transaksi (lihat tabel di atas) |
| `reference` | string\|null | Referensi eksternal (ID gerbang pembayaran, dll.) |
| `backing_id` | integer\|null | ID backing (jika terkait dengan backing) |
| `campaign_id` | integer\|null | ID kampanye (jika terkait dengan kampanye) |
| `created_at` | datetime | Timestamp pembuatan transaksi |

## Aturan Bisnis

### 1. Filter Berdasarkan Tipe

Pengguna dapat memfilter transaksi berdasarkan 6 tipe: `payment`, `disbursement`, `refund`, `platform_fee`, `deposit`, `withdrawal`.

### 2. Bidang yang Dapat Diurutkan

Pengurutan default adalah berdasarkan `created_at` secara menurun (terbaru dulu). Pengguna dapat mengurutkan berdasarkan kolom apa saja di tabel `transactions`.

### 3. Paginasi

Paginasi default adalah 15 item per halaman. Pengguna dapat meminta hingga 100 item per halaman melalui parameter `per_page`.

### 4. Lingkup

Setiap pengguna hanya dapat melihat **transaksinya sendiri**. Controller menggunakan scope kueri `Auth::user()->transactions()`, memastikan tidak ada kebocoran data antar-pengguna.

## Pengujian Postman

### Skrip Pengujian (Transaksi)

#### Pengaturan: Login sebagai Backer

```
POST {{base_url}}/login
{ "email": "backer@example.com", "password": "password123" }
→ Simpan token ke {{backer_token}}
```

#### Pengujian 1: Daftar Semua Transaksi

1. `GET {{base_url}}/transactions`
2. Header: `Authorization: Bearer {{backer_token}}`
3. Diperkirakan: `200 OK` dengan daftar transaksi terpaginasi pengguna.

#### Pengujian 2: Filter Berdasarkan Tipe Deposit

1. `GET {{base_url}}/transactions?type=deposit`
2. Diperkirakan: `200 OK` dengan hanya transaksi deposit.

#### Pengujian 3: Filter Berdasarkan Tipe Payment

1. `GET {{base_url}}/transactions?type=payment`
2. Diperkirakan: `200 OK` dengan hanya transaksi pembayaran (backing).

#### Pengujian 4: Urutkan Berdasarkan Jumlah (Naik)

1. `GET {{base_url}}/transactions?sort_by=amount&order=asc`
2. Diperkirakan: `200 OK` dengan transaksi yang diurutkan berdasarkan jumlah secara naik.

#### Pengujian 5: Filter Berdasarkan Status Success

1. `GET {{base_url}}/transactions?status=success`
2. Diperkirakan: `200 OK` dengan hanya transaksi yang berhasil.

#### Pengujian 6: Akses Tanpa Otentikasi

1. `GET {{base_url}}/transactions` (tanpa header Authorization)
2. Diperkirakan: `401 Unauthenticated`.

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Daftar semua transaksi | Token valid | 200 + daftar terpaginasi |
| 2 | Daftar transaksi dengan deposit | `?type=deposit` | 200 + hanya deposit |
| 3 | Daftar transaksi dengan payment | `?type=payment` | 200 + hanya payment |
| 4 | Daftar transaksi dengan penarikan | `?type=withdrawal` | 200 + hanya penarikan |
| 5 | Urutkan berdasarkan jumlah naik | `?sort_by=amount&order=asc` | 200 + diurutkan secara naik |
| 6 | Urutkan berdasarkan jumlah turun | `?sort_by=amount&order=desc` | 200 + diurutkan secara turun |
| 7 | Filter berdasarkan status success | `?status=success` | 200 + hanya yang berhasil |
| 8 | Filter berdasarkan status failed | `?status=failed` | 200 + hanya yang gagal |
| 9 | Akses tanpa otentikasi | Tidak ada token | 401 tidak terotentikasi |
| 10 | Paginasi transaksi | `?per_page=5` | 200 + 5 item per halaman |
| 11 | Tidak ada transaksi | Akun pengguna baru | 200 + array data kosong |
| 12 | Enum tipe transaksi benar | Dari berbagai aksi | field type cocok dengan nilai enum |

## Pemecahan Masalah

### 1. Tipe Transaksi Hilang pada Daftar

Migrasi `transactions` awalnya mendefinisikan kolom `type` enum sebagai `['payment', 'refund', 'disbursement', 'platform_fee']`. Tipe `deposit` dan `withdrawal` ditambahkan ke enum `TransactionType` PHP nanti tetapi **database enum tidak diperbarui**.

Ini adalah **masalah kritis**: Di bawah mode strict MySQL, memasukkan `type = 'deposit'` atau `'withdrawal'` akan gagal, tetapi error mungkin tertelan secara diam-diam atau menghasilkan error SQL.

**Perbaikan:** Jalankan migrasi untuk memperbarui enum:
```php
DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('payment','refund','disbursement','platform_fee','deposit','withdrawal')");
```

### 2. Transaksi Menampilkan `null` untuk backing_id atau campaign_id

Ini adalah perilaku yang diharapkan untuk transaksi `deposit` dan `withdrawal` — mereka tidak dikaitkan dengan backing atau kampanye tertentu. Kolom `backing_id` dan `campaign_id` dapat bernilai null di migrasi.

### 3. Referensi Transaksi Selalu "mock"

Untuk pembayaran backing, format referensinya adalah `mock_payment_{timestamp}_{random}`. Ini adalah placeholder untuk pemrosesan pembayaran mock. Di produksi, ini harus diganti dengan referensi gerbang pembayaran nyata.

Untuk deposit dan penarikan, format referensinya adalah `deposit_20260826_abc123` atau `withdrawal_20260826_xyz789`.

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Daftar transaksi | Terotentikasi | `auth:sanctum` |
