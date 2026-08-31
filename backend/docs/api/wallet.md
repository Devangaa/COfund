# CoFund API - Modul Dompet Digital & Virtual Escrow (Wallet Module)

## 1. Judul & Deskripsi Modul

Modul Dompet Digital mengelola akun saldo virtual pengguna, meliputi transaksi pengisian saldo instan (*top-up / deposit*), penarikan saldo ke rekening bank (*withdrawal*), serta pencatatan mutasi transaksi dan aturan operasional penahanan dana (*holding*), pencairan dana sukses (*95% disbursement* + *5% platform fee*), dan pengembalian saldo otomatis (*100% auto-refund*) melalui sistem **Virtual Escrow**.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/WalletController.php` | Endpoint `deposit` dan `withdraw` |
| **Service Layer** | `backend/app/Services/WalletService.php` | Enkapsulasi logika debit/kredit saldo & validasi suspended |
| | `backend/app/Services/TransactionService.php` | Pencatatan buku besar mutasi transaksi |
| **Form Requests** | `backend/app/Http/Requests/StoreDepositRequest.php` | Validasi nominal deposit |
| | `backend/app/Http/Requests/StoreWithdrawRequest.php` | Validasi nominal withdraw |
| **Resource** | `backend/app/Http/Resources/TransactionResource.php` | Format JSON output transaksi |
| **Model** | `backend/app/Models/User.php` | Method `deposit($amount)` dan `withdraw($amount)` |
| | `backend/app/Models/Transaction.php` | Model buku besar transaksi |
| **Jobs** | `backend/app/Jobs/DisburseCampaignJob.php` | Job pencairan 95% dana ke kreator |
| | `backend/app/Jobs/RefundBackersJob.php` | Job pengembalian 100% saldo ke donatur |
| **Events** | `backend/app/Events/DepositProcessed.php` | Event deposit berhasil |
| | `backend/app/Events/WithdrawalProcessed.php` | Event penarikan berhasil |

### Diagram Alur Siklus Dana Virtual Escrow

```
[ Backer ] ──► (Deposit Saldo) ──► Saldo Masuk ke User (balance)
                                            │
                                            ▼
[ Backer ] ──► (Backing Kampanye) ──────────┘
                      │
                      ▼
        [ Virtual Escrow Holding ]
                      │
                      ├─► Kampanye SUKSES (Deadline / Target Tercapai)
                      │         │
                      │         ├─► 95% Dana ──► [ Saldo Creator ] (Disbursement)
                      │         └─►  5% Dana ──► [ Platform Fee Revenue ]
                      │
                      └─► Kampanye GAGAL / Force-Fail
                                │
                                └─► 100% Dana ──► [ Refund ke Saldo Backer ]
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Events/
│   │   ├── DepositProcessed.php
│   │   └── WithdrawalProcessed.php
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── WalletController.php
│   │   ├── Requests/
│   │   │   ├── StoreDepositRequest.php
│   │   │   └── StoreWithdrawRequest.php
│   │   └── Resources/
│   │       └── TransactionResource.php
│   ├── Jobs/
│   │   ├── DisburseCampaignJob.php
│   │   └── RefundBackersJob.php
│   ├── Models/
│   │   ├── Transaction.php
│   │   └── User.php
│   └── Services/
│       ├── TransactionService.php
│       └── WalletService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Deposit Saldo Dompet (`POST /api/v1/wallet/deposit`)
- **Deskripsi:** Menambahkan saldo virtual dompet secara instan (tersedia untuk semua pengguna terotentikasi tanpa terhalang verifikasi email).
- **Middleware:** `auth:sanctum`

#### Tabel Parameter Body
| Field | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `amount` | numeric | Ya | `required, numeric, min:10000, max:100000000` | Nominal deposit (Rp 10.000 - Rp 100.000.000) |

#### Contoh Request:
```json
{
  "amount": 500000
}
```

#### Contoh Response (`201 Created`):
```json
{
  "success": true,
  "message": "Deposit successful",
  "data": {
    "id": 50,
    "type": "deposit",
    "amount": 500000,
    "status": "success",
    "reference": "deposit_1725095432_1",
    "created_at": "2026-08-31T10:30:00.000000Z"
  }
}
```

---

### 4.2 Endpoint: Penarikan Saldo Dompet (`POST /api/v1/wallet/withdraw`)
- **Deskripsi:** Menarik saldo virtual dompet yang tersedia.
- **Middleware:** `auth:sanctum`

#### Contoh Request:
```json
{
  "amount": 250000
}
```

#### Contoh Response (`201 Created`):
```json
{
  "success": true,
  "message": "Withdrawal successful",
  "data": {
    "id": 51,
    "type": "withdrawal",
    "amount": 250000,
    "status": "success",
    "reference": "withdrawal_1725095500_1",
    "created_at": "2026-08-31T10:32:00.000000Z"
  }
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### TransactionResource
```json
{
  "id": 50,
  "type": "deposit",
  "amount": 500000,
  "status": "success",
  "reference": "deposit_1725095432_1",
  "created_at": "2026-08-31T10:30:00.000000Z"
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Deposit created transaction successfully", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.data.type).to.eql("deposit");
    pm.expect(jsonData.data.amount).to.eql(500000);
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Deposit nominal valid | `amount: 100000` | `201 Created` + Saldo bertambah |
| 2 | Deposit di bawah minimum | `amount: 5000` | `422 Unprocessable Content` |
| 3 | Withdraw saldo mencukupi | `amount: 50000` (Saldo 100.000) | `201 Created` + Saldo berkurang |
| 4 | Withdraw melebihi saldo | `amount: 200000` (Saldo 100.000) | `422 Unprocessable Content` |
| 5 | Deposit pada akun suspended | Akun suspended | `403 Forbidden` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| `Insufficient balance` | Saldo pengguna lebih kecil dari nominal penarikan | Pastikan saldo virtual mencukupi sebelum melakukan penarikan. |
| Dana tidak kembali setelah kampanye gagal | Queue worker belum berjalan | Jalankan `php artisan queue:work` pada terminal server. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /wallet/deposit` | ✗ | ✓ | ✓ | ✓ |
| `POST /wallet/withdraw` | ✗ | ✓ | ✓ | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-WAL-DEP-001` | Deposit normal | Positive | `amount: 100000` | `201 Created` | Transaksi `deposit` sukses |
| `TC-WAL-DEP-002` | Deposit < 10.000 | Negative | `amount: 9000` | `422 Unprocessable` | Error "The amount must be at least 10000." |
| `TC-WAL-WIT-001` | Withdraw normal | Positive | `amount: 50000` | `201 Created` | Transaksi `withdrawal` sukses |
| `TC-WAL-WIT-002` | Withdraw saldo kurang | Negative | `amount: 99999999` | `422 Unprocessable` | Error "Insufficient balance" |
| `TC-WAL-WIT-003` | Withdraw tanpa auth | Security | No token | `401 Unauthorized` | Error "Unauthenticated" |
