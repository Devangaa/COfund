# Campaign Update Module API

Campaign update posts for communication between creators and backers.

## Architecture

The Campaign Update module allows creators to post updates on their campaigns. When a new update is posted, all backers of that campaign receive in-app notifications. Updates can only be created for ACTIVE campaigns.

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controller | `app/Http/Controllers/Api/CampaignUpdateController.php` | Handles update CRUD operations |
| Service | `app/Services/CampaignUpdateService.php` | Business logic for updates |
| Requests | `app/Http/Requests/{StoreCampaignUpdateRequest, UpdateCampaignUpdateRequest, DeleteCampaignUpdateRequest}.php` | Validation rules |
| Resource | `app/Http/Resources/CampaignUpdateResource.php` | JSON response formatting |
| Model | `app/Models/CampaignUpdate.php` | Update entity |
| Notification | (via model, not Laravel Notifications) | Creates in-app notifications for all backers |

### Flow

```
Creator → StoreCampaignUpdateRequest → CampaignUpdateService::create()
       → Check campaign status = ACTIVE
       → Create update
       → notifyBackers() — gets distinct backer IDs
       → Mass insert Notification records
       → Return update resource

Backer → GET /campaigns/{slug}/updates (public)
       → CampaignUpdateResource collection
```

## File Structure

```
app/
├── Http/Controllers/Api/CampaignUpdateController.php
├── Services/CampaignUpdateService.php
├── Http/Requests/
│   ├── StoreCampaignUpdateRequest.php
│   ├── UpdateCampaignUpdateRequest.php
│   └── DeleteCampaignUpdateRequest.php
├── Http/Resources/CampaignUpdateResource.php
└── Models/CampaignUpdate.php
```

## API Endpoints

### 1. List Updates (Public)

Returns all updates for a campaign. Publicly accessible (no auth required).

**Endpoint:** `GET /api/campaigns/{slug}/updates`  
**Middleware:** `public`  
**Description:** Returns campaign updates. No authentication required.

#### Response (Success: 200)

