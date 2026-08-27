# Backing Module API

Backing (pledging to support a campaign) creation, listing, and status management.

## Architecture

The backing module handles the process of a user pledging money to a campaign. It supports optional reward tiers and validates minimum backing amounts, tier availability, and prevents creators from backing their own campaigns.

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/BackingController.php` | Lists backings and handles campaign backing |
| Service | `app/Services/BackingService.php` | Business logic for creating backing, tier validation, target check |
| Request | `app/Http/Requests/StoreBackingRequest.php` | Validation for new backing |
| Resource | `app/Http/Resources/BackingResource.php` | JSON response formatting |
| Model | `app/Models/Backing.php` | Backing entity with relationships |
| Enum | `app/Enums/BackingStatus.php` | Backing status states |
| Enum | `app/Enums/TransactionType.php` | Transaction types including `PAYMENT` |
| Job (related) | `app/Jobs/RefundBackersJob.php` | Dispatched for refunded backings on campaign failure |
| Event | `app/Events/BackingCreated.php` | Fired after successful backing |
| Listener | `app/Listeners/HandleBackingCreated.php` | Creates in-app notifications + emails |

### Flow

```
Backer → StoreBackingRequest → BackingService::create() → DB Transaction
       → Validate creator ≠ backer
       → Validate campaign is ACTIVE
       → Validate tier availability or minimum amount
       → Create Backing (status=completed)
       → Create Transaction (type=payment, status=success)
       → Decrement tier quota if applicable
       → Increment campaign collected_amount
       → Check if target reached → Fire CampaignFunded event
       → Fire BackingCreated event → HandleBackingCreated listener
