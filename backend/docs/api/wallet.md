# CoFund API - Modul Dompet / Wallet (Wallet Module)

## 1. Judul & Deskripsi Modul

Modul dompet memungkinkan pengguna untuk melakukan deposit (menambah saldo) dan withdrawal (menarik saldo) dari dompet digital pribadi. Setiap transaksi dicatat di tabel `transactions` dengan tipe `deposit` atau `withdrawal`. Transaksi memicu event yang menghasilkan notifikasi in-app dan email.

**Base Path:** `/api/v1`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi | Deskripsi |
|---|---|---|
| **Controller** | `app/Http/Controllers/Api/WalletController.php` | Endpoint deposit dan withdrawal |
| **Service** | `app/Services/WalletService.php` | Logika bisnis deposit, withdrawal, pengecekan saldo, pengecekan status akun |
| **Form Request** | `app/Http/Requests/StoreDepositRequest.php` | Validasi deposit |
| | `app/Http/Requests/StoreWithdrawRequest.php` | Validasi withdrawal |
| **Resource** | `app/Http/Resources/TransactionResource.php` | Serialisasi data transaksi |
| **Model** | `app/Models/User.php` | Model User dengan method `deposit()`, `withdraw()` |
| | `app/Models/Transaction.php` | Model Transaction |
| **Enums** | `app/Enums/TransactionType.php` | `deposit`, `withdrawal` |
| | `app/Enums/TransactionStatus.php` | `pending`, `success`, `failed` |
| **Event** | `app/Events/DepositProcessed.php` | Event deposit berhasil |
| | `app/Events/WithdrawalProcessed.php` | Event withdrawal berhasil |
| **Listener** | `app/Listeners/HandleWalletTransaction.php` | Membuat notifikasi untuk deposit & withdrawal |
| **Middleware** | `auth:sanctum`, `verified` | Otentikasi dan verifikasi email |

### Alur Proses Logika Bisnis

```
User pilih deposit/withdraw
        |
        v
WalletController::deposit() / withdraw()
        |
        v
StoreDepositRequest / StoreWithdrawRequest
        |
        v
WalletService::deposit() / withdraw()
        |
        +---> Ensure user not suspended
        |
        +---> (withdraw only) Check balance >= amount
        |
        v
    DB::transaction()
        |
        +---> User::deposit() / User::withdraw()
        |
        +---> Create Transaction
        |
        +---> DB::afterCommit -> event(DepositProcessed/WithdrawalProcessed)
        |
        v
Return TransactionResource + HTTP 201

Event: DepositProcessed / WithdrawalProcessed
        |
        v
HandleWalletTransaction::handleDeposit() / handleWithdrawal()
        |
        v
Notification::create() [in-app notification]
```

---

## 3. Struktur File

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── WalletController.php
│   │   ├── Requests/
│   │   │   ├── StoreDepositRequest.php
│   │   │   └── StoreWithdrawRequest.php
│   │   └── Resources/
│   │       └── TransactionResource.php
│   ├── Models/
│   │   ├── User.php
│   │   └── Transaction.php
│   ├── Enums/
│   │   ├── TransactionType.php
│   │   └── TransactionStatus.php
│   ├── Events/
│   │   ├── DepositProcessed.php
│   │   └── WithdrawalProcessed.php
│   ├── Listeners/
│   │   └── HandleWalletTransaction.php
│   └── Services/
│       └── WalletService.php
└── routes/
    └── api.php
