# Campaign Module API

Campaign creation, management, listing, review, and administrative actions.

## Architecture

The campaign module implements a multi-stage lifecycle (DRAFT → REVIEW → ACTIVE → SUCCESS/FAILED). It uses a dedicated `CampaignService` for business logic, Form Requests for validation, and a Resource class for JSON serialization. Images, tiers, and updates are managed via separate controllers but are associated with a campaign.

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/CampaignController.php` | All campaign CRUD + admin review actions |
| Service | `app/Services/CampaignService.php` | Business logic for create, update, review, approve/reject |
| Requests | `app/Http/Requests/{StoreCampaignRequest, UpdateCampaignRequest, SubmitCampaignReviewRequest, DeleteCampaignRequest}.php` | Validation rules per action |
| Resource | `app/Http/Resources/CampaignResource.php`, `CampaignTierResource.php`, `CampaignImageResource.php`, `CampaignUpdateResource.php`, `CategoryResource.php` | JSON response formatting |
| Model | `app/Models/Campaign.php`, `CampaignTier.php`, `CampaignImage.php`, `CampaignUpdate.php`, `Category.php` | Eloquent models with relationships |
| Enum | `app/Enums/CampaignStatus.php` | Campaign status states |
| Job (related) | `app/Jobs/DisburseCampaignJob.php` | Dispatched when campaign reaches funding target |

### Flow

```
Creator → StoreCampaignRequest → CampaignService::create() → DB Transaction
         → Upload images to campaigns disk → Auto-set first as primary
         → Create tiers → Set status=DRAFT

Creator → SubmitCampaignReviewRequest → CampaignService::submitForReview() → Set status=REVIEW

Admin → approve/reject → CampaignService::approve()/reject() → Fire Events
       → CampaignApproved / CampaignRejected

System → BackingService checks target → Fire CampaignFunded
       → HandleCampaignFunded listener → DisburseCampaignJob (queued)

System → ExpireScheduler (cron) → CheckExpiredCampaigns command
       → Auto-success/failed + dispatch jobs
```

## File Structure

```
app/
├── Http/Controllers/Api/CampaignController.php
├── Services/CampaignService.php
├── Http/Requests/
│   ├── StoreCampaignRequest.php
│   ├── UpdateCampaignRequest.php
│   ├── SubmitCampaignReviewRequest.php
│   └── DeleteCampaignRequest.php
├── Http/Resources/
│   ├── CampaignResource.php
│   ├── CampaignTierResource.php
│   ├── CampaignImageResource.php
│   ├── CampaignUpdateResource.php
│   └── CategoryResource.php
├── Models/
│   ├── Campaign.php
│   ├── CampaignTier.php
│   ├── CampaignImage.php
│   ├── CampaignUpdate.php
│   └── Category.php
└── Enums/CampaignStatus.php
```

## Campaign Lifecycle

```
DRAFT → (submit for review) → REVIEW → (admin approve) → ACTIVE → (funded) → SUCCESS
                               → (admin reject) →  ↖       → (expired, unfunded) → FAILED
