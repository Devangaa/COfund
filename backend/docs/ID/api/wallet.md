# API Modul Dompet

Deposito dan penarikan dana untuk dompet pengguna yang terotentikasi.

## Arsitektur

Modul dompet memungkinkan pengguna untuk mendepositokan dana ke saldo akun dan menarik dana dari saldonya. Semua transaksi dicatat di tabel `transactions` dan memicu event yang membuat notifikasi dalam aplikasi. Pemeriksaan keamanan mencegah transaksi pada akun yang disuspend.

### Komponen

| Komponen | Path | Deskripsi |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/WalletController.php` | Menangani permintaan deposit dan penarikan |
| Service | `app/Services/WalletService.php` | Logika bisnis untuk deposit/penarikan |
| Requests | `app/Http/Requests/{StoreDepositRequest, StoreWithdrawRequest}.php` | Aturan validasi per tipe transaksi |
| Enum | `app/Enums/TransactionType.php` | Tipe transaksi (`DEPOSIT`, `WITHDRAWAL`, dll.) |
| Model | `app/Models/Transaction.php` | Catatan transaksi dengan enum tipe/status |
| Event | `app/Events/{DepositProcessed, WithdrawalProcessed}.php` | Dipicu setelah transaksi berhasil |
| Listener | `app/Listeners/HandleWalletTransaction.php` | Membuat notifikasi dalam aplikasi untuk deposit/penarikan |

### Alur

```
User → StoreDepositRequest/StoreWithdrawRequest → ensureActive() (cek is_suspended)
     → WalletService::deposit()/withdraw() → DB Transaction
     → Buat catatan Transaction
     → Perbarui saldo User
     → DB::afterCommit → Trigger Event
     → Listener HandleWalletTransaction → Buat notifikasi dalam aplikasi