```

---

## 4. API Endpoints

### 4.1 Endpoint: Deposit

- **Deskripsi:** Menambahkan saldo ke dompet pengguna. Transaksi tercatat dan memicu notifikasi.
- **HTTP Method & URL Path:** `POST /api/v1/wallet/deposit`
- **Middleware:** `auth:sanctum`, `verified`

#### Tabel Parameter (Body)

| Nama | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|
| `amount` | numeric | Ya | `min:10000`, `max:100000000` | Jumlah deposit (Rp10.000 - Rp100.000.000) |

#### Contoh Request Payload

```json
{
    "amount": 500000
}
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "message": "Deposit successful",
    "data": {
        "id": 15,
        "type": "deposit",
        "amount": 500000,
        "status": "success",
        "reference": "deposit_1706789012_1",
        "backing_id": null,
        "campaign_id": null,
        "created_at": "2024-02-01T10:30:00Z"
    }
}
```

#### Efek Samping

- Saldo pengguna bertambah sesuai jumlah deposit
- Membuat entri transaksi di tabel `transactions`
- Trigger event `DepositProcessed`
- Membuat notifikasi in-app untuk pengguna

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 403 | `{"success":false,"message":"The given data was invalid.","errors":{"user":["Account is suspended"]}}` | Akun pengguna disuspended |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"amount":["Minimum deposit is Rp10,000"]}}` | Jumlah kurang dari minimum |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"amount":["Maximum deposit per transaction is Rp100,000,000"]}}` | Jumlah melebihi maksimum |

---

### 4.2 Endpoint: Withdrawal

- **Deskripsi:** Menarik saldo dari dompet pengguna. Hanya dapat melakukan withdrawal jika saldo mencukupi.
- **HTTP Method & URL Path:** `POST /api/v1/wallet/withdraw`
- **Middleware:** `auth:sanctum`, `verified`

#### Tabel Parameter (Body)

| Nama | Tipe | Wajib | Aturan Validasi Laravel | Deskripsi |
|---|---|---|---|---|
| `amount` | numeric | Ya | `min:50000`, `max:50000000` | Jumlah withdrawal (Rp50.000 - Rp50.000.000) |

#### Contoh Request Payload

```json
{
    "amount": 250000
}
```

#### Contoh Response (HTTP 201)

```json
{
    "success": true,
    "message": "Withdrawal successful",
    "data": {
        "id": 16,
        "type": "withdrawal",
        "amount": 250000,
        "status": "success",
        "reference": "withdrawal_1706789100_1",
        "backing_id": null,
        "campaign_id": null,
        "created_at": "2024-02-01T10:30:00Z"
    }
}
```

#### Efek Samping

- Saldo pengguna berkurang sesuai jumlah withdrawal
- Membuat entri transaksi di tabel `transactions`
- Trigger event `WithdrawalProcessed`
- Membuat notifikasi in-app untuk pengguna

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 403 | `{"success":false,"message":"The given data was invalid.","errors":{"user":["Account is suspended"]}}` | Akun pengguna disuspended |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"amount":["Insufficient balance"]}}` | Saldo tidak mencukupi |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"amount":["Minimum withdrawal is Rp50,000"]}}` | Jumlah kurang dari minimum |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"amount":["Maximum withdrawal per transaction is Rp50,000,000"]}}` | Jumlah melebihi maksimum |

---

### 4.3 Endpoint: List Transactions

- **Deskripsi:** Mendapatkan riwayat transaksi pengguna. Admin dapat memfilter berdasarkan `user_id`.
- **HTTP Method & URL Path:** `GET /api/v1/transactions`
- **Middleware:** `auth:sanctum`, `verified`

#### Tabel Parameter (Query)

| Nama | Tipe | Wajib | Aturan Validasi | Deskripsi |
|---|---|---|---|---|
| `type` | string | Tidak | `in:payment,refund,disbursement,platform_fee,deposit,withdrawal` | Filter tipe transaksi |
| `status` | string | Tidak | `in:pending,success,failed` | Filter status |
| `start_date` | date | Tidak | - | Filter tanggal minimum |
| `end_date` | date | Tidak | - | Filter tanggal maksimum |
| `user_id` | integer | Tidak (admin only) | `exists:users,id` | Filter berdasarkan user (hanya admin) |
| `sort` | string | Tidak | `latest`, `oldest` | Urutkan |
| `per_page` | integer | Tidak | `min:1`, `max:50` | Item per halaman (default: 10, maksimal: 50) |

