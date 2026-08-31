# CoFund API - Modul Mutasi Transaksi & Buku Besar (Transaction Module)

## 1. Judul & Deskripsi Modul

Modul Transaksi mengelola catatan buku besar (*general ledger*) atas seluruh aliran dana yang terjadi di platform CoFund, mencakup pengisian saldo (*deposit*), penarikan saldo (*withdrawal*), pembayaran donasi (*payment*), pengembalian dana (*refund*), pencairan dana kampanye sukses (*disbursement*), dan pemotongan biaya operasional platform (*platform_fee*).

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Controller** | `backend/app/Http/Controllers/Api/TransactionController.php` | Method `index` mutasi transaksi |
| **Service Layer** | `backend/app/Services/TransactionService.php` | Enkapsulasi query buku besar dan pencatatan ledger |
| **Form Request** | `backend/app/Http/Requests/IndexTransactionRequest.php` | Validasi parameter query pencarian & filter |
| **Resource** | `backend/app/Http/Resources/TransactionResource.php` | Transformasi JSON output transaksi |
| **Model** | `backend/app/Models/Transaction.php` | Model transaksi dengan SoftDeletes |
| **Enums** | `backend/app/Enums/TransactionType.php` | `deposit`, `withdrawal`, `payment`, `refund`, `disbursement`, `platform_fee` |
| | `backend/app/Enums/TransactionStatus.php` | `pending`, `success`, `failed` |

### Diagram Alur Pencatatan Buku Besar

```
Aksi Keuangan (Deposit / Backing / Disburse / Refund)
        │
        ▼
[ TransactionService::createTransaction ]
        │
        ├─► Tetapkan Tipe Transaksi (Enum)
        ├─► Generate Kode Referensi Unik (misal: deposit_1725095432_1)
        ├─► Catat Nominal Mutasi & Status
        │
        ▼
Simpan ke Tabel `transactions` dalam DB Transaction
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Enums/
│   │   ├── TransactionStatus.php
│   │   └── TransactionType.php
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── TransactionController.php
│   │   ├── Requests/
│   │   │   └── IndexTransactionRequest.php
│   │   └── Resources/
│   │       └── TransactionResource.php
│   ├── Models/
│   │   └── Transaction.php
│   └── Services/
│       └── TransactionService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: List Riwayat Transaksi (`GET /api/v1/transactions`)
- **Deskripsi:** Mengambil daftar mutasi transaksi milik akun pengguna yang login.
- **Middleware:** `auth:sanctum`

#### Parameter Query
| Parameter | Tipe | Wajib | Nilai yang Diizinkan | Deskripsi |
|---|---|---|---|---|
| `type` | string | Tidak | `deposit`, `withdrawal`, `payment`, `refund`, `disbursement`, `platform_fee` | Filter jenis mutasi |
| `status` | string | Tidak | `pending`, `success`, `failed` | Filter status transaksi |
| `sort` | string | Tidak | `latest`, `oldest` | Pengurutan tanggal |
| `start_date` | date | Tidak | YYYY-MM-DD | Batas awal tanggal |
| `end_date` | date | Tidak | YYYY-MM-DD | Batas akhir tanggal |
| `page` | integer | Tidak | Min 1 | Nomor halaman |
| `per_page` | integer | Tidak | Min 1, Max 50 | Jumlah item per halaman |

#### Contoh Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": 48,
      "type": "payment",
      "amount": 500000,
      "status": "success",
      "reference": "payment_1725091234_1",
      "created_at": "2026-08-31T11:00:00.000000Z"
    },
    {
      "id": 47,
      "type": "deposit",
      "amount": 1000000,
      "status": "success",
      "reference": "deposit_1725090000_1",
      "created_at": "2026-08-31T10:30:00.000000Z"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 2
    }
  }
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### TransactionResource
```json
{
  "id": 48,
  "type": "payment",
  "amount": 500000,
  "status": "success",
  "reference": "payment_1725091234_1",
  "created_at": "2026-08-31T11:00:00.000000Z"
}
```

---

## 6. Pengujian Postman

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Transaction list has pagination", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.meta.pagination).to.exist;
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario | Input | Output yang Diharapkan |
|---|---|---|---|
| 1 | Filter transaksi tipe deposit | `?type=deposit` | `200 OK` + Hanya item tipe deposit |
| 2 | Filter rentang tanggal | `?start_date=2026-08-01&end_date=2026-08-31` | `200 OK` + Mutasi bulan Agustus |
| 3 | Akses tanpa token | No header auth | `401 Unauthorized` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Mutasi transaksi tidak muncul setelah deposit | Transaksi gagal commit ke DB | Periksa log error pada `storage/logs/laravel.log`. |

---

## 9. Matriks RBAC

| Endpoint | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `GET /transactions` | ✗ | ✓ (Militer sendiri) | ✓ (Milik sendiri) | ✓ (Semua) |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-TRX-001` | Get list transaksi valid | Positive | Token login | `200 OK` | Array mutasi user |
| `TC-TRX-002` | Filter tipe invalid | Negative | `?type=unknown` | `422 Unprocessable` | Error "The selected type is invalid." |
| `TC-TRX-003` | Get transaksi tanpa auth | Security | No token | `401 Unauthorized` | Error "Unauthenticated" |
