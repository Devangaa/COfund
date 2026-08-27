# Admin Module API

Administrative endpoints for user management, campaign moderation, and platform statistics.

## Architecture

The admin module is accessible only to users with the `admin` role. It provides:
- User management (list, view, suspend, unsuspend)
- Campaign moderation (approve, reject, force-fail)
- Platform statistics (user counts, campaign metrics, financial summaries)

### Components

| Component | Path | Description |
|----------|------|-------------|
| Controllers | `app/Http/Controllers/Api/Admin/{UserController, StatisticsController}.php` | Admin user management + campaign moderation |
| Campaign Moderation | `app/Http/Controllers/Api/CampaignController.php` | approve, reject, force-fail methods |
| Services | `app/Services/{UserService, CampaignService, TransactionService}.php` | Business logic for admin actions |
| Requests | `app/Http/Requests/SubmitCampaignReviewRequest.php` | Validation for review actions |
| Resources | `app/Http/Resources/{UserResource, CampaignResource}.php` | JSON response formatting |
| Events | `app/Events/{UserSuspended, UserUnsuspended}.php` | Fired for user state changes |

### Flow

```
Admin → auth:sanctum + role:admin → Admin Controller
      → Service method (approve/reject/suspend/unsuspend)
      → DB Transaction → Fire Event → Listener → Notification
      → Statistics: aggregate queries → return metrics
```

## File Structure

```
app/
├── Http/Controllers/Api/
│   ├── CampaignController.php (approve, reject, force-fail)
│   └── Admin/
│       ├── UserController.php
│       └── StatisticsController.php
├── Services/
│   ├── UserService.php
│   ├── CampaignService.php
│   └── TransactionService.php
└── Http/Requests/SubmitCampaignReviewRequest.php
```

## API Endpoints

### Campaign Moderation

#### 1. Approve Campaign

Transitions a campaign from REVIEW to ACTIVE status.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/approve`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Approves a campaign in review status.

##### Response (Success: 200)

```json
{
  "message": "Campaign approved",
  "campaign": { ... full CampaignResource ... }
}
```

##### Side Effects

- `status` → `active`
- `reviewed_by` → admin ID
- `reviewed_at` → current timestamp
- Fires `CampaignApproved` event → creates in-app notification + sends email to creator

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |
| 404 | Campaign not found | Invalid slug |

---

#### 2. Reject Campaign

Rejects a campaign, moving it from REVIEW back to DRAFT.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/reject`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Rejects a campaign submission with a reason.

##### Request Body

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `rejection_note` | string | Yes | `required` | Reason for rejection |

##### Example Request

```json
{
  "rejection_note": "Campaign description is too vague."
}
```

##### Response (Success: 200)

```json
{
  "message": "Campaign rejected",
  "campaign": { ... full CampaignResource ... }
}
```

##### Side Effects

- `status` → `draft`
- `rejection_note` → set
- `reviewed_by` → admin ID
- `reviewed_at` → current timestamp
- Fires `CampaignRejected` event → creates in-app notification + sends email to creator

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |
| 404 | Campaign not found | Invalid slug |

---

#### 3. Force Fail Campaign

Manually marks a campaign as failed.

**Endpoint:** `PUT /api/admin/campaigns/{slug}/force-fail`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Forces a campaign to failed status.

##### Response (Success: 200)

```json
{
  "message": "Campaign marked as failed"
}
```

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |

---

### User Management

#### 4. List Users

Returns a paginated list of all users.

**Endpoint:** `GET /api/admin/users`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Lists users with filtering options.

##### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number |
| `per_page` | integer | No | 15 | Items per page |
| `role` | string | No | — | Filter by role (`backer`, `creator`, `admin`) |
| `is_suspended` | boolean | No | — | Filter by suspension status |
| `search` | string | No | — | Search by name or email |

##### Example Request

```
GET /api/admin/users?role=creator&is_suspended=0&search=ali
```

##### Response (Success: 200)