Perbedaan utama dari pembayaran backing:
- Deposit/Penarikan secara langsung memanipulasi saldo pengguna
- Pembayaran backing mengurangi jumlah backing tetapi tidak menambah saldo
- Penarikan memerlukan pemeriksaan saldo yang cukup
```

## Struktur File

```
app/
├── Http/Controllers/Api/WalletController.php
├── Services/WalletService.php
├── Http/Requests/
│   ├── StoreDepositRequest.php
│   └── StoreWithdrawRequest.php
├── Enums/
│   ├── TransactionType.php
│   └── TransactionStatus.php
├── Events/
│   ├── DepositProcessed.php
│   └── WithdrawalProcessed.php
├── Listeners/HandleWalletTransaction.php
└── Models/Transaction.php
```

## Tipe Transaksi

| Tipe | Nilai Enum | Arah | Deskripsi |
|------|------------|-----------|-------------|
| `PAYMENT` | `payment` | Keluar | Pembayaran backing (pendanaan kampanye) |
| `DISBURSEMENT` | `disbursement` | Masuk | Dana kampanye dilepaskan ke creator |
| `REFUND` | `refund` | Masuk | Jumlah backing yang dikembalikan |
| `PLATFORM_FEE` | `platform_fee` | Keluar | Biaya platform yang dikurangkan dari pencairan |
| `DEPOSIT` | `deposit` | Masuk | Pengguna mendepositokan dana ke dompet |
| `WITHDRAWAL` | `withdrawal` | Keluar | Pengguna menarik dana dari dompet |

## Status Transaksi

| Status | Nilai Enum | Deskripsi |
|--------|------------|-------------|
| `PENDING` | `pending` | Transaksi dibuat namun belum final |
| `SUCCESS` | `success` | Transaksi berhasil diselesaikan |
| `FAILED` | `failed` | Transaksi gagal |

## API Endpoints

### 1. Deposito ke Dompet

Menambahkan dana ke saldo pengguna yang terotentikasi.

**Endpoint:** `POST /api/wallet/deposit`  
**Middleware:** `auth:sanctum` + `verified`  
**Deskripsi:** Mendepositokan uang ke saldo dompet pengguna.

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Pesan Validasi (ID) | Deskripsi |
|-----------|------|----------|------------|-------------------------|-------------|
| `amount` | decimal | Ya | `required, numeric, min:10000, max:100000000` | "Jumlah deposit minimal 10.000" / "Jumlah deposit maksimal 100.000.000" | Jumlah deposit (min 10k, max 100M) |

#### Contoh Request

```json
{
  "amount": 500000
}
```

#### Respons (Sukses: 201)

```json
{
  "transaction": {
    "id": 10,
    "type": "deposit",
    "amount": "500000.00",
    "status": "success",
    "reference": "deposit_20260826_abc123",
    "backing_id": null,
    "campaign_id": null,
    "created_at": "2026-08-26T10:00:00.000000Z"
  },
  "balance": "1000000.00"
}
```

#### Efek Samping

- Memeriksa apakah pengguna disuspend (`ValidationException` jika `is_suspended = true`)
- Membuat catatan `Transaction` (type=deposit, status=success)
- Meningkatkan `User.balance`
- Memicu event `DepositProcessed` setelah commit
- Listener `HandleWalletTransaction::handleDeposit` membuat notifikasi dalam aplikasi `Notification`

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 422 | "User is currently suspended" | `is_suspended = true` |
| 422 | "Jumlah deposit minimal 10.000" | Amount < 10.000 |
| 422 | "Jumlah deposit maksimal 100.000.000" | Amount > 100.000.000 |

---

### 2. Tarik dari Dompet

Mengurangkan dana dari saldo pengguna yang terotentikasi.

**Endpoint:** `POST /api/wallet/withdraw`  
**Middleware:** `auth:sanctum` + `verified`  
**Deskripsi:** Menarik uang dari saldo dompet pengguna.

#### Body Permintaan

| Parameter | Tipe | Wajib | Validasi | Pesan Validasi (ID) | Deskripsi |
|-----------|------|----------|------------|-------------------------|-------------|
| `amount` | decimal | Ya | `required, numeric, min:50000, max:50000000` | "Jumlah penarikan minimal 50.000" / "Jumlah penarikan maksimal 50.000.000" | Jumlah penarikan (min 50k, max 50M) |

#### Contoh Request

```json
{
  "amount": 250000
}
```

#### Respons (Sukses: 201)

```json
{
  "transaction": {
    "id": 11,
    "type": "withdrawal",
    "amount": "250000.00",
    "status": "success",
    "reference": "withdrawal_20260826_xyz789",
    "backing_id": null,
    "campaign_id": null,
    "created_at": "2026-08-26T10:00:00.000000Z"
  },
  "balance": "250000.00"
}
```

#### Efek Samping

- Memeriksa apakah pengguna disuspend (`ValidationException` jika `is_suspended = true`)
- Memeriksa apakah saldo cukup (`ValidationException` jika `balance < amount`)
- Membuat catatan `Transaction` (type=withdrawal, status=success)
- Mengurangi `User.balance`
- Memicu event `WithdrawalProcessed` setelah commit
- Listener `HandleWalletTransaction::handleWithdrawal` membuat notifikasi dalam aplikasi `Notification`

#### Error

| Kode | Pesan | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Token hilang/tidak valid |
| 422 | "User is currently suspended" | `is_suspended = true` |
| 422 | "Insufficient balance" | `balance < amount` |
| 422 | "Jumlah penarikan minimal 50.000" | Amount < 50.000 |
| 422 | "Jumlah penarikan maksimal 50.000.000" | Amount > 50.000.000 |

## Skema Sumber Daya Transaksi

```json
{
  "id": 10,
  "type": "deposit",
  "amount": "500000.00",
  "status": "success",
  "reference": "deposit_20260826_abc123",
  "backing_id": null,
  "campaign_id": null,
  "created_at": "2026-08-26T10:00:00.000000Z"
}
```

### Referensi Kolom

| Kolom | Tipe | Deskripsi |
|-------|------|-------------|
| `id` | integer | ID transaksi |
| `type` | enum | Salah satu dari `payment`, `disbursement`, `refund`, `platform_fee`, `deposit`, `withdrawal` |
| `amount` | decimal | Jumlah transaksi |
| `status` | enum | Salah satu dari `pending`, `success`, `failed` |
| `reference` | string\|null | Referensi transaksi (mis., `deposit_20260826_abc123`) |
| `backing_id` | integer\|null | ID backing (jika terkait) |
| `campaign_id` | integer\|null | ID kampanye (jika terkait) |
| `created_at` | datetime | Timestamp transaksi |

## Diagram Alur Dompet

```
┌──────────────────┐     ┌──────────────────┐
│  User (Backer)   │     │  WalletService   │
└────────┬─────────┘     └────────┬─────────┘
         │ Deposit Request        │
         │ amount                 │
         ├───────────────────────►│
         │                        │
         │ ensureActive()        │
         │ (check is_suspended)  │
         │                        │
         │                        │ DB::transaction()
         │                        │ → increment balance
         │                        │ → create Transaction (DEPOSIT)
         │                        │ → commit
         │                        │ → DB::afterCommit
         │                        │ → fire DepositProcessed
         │                        │ → HandleWalletTransaction
         │                        │ → create Notification
         │                        │
         │ 201 + balance          │
         │◄──────────────────────┤
         │                        │
         └────────────────────────┘