```json
[
  {
    "id": 1,
    "title": "First Update",
    "content": "We've reached 50% of our goal! Thank you to everyone who supported us.",
    "created_at": "2026-08-25T10:00:00.000000Z"
  },
  {
    "id": 2,
    "title": "Milestone Reached",
    "content": "We hit our target! Production will begin next month.",
    "created_at": "2026-08-26T10:00:00.000000Z"
  }
]
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 404 | Campaign not found | Invalid slug |

---

### 2. Create Update

Creates a new update for an ACTIVE campaign. Sends notifications to all backers.

**Endpoint:** `POST /api/campaigns/{slug}/updates`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Posts an update and notifies all backers.

#### Authorization

User must own the campaign.

#### Request Body

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `title` | string | Yes | `required, string, max:255` | Update title |
| `content` | string | Yes | `required, string` | Update content (body) |

#### Example Request

```json
{
  "title": "Production Update",
  "content": "We've placed the order for materials. Expected delivery in 2 weeks."
}
```

#### Response (Success: 201)

```json
{
  "id": 3,
  "title": "Production Update",
  "content": "We've placed the order for materials. Expected delivery in 2 weeks.",
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

#### Side Effects

- Creates `CampaignUpdate` record
- Collects all distinct backer user IDs for the campaign
- Mass inserts `Notification` records (type=`campaign_update`) for each backer
- **No queue** — `notifyBackers()` runs synchronously

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not the campaign creator |
| 409 | Campaign has not been approved yet | Campaign status ≠ ACTIVE |
| 422 | Validation error | Missing title or content |

> **Note on error code:** The `CampaignUpdateService::create()` throws a `ConflictHttpException('Campaign has not been approved yet')` when the campaign is not active, which results in a **409** response. However, the custom exception handler returns this as HTTP 409 (Conflict), **not** 422.

---

### 3. Update Update

Updates an existing update post.

**Endpoint:** `PUT /api/campaigns/{slug}/updates/{update}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Edits an existing campaign update.

#### Authorization

- User must own the campaign
- The update must belong to the campaign

#### Request Body

| Parameter | Type | Validation | Description |
|-----------|------|------------|-------------|
| `title` | string | `sometimes, string, max:255` | New title |
| `content` | string | `sometimes, string` | New content |

#### Example Request

```json
{
  "title": "Production Update (Revised)",
  "content": "Updated content..."
}
```

#### Response (Success: 200)

```json
{
  "id": 3,
  "title": "Production Update (Revised)",
  "content": "Updated content...",
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not the campaign creator |
| 404 | Campaign update not found | Invalid update ID |

---

### 4. Delete Update

Deletes an update post.

**Endpoint:** `DELETE /api/campaigns/{slug}/updates/{update}`  
**Middleware:** `auth:sanctum` + `role:creator` + `verified`  
**Description:** Soft-deletes an update.

#### Authorization

- User must own the campaign
- The update must belong to the campaign

#### Response (Success: 200)

```json
{
  "message": "Update deleted successfully"
}
```

#### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not the campaign creator |
| 404 | Campaign update not found | Invalid update ID |

## Campaign Update Resource Schema

```json
{
  "id": 3,
  "title": "Production Update",
  "content": "We've placed the order for materials...",
  "created_at": "2026-08-26T15:30:00.000000Z"
}
```

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Update ID |
| `title` | string | Update title |
| `content` | string | Update content (HTML-safe text) |
| `created_at` | datetime | Formatted as `Y-m-d H:i:s` |

## Business Rules

### 1. Active Campaign Requirement

Campaign updates can only be created for campaigns with `status = 'active'`. Attempting to create an update on a DRAFT, REVIEW, SUCCESS, or FAILED campaign results in a `409 Conflict` error with message "Campaign has not been approved yet".

### 2. Backer Notifications

When a new update is posted:
1. All **distinct** backer IDs are collected from the campaign's backings
2. `Notification` records are mass-inserted (using `Notification::insert()`) for efficiency
3. Each notification has `type = 'campaign_update'`, `title`, and `body` containing the update content
4. Notifications are created synchronously (not queued)

### 3. Ownership Check

Only the campaign creator can create, edit, or delete updates. The checks are done at both the Form Request level (`authorize()` method) and within the service.

## Postman Testing

### Test Scripts (Campaign Updates)

#### Test 1: List Updates (Public)

1. `GET {{base_url}}/campaigns/test-campaign/updates`
2. Expected: `200 OK` with array of updates.

#### Test 2: Create Update (Creator)

1. `POST {{base_url}}/campaigns/{active-sluggy}/updates`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Body:
   ```json
   {
     "title": "New Update",
     "content": "Campaign is going well!"
   }
   ```
4. Expected: `201 Created` + update created.

#### Test 3: Create Update on Non-Active Campaign

1. Use a DRAFT or REVIEW campaign slug.
2. Same as Test 2.
3. Expected: `409 Conflict`.

#### Test 4: Update Existing Update

1. `PUT {{base_url}}/campaigns/{slug}/updates/{id}`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Body:
   ```json
   { "title": "Updated Title" }
   ```
4. Expected: `200 OK`.

#### Test 5: Delete Update

1. `DELETE {{base_url}}/campaigns/{slug}/updates/{id}`
2. Headers: `Authorization: Bearer {{creator_token}}`
3. Expected: `200 OK` + "Update deleted successfully".

#### Test 6: Access as Non-Creator

1. Use a different creator's token.
2. Try to create/update/delete an update on another's campaign.
3. Expected: `403 Forbidden`.

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | List updates (public) | Valid slug | 200 + array of updates |
| 2 | Create update on active campaign | Valid title + content | 201 + created update |
| 3 | Create update on draft campaign | Valid data | 409 conflict |
| 4 | Create update on review campaign | Valid data | 409 conflict |
| 5 | Create update on success/failed campaign | Valid data | 409 conflict |
| 6 | Create update as non-owner | Other creator's campaign | 403 forbidden |
| 7 | Create update without title | Missing title | 422 validation error |
| 8 | Create update without content | Missing content | 422 validation error |
| 9 | Update existing update (owner) | Valid ID + data | 200 + updated |
| 10 | Update update (non-owner) | Other creator's update | 403 forbidden |
| 11 | Delete update (owner) | Valid ID | 200 + deleted message |
| 12 | Delete update (non-owner) | Other creator's update | 403 forbidden |
| 13 | Backer receives notification | After creator creates update | Notification record created |
| 14 | List updates on invalid slug | Non-existent slug | 404 not found |

## Troubleshooting

### 1. "Campaign has not been approved yet" (409)

This error occurs when trying to create an update on a campaign whose status is not `ACTIVE`. The `CampaignUpdateService::create()` method throws a `ConflictHttpException`.

**Fix:** Ensure the campaign has been approved by an admin and has `status = 'active'`.

---

### 2. Notifications not created for backers

If backers don't receive in-app notifications after an update:

1. Check that the campaign has at least one COMPLETED backing
2. The `notifyBackers()` method gets `distinct` backer user IDs — ensure the `backings` table has entries with `status = 'completed'`
3. Notifications are created synchronously using `Notification::insert()` — no queue processing required

> **Warning:** This notification creation is **not queued**. For campaigns with thousands of backers, this could cause a timeout. Consider implementing queue-based notification dispatch for high-volume scenarios.

---

### 3. Update deleted but backers not notified

Deleting an update does **not** send notifications to backers. Only **creating** new updates triggers notifications. This is by design.

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| List campaign updates | Public | — |
| Create update | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Update update | Creator (owner) | `auth:sanctum, role:creator, verified` |
| Delete update | Creator (owner) | `auth:sanctum, role:creator, verified` |