#### Contoh Request

```
GET /api/v1/transactions?type=deposit&sort=latest&per_page=10
Authorization: Bearer {token}
```

#### Contoh Response (HTTP 200)

```json
{
    "success": true,
    "data": [
        {
            "id": 16,
            "type": "withdrawal",
            "amount": 250000,
            "status": "success",
            "reference": "withdrawal_1706789100_1",
            "backing_id": null,
            "campaign_id": null,
            "created_at": "2024-02-01T10:30:00Z"
        },
        {
            "id": 15,
            "type": "deposit",
            "amount": 500000,
            "status": "success",
            "reference": "deposit_1706789012_1",
            "backing_id": null,
            "campaign_id": null,
            "created_at": "2024-02-01T10:25:00Z"
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

- Backer hanya melihat transaksinya sendiri
- Admin dapat melihat transaksi semua pengguna (dengan filter `user_id`)

#### Error Handling

| Kode HTTP | Pesan Error JSON | Kondisi Pemicu |
|---|---|---|
| 401 | `{"success":false,"message":"Unauthenticated."}` | Token tidak valid |
| 422 | `{"success":false,"message":"The given data was invalid.","errors":{"user_id":["The user_id field is prohibited."]}}}` | Backer mencoba filter `user_id` |

---

## 5. Skema Sumber Daya (Resource Schema)

### TransactionResource

```json
{
    "id": 15,
    "type": "deposit",
    "amount": 500000,
    "status": "success",
    "reference": "deposit_1706789012_1",
    "backing_id": null,
    "campaign_id": null,
    "created_at": "2024-02-01T10:30:00Z"
}
```

| Kolom | Tipe | Deskripsi |
|---|---|---|
| `id` | integer | Primary key transaksi |
| `type` | enum | `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal` |
| `amount` | decimal | Jumlah transaksi |
| `status` | enum | `pending`, `success`, `failed` |
| `reference` | string | Nomor referensi unik |
| `backing_id` | integer\|null | ID backing (jika berkaitan dengan backing) |
| `campaign_id` | integer\|null | ID kampanye (jika berkaitan dengan kampanye) |
| `created_at` | datetime | Timestamp transaksi |

---

## 6. Pengujian Postman

### Deposit

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/wallet/deposit`
3. Headers: `Authorization: Bearer {{auth_token}}`, `Content-Type: application/json`
4. Body (raw JSON):

```json
{
    "amount": 500000
}
```

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Deposit recorded", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.data.type).to.eql("deposit");
});
```

### Withdrawal

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/wallet/withdraw`
3. Headers: `Authorization: Bearer {{auth_token}}`, `Content-Type: text/plain`
4. Body (raw JSON):

```json
{
    "amount": 250000
}
```

**Tests Script:**

```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});
pm.test("Withdrawal recorded", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.data.type).to.eql("withdrawal");
});
```

### Insufficient Balance

1. Method: `POST`
2. URL: `{{base_url}}/api/v1/wallet/withdraw`
3. Headers: `Authorization: Bearer {{auth_token}}`
4. Body:

```json
{
    "amount": 10000000
}
```

**Tests Script:**

```javascript
pm.test("Status code is 422", function () {
    pm.response.to.have.status(422);
});
pm.test("Insufficient balance error", function () {
    pm.expect(pm.response.json().errors.amount).to.include("Insufficient balance");
});
```

---

## 7. Kasus Pengujian