```

## File Structure

```
app/
├── Http/Controllers/Api/BackingController.php
├── Services/BackingService.php
├── Http/Requests/StoreBackingRequest.php
├── Http/Resources/BackingResource.php
└── Models/Backing.php
```

## Backing Status States

| Status | Label | Description |
|--------|-------|-------------|
| `PENDING` | `pending` | Backing created but payment not yet confirmed |
| `COMPLETED` | `completed` | Payment successful and backing is active |
| `REFUNDED` | `refunded` | Backing refunded (campaign failed) |

## API Endpoints

### 1. List My Backings

Returns a paginated list of backings made by the authenticated user. Admins see all backings.

**Endpoint:** `GET /api/backings`  
**Middleware:** `auth:sanctum` + `verified`  
**Description:** Lists all backings for the current user. Admins see all backings system-wide.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number |
| `per_page` | integer | No | 15 | Items per page |

#### Response (Success: 200)

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

> **For admins:** Same response but includes backings from all users.

---

### 2. Get Campaign Backings (List Backer for Campaign Detail)

Returns a paginated list of backings for a specific campaign.

**Endpoint:** `GET /api/campaigns/{slug}/backings`  
**Middleware:** `auth:sanctum` + `verified`  
**Description:** Lists all backers for a campaign. Used to display backer list in campaign detail page.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number |
| `per_page` | integer | No | 15 | Items per page |

#### Response (Success: 200)

Same structure as "List My Backings", but filtered by the specified campaign.

---

### 3. Create Backing

Pledges money to a campaign. Supports optional tier selection.

**Endpoint:** `POST /api/campaigns/{slug}/back`  
**Middleware:** `auth:sanctum` + `verified`  
**Description:** Creates a new backing for the authenticated user on the specified campaign.

#### Request Body

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `tier_id` | integer | No | `nullable, integer, exists:campaign_tiers,id` | Reward tier to claim (if any) |
| `amount` | decimal | Yes | `required, numeric, min:10000` | Backing amount (min 10,000) |

#### Example Request

```json
{
  "tier_id": 1,
  "amount": 100000
}
```

> **Or without a tier:**
> ```json
> {
>   "amount": 50000
> }
> ```

#### Response (Success: 201)

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

#### Side Effects

- Creates a `Backing` record (status=completed)
- Creates a `Transaction` record (type=payment, status=success, reference=`mock_payment_*`)
- Decrements `CampaignTier.remaining_quota` if tier used
- Increments `Campaign.collected_amount`
- If target reached → campaign status changes to `success`, fires `CampaignFunded` event → dispatches `DisburseCampaignJob`
- Fires `BackingCreated` event → creates 2 in-app notifications (to backer and creator) + sends backing confirmation email

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You cannot back your own campaign | Creator backing their own campaign |
| 422 | Validation error | Amount < 10,000 / Tier not available / Tier quota exhausted / Campaign not active |

## Backing Resource Schema

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

## Business Rules

### 1. Minimum Backing Amount

The minimum backing amount (without a tier) is **10,000** (10k VND). This is enforced in `BackingService::ensureMinimumAmount()`.

### 2. Tier Validation

When using a tier:
- The tier must exist and belong to the campaign
- `remaining_quota` must be > 0 OR the tier must be unlimited (`quota = 0`)
- The backing amount must be ≥ `tier.min_amount`

When **not** using a tier:
- The backing amount must be ≥ 10,000

### 3. Creator Self-Backing Prevention

A campaign creator cannot back their own campaign. This is enforced in `BackingService::ensureCanBack()`.

### 4. Campaign Status Check

Backings can only be created on campaigns with status `ACTIVE`. Campaigns in DRAFT, REVIEW, SUCCESS, or FAILED status will reject new backings.

### 5. Target Reach Check

After each successful backing, the system checks if `collected_amount >= target_amount`. If so:
1. Campaign status → `success`
2. `CampaignFunded` event is fired
3. `HandleCampaignFunded` listener dispatches `DisburseCampaignJob`
4. `DisburseCampaignJob` transfers funds (95%) to creator, takes 5% platform fee

## Postman Testing

### Test Scripts (Backing)

#### Test 1: List My Backings

1. Set request: `GET {{base_url}}/backings`
2. Headers: `Authorization: Bearer {{backer_token}}`
3. Expected: `200 OK` with paginated backing list.

#### Test 2: List Campaign Backings (Admin or Creator)

1. Set request: `GET {{base_url}}/campaigns/kampanye-teknologi-gojek/backings`
2. Headers: `Authorization: Bearer {{admin_token or creator_token}}`
3. Expected: `200 OK` with paginated backer list.

#### Test 3: Create Backing with Tier

1. Set request: `POST {{base_url}}/campaigns/{slug}/back`
2. Headers: `Authorization: Bearer {{backer_token}}`
3. Body: `{"tier_id": 1, "amount": 100000}`
4. Expected: `201 Created` with backing + campaign progress updated.

#### Test 4: Create Backing without Tier

1. Set request: `POST {{base_url}}/campaigns/{slug}/back`
2. Headers: `Authorization: Bearer {{backer_token}}`
3. Body: `{"amount": 50000}`
4. Expected: `201 Created`.

#### Test 5: Self-Backing Attempt

1. Set request: `POST {{base_url}}/campaigns/{creator_slug}/back`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Body: `{"amount": 100000}`
4. Expected: `403 Forbidden`.

#### Test 6: Insufficient Amount

1. Set request: `POST {{base_url}}/campaigns/{slug}/back`
2. Body: `{"amount": 5000}`
3. Expected: `422 Validation error`.

#### Test 7: Tier Quota Exhausted

1. Set request on a campaign where all tiers are full.
2. Body: `{"tier_id": 1, "amount": 100000}`
3. Expected: `422 Validation error` — tier quota exhausted.

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | List my backings (backer) | Valid token | 200 + paginated list |
| 2 | List all backings (admin) | Admin token | 200 + all backings |
| 3 | List campaign backings | Valid campaign slug | 200 + paginated backer list |
| 4 | Create backing with valid tier | tier_id=1, amount=100000 | 201 + backing created |
| 5 | Create backing without tier | amount=50000 | 201 + backing created |
| 6 | Self-backing attempt | Creator backs own campaign | 403 forbidden |
| 7 | Backing below minimum | amount=5000 | 422 validation error |
| 8 | Backing with exhausted tier | tier_id with 0 remaining_quota | 422 validation error |
| 9 | Backing with invalid tier | tier_id that doesn't exist | 422 validation error |
| 10 | Backing on non-active campaign | slug of DRAFT/REVIEW/FAILED campaign | 422 validation error |
| 11 | Backing exactly at tier minimum | tier min_amount = 100000, amount=100000 | 201 created |
| 12 | Backing above tier minimum | tier min_amount=100000, amount=150000 | 201 created |
| 13 | Backing at 0 quota tier (unlimited) | tier with quota=0 | 201 created |
| 14 | Backing reaches target exactly | collected + amount = target | 201 + status=success + disbursement triggered |

## Troubleshooting

### 1. "You cannot back your own campaign"

This error occurs when a creator tries to back their own campaign. The `BackingService::ensureCanBack()` method throws an `AuthorizationException` (403) for this case.

**Fix:** Use a different user account (backer) to create the backing.

---

### 2. "Tier quota exhausted"

When a tier has `remaining_quota = 0` and `quota != 0` (finite quota), the system prevents additional backings on that tier.

**Fix:** Either use a different tier or back without a tier.

---

### 3. "Campaign is not active"

Backings can only be created on campaigns with `status = 'active'`. Campaigns in DRAFT, REVIEW, SUCCESS, or FAILED status reject new backings.

**Fix:** The campaign must be approved by an admin first (status → active).

---

### 4. "Amount must be at least 10000"

The minimum backing amount is 10,000 (10k) when no tier is selected.

**Fix:** Ensure `amount >= 10000`.

---

### 5. Backing succeeds but campaign status doesn't change to SUCCESS

This is handled in `BackingService::checkCampaignReachedTarget()`. It uses `DB::afterCommit()` to fire the `CampaignFunded` event after the transaction commits. Ensure:
1. The total `collected_amount` equals or exceeds `target_amount`
2. No DB transaction errors occurred

---

### 6. Email notification not received

The `HandleBackingCreated` listener sends emails only if the recipient (`email_verified_at` is not null). Ensure the user has verified their email before issuing a backing.

---

### 7. Transaction reference is "mock"

The current implementation uses mock transaction references (`mock_payment_*`). In production, this should be replaced with real payment gateway integration (e.g., Midtrans, Doku, etc.).

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| List my backings | Authenticated | `auth:sanctum, verified` |
| List campaign backings | Authenticated | `auth:sanctum, verified` |
| Create backing | Authenticated (backer) | `auth:sanctum, verified` |