```json
{
  "data": [
    {
      "id": 2,
      "name": "Ali Creator",
      "email": "ali@example.com",
      "role": "creator",
      "balance": "500000.00",
      "email_verified_at": "2026-08-24T10:00:00.000000Z",
      "is_suspended": false,
      "backings_count": 0,
      "created_at": "2026-08-24T10:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |

---

#### 5. Get User Detail

Returns detailed information about a specific user, including statistics.

**Endpoint:** `GET /api/admin/users/{user}`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Fetches a single user's details and stats.

##### Response (Success: 200)

```json
{
  "user": {
    "id": 2,
    "name": "Ali Creator",
    "email": "ali@example.com",
    "role": "creator",
    "balance": "500000.00",
    "email_verified_at": "2026-08-24T10:00:00.000000Z",
    "is_suspended": false,
    "created_at": "2026-08-24T10:00:00.000000Z"
  },
  "stats": {
    "backings_count": 3,
    "campaigns_count": 2,
    "total_spent": "300000.00",
    "total_contributed": "500000.00"
  }
}
```

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |
| 404 | User not found | Invalid user ID |

---

#### 6. Suspend User

Suspends a user account.

**Endpoint:** `PUT /api/admin/users/{user}/suspend`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Suspends a user, preventing login and wallet transactions.

##### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `reason` | string | No | Reason for suspension |

##### Example Request

```json
{
  "reason": "Violated platform terms of service"
}
```

##### Response (Success: 200)

```json
{
  "message": "User suspended successfully",
  "user": {
    "id": 3,
    "name": "Test User",
    "email": "test@example.com",
    "role": "backer",
    "is_suspended": true,
    "suspended_at": "2026-08-26T10:00:00.000000Z"
  }
}
```

##### Side Effects

- Sets `is_suspended = true`, `suspended_at = now()`
- Fires `UserSuspended` event (⚠️ not registered in `EventServiceProvider`)

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |
| 422 | You cannot suspend yourself | Admin tries to suspend own account |

---

#### 7. Unsuspend User

Reactivates a previously suspended user account.

**Endpoint:** `PUT /api/admin/users/{user}/unsuspend`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Reactivates a suspended user.

##### Response (Success: 200)

```json
{
  "message": "User unsuspended successfully",
  "user": {
    "id": 3,
    "name": "Test User",
    "is_suspended": false,
    "suspended_at": null
  }
}
```

##### Side Effects

- Sets `is_suspended = false`, `suspended_at = null`
- Fires `UserUnsuspended` event (⚠️ not registered in `EventServiceProvider`)

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |
| 404 | User not found | Invalid user ID |

---

### Platform Statistics

#### 8. Get Platform Statistics

Returns aggregated platform-wide statistics.

**Endpoint:** `GET /api/admin/statistics`  
**Middleware:** `auth:sanctum` + `role:admin`  
**Description:** Returns comprehensive platform metrics including user counts, campaign metrics, financial summaries, charts, and top campaigns.

##### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `period` | string | No | `7d` | Time period for chart data: `7d`, `30d`, `90d`, `all` |
| `start_date` | date | No | — | Custom start date (YYYY-MM-DD) |
| `end_date` | date | No | — | Custom end date (YYYY-MM-DD) |

##### Example Request

```
GET /api/admin/statistics?period=30d
```

##### Response (Success: 200)

```json
{
  "users": {
    "total": 150,
    "by_role": {
      "backer": 90,
      "creator": 50,
      "admin": 10
    }
  },
  "campaigns": {
    "total": 42,
    "status_distribution": {
      "active": 25,
      "draft": 5,
      "review": 3,
      "success": 6,
      "failed": 3
    },
    "total_target": "100000000.00",
    "total_collected": "65000000.00"
  },
  "backings": {
    "total": 180,
    "total_amount": "65000000.00"
  },
  "fees": {
    "total_platform_fee": "3250000.00",
    "rate": "10%"
  },
  "chart_data": [
    {
      "date": "2026-08-20",
      "backings": 5,
      "amount": "5000000.00",
      "campaigns": 2
    },
    {
      "date": "2026-08-21",
      "backings": 3,
      "amount": "3000000.00",
      "campaigns": 0
    }
  ],
  "top_campaigns": [
    {
      "id": 1,
      "title": "Top Campaign",
      "slug": "top-campaign",
      "creator_name": "Creator Name",
      "target_amount": "5000000.00",
      "collected_amount": "4500000.00",
      "progress_percentage": 90,
      "backings_count": 150,
      "status": "active"
    }
  ]
}
```

##### Errors

| SC | Message | Kondisi |
|----|---------|---------|
| 401 | Unauthenticated | Missing/invalid token |
| 403 | You do not have permission to access this resource. | Not an admin |

## User Resource Schema (Admin)

```json
{
  "id": 2,
  "name": "Ali Creator",
  "email": "ali@example.com",
  "role": "creator",
  "balance": "500000.00",
  "email_verified_at": "2026-08-24T10:00:00.000000Z",
  "is_suspended": false,
  "backings_count": 0,
  "created_at": "2026-08-24T10:00:00.000000Z"
}
```

## Postman Testing

### Test Scripts (Admin)

#### Setup: Login as Admin

```
POST {{base_url}}/login
{
  "email": "admin@example.com",
  "password": "password123"
}
```

Save the returned `token` to `{{admin_token}}`.

#### Test 1: List All Users

1. Set request: `GET {{base_url}}/admin/users`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with paginated user list.

#### Test 2: Filter Users by Role

1. Set request: `GET {{base_url}}/admin/users?role=creator`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with only creator users.

#### Test 3: Search Users

1. Set request: `GET {{base_url}}/admin/users?search=ali`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with users matching "ali" in name or email.

#### Test 4: Get User Detail

1. Set request: `GET {{base_url}}/admin/users/2`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with user info + stats.

#### Test 5: Suspend User

1. Set request: `PUT {{base_url}}/admin/users/3/suspend`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Body:
   ```json
   {
     "reason": "Test suspension"
   }
   ```
4. Expected: `200 OK` with `is_suspended = true`.

#### Test 6: Unsuspend User

1. Set request: `PUT {{base_url}}/admin/users/3/unsuspend`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with `is_suspended = false`.

#### Test 7: Access Admin Endpoint as Non-Admin

1. Set request: `GET {{base_url}}/admin/users`
2. Headers: `Authorization: Bearer {{backer_token}}`
3. Expected: `403 Forbidden`.

#### Test 8: Get Platform Statistics

1. Set request: `GET {{base_url}}/admin/statistics`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with full statistics object.

#### Test 9: Get Statistics with Custom Period

1. Set request: `GET {{base_url}}/admin/statistics?period=30d`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with 30-day chart data.

#### Test 10: Approve a Campaign (Admin)

1. Set request: `PUT {{base_url}}/admin/campaigns/test-campaign/approve`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `200 OK` with status=active.

#### Test 11: Reject a Campaign (Admin)

1. Set request: `PUT {{base_url}}/admin/campaigns/test-campaign/reject`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Body:
   ```json
   {
     "rejection_note": "Content needs improvement"
   }
   ```
4. Expected: `200 OK` with status=draft + rejection_note set.

#### Test 12: Try to Suspend Self

1. Set request: `PUT {{base_url}}/admin/users/{admin_id}/suspend`
2. Headers: `Authorization: Bearer {{admin_token}}`
3. Expected: `422` — "You cannot suspend yourself".

## Test Cases

| No | Scenario | Input | Expected Output |
|----|----------|-------|-----------------|
| 1 | List users (admin) | Admin token | 200 + paginated user list |
| 2 | Filter users by role | `role=backer` | 200 + filtered list |
| 3 | Filter users by suspension | `is_suspended=1` | 200 + suspended users only |
| 4 | Search users | `search=test` | 200 + matching users |
| 5 | Get user detail | User ID | 200 + user + stats |
| 6 | Get non-existent user | Invalid ID | 404 not found |
| 7 | Suspend user (admin) | User ID + reason | 200 + is_suspended=true |
| 8 | Suspend self | Admin's own ID | 422 + "You cannot suspend yourself" |
| 9 | Unsuspend user (admin) | Suspended user ID | 200 + is_suspended=false |
| 10 | Access admin as backer | Backer token | 403 forbidden |
| 11 | Access admin as unauthenticated | No token | 401 unauthenticated |
| 12 | Get statistics | Admin token | 200 + full stats object |
| 13 | Get statistics with period | `period=30d` | 200 + 30-day chart data |
| 14 | Get statistics with date range | `start_date=...&end_date=...` | 200 + custom date range data |
| 15 | Approve campaign (admin) | Campaign slug | 200 + status=active |
| 16 | Reject campaign (admin) | slug + rejection_note | 200 + status=draft + note |
| 17 | Force fail campaign (admin) | Campaign slug | 200 + message |

## Troubleshooting

### 1. "You cannot suspend yourself"

An admin cannot suspend their own account. This is a safety check in `UserService::suspend()`.

**Fix:** Use a different admin account, or implement a separate mechanism for self-deactivation.

---

### 2. Statistics show 10% platform fee (instead of 5%)

The `StatisticsController` uses `config('cofund.platform_fee', 0.1)` to display the platform fee rate. However, **no `config/cofund.php` file exists**, so it always returns the fallback `0.1` (10%). Meanwhile, `TransactionService::disburseCampaign()` uses a **hardcoded 5%** fee.

This is a known inconsistency. The actual fee applied during disbursement is 5%, but the statistics display shows 10%.

**Fix:** Create `config/cofund.php`:
```php
// config/cofund.php
return [
    'platform_fee' => 0.05, // 5%
];
```

---

### 3. UserSuspended/UserUnsuspended events don't trigger listeners

The `UserSuspended` and `UserUnsuspended` events are dispatched in `UserService::suspend()` and `UserService::unsuspend()`, but are **NOT registered** in `EventServiceProvider::$listen`. Since `shouldDiscoverEvents()` returns `false`, no auto-discovery occurs.

**Fix:** Register both events in `app/Providers/EventServiceProvider.php`:
```php
use App\Events\UserSuspended;
use App\Events\UserUnsuspended;
use App\Listeners\HandleUserSuspended;
use App\Listeners\HandleUserUnsuspended;