| No | Skenario | Input | Keluaran yang Diperkirakan |
|---|---|---|---|
| 1 | Deposit dengan jumlah valid | `{"amount": 500000}` | HTTP 201, saldo bertambah |
| 2 | Deposit di bawah minimum (10.000) | `{"amount": 5000}` | HTTP 422, "Minimum deposit is Rp10,000" |
| 3 | Deposit di atas maksimum (100.000.000) | `{"amount": 150000000}` | HTTP 422, "Maximum deposit per transaction is Rp100.000.000" |
| 4 | Withdrawal dengan saldo cukup | `{"amount": 250000}` | HTTP 201, saldo berkurang |
| 5 | Withdrawal di bawah minimum (50.000) | `{"amount": 10000}` | HTTP 422, "Minimum withdrawal is Rp50.000" |
| 6 | Withdrawal di atas maksimum (50.000.000) | `{"amount": 75000000}` | HTTP 422, "Maximum withdrawal per transaction is Rp50.000.000" |
| 7 | Withdrawal melebihi saldo | `{"amount": 99999999}` | HTTP 422, "Insufficient balance" |
| 8 | Deposit/Withdrawal dengan akun disuspended | `{"amount": 500000}` | HTTP 422, "Account is suspended" |
| 9 | List transaksi deposit | `GET /transactions?type=deposit` | Hanya transaksi type deposit |
| 10 | Admin filter transaksi user lain | `GET /transactions?user_id=5` | Menampilkan transaksi user ID 5 |
| 11 | Backer filter transaksi user lain | `GET /transactions?user_id=5` | HTTP 422, "field is prohibited" |

---

## 8. Pemecahan Masalah

| Masalah | Solusi / Workaround |
|---|---|
| Deposit gagal dengan "Account is suspended" | Hubungi admin untuk unsuspend akun. |
| Withdrawal gagal dengan "Insufficient balance" | Lakukan deposit terlebih dahulu untuk menambah saldo. |
| Transaksi tidak muncul di list | Pastikan transaksi berhasil dibuat. Cek status di database. |
| Saldo tidak bertambah setelah deposit | Pastikan transaksi `status=success`. Transaksi dalam DB transaction, cek log error. |
| Notifikasi tidak muncul | Pastikan queue worker berjalan. Notifikasi dibuat oleh event listener yang berjalan setelah `DB::afterCommit`. |

---

## 9. Matriks RBAC

| Endpoint | Public | Backer | Creator | Admin |
|---|---|---|---|---|
| `POST /api/v1/wallet/deposit` | - | ✓ | ✓ | ✓ |
| `POST /api/v1/wallet/withdraw` | - | ✓ | ✓ | ✓ |
| `GET /api/v1/transactions` | - | ✓ (own) | ✓ (own) | ✓ (all, with filter) |

---

## 10. Matriks Kasus Pengujian (Test Case)

