# Transaction Module API

Read-only transaction history for authenticated users.

## Architecture

The transaction module provides endpoints for viewing all transactions associated with a user's account. Transactions are created automatically by the system (backings, disbursements, refunds, fees, deposits, withdrawals) and are read-only from the user perspective.

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/TransactionController.php` | Lists user transactions |
| Model | `app/Models/Transaction.php` | Transaction entity |
| Enum | `app/Enums/TransactionType.php`, `TransactionStatus.php` | Type and status enums |
| Resource | `app/Http/Resources/TransactionResource.php` | JSON response formatting |
| Services | `app/Services/WalletService.php`, `app/Services/BackingService.php`, `app/Services/TransactionService.php` | Create transactions for various operations |

### Flow

```
User → GET /api/transactions
     → Auth::user()
     → User->transactions (hasMany)
     → Filter by type / sort
     → Paginate
     → TransactionResource collection
```

## File Structure

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

## Transaction Types

| Type | Enum Value | Direction | Trigger |
|------|------------|-----------|---------|
| `PAYMENT` | `payment` | Outbound | Backing created |
| `DISBURSEMENT` | `disbursement` | Inbound | Campaign successfully funded |
| `REFUND` | `refund` | Inbound | Campaign failed, backers refunded |
| `PLATFORM_FEE` | `platform_fee` | Outbound | Platform fee (5%) deducted |
| `DEPOSIT` | `deposit` | Inbound | User deposits to wallet |
| `WITHDRAWAL` | `withdrawal` | Outbound | User withdraws from wallet |

## Transaction Statuses

| Status | Enum Value | Description |
|--------|------------|-------------|
| `PENDING` | `pending` | Transaction created but not finalized |
| `SUCCESS` | `success` | Transaction completed |
| `FAILED` | `failed` | Transaction failed |

## API Endpoints

### 1. List Transactions

Returns a paginated list of transactions for the authenticated user.

**Endpoint:** `GET /api/transactions`  
**Middleware:** `auth:sanctum`  
**Description:** Returns the transaction history for the current user.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number |
| `per_page` | integer | No | 15 | Items per page |
| `type` | string | No | — | Filter by type: `payment`, `disbursement`, `refund`, `platform_fee`, `deposit`, `withdrawal` |
| `status` | string | No | — | Filter by status: `pending`, `success`, `failed` |
| `sort_by` | string | No | `created_at` | Sort field |
| `order` | string | No | `desc` | Sort direction: `asc`, `desc` |

#### Example Request

```
GET /api/transactions?type=payment&status=success&sort_by=created_at&order=desc
```

#### Response (Success: 200)

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

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |

## Transaction Resource Schema

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

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Transaction ID |
| `type` | enum | Transaction type (see table above) |
| `amount` | decimal | Transaction amount |
| `status` | enum | Transaction status (see table above) |
| `reference` | string\|null | External reference (payment gateway ID, etc.) |
| `backing_id` | integer\|null | Backing ID (if related to a backing) |
| `campaign_id` | integer\|null | Campaign ID (if related to a campaign) |
| `created_at` | datetime | Transaction creation timestamp |

## Business Rules

### 1. Filter by Type

Users can filter transactions by any of the 6 types: `payment`, `disbursement`, `refund`, `platform_fee`, `deposit`, `withdrawal`.

### 2. Sortable Fields

Default sorting is by `created_at` in descending order (newest first). Users can sort by any column in the `transactions` table.

### 3. Pagination

Default pagination is 15 items per page. Users can request up to 100 items per page via the `per_page` parameter.

### 4. Scope

Each user can only see their **own** transactions. The controller uses `Auth::user()->transactions()` query scope, ensuring no cross-user data leakage.

## Postman Testing

### Test Scripts (Transactions)

#### Setup: Login as Backer

```
POST {{base_url}}/login
{ "email": "backer@example.com", "password": "password123" }
→ Save token to {{backer_token}}
```

#### Test 1: List All Transactions

1. `GET {{base_url}}/transactions`
2. Headers: `Authorization: Bearer {{backer_token}}`
3. Expected: `200 OK` with paginated list of all user's transactions.

#### Test 2: Filter by Deposit Type

1. `GET {{base_url}}/transactions?type=deposit`
2. Expected: `200 OK` with only deposit transactions.

#### Test 3: Filter by Payment Type

1. `GET {{base_url}}/transactions?type=payment`
2. Expected: `200 OK` with only payment (backing) transactions.

#### Test 4: Sort by Amount (Ascending)

1. `GET {{base_url}}/transactions?sort_by=amount&order=asc`
2. Expected: `200 OK` with transactions sorted by amount ascending.

#### Test 5: Filter by Success Status

1. `GET {{base_url}}/transactions?status=success`
2. Expected: `200 OK` with only successful transactions.

#### Test 6: Unauthenticated Access

1. `GET {{base_url}}/transactions` (no Authorization header)
2. Expected: `401 Unauthenticated`.

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | List all transactions | Valid token | 200 + paginated list |
| 2 | List transactions with deposit | `?type=deposit` | 200 + only deposits |
| 3 | List transactions with payment | `?type=payment` | 200 + only payments |
| 4 | List transactions with withdrawal | `?type=withdrawal` | 200 + only withdrawals |
| 5 | Sort by amount ascending | `?sort_by=amount&order=asc` | 200 + sorted ascending |
| 6 | Sort by amount descending | `?sort_by=amount&order=desc` | 200 + sorted descending |
| 7 | Filter by success status | `?status=success` | 200 + only success |
| 8 | Filter by failed status | `?status=failed` | 200 + only failed |
| 9 | Unauthenticated access | No token | 401 unauthenticated |
| 10 | Paginate transactions | `?per_page=5` | 200 + 5 items per page |
| 11 | No transactions exist | Fresh user account | 200 + empty data array |
| 12 | Transaction type enum correct | From various actions | type field matches enum values |

## Troubleshooting

### 1. Missing transaction types in list

The `transactions` migration originally defined the `type` column enum as `['payment', 'refund', 'disbursement', 'platform_fee']`. The `deposit` and `withdrawal` types were added to the `TransactionType` enum later but **the database enum was not updated**.

This is a **critical issue**: Under MySQL strict mode, inserting `type = 'deposit'` or `'withdrawal'` will fail, but the error may be silently swallowed or produce an SQL error.

**Fix:** Run a migration to update the enum:
```php
DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('payment','refund','disbursement','platform_fee','deposit','withdrawal')");
```

### 2. Transaction shows `null` for backing_id or campaign_id

This is expected behavior for `deposit` and `withdrawal` transactions — they are not associated with a specific backing or campaign. The `backing_id` and `campaign_id` columns are nullable in the migration.

### 3. Transaction reference is always "mock"

For backing payments, the reference uses the format `mock_payment_{timestamp}_{random}`. This is a placeholder for mock payment processing. In production, this should be replaced with real payment gateway references.

For deposits and withdrawals, the reference format is `deposit_20260826_abc123` or `withdrawal_20260826_xyz789`.

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| List transactions | Authenticated | `auth:sanctum` |