protected $listen = [
    // ... existing mappings ...
    UserSuspended::class => [
        HandleUserSuspended::class,
    ],
    UserUnsuspended::class => [
        HandleUserUnsuspended::class,
    ],
];
```

You would also need to create the `HandleUserSuspended` and `HandleUserUnsuspended` listener classes.

---

### 4. Admin trying to approve a non-REVIEW campaign

If you attempt to approve a campaign that is not in REVIEW status, the campaign will still be set to ACTIVE, but the flow may not be logically correct.

**Fix:** Add a status check in `CampaignService::approve()`:
```php
if ($campaign->status !== CampaignStatus::REVIEW) {
    throw new ConflictHttpException('Campaign is not in review status');
}
```

---

### 5. Statistics endpoint returns stale data

The statistics are calculated in real-time using aggregate queries. If caching is implemented later, remember to invalidate the cache when:
- Users are created/updated
- Campaigns are created/updated
- Backings are created
- Transactions are created
- Campaigns reach success/failure

## RBAC Matrix

| Action | Role | Middleware |
|--------|------|------------|
| List users | Admin | `auth:sanctum, role:admin` |
| Get user detail | Admin | `auth:sanctum, role:admin` |
| Suspend user | Admin | `auth:sanctum, role:admin` |
| Unsuspend user | Admin | `auth:sanctum, role:admin` |
| Approve campaign | Admin | `auth:sanctum, role:admin` |
| Reject campaign | Admin | `auth:sanctum, role:admin` |
| Force fail campaign | Admin | `auth:sanctum, role:admin` |
| View statistics | Admin | `auth:sanctum, role:admin` |