### 10.1 Deposit

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-WALLET-DEP-001` | Deposit dengan amount valid | Positive | `amount: 500000` | `201 Created` | Balance bertambah, transaksi `deposit` tercatat |
| `TC-WALLET-DEP-002` | Deposit 10000 (minimal) | Positive | `amount: 10000` | `201 Created` | Transaksi berhasil |
| `TC-WALLET-DEP-003` | Deposit 100000000 (maksimal) | Positive | `amount: 100000000` | `201 Created` | Transaksi berhasil |
| `TC-WALLET-DEP-004` | Deposit 9999 (di bawah minimum) | Negative | `amount: 9999` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-WALLET-DEP-005` | Deposit 100000001 (di atas maksimal) | Negative | `amount: 100000001` | `422 Unprocessable` | Error "Amount may not be greater than 100000000" |
| `TC-WALLET-DEP-006` | Deposit dengan amount negatif | Negative | `amount: -5000` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-WALLET-DEP-007` | Deposit dengan amount 0 | Negative | `amount: 0` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-WALLET-DEP-008` | Deposit tanpa amount | Negative | `amount: null` | `422 Unprocessable` | Error "The amount field is required" |
| `TC-WALLET-DEP-009` | Deposit dengan tipe data string | Negative | `amount: "abc"` | `422 Unprocessable` | Error tipe data numeric |
| `TC-WALLET-DEP-010` | Deposit dengan integer di amount | Positive | `amount: 100000` (integer) | `201 Created` | Diterima dan diproses |
| `TC-WALLET-DEP-011` | Deposit dengan decimal | Positive | `amount: 50000.50` | `201 Created` | Diterima dan diproses |
| `TC-WALLET-DEP-012` | Deposit tanpa token | Security | No Authorization | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-WALLET-DEP-013` | Deposit dengan email belum terverifikasi | Security | Email belum verified | `403 Forbidden` | Error "Email verification required" |

### 10.2 Withdraw

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-WALLET-WITH-001` | Withdraw dengan balance mencukupi | Positive | `amount: 50000`, balance `>= 50000` | `201 Created` | Balance berkurang, transaksi `withdrawal` tercatat |
| `TC-WALLET-WITH-002` | Withdraw 10000 (minimal) | Positive | `amount: 10000` | `201 Created` | Transaksi berhasil |
| `TC-WALLET-WITH-003` | Withdraw melebihi balance | Business Logic | `amount > balance` | `409 Conflict` | Error "Insufficient balance" |
| `TC-WALLET-WITH-004` | Withdraw dengan amount negatif | Negative | `amount: -5000` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-WALLET-WITH-005` | Withdraw dengan amount 0 | Negative | `amount: 0` | `422 Unprocessable` | Error "Amount is required" |
| `TC-WALLET-WITH-006` | Withdraw tanpa amount | Negative | `amount: null` | `422 Unprocessable` | Error "The amount field is required" |
| `TC-WALLET-WITH-007` | Withdraw dengan tipe data salah | Negative | `amount: "string"` | `422 Unprocessable` | Error tipe data |
| `TC-WALLET-WITH-008` | Withdraw dengan amount di bawah minimum | Negative | `amount: 500` | `422 Unprocessable` | Error "Amount must be at least 10000" |
| `TC-WALLET-WITH-009` | Withdraw tanpa token | Security | No Authorization | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-WALLET-WITH-010` | Withdraw dengan email belum terverifikasi | Security | Email belum verified | `403 Forbidden` | Error "Email verification required" |
| `TC-WALLET-WITH-011` | Spam withdraw 20x | Throttling | Rapid requests | `429 Too Many Requests` | Rate limited |

### 10.3 List Transactions

| Test ID | Skenario Pengujian | Kategori | Input / Kondisi | Expected HTTP Code | Expected Respon / Side Effect |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TC-WALLET-TRANS-001` | List transaksi sendiri | Positive | Auth user | `200 OK` | Semua transaksi user, meta pagination |
| `TC-WALLET-TRANS-002` | List transaksi dengan filter type | Positive | `?type=deposit` | `200 OK` | Hanya transaksi deposit |
| `TC-WALLET-TRANS-003` | List transaksi dengan sort | Positive | `?sort=latest` | `200 OK` | Diurutkan terbaru ke terlama |
| `TC-WALLET-TRANS-004` | List transaksi dengan date range | Positive | `?start_date=2024-01-01&end_date=2024-12-31` | `200 OK` | Transaksi dalam rentang tanggal |
| `TC-WALLET-TRANS-005` | Admin filter user_id lain | Positive | Admin login + `?user_id=5` | `200 OK` | Transaksi user ke-5 |
| `TC-WALLET-TRANS-006` | Backer filter user_id orang lain | Security | Backer + `?user_id=5` | `422 Unprocessable` | Error "user_id field is prohibited" |
| `TC-WALLET-TRANS-007` | List transaksi dengan type invalid | Negative | `?type=invalid` | `422 Unprocessable` | Error enum tidak valid |
| `TC-WALLET-TRANS-008` | List transaksi dengan per_page > 50 | Positive | `?per_page=999` | `200 OK` | Dibatasi ke 50 |
| `TC-WALLET-TRANS-009` | List transaksi tanpa token | Security | No Authorization | `401 Unauthorized` | Error "Unauthenticated" |
| `TC-WALLET-TRANS-010` | List transaksi dengan date range tidak valid | Negative | `?start_date=2024-12-31&end_date=2024-01-01` | `422 Unprocessable` | Error "end_date must be after start_date" |