┌──────────────────┐     ┌──────────────────┐
│  User (Backer)   │     │  WalletService   │
└────────┬─────────┘     └────────┬─────────┘
         │ Withdraw Request       │
         │ amount                 │
         ├───────────────────────►│
         │                        │
         │ ensureActive()        │
         │ (check is_suspended)  │
         │ check balance          │
         │ (balance >= amount)    │
         │                        │ DB::transaction()
         │                        │ → decrement balance
         │                        │ → create Transaction (WITHDRAWAL)
         │                        │ → commit
         │                        │ → DB::afterCommit
         │                        │ → fire WithdrawalProcessed
         │                        │ → HandleWalletTransaction
         │                        │ → create Notification
         │                        │
         │ 201 + balance          │
         │◄──────────────────────┤
         │                        │
         └────────────────────────┘
```

## Aturan Bisnis

### 1. Batas Deposit

| Constraint | Minimum | Maksimum |
|------------|---------|---------|
| Jumlah | 10.000 | 100.000.000 (100M) |

Batasan ini mencegah transaksi mikro dan penyalahgunaan.

### 2. Batas Penarikan

| Constraint | Minimum | Maksimum |
|------------|---------|---------|
| Jumlah | 50.000 | 50.000.000 (50M) |

### 3. Pemeriksaan Akun yang Disuspend

Operasi deposit dan penarikan keduanya memeriksa apakah pengguna disuspend (`is_suspended = true`). Jika disuspend, `ValidationException` dilempar dengan pesan "User is currently suspended".

### 4. Saldo Tidak Mencukupi

Operasi penarikan memeriksa apakah saldo pengguna cukup. Jika `balance < amount`, `ValidationException` dilempar dengan pesan "Insufficient balance".

### 5. Verifikasi Email Diperlukan

Baik `StoreDepositRequest` dan `StoreWithdrawRequest` memiliki metode `authorize()` yang memeriksa apakah email pengguna diverifikasi. Pengguna yang tidak diverifikasi tidak dapat mendepositokan atau menarik.

### 6. Atomisitas Transaksi

Semua operasi dompet menggunakan `DB::transaction()` dan memicu event melalui `DB::afterCommit()` untuk memastikan konsistensi. Jika transaksi gagal, tidak ada event yang dipicu dan saldo tetap tidak berubah.

## Pengujian Postman

### Skrip Pengujian (Dompet)

#### Pengujian 1: Deposito ke Dompet

1. Atur permintaan: `POST {{base_url}}/wallet/deposit`
2. Header: `Authorization: Bearer {{backer_token}}`
3. Body (raw JSON):
   ```json
   {
     "amount": 500000
   }
   ```
4. Diperkirakan: `201 Created` dengan catatan transaksi + saldo yang diperbarui.

#### Pengujian 2: Tarik dari Dompet

1. Atur permintaan: `POST {{base_url}}/wallet/withdraw`
2. Header: `Authorization: Bearer {{backer_token}}`
3. Body (raw JSON):
   ```json
   {
     "amount": 250000
   }
   ```
4. Diperkirakan: `201 Created` dengan catatan transaksi + saldo yang diperbarui.

#### Pengujian 3: Saldo Tidak Mencukupi

1. Pastikan saldo pengguna 0 atau kurang dari jumlah penarikan.
2. Atur permintaan: `POST {{base_url}}/wallet/withdraw`
3. Body: `{"amount": 250000}`
4. Diperkirakan: `422 Validation error` — "Insufficient balance"

#### Pengujian 4: Deposito di Bawah Minimum

1. Atur permintaan: `POST {{base_url}}/wallet/deposit`
2. Body: `{"amount": 5000}`
3. Diperkirakan: `422 Validation error` — "Jumlah deposit minimal 10.000"

#### Pengujian 5: Deposito di Atas Maksimum

1. Atur permintaan: `POST {{base_url}}/wallet/deposit`
2. Body: `{"amount": 200000000}`
3. Diperkirakan: `422 Validation error` — "Jumlah deposit maksimal 100.000.000"

#### Pengujian 6: Penarikan di Bawah Minimum

1. Atur permintaan: `POST {{base_url}}/wallet/withdraw`
2. Body: `{"amount": 10000}`
3. Diperkirakan: `422 Validation error` — "Jumlah penarikan minimal 50.000"

#### Pengujian 7: Akses Tanpa Otentikasi

1. Atur permintaan: `POST {{base_url}}/wallet/deposit` (tanpa header Authorization)
2. Body: `{"amount": 500000}`
3. Diperkirakan: `401 Unauthenticated`

## Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|----|----------|-------|-----------------|
| 1 | Deposito jumlah valid | amount=500000, pengguna terverifikasi, tidak disuspend | 201 + transaksi + saldo diperbarui |
| 2 | Tarik jumlah valid | amount=250000, saldo cukup | 201 + transaksi + saldo diperbarui |
| 3 | Deposito di bawah minimum | amount=5000 | 422 error validasi |
| 4 | Deposito di atas maksimum | amount=200000000 | 422 error validasi |
| 5 | Tarik di bawah minimum | amount=10000 | 422 error validasi |
| 6 | Tarik di atas maksimum | amount=100000000 | 422 error validasi |
| 7 | Tarik dengan saldo tidak mencukupi | balance=0, amount=250000 | 422 error validasi |
| 8 | Deposito sebagai pengguna disuspend | is_suspended=true | 422 error validasi |
| 9 | Tarik sebagai pengguna disuspend | is_suspended=true | 422 error validasi |
| 10 | Deposito tanpa otentikasi | tidak ada token | 401 tidak terotentikasi |
| 11 | Tarik tanpa otentikasi | tidak ada token | 401 tidak terotentikasi |
| 12 | Deposito email tidak terverifikasi | tidak ada email terverifikasi | 403 dilarang |
| 13 | Tarik email tidak terverifikasi | tidak ada email terverifikasi | 403 dilarang |
| 14 | Deposito tepat di minimum | amount=10000 | 201 berhasil |
| 15 | Deposito tepat di maksimum | amount=100000000 | 201 berhasil |
| 16 | Tarik tepat di minimum | amount=50000 | 201 berhasil |
| 17 | Tarik tepat di maksimum | amount=50000000 | 201 berhasil |
| 18 | Periksa notifikasi dibuat | Setelah deposit/penarikan | Notifikasi dalam aplikasi ada |

## Pemecahan Masalah

### 1. "User is currently suspended" (422)

Flag `is_suspended` pengguna diatur ke `true`. Periksa tabel `users` untuk kolom `is_suspended`.

**Perbaikan:** Admin harus mengembalikan pengguna melalui `PUT /api/admin/users/{user}/unsuspend`.

---

### 2. "Insufficient balance" (422)

Jumlah penarikan melebihi saldo yang tersedia pengguna.

**Perbaikan:** Periksa saldo pengguna saat ini melalui `GET /api/me` dan pastikan jumlah penarikan ≤ saldo.

---

### 3. "Jumlah deposit minimal 10.000" (422)

Jumlah deposit di bawah ambang minimum 10.000.

**Perbaikan:** Pastikan `amount >= 10000`.

---

### 4. "Jumlah penarikan minimal 50.000" (422)

Jumlah penarikan di bawah ambang minimum 50.000.

**Perbaikan:** Pastikan `amount >= 50000`.

---

### 5. Verifikasi Email Diperlukan

Permintaan deposit dan penarikan keduanya memerlukan email pengguna diverifikasi (`email_verified_at` tidak boleh null). Metode `authorize()` di `StoreDepositRequest` dan `StoreWithdrawRequest` memeriksa hal ini.

**Perbaikan:** Pastikan pengguna sudah mengeklik tautan verifikasi email sebelum melakukan transaksi dompet.

---

### 6. Transaksi tidak ditemukan di notifikasi

Listener `HandleWalletTransaction` membuat notifikasi dalam aplikasi untuk event `DepositProcessed` dan `WithdrawalProcessed`. Event-event ini terdaftar di `EventServiceProvider`. Pastikan:

1. Transaksi berhasil di-commit
2. Event dipicu (melalui `DB::afterCommit`)
3. Listener dengan benar terdaftar

---

### 7. Error Enum MySQL pada Pembuatan Transaksi

**Masalah Kritis:** Migrasi `transactions` mendefinisikan kolom `type` enum sebagai `['payment', 'refund', 'disbursement', 'platform_fee']` — **hilang** nilai `deposit` dan `withdrawal` (ditambahkan kemudian ke enum `TransactionType` PHP). Di bawah mode strict MySQL, memasukkan `type = 'deposit'` atau `'withdrawal'` akan gagal.

**Perbaikan:** Jalankan migrasi untuk memperbarui nilai enum:
```php
DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('payment','refund','disbursement','platform_fee','deposit','withdrawal')");
```

---

### 8. Event Tidak Terpicu (UserSuspended/UserUnsuspended)

Event `UserSuspended` dan `UserUnsuspended` dipicu di `UserService`, tetapi **TIDAK terdaftar** di `EventServiceProvider`. Karena `shouldDiscoverEvents()` mengembalikan `false`, tidak ada listener yang akan beraksi untuk event-event ini.

**Perbaikan:** Daftarkan di `EventServiceProvider::$listen`:
```php
UserSuspended::class => [...],
UserUnsuspended::class => [...],
```

## Matriks RBAC

| Aksi | Peran | Middleware |
|--------|------|------------|
| Deposito ke dompet | Terotentikasi + Terverifikasi | `auth:sanctum, verified` |
| Tarik dari dompet | Terotentikasi + Terverifikasi | `auth:sanctum, verified` |
| Lihat transaksi | Terotentikasi | `auth:sanctum` |