```

### Status States

| Status | Label | Description |
|--------|-------|-------------|
| `DASHBOARD` | `draft` | Initial state after creation |
| `REVIEW` | `review` | Submitted for admin review |
| `ACTIVE` | `active` | Published and accepting backings |
| `SUCCESS` | `success` | Reached funding target before deadline |
| `FAILED` | `failed` | Deadline expired before reaching target |

## API Endpoints

### 1. List Campaigns

Returns a paginated list of campaigns with optional filtering, sorting, and search.

**Endpoint:** `GET /api/campaigns`  
**Middleware:** `public`  
**Description:** Lists campaigns with filters, sorting, and search.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number for pagination |
| `per_page` | integer | No | 12 | Number of items per page |
| `search` | string | No | — | Search in title, description, and creator name |
| `category` | string | No | — | Filter by category slug |
| `min_amount` | decimal | No | — | Filter campaigns with target ≥ this amount |
| `max_amount` | decimal | No | — | Filter campaigns with collected amount ≤ this amount |
| `status` | string | No | `active` | Filter by status. Only admin and creator (?scope=mine) respect this. Guest/backer always get `active` only |
| `scope` | string | No | `public` | For creators: `mine` shows all of the creator's campaigns (any status). Public scope shows active campaigns only |
| `sort` | string | No | `latest` | Sort mode: `latest` (by created_at desc), `oldest` (by created_at asc), `popular` (by collected_amount desc) |
| `start_date` | date | No | — | Filter campaigns created at or after this date (format: YYYY-MM-DD) |
| `end_date` | date | No | — | Filter campaigns created at or before this date (format: YYYY-MM-DD). Must be ≥ start_date |

#### Response (Success: 200)

```json
{
  "data": [
    {
      "id": 1,
      "creator": {
        "id": 2,
        "name": "Zaki Creator 1",
        "email": "creator1@example.com",
        "role": "creator",
        "balance": "0.00"
      },
      "category": {
        "id": 1,
        "name": "Teknologi",
        "slug": "teknologi"
      },
      "title": "Kampanye Teknologi Gojek",
      "slug": "kampanye-teknologi-gojek",
      "description": "Deskripsi kampanye...",
      "description_html": "<p>Deskripsi kampanye...</p>",
      "target_amount": "5000000.00",
      "collected_amount": "3000000.00",
      "progress_percentage": 60,
      "deadline": "2026-08-31",
      "status": "active",
      "video_url": "https://www.youtube.com/watch?v=example",
      "rejection_note": null,
      "reviewed_at": "2026-08-20T10:00:00.000000Z",
      "images": [
        {
          "id": 1,
          "url": "http://localhost/storage/campaigns/IMG-abc123.jpg",
          "is_primary": true
        }
      ],
      "tiers": [
        {
          "id": 1,
          "name": "Early Bird",
          "min_amount": "100000.00",
          "quota": 10,
          "remaining_quota": 5,
          "has_availability": true,
          "is_unlimited": false,
          "reward_description": "Reward for early birds"
        }
      ],
      "updates_count": 2
    }
  ],
  "links": {
    "first": "http://localhost/api/campaigns?page=1",
    "last": "http://localhost/api/campaigns?page=5",
    "prev": null,
    "next": "http://localhost/api/campaigns?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "links": [...],
    "path": "http://localhost/api/campaigns",
    "per_page": 12,
    "to": 12,
    "total": 60
  }
}
```

#### Notes

- By default, **only ACTIVE campaigns** are returned. To see campaigns in other statuses, pass the `status` parameter explicitly.
- The `search` parameter uses a MySQL `FULLTEXT` index (added via migration `2026_08_26_165000_add_fulltext_search_to_campaigns_table.php`) — but searches with fewer than 4 characters may not match.
- Sorting by `deadline` will place campaigns ending sooner first (if `order=asc`).

---

### 2. Get Campaign Detail

Returns full details of a single campaign including all relations.

**Endpoint:** `GET /api/campaigns/{slug}`  
**Middleware:** `public`  
**Description:** Returns a single campaign by slug with all related resources.

#### Response (Success: 200)

```json
{
  "id": 1,
  "creator": {
    "id": 2,
    "name": "Zaki Creator 1",
    "email": "creator1@example.com",
    "role": "creator",
    "balance": "0.00",
    "email_verified_at": "2026-08-24T10:00:00.000000Z",
    "is_suspended": false
  },
  "category": {
    "id": 1,
    "name": "Teknologi",
    "slug": "teknologi"
  },
  "title": "Kampanye Teknologi Gojek",
  "slug": "kampanye-teknologi-gojek",
  "description": "Deskripsi kampanye...",
  "target_amount": "5000000.00",
  "collected_amount": "3000000.00",
  "progress_percentage": 60,
  "deadline": "2026-08-31",
  "status": "active",
  "video_url": "https://www.youtube.com/watch?v=example",
  "rejection_note": null,
  "reviewed_at": "2026-08-20T10:00:00.000000Z",
  "images": [...],
  "tiers": [...],
  "updates": [...],
  "updates_count": 2
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 404 | `Campaign not found` | Slug does not exist |

---

### 3. Create Campaign

Creates a new campaign in DRAFT status.

**Endpoint:** `POST /api/campaigns`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Creates a new campaign with images and tiers.

#### Request Body

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `category_id` | integer | Yes | `required, exists:categories,id` | Category of the campaign |
| `title` | string | Yes | `required, string, max:100` | Campaign title |
| `description` | string | Yes | `required, string, max:2000` | Detailed description |
| `target_amount` | decimal | Yes | `required, numeric, min:100000` | Funding target (min 100,000) |
| `deadline` | date | Yes | `required, date, after:+7 days` | Deadline (must be at least 7 days from now) |
| `video_url` | string | No | `nullable, string, url` | YouTube/video URL |
| `images` | array | Yes | `required, array, min:1, max:5` | Campaign images |
| `images.*.file` | file | Yes | `required, image, mimes:jpeg,png,jpg,gif, max:2048` | Image files |
| `tiers` | array | Yes | `required, array, min:1` | Reward tiers |
| `tiers.*.name` | string | Yes | `required, string, max:255` | Tier name |
| `tiers.*.min_amount` | decimal | Yes | `required, numeric, min:0` | Minimum backing amount |
| `tiers.*.quota` | integer | Yes | `required, integer, min:0` | Quota (0 = unlimited) |
| `tiers.*.reward_description` | string | No | `nullable, string` | Reward description |

#### Example Request

```json
{
  "category_id": 1,
  "title": "Kampanye Teknologi Gojek",
  "description": "Deskripsi kampanye...",
  "target_amount": 5000000,
  "deadline": "2026-09-15",
  "video_url": "https://www.youtube.com/watch?v=example",
  "tiers": [
    {
      "name": "Early Bird",
      "min_amount": 100000,
      "quota": 10,
      "reward_description": "Special reward for early birds"
    },
    {
      "name": "Supporter",
      "min_amount": 250000,
      "quota": 20,
      "reward_description": "Standard supporter reward"
    }
  ]
}
```

> **Note:** Images are uploaded as multipart form-data, not JSON. Each image file is keyed as `images[]`.

#### Multipart Form Data Example

```
POST /api/campaigns
Content-Type: multipart/form-data
Authorization: Bearer {token}

Form data:
- category_id: 1
- title: Kampanye Teknologi Gojek
- description: Deskripsi kampanye...
- target_amount: 5000000
- deadline: 2026-09-15
- video_url: https://www.youtube.com/watch?v=example
- images[]: (file upload 1)
- images[]: (file upload 2)
- tiers: [{"name": "Early Bird", "min_amount": 100000, "quota": 10, "reward_description": "..."}]
```

#### Response (Success: 201)

```json
{
  "id": 1,
  "creator": {
    "id": 2,
    "name": "Zaki Creator 1",
    "email": "creator1@example.com",
    "role": "creator",
    "balance": "0.00"
  },
  "category": {
    "id": 1,
    "name": "Teknologi",
    "slug": "teknologi"
  },
  "title": "Kampanye Teknologi Gojek",
  "slug": "kampanye-teknologi-gojek",
  "description": "Deskripsi kampanye...",
  "target_amount": "5000000.00",
  "collected_amount": "0.00",
  "progress_percentage": 0,
  "deadline": "2026-09-15",
  "status": "draft",
  "video_url": "https://www.youtube.com/watch?v=example",
  "rejection_note": null,
  "images": [...],
  "tiers": [...],
  "updates_count": 0
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 403 | `You do not have permission to access this resource.` | User is not a creator |
| 422 | `Validation error` | Missing/invalid fields |

---

### 4. Update Campaign

Updates an existing campaign in DRAFT or REVIEW status.

**Endpoint:** `PUT /api/campaigns/{slug}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Updates campaign info. Only editable in DRAFT or REVIEW state.

#### Authorization

User must own the campaign (`$campaign->user_id === auth()->id()`).

#### Request Body

All fields are optional (`sometimes` validation):

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `title` | string | No | `sometimes, string, max:100` | New title |
| `description` | string | No | `sometimes, string, max:2000` | New description |
| `target_amount` | decimal | No | `sometimes, numeric, min:100000` | New target |
| `deadline` | date | No | `sometimes, date, after:+7 days` | New deadline |
| `video_url` | string | No | `sometimes, string, url` | New video URL |
| `category_id` | integer | No | `sometimes, exists:categories,id` | New category |

#### Response (Success: 200)

Returns the updated `CampaignResource`.

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 403 | `You do not have permission to access this resource.` | Not the campaign owner |
| 409 | `Campaign is not in editable state` | Status is ACTIVE/SUCCESS/FAILED |
| 422 | `Validation error` | Invalid fields |

---

### 5. Submit Campaign for Review

Transitions a campaign from DRAFT/REVIEW to REVIEW status.

**Endpoint:** `POST /api/campaigns/{slug}/submit-review`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Submits the campaign for admin review.

#### Response (Success: 200)

```json
{
  "message": "Campaign submitted for review",
  "campaign": { ... full CampaignResource ... }
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 403 | You do not have permission to access this resource. | Not the campaign owner |
| 409 | Campaign is not in editable state | Status is ACTIVE/SUCCESS/FAILED |

---

### 6. Delete Campaign

Soft-deletes a campaign in DRAFT status.

**Endpoint:** `DELETE /api/campaigns/{slug}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Soft-deletes a campaign and its associated images, tiers, and updates.

#### Response (Success: 200)

```json
{
  "message": "Campaign deleted successfully"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 403 | You do not have permission to access this resource. | Not the campaign owner |
| 409 | Campaign is not in editable state | Status is ACTIVE/SUCCESS/FAILED |

---

### 7. Approve Campaign (Admin)

Transitions a campaign from REVIEW to ACTIVE status.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/approve`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Approves a campaign in review. Fires `CampaignApproved` event.

#### Response (Success: 200)

```json
{
  "message": "Campaign approved",
  "campaign": { ... full CampaignResource ... }
}
```

#### Side Effects

- `status` → `active`
- `reviewed_by` → admin ID
- `reviewed_at` → current timestamp
- Fires `CampaignApproved` event → Creates in-app notification + email to creator

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 403 | You do not have permission to access this resource. | Not an admin |
| 404 | Campaign not found | Invalid slug |

---

### 8. Reject Campaign (Admin)

Returns a campaign from REVIEW to DRAFT with a rejection note.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/reject`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Rejects a campaign. Fires `CampaignRejected` event.

#### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `rejection_note` | string | Yes | Reason for rejection |

#### Example Request

```json
{
  "rejection_note": "Campaign description is too vague."
}
```

#### Response (Success: 200)

```json
{
  "message": "Campaign rejected",
  "campaign": { ... }
}
```

#### Side Effects

- `status` → `draft`
- `rejection_note` → set
- `reviewed_by` → admin ID
- `reviewed_at` → current timestamp
- Fires `CampaignRejected` event → Creates in-app notification + email to creator

---

### 9. Force Fail Campaign (Admin)

Forces a campaign to FAILED status.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/force-fail`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Manually marks a campaign as failed.

#### Response (Success: 200)

```json
{
  "message": "Campaign marked as failed"
}
```

---

## Campaign Resource Schema

```json
{
  "id": 1,
  "creator": {
    "id": 2,
    "name": "Zaki Creator 1",
    "email": "creator1@example.com",
    "role": "creator",
    "balance": "0.00"
  },
  "category": {
    "id": 1,
    "name": "Teknologi",
    "slug": "teknologi"
  },
  "title": "Kampanye Teknologi Gojek",
  "slug": "kampanye-teknologi-gojek",
  "description": "...",
  "target_amount": "5000000.00",
  "collected_amount": "3000000.00",
  "progress_percentage": 60,
  "deadline": "2026-08-31",
  "status": "active",
  "video_url": "...",
  "rejection_note": null,
  "reviewed_at": "2026-08-20T10:00:00.000000Z",
  "images": [...],
  "tiers": [...],
  "updates": [...],
  "updates_count": 2
}
```

## Postman Testing

### Test Scripts (Campaigns)

#### Test 1: List Active Campaigns

1. Set request: `GET {{base_url}}/campaigns`
2. Expected: `200 OK` with paginated data (only ACTIVE campaigns by default).

#### Test 2: Filter by Category

1. Set request: `GET {{base_url}}/campaigns?category_id=1`
2. Expected: `200 OK` with campaigns filtered by category.

#### Test 3: Search Campaigns

1. Set request: `GET {{base_url}}/campaigns?search=teknologi`
2. Expected: `200 OK` with campaigns matching the search term.

#### Test 4: Sort by Collected Amount

1. Set request: `GET {{base_url}}/campaigns?sort_by=collected_amount&order=desc`
2. Expected: `200 OK` with campaigns sorted by collected amount (descending).

#### Test 5: Get Campaign Detail

1. Set request: `GET {{base_url}}/campaigns/kampanye-teknologi-gojek`
2. Expected: `200 OK` with full campaign detail.

#### Test 6: Create Campaign (Creator)

1. Set request: `POST {{base_url}}/campaigns`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Body (multipart form-data with images + JSON fields).
4. Expected: `201 Created` with campaign in DRAFT status.

#### Test 7: Submit Campaign for Review

1. Set request: `POST {{base_url}}/campaigns/{slug}/submit-review`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Expected: `200 OK` with status=REVIEW.

#### Test 8: Approve Campaign (Admin)

1. Set request: `PUT {{base_url}}/admin/campaigns/{slug}/approve`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with status=ACTIVE.

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | List campaigns (no params) | None | 200 + active campaigns only |
| 2 | Filter by category | `category_id=1` | 200 + filtered list |
| 3 | Filter by min target | `min_amount=1000000` | 200 + campaigns ≥ 1M |
| 4 | Filter by max target | `max_amount=10000000` | 200 + campaigns ≤ 10M |
| 5 | Sort by collected | `sort_by=collected_amount&order=desc` | 200 + sorted list |
| 6 | Search by keyword | `search=teknologi` | 200 + matching campaigns |
| 7 | Get campaign by slug | Valid slug | 200 + full detail |
| 8 | Get campaign invalid slug | Invalid slug | 404 not found |
| 9 | Create campaign (creator) | Valid data + images | 201 + draft campaign |
| 10 | Create campaign (backer) | Any data | 403 forbidden |
| 11 | Create campaign missing images | No images | 422 validation error |
| 12 | Create campaign short deadline | deadline ≤ 7 days | 422 validation error |
| 13 | Update own campaign (draft) | Any fields | 200 + updated |
| 14 | Update other's campaign | Any fields | 403 forbidden |
| 15 | Update active campaign | Any fields | 409 not editable |
| 16 | Submit for review (owner) | Valid slug | 200 + status=REVIEW |
| 17 | Submit for review (other) | Valid slug | 403 forbidden |
| 18 | Delete campaign (owner, draft) | Valid slug | 200 + deleted |
| 19 | Delete active campaign | Valid slug | 409 not editable |
| 20 | Approve campaign (admin) | Valid slug in REVIEW | 200 + status=ACTIVE |
| 21 | Approve campaign (non-admin) | Valid slug | 403 forbidden |
| 22 | Reject campaign (admin) | slug + rejection_note | 200 + status=DRAFT |
| 23 | Force fail campaign (admin) | Valid slug | 200 + status=FAILED |

## Troubleshooting

### 1. "Campaign is not in editable state" (409)

This error occurs when trying to update, submit, or delete a campaign whose status is `active`, `success`, or `failed`. Only campaigns in `draft` or `review` status are editable.

**Fix:** Check the campaign's current status. Campaigns must not be modified once they are actively accepting backings or have reached success/failed state.

---

### 2. Image Upload Failing

The server expects images as **multipart form-data**, not JSON. Each image file must be included as a separate file upload with the key `images[]`.

**Fix:** Use proper multipart encoding. In Postman, select "form-data" and add files under the key `images[]`.

---

### 3. Search Not Returning Results

The search uses MySQL `FULLTEXT` index on the `title` and `description` columns. MySQL requires search terms to be at least 4 characters long by default.

**Fix:** Ensure your search term is ≥ 4 characters.

---

### 4. Slug Conflicts

If two campaigns have the same title, the model automatically appends `-1`, `-2`, etc. to the slug to ensure uniqueness.

---

### 5. Deadline Validation Error

The `deadline` field must be at least **7 days after the current date** (`after:+7 days` rule).

**Fix:** Set a deadline at least one week in the future.

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| List campaigns | Public | — |
| Get campaign detail | Public | — |
| Create campaign | Creator | `auth:sanctum, role:creator, verified` |
| Update campaign | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Submit for review | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Delete campaign | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Approve campaign | Admin | `auth:sanctum, role:admin` |
| Reject campaign | Admin | `auth:sanctum, role:admin` |
| Force fail | Admin | `auth:sanctum, role:admin` |
