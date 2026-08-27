# Tier Module API

Reward tier management for campaigns.

## Architecture

The Tier module manages reward tiers associated with a campaign. Each tier specifies a minimum backing amount, optional quota, and a reward description. Tiers are created, updated, and deleted as part of campaign management.

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/TierController.php` | Handles tier CRUD operations |
| Service | `app/Services/TierService.php` | Business logic for tier management |
| Requests | `app/Http/Requests/{StoreTierRequest, UpdateTierRequest, DeleteTierRequest}.php` | Validation rules |
| Resource | `app/Http/Resources/CampaignTierResource.php` | JSON response formatting |
| Model | `app/Models/CampaignTier.php` | Tier entity with quota logic |

### Flow

```
Creator → Request validation (ownership check)
       → TierService::create/update/deleteMany()
       → CampaignService::ensureEditable() check
       → DB::transaction() (for multi-deletes)
       → Update CampaignTier records
```

## File Structure

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

### 1. Create Tier

Creates a new reward tier for a campaign.

**Endpoint:** `POST /api/campaigns/{slug}/tiers`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Adds a reward tier to an editable campaign.

#### Request Body

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `name` | string | Yes | `required, string, max:255` | Tier name |
| `min_amount` | decimal | Yes | `required, numeric, min:0` | Minimum backing amount |
| `quota` | integer | Yes | `required, integer, min:0` | Quota (0 = unlimited) |
| `reward_description` | string | No | `nullable, string` | Description of the reward |

#### Example Request

```json
{
  "name": "Early Bird",
  "min_amount": 100000,
  "quota": 10,
  "reward_description": "Special thank you + exclusive sticker pack"
}
```

#### Response (Success: 201)

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

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not creator / not campaign owner |
| 409 | Campaign is not in editable state | Campaign is DRAFT... wait, DRAFT IS editable |

> **Bug note:** `ensureEditable()` is called before creating tiers, but it only allows DRAFT status (not REVIEW). If the campaign is in REVIEW status, tiers cannot be added. This is by design — campaigns should be in DRAFT before adding tiers.

---

### 2. Update Tier

Updates an existing tier.

**Endpoint:** `PUT /api/campaigns/{slug}/tiers/{tier}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Updates tier details.

#### Authorization

- User must own the campaign (`$campaign->user_id === auth()->id()`)
- The tier must belong to the campaign

#### Request Body (All fields optional)

| Parameter | Type | Validation | Description |
|-----------|------|------------|-------------|
| `name` | string | `sometimes, string, max:255` | Tier name |
| `min_amount` | decimal | `sometimes, numeric, min:0` | Minimum backing amount |
| `quota` | integer | `sometimes, integer, min:0` | Quota (0 = unlimited) |
| `reward_description` | string | `nullable, string` | Reward description |

#### Response (Success: 200)

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

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not owner |
| 409 | Campaign is not in editable state | Not DRAFT |

---

### 3. Delete Tiers (Bulk)

Deletes multiple tiers from a campaign.

**Endpoint:** `DELETE /api/campaigns/{slug}/tiers`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Deletes multiple tiers in one request.

#### Request Body

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `ids` | array | Yes | `required, array, min:1` | Array of tier IDs |
| `ids.*` | integer | Yes | `integer, exists:campaign_tiers,id` | Must exist |

#### Example Request

```json
{
  "ids": [4, 5]
}
```

#### Response (Success: 200)

```json
{
  "message": "Tiers deleted successfully"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not owner |
| 409 | A campaign must have at least one tier | Attempting to delete all tiers |
| 422 | Validation error | Invalid tier IDs |

## Tier Resource Schema

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

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Tier ID |
| `name` | string | Tier name |
| `min_amount` | decimal | Minimum backing amount for this tier |
| `quota` | integer\|null | Maximum number of backings (null = unlimited) |
| `remaining_quota` | integer\|null | Remaining slots (null = unlimited) |
| `is_unlimited` | boolean | True if quota = 0 |
| `has_availability` | boolean | True if still available for backing |
| `reward_description` | string\|null | Description of rewards |

## Business Rules

### 1. Unlimited Quota

When `quota = 0`, the tier is considered unlimited:
- `is_unlimited` → `true`
- `remaining_quota` → `null`
- `has_availability` → `true` (always, regardless of how many backings)

### 2. Quota Management

Each time a backing is created using a tier:
- `remaining_quota` is decremented by 1
- If `remaining_quota` reaches 0, `has_availability` becomes `false`

### 3. Minimum Tier Requirement

The `StoreCampaignRequest` validation enforces `tiers.*.min_amount` with a `min:0` rule, but business logic doesn't prevent `min_amount = 0`. However, `BackingService` requires the backing amount to be ≥ tier's `min_amount`.

### 4. Editable State Only

Tiers can only be created, updated, or deleted when the campaign is in DRAFT status. Once submitted for review (REVIEW) or active (ACTIVE), tier modifications are blocked.

## Postman Testing

### Setup: Login as Creator

```
POST {{base_url}}/login
{ "email": "creator1@example.com", "password": "password123" }
→ Save token to {{creator_token}}
```

### Test 1: Create Tier

1. `POST {{base_url}}/campaigns/{draft-slug}/tiers`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Body:
   ```json
   {
     "name": "Early Bird",
     "min_amount": 100000,
     "quota": 10,
     "reward_description": "Exclusive sticker pack"
   }
   ```
4. Expected: `201 Created`.

### Test 2: Update Tier

1. `PUT {{base_url}}/campaigns/{draft-slug}/tiers/4`
2. Body:
   ```json
   { "name": "Early Bird Updated", "min_amount": 150000 }
   ```
3. Expected: `200 OK`.

### Test 3: Delete Tiers

1. `DELETE {{base_url}}/campaigns/{draft-slug}/tiers`
2. Body:
   ```json
   { "ids": [5, 6] }
   ```
3. Expected: `200 OK`.

### Test 4: Try to delete all tiers

1. Delete all tiers except one.
2. `DELETE {{base_url}}/campaigns/{draft-slug}/tiers`
3. Body:
   ```json
   { "ids": [remaining_tier_id] }
   ```
4. Expected: `409 Conflict` — "A campaign must have at least one tier".

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | Create tier on editable campaign | Valid tier data | 201 + tier schema |
| 2 | Create tier on non-editable campaign | Active/Review campaign | 409 conflict |
| 3 | Create tier as non-owner | Other creator's campaign | 403 forbidden |
| 4 | Update tier (owner) | Valid tier data | 200 + updated tier |
| 5 | Update tier (non-owner) | Other creator's tier | 403 forbidden |
| 6 | Delete multiple tiers | Array of valid IDs | 200 + deleted |
| 7 | Delete all tiers (keep 1) | Last remaining tier | 409 conflict |
| 8 | Delete tier not on campaign | Tier from different campaign | 403/422 error |
| 9 | Create unlimited tier | quota=0, min_amount=50000 | 201 + is_unlimited=true |
| 10 | Back on unlimited tier | Multiple backings | remaining_quota stays null |

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| Create tier | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Update tier | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Delete tier | Creator (owner) | `auth:sanctum, role:creator, verified` |
