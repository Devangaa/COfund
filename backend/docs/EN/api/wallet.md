# Wallet Module API

Wallet deposit and withdrawal for authenticated users.

## Architecture

The wallet module enables users to deposit funds into their account balance and withdraw funds from it. All transactions are recorded in the `transactions` table and trigger events that create in-app notifications. Security checks prevent transactions on suspended accounts.

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/WalletController.php` | Handles deposit and withdrawal requests |
| Service | `app/Services/WalletService.php` | Business logic for deposits/withdrawals |
| Requests | `app/Http/Requests/{StoreDepositRequest, StoreWithdrawRequest}.php` | Validation rules per transaction type |
| Enum | `app/Enums/TransactionType.php` | Transaction types (`DEPOSIT`, `WITHDRAWAL`, etc.) |
| Model | `app/Models/Transaction.php` | Transaction record with type/status enums |
| Event | `app/Events/{DepositProcessed, WithdrawalProcessed}.php` | Fired after successful transactions |
| Listener | `app/Listeners/HandleWalletTransaction.php` | Creates in-app notifications for deposits/withdrawals |

### Flow

```
User → StoreDepositRequest/StoreWithdrawRequest → ensureActive() (checks is_suspended)
     → WalletService::deposit()/withdraw() → DB Transaction
     → Create Transaction record
     → Update User balance
     → DB::afterCommit → Fire Event
     → HandleWalletTransaction listener → Create in-app Notification

Key difference from backing payments:
- Deposits/Withdrawals directly manipulate the user balance
- Backing payments deduct backing amount but do not add to balance
- Withdrawals require sufficient balance check
```

## File Structure

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

## Transaction Types

| Type | Enum Value | Direction | Description |
|------|------------|-----------|-------------|
| `PAYMENT` | `payment` | Outbound | Backing payment (campaign funding) |
| `DISBURSEMENT` | `disbursement` | Inbound | Campaign funds released to creator |
| `REFUND` | `refund` | Inbound | Refunded backing amount |
| `PLATFORM_FEE` | `platform_fee` | Outbound | Platform fee deducted from disbursement |
| `DEPOSIT` | `deposit` | Inbound | User deposits funds into wallet |
| `WITHDRAWAL` | `withdrawal` | Outbound | User withdraws funds from wallet |

## Transaction Statuses

| Status | Enum Value | Description |
|--------|------------|-------------|
| `PENDING` | `pending` | Transaction created but not yet finalized |
| `SUCCESS` | `success` | Transaction completed successfully |
| `FAILED` | `failed` | Transaction failed |

## API Endpoints

### 1. Deposit to Wallet

Adds funds to the authenticated user's balance.

**Endpoint:** `POST /api/wallet/deposit`  
**Middleware:** `auth:sanctum` + `verified`  
**Description:** Deposits money into the user's wallet balance.

#### Request Body

| Parameter | Type | Required | Validation | Validation Message (ID) | Description |
|-----------|------|----------|------------|-------------------------|-------------|
| `amount` | decimal | Yes | `required, numeric, min:10000, max:100000000` | "Jumlah deposit minimal 10.000" / "Jumlah deposit maksimal 100.000.000" | Deposit amount (10k min, 100M max) |

#### Example Request

```json
{
  "amount": 500000
}
```

#### Response (Success: 201)

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

#### Side Effects

- Checks if user is suspended (`ValidationException` if `is_suspended = true`)
- Creates `Transaction` record (type=deposit, status=success)
- Increments `User.balance`
- Fires `DepositProcessed` event after commit
- `HandleWalletTransaction::handleDeposit` listener creates in-app `Notification`

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 422 | "User is currently suspended" | `is_suspended = true` |
| 422 | "Jumlah deposit minimal 10.000" | Amount < 10,000 |
| 422 | "Jumlah deposit maksimal 100.000.000" | Amount > 100,000,000 |

---

### 2. Withdraw from Wallet

Deducts funds from the authenticated user's balance.

**Endpoint:** `POST /api/wallet/withdraw`  
**Middleware:** `auth:sanctum` + `verified`  
**Description:** Withdraws money from the user's wallet balance.

#### Request Body

| Parameter | Type | Required | Validation | Validation Message (ID) | Description |
|-----------|------|----------|------------|-------------------------|-------------|
| `amount` | decimal | Yes | `required, numeric, min:50000, max:50000000` | "Jumlah penarikan minimal 50.000" / "Jumlah penarikan maksimal 50.000.000" | Withdrawal amount (50k min, 50M max) |

#### Example Request

```json
{
  "amount": 250000
}
```

#### Response (Success: 201)

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

#### Side Effects

- Checks if user is suspended (`ValidationException` if `is_suspended = true`)
- Checks if balance is sufficient (`ValidationException` if `balance < amount`)
- Creates `Transaction` record (type=withdrawal, status=success)
- Decrements `User.balance`
- Fires `WithdrawalProcessed` event after commit
- `HandleWalletTransaction::handleWithdrawal` listener creates in-app `Notification`

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 422 | "User is currently suspended" | `is_suspended = true` |
| 422 | "Insufficient balance" | `balance < amount` |
| 422 | "Jumlah penarikan minimal 50.000" | Amount < 50,000 |
| 422 | "Jumlah penarikan maksimal 50.000.000" | Amount > 50,000,000 |

## Transaction Resource Schema

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

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Transaction ID |
| `type` | enum | One of `payment`, `disbursement`, `refund`, `platform_fee`, `deposit`, `withdrawal` |
| `amount` | decimal | Transaction amount |
| `status` | enum | One of `pending`, `success`, `failed` |
| `reference` | string\|null | Transaction reference (e.g., `deposit_20260826_abc123`) |
| `backing_id` | integer\|null | Backing ID (if related) |
| `campaign_id` | integer\|null | Campaign ID (if related) |
| `created_at` | datetime | Transaction timestamp |

## Wallet Flow Diagram

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

## Business Rules

### 1. Deposit Limits

| Constraint | Minimum | Maximum |
|------------|---------|---------|
| Amount | 10,000 | 100,000,000 (100M) |

These limits prevent micro-transactions and abuse.

### 2. Withdrawal Limits

| Constraint | Minimum | Maximum |
|------------|---------|---------|
| Amount | 50,000 | 50,000,000 (50M) |

### 3. Suspended Account Check

Both deposit and withdrawal operations check if the user is suspended (`is_suspended = true`). If suspended, a `ValidationException` is thrown with the message "User is currently suspended".

### 4. Insufficient Balance

Withdrawal operations check if the user's balance is sufficient. If `balance < amount`, a `ValidationException` is thrown with the message "Insufficient balance".

### 5. Email Verification Required

Both `StoreDepositRequest` and `StoreWithdrawRequest` have `authorize()` methods that check if the user's email is verified. Unverified users cannot deposit or withdraw.

### 6. Transaction Atomicity

All wallet operations use `DB::transaction()` and fire events via `DB::afterCommit()` to ensure consistency. If the transaction fails, no events are fired and the balance remains unchanged.

## Postman Testing

### Test Scripts (Wallet)

#### Test 1: Deposit to Wallet

1. Set request: `POST {{base_url}}/wallet/deposit`
2. Headers: `Authorization: Bearer {{backer_token}}`
3. Body (raw JSON):
   ```json
   {
     "amount": 500000
   }
   ```
4. Expected: `201 Created` with transaction record + updated balance.

#### Test 2: Withdraw from Wallet

1. Set request: `POST {{base_url}}/wallet/withdraw`
2. Headers: `Authorization: Bearer {{backer_token}}`
3. Body (raw JSON):
   ```json
   {
     "amount": 250000
   }
   ```
4. Expected: `201 Created` with transaction record + updated balance.

#### Test 3: Insufficient Balance

1. Ensure user balance is 0 or less than withdrawal amount.
2. Set request: `POST {{base_url}}/wallet/withdraw`
3. Body: `{"amount": 250000}`
4. Expected: `422 Validation error` — "Insufficient balance"

#### Test 4: Deposit Below Minimum

1. Set request: `POST {{base_url}}/wallet/deposit`
2. Body: `{"amount": 5000}`
3. Expected: `422 Validation error` — "Jumlah deposit minimal 10.000"

#### Test 5: Deposit Above Maximum

1. Set request: `POST {{base_url}}/wallet/deposit`
2. Body: `{"amount": 200000000}`
3. Expected: `422 Validation error` — "Jumlah deposit maksimal 100.000.000"

#### Test 6: Withdraw Below Minimum

1. Set request: `POST {{base_url}}/wallet/withdraw`
2. Body: `{"amount": 10000}`
3. Expected: `422 Validation error` — "Jumlah penarikan minimal 50.000"

#### Test 7: Unauthenticated Access

1. Set request: `POST {{base_url}}/wallet/deposit` (no Authorization header)
2. Body: `{"amount": 500000}`
3. Expected: `401 Unauthenticated`

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | Deposit valid amount | amount=500000, verified user, not suspended | 201 + transaction + balance updated |
| 2 | Withdraw valid amount | amount=250000, sufficient balance | 201 + transaction + balance updated |
| 3 | Deposit below minimum | amount=5000 | 422 validation error |
| 4 | Deposit above maximum | amount=200000000 | 422 validation error |
| 5 | Withdraw below minimum | amount=10000 | 422 validation error |
| 6 | Withdraw above maximum | amount=100000000 | 422 validation error |
| 7 | Withdraw with insufficient balance | balance=0, amount=250000 | 422 validation error |
| 8 | Deposit as suspended user | is_suspended=true | 422 validation error |
| 9 | Withdraw as suspended user | is_suspended=true | 422 validation error |
| 10 | Deposit unauthenticated | no token | 401 unauthenticated |
| 11 | Withdraw unauthenticated | no token | 401 unauthenticated |
| 12 | Deposit unverified email | no verified email | 403 forbidden |
| 13 | Withdraw unverified email | no verified email | 403 forbidden |
| 14 | Deposit exact minimum | amount=10000 | 201 created |
| 15 | Deposit exact maximum | amount=100000000 | 201 created |
| 16 | Withdraw exact minimum | amount=50000 | 201 created |
| 17 | Withdraw exact maximum | amount=50000000 | 201 created |
| 18 | Check notification created | After deposit/withdraw | In-app notification exists |

## Troubleshooting

### 1. "User is currently suspended" (422)

The user's `is_suspended` flag is set to `true`. Check the `users` table for the `is_suspended` column.

**Fix:** Admin must unsuspend the user via `PUT /api/admin/users/{user}/unsuspend`.

---

### 2. "Insufficient balance" (422)

The withdrawal amount exceeds the user's available balance.

**Fix:** Check the user's current balance via `GET /api/me` and ensure the withdrawal amount ≤ balance.

---

### 3. "Jumlah deposit minimal 10.000" (422)

The deposit amount is below the minimum threshold of 10,000.

**Fix:** Ensure `amount >= 10000`.

---

### 4. "Jumlah penarikan minimal 50.000" (422)

The withdrawal amount is below the minimum threshold of 50,000.

**Fix:** Ensure `amount >= 50000`.

---

### 5. Email verification required

Both deposit and withdrawal requests require the user's email to be verified (`email_verified_at` must not be null). The `authorize()` method in both `StoreDepositRequest` and `StoreWithdrawRequest` checks this.

**Fix:** Ensure the user has clicked the email verification link before attempting wallet transactions.

---

### 6. Transaction not found in notifications

The `HandleWalletTransaction` listener creates in-app notifications for `DepositProcessed` and `WithdrawalProcessed` events. These events are registered in `EventServiceProvider`. Ensure:

1. The transaction was committed successfully
2. The event was fired (via `DB::afterCommit`)
3. The listener is properly registered

---

### 7. MySQL enum error on transaction creation

**Critical issue:** The `transactions` migration defines the `type` column enum as `['payment', 'refund', 'disbursement', 'platform_fee']` — it is **missing** `deposit` and `withdrawal` values (added later to the `TransactionType` enum). Under MySQL strict mode, inserting `type = 'deposit'` or `'withdrawal'` will fail.

**Fix:** Run a migration to update the enum values:
```php
DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('payment','refund','disbursement','platform_fee','deposit','withdrawal')");
```

---

### 8. Event not firing (UserSuspended/UserUnsuspended)

The `UserSuspended` and `UserUnsuspended` events are dispatched in `UserService`, but are **NOT registered** in `EventServiceProvider`. Since `shouldDiscoverEvents()` returns `false`, no listeners will fire for these events.

**Fix:** Register them in `EventServiceProvider::$listen`:
```php
UserSuspended::class => [...],
UserUnsuspended::class => [...],
```

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| Deposit to wallet | Authenticated + Verified | `auth:sanctum, verified` |
| Withdraw from wallet | Authenticated + Verified | `auth:sanctum, verified` |
| View transactions | Authenticated | `auth:sanctum` |
