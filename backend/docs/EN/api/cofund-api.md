# CoFund API Documentation

Complete API documentation for the CoFund crowdfunding platform backend.

## Table of Contents

1. [Introduction](#1-introduction)
2. [Architecture Overview](#2-architecture-overview)
3. [Getting Started](#3-getting-started)
4. [Authentication](#4-authentication)
5. [User Roles](#5-user-roles)
6. [Rate Limiting](#6-rate-limiting)
7. [Error Handling](#7-error-handling)
8. [API Endpoint Summary](#8-api-endpoint-summary)
9. [Event System](#9-event-system)
10. [Background Jobs](#10-background-jobs)
11. [Modules](#11-modules)
12. [Known Issues](#12-known-issues)
13. [Postman Setup](#13-postman-setup)
14. [Development Commands](#14-development-commands)

---

## 1. Introduction

CoFund is a crowdfunding platform built on Laravel 10 and PHP 8.1+. The API provides endpoints for:
- User authentication and account management
- Campaign creation, moderation, and browsing
- Backing (pledging) to campaigns
- Wallet management (deposit/withdraw)
- Admin panel functionality (user management, statistics)
- Transaction history

**Base URL:** `http://localhost:8000/api`

**Protocol:** HTTPS (in production)  
**Format:** JSON (request and response bodies)  
**Encoding:** UTF-8  
**Authentication:** Laravel Sanctum Bearer Tokens

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      Frontend (Vite/React)                    │
└─────────────┬───────────────────────────────────────────────┘
              │ HTTP
┌─────────────▼──────────────────────────────────────────────┐
│                    Laravel Backend (API)                  │
├───────────────────────────────────────────────────────────┤
│  Routes (api.php) — Route Groups + Middleware             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Controllers                                         │  │
│  │  - AuthController, CampaignController                │  │
│  │  - BackingController, WalletController               │  │
│  │  - TransactionController, TierController             │  │
│  │  - CampaignUpdateController, CampaignImageController  │  │
│  │  - Admin\UserController, Admin\StatisticsController  │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Services (Business Logic)                           │  │
│  │  - AuthService, CampaignService, BackingService     │  │
│  │  - WalletService, TransactionService, UserService   │  │
│  │  - TierService, CampaignUpdateService, ImageService │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Models + Enums                                        │  │
│  │  - User, Campaign, Backing, Transaction             │  │
│  │  - CampaignTier, CampaignImage, CampaignUpdate      │  │
│  │  - Category, Notification                            │  │
│  │  - CampaignStatus, BackingStatus                     │  │
│  │  - TransactionType, TransactionStatus              │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Events ↔ Listeners ↔ Notifications                 │  │
│  │  - CampaignApproved, BackingCreated, etc.           │  │
│  │  - HandleCampaignApproved, HandleBackingCreated     │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Jobs (Queued)                                        │  │
│  │  - DisburseCampaignJob, RefundBackersJob            │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Database (MySQL 8)                                   │  │
│  │  - 9 tables with Foreign Key constraints             │  │
│  └─────────────────────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ File Storage (Local)                                 │  │
│  │  - Campaign images on `campaigns` disk                │  │
│  └─────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────┘
```

### Design Patterns

| Pattern | Usage |
|---------|-------|
| **Service Layer** | All business logic in `app/Services/*.php`; controllers are thin |
| **Form Request Validation** | Separate request classes for validation per endpoint |
| **Resource Transformers** | `app/Http/Resources/*.php` for consistent JSON output |
| **Event-Driven** | Events fire for important actions; listeners create notifications |
| **Repository via Eloquent** | Models act as repositories; direct Eloquent usage in services |
| **Dependency Injection** | Services injected into controllers via constructor |

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 10.x |
| PHP | 8.1+ |
| Database | MySQL 8 (utf8mb4) |
| Authentication | Sanctum API Tokens |
| Queue | Database driver (sync by default) |
| Cache | File driver |
| Session | File driver |
| Storage | Local filesystem |
| Mail | SMTP (Mailpit for local dev) |
| Testing | PHPUnit (Laravel Dusk not configured) |

---

## 3. Getting Started

### Prerequisites

- PHP 8.1+
- Composer
- MySQL 8.0+
- Node.js 18+ & NPM (for frontend)
- Git

### Installation

```bash
# Clone the repository
git clone <repo-url>
cd cofund/backend

# Install PHP dependencies
composer install

# Copy .env and configure
cp .env.example .env
# Edit .env with your database credentials

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed sample data (optional)
php artisan db:seed

# Create storage symlink
php artisan storage:link

# Start development server
php artisan serve
```

### Environment Variables

```env
APP_NAME=CoFund
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cofund
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=sync
SESSION_DRIVER=file
CACHE_DRIVER=file
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=hello@cofund.test
```

---

## 4. Authentication

CoFund uses **Laravel Sanctum** for API authentication with bearer tokens.

### Token-Based Authentication

1. User calls `POST /api/login` with email and password
2. Server validates credentials and creates a Sanctum token
3. Token is returned in the response
4. Client includes the token in the `Authorization: Bearer {token}` header for subsequent requests

### Token Format

```
Authorization: Bearer 5|9e7a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a
```

### Token Scope

Tokens have no expiration by default (`config/sanctum.php` `expiration` is `null`). Tokens can be revoked by:
- Logging out (`POST /api/logout`) — deletes the current token
- Admin intervention (future feature — token revocation via database)

### Email Verification

- Users receive an email verification link upon registration
- The `verified` middleware prevents unverified users from accessing wallet, backing, and campaign creation endpoints
- Unverified users get a `403` response: `{"message": "Your email address is not verified."}`

### Password Reset Flow

```
User → POST /api/forgot-password (email)
     → Laravel Password Broker sends email with reset link
     → User clicks link → POST /api/reset-password (email, token, password, password_confirmation)
     → Password is updated
```

---

## 5. User Roles

CoFund implements a 3-role RBAC system using a `role` column on the `users` table.

| Role | Enum Value | Permissions |
|------|-----------|-------------|
| **Backer** | `backer` | Browse campaigns, create backings, deposit/withdraw |
| **Creator** | `creator` | All backer permissions + create/edit/delete own campaigns, tiers, images, updates |
| **Admin** | `admin` | All permissions + approve/reject campaigns, manage users, view statistics |

### Role Matrix

| Action | Public | Backer | Creator | Admin |
|--------|--------|--------|---------|-------|
| Register | ✅ | — | — | — |
| Login | ✅ | ✅ | ✅ | ✅ |
| Browse campaigns | ✅ | ✅ | ✅ | ✅ |
| View campaign detail | ✅ | ✅ | ✅ | ✅ |
| List campaign updates | ✅ | ✅ | ✅ | ✅ |
| Create campaign | ❌ | ❌ | ✅ | ✅ |
| Edit own campaign | ❌ | ❌ | ✅ (owner) | ✅ |
| Submit campaign for review | ❌ | ❌ | ✅ (owner) | ✅ |
| Approve campaign | ❌ | ❌ | ❌ | ✅ |
| Reject campaign | ❌ | ❌ | ❌ | ✅ |
| Create backing | ❌ | ✅ | ❌* | ✅ |
| List own backings | ❌ | ✅ | ✅ | ✅ (all) |
| Deposit to wallet | ❌ | ✅ | ✅ | ✅ |
| Withdraw from wallet | ❌ | ✅ | ✅ | ✅ |
| List transactions | ❌ | ✅ | ✅ | ✅ |
| Manage users | ❌ | ❌ | ❌ | ✅ |
| View statistics | ❌ | ❌ | ❌ | ✅ |

> \* Creators cannot back their own campaigns (enforced in `BackingService::ensureCanBack()`)

### Middleware

| Middleware | Alias | Description |
|-----------|------|-------------|
| `auth:sanctum` | `auth` | Validates bearer token |
| `role:admin` | `role` | Checks user role matches parameter |
| `verified` | — | Checks `email_verified_at` is not null |
| `throttle:login` | — | 5 login attempts/minute per email+IP |
| `throttle:register` | — | 3 registrations/minute per IP |
| `throttle:password.request` | — | 5 password requests/minute per email+IP |
| `throttle:api` | — | 60 requests/minute per user/IP (global) |

---

## 6. Rate Limiting

Rate limits are configured in `RouteServiceProvider::configureRateLimiting()`.

| Endpoint Group | Limit | Key |
|----------------|-------|-----|
| Register | 3 requests / minute | IP address |
| Login | 5 requests / minute | email + IP |
| Forgot Password | 5 requests / minute | email + IP |
| Reset Password | 5 requests / minute | email + IP |
| All other API endpoints | 60 requests / minute | User ID or IP |

### 429 Response Format

```json
{
  "message": "Too many registration attempts. Please try again in 60 seconds."
}
```

### Rate Limit Headers

All responses include standard rate limit headers:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests in current window
- `Retry-After`: Seconds until the limit resets (only on 429)

---

## 7. Error Handling

All API endpoints return JSON responses. Errors follow the standard Laravel format.

### Standard Error Response

```json
{
  "message": "Descriptive error message"
}
```

### Validation Error Response (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Common Error Codes

| Code | Meaning | When It Happens |
|------|---------|-----------------|
| 401 | Unauthenticated | Missing/invalid/expired bearer token |
| 403 | Forbidden | User lacks required role or email unverified |
| 404 | Not Found | Resource (campaign, user, backing) doesn't exist |
| 409 | Conflict | Resource is in wrong state (e.g., campaign not editable) |
| 422 | Validation Error | Invalid input data |
| 422 | Business Rule Violation | Insufficient balance, suspended user, etc. |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Unexpected application error |

### Exception Handler

Custom exception handler in `app/Exceptions/Handler.php` returns:
- **401** for `AuthenticationException` — `{"message": "Unauthenticated"}`
- **403** for `AuthorizationException` — `{"message": "This action is unauthorized"}`
- **404** for `ModelNotFoundException` — `{"message": "Resource not found"}`
- **409** for `ConflictHttpException` — uses the exception's message
- **422** for `ValidationException` — standard Laravel validation format

---

## 8. API Endpoint Summary

### Authentication

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| POST | `/api/register` | `throttle:register` | Register new user |
| POST | `/api/login` | `throttle:login` | Login & get token |
| POST | `/api/logout` | `auth:sanctum` | Logout (revoke token) |
| GET | `/api/me` | `auth:sanctum` | Get current user |
| POST | `/api/forgot-password` | `throttle:password.request` | Send reset link |
| POST | `/api/reset-password` | `throttle:password.request` | Reset password |
| POST | `/api/email/resend` | `auth:sanctum` | Resend verification email |
| GET | `/api/email/verify/notice` | public | Email verification required notice (403) |
| GET | `/api/email/verify/{id}/{hash}` | `signed, throttle:6,1` | Verify email |

### Campaigns

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| GET | `/api/campaigns` | public | List campaigns (with filters) |
| GET | `/api/campaigns/{slug}` | public | Get campaign detail |
| POST | `/api/campaigns` | `auth:sanctum, role:creator, verified` | Create campaign |
| PUT | `/api/campaigns/{slug}` | `auth:sanctum, role:creator, verified` | Update campaign (DRAFT only) |
| POST | `/api/campaigns/{slug}/submit-review` | `auth:sanctum, role:creator, verified` | Submit for review |
| DELETE | `/api/campaigns/{slug}` | `auth:sanctum, role:creator, verified` | Delete campaign (DRAFT only) |

### Campaign Admin

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| PUT | `/api/admin/campaigns/{slug}/approve` | `auth:sanctum, role:admin` | Approve campaign (REVIEW → ACTIVE) |
| PUT | `/api/admin/campaigns/{slug}/reject` | `auth:sanctum, role:admin` | Reject campaign (REVIEW → DRAFT) |
| PUT | `/api/admin/campaigns/{slug}/force-fail` | `auth:sanctum, role:admin` | Force-fail campaign |

### Campaign Sub-resources

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| POST | `/api/campaigns/{slug}/tiers` | `auth:sanctum, role:creator, verified` | Create tier |
| PUT | `/api/campaigns/{slug}/tiers/{tier}` | `auth:sanctum, role:creator, verified` | Update tier |
| DELETE | `/api/campaigns/{slug}/tiers` | `auth:sanctum, role:creator, verified` | Delete tiers (bulk) |
| POST | `/api/campaigns/{slug}/images` | `auth:sanctum, role:creator, verified` | Upload image |
| DELETE | `/api/campaigns/{slug}/images` | `auth:sanctum, role:creator, verified` | Delete images (bulk) |
| GET | `/api/campaigns/{slug}/updates` | public | List campaign updates |
| POST | `/api/campaigns/{slug}/updates` | `auth:sanctum, role:creator, verified` | Create update |
| PUT | `/api/campaigns/{slug}/updates/{update}` | `auth:sanctum, role:creator, verified` | Update post |
| DELETE | `/api/campaigns/{slug}/updates/{update}` | `auth:sanctum, role:creator, verified` | Delete update |

### Backing

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| GET | `/api/backings` | `auth:sanctum, verified` | List my backings |
| GET | `/api/campaigns/{slug}/backings` | `auth:sanctum, verified` | List campaign backers |
| POST | `/api/campaigns/{slug}/back` | `auth:sanctum, verified` | Create backing |

### Wallet

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| POST | `/api/wallet/deposit` | `auth:sanctum, verified` | Deposit to wallet |
| POST | `/api/wallet/withdraw` | `auth:sanctum, verified` | Withdraw from wallet |

### Transactions

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| GET | `/api/transactions` | `auth:sanctum` | List user transactions |

### Admin

| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| GET | `/api/admin/users` | `auth:sanctum, role:admin` | List users |
| GET | `/api/admin/users/{user}` | `auth:sanctum, role:admin` | Get user detail |
| PUT | `/api/admin/users/{user}/suspend` | `auth:sanctum, role:admin` | Suspend user |
| PUT | `/api/admin/users/{user}/unsuspend` | `auth:sanctum, role:admin` | Unsuspend user |
| GET | `/api/admin/statistics` | `auth:sanctum, role:admin` | Platform statistics |

---

## 9. Event System

The application uses Laravel's event system to decouple important business actions from their side effects.

### Events Overview

| Event | Fired By | Listeners | Purpose |
|-------|----------|-----------|---------|
| `CampaignApproved` | `CampaignService::approve()` | `HandleCampaignApproved` | Notify creator + send email |
| `CampaignRejected` | `CampaignService::reject()` | `HandleCampaignRejected` | Notify creator + send email |
| `CampaignFunded` | `BackingService::checkCampaignReachedTarget()` | `HandleCampaignFunded` | Trigger disbursement job |
| `BackingCreated` | `BackingService::create()` | `HandleBackingCreated` | Notify backer + creator, send email |
| `DepositProcessed` | `WalletService::deposit()` | `HandleWalletTransaction::handleDeposit` | Create in-app notification |
| `WithdrawalProcessed` | `WalletService::withdraw()` | `HandleWalletTransaction::handleWithdrawal` | Create in-app notification |
| `UserSuspended` | `UserService::suspend()` | ❌ NOT REGISTERED | Currently no listener |
| `UserUnsuspended` | `UserService::unsuspend()` | ❌ NOT REGISTERED | Currently no listener |

### Event Listeners

All listeners are registered in `app/Providers/EventServiceProvider.php`. Auto-discovery is disabled (`shouldDiscoverEvents() = false`).

| Listener | Handles | Actions |
|----------|---------|---------|
| `HandleCampaignApproved` | `CampaignApproved` | Creates in-app `Notification` for creator; sends `CampaignApproved` email if verified |
| `HandleCampaignRejected` | `CampaignRejected` | Creates in-app `Notification` for creator; sends `CampaignRejected` email if verified |
| `HandleCampaignFunded` | `CampaignFunded` | Dispatches `DisburseCampaignJob` for queued processing |
| `HandleBackingCreated` | `BackingCreated` | Creates 2 in-app `Notification`s (backer + creator); sends `BackingConfirmation` email to backer if verified |
| `HandleWalletTransaction` | `DepositProcessed`, `WithdrawalProcessed` | Creates in-app `Notification` for deposit/withdrawal |

### Events Not Registered

`UserSuspended` and `UserUnsuspended` events are dispatched but **not registered** in the `EventServiceProvider`. No listeners will ever fire for these events.

### Transactional Event Safety

Certain events use `DB::afterCommit()` to ensure they only fire after the database transaction succeeds:

- `CampaignApproved` — fired in `CampaignService::approve()` inside `DB::transaction()`
- `CampaignRejected` — fired in `CampaignService::reject()` inside `DB::transaction()`
- `BackingCreated` — fired in `BackingService::create()` inside `DB::transaction()`
- `CampaignFunded` — fired via `DB::afterCommit()` in `BackingService::checkCampaignReachedTarget()`
- `DepositProcessed` — fired via `DB::afterCommit()` in `WalletService::deposit()`
- `WithdrawalProcessed` — fired via `DB::afterCommit()` in `WalletService::withdraw()`

> Using `DB::afterCommit()` means events fire after the transaction is committed, but they fire **synchronously**. If queue workers are configured, `ShouldQueue` listeners would run asynchronously.

---

## 10. Background Jobs

The application uses Laravel's queue system for long-running tasks. By default, the queue connection is `sync` (runs immediately in the same process).

### Jobs

| Job | Queue Connection | Dispatched By | Purpose |
|-----|-----------------|---------------|---------|
| `DisburseCampaignJob` | `sync` (default) | `HandleCampaignFunded` listener | Disburses funds (95%) to creator, takes 5% platform fee |
| `RefundBackersJob` | `sync` (default) | `CheckExpiredCampaigns` command | Refunds all backers of a failed campaign |

### Job Implementation

Both jobs implement `ShouldQueue` but since `QUEUE_CONNECTION=sync`, they actually run synchronously. This means:
- No separate queue worker process is needed
- Jobs run inline during the request lifecycle
- If queues are changed to `database` or `redis`, these jobs will be queued and require a worker

### Cron / Scheduler

`app/Console/Kernel.php` defines a schedule via `schedule()`:

| Command | Schedule | Description |
|---------|----------|-------------|
| `campaign:check-expired` | Daily at 00:05 | Checks if campaigns passed deadline, success/fail + refund/disburse |
| `campaign:notify-deadline` | Daily at 09:00 | Sends H-3 and H-1 deadline approaching notifications to backers |

### Running the Scheduler

The Laravel scheduler requires a single cron entry on the server:

```bash
* * * * * cd /path/to/cofund/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Running Queue Workers (If Queue Driver Changed)

If `QUEUE_CONNECTION` is changed from `sync` to `database` or `redis`:

```bash
# Start a worker
php artisan queue:work

# For production, use supervisor
# /etc/supervisor/conf.d/cofund-worker.conf
[program:cofund-worker]
command=php /path/to/cofund/backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
```

### Queue Tables

The database queue driver uses these tables:
- `jobs` — queued jobs
- `failed_jobs` — failed job records (UUID-based)

Run migration if switching to database queue:
```bash
php artisan queue:table
php artisan migrate
```

---

## 11. Modules

Detailed documentation is available in the following module-specific files:

| Module | File | Description |
|--------|------|-------------|
| Authentication | [`api/auth.md`](api/auth.md) | Register, login, logout, password reset, verification |
| Campaigns | [`api/campaigns.md`](api/campaigns.md) | Create, edit, list, detail, submit review |
| Campaign Admin Actions | [`api/admin.md`](api/admin.md) | Approve, reject, force-fail |
| Backing | [`api/backing.md`](api/backing.md) | Create backing, list backings |
| Tier | [`api/tier.md`](api/tier.md) | Create, update, delete reward tiers |
| Campaign Images | [`api/campaign-image.md`](api/campaign-image.md) | Upload, delete campaign images |
| Campaign Updates | [`api/campaign-update.md`](api/campaign-update.md) | Create, edit, delete campaign updates |
| Wallet | [`api/wallet.md`](api/wallet.md) | Deposit, withdraw funds |
| Transactions | [`api/transaction.md`](api/transaction.md) | List transaction history |
| Admin | [`api/admin.md`](api/admin.md) | User management, platform statistics |

---

## 12. Known Issues

### 1. Transactions Table Missing Enum Values (Critical)

The `transactions` migration defines the `type` column enum as:
```sql
ENUM('payment', 'refund', 'disbursement', 'platform_fee')
```

But the `TransactionType` enum includes `deposit` and `withdrawal`. Under MySQL strict mode, inserting a `deposit` or `withdrawal` transaction will **fail**.

**Fix Required:** Add these values to the database enum:
```sql
ALTER TABLE transactions MODIFY COLUMN type ENUM('payment', 'refund', 'disbursement', 'platform_fee', 'deposit', 'withdrawal');
```

### 2. UserSuspended/UserUnsuspended Not Registered (Medium)

The `UserService::suspend()` and `UserService::unsuspend()` methods dispatch `UserSuspended` and `UserUnsuspended` events, but these events are **not registered** in `EventServiceProvider::$listen`. Since `shouldDiscoverEvents()` returns `false`, no listeners will fire.

**Fix Required:** Register the events and create corresponding listeners (e.g., for account lockout effects, admin notifications, etc.).

### 3. Config Cofund Not Defined (Medium)

`Admin\StatisticsController` calls `config('cofund.platform_fee', 0.1)` but no `config/cofund.php` file exists. The fallback `0.1` (10%) is inconsistent with the hardcoded `0.05` (5%) in `TransactionService::disburseCampaign()` and `DisburseCampaignJob`.

**Fix Required:** Create `config/cofund.php`:
```php
return ['platform_fee' => 0.05];
```

### 4. NotifyDeadlineApproaching Command Bug (Medium)

The `NotifyDeadlineApproaching` command references undefined variables `$countH3` and `$countH1` on lines 73-74. This will cause a runtime error when the command executes.

**Fix Required:** Define these variables or remove the references.

### 5. .env Committed to Repository (Critical)

The `.env` file (containing `APP_KEY`, database credentials, etc.) is tracked in version control. This is a security risk.

**Fix Required:** Add `.env` to `.gitignore` and remove it from version control.

### 6. Frontend URL for Password Reset Not Configurable (Low)

`AuthServiceProvider::boot()` overrides `ResetPassword::createUrlUsing()` to generate frontend URLs using `config('app.frontend_url', config('app.url'))`. If this config key is not set, the reset URL will point to the API backend instead of the frontend app.

### 7. Email Sending Not Queued (Medium)

All mailable emails are sent synchronously via `Mail::send()` (or `Mail::to()->send()`). If the email service is slow, this delays API responses.

**Fix (Optional):** Queue emails by implementing `ShouldQueue` on the mailable classes and changing mail driver settings.

### 8. FULLTEXT Index Unused (Low)

A FULLTEXT index was added to the `campaigns` table for `title` and `description`, but `CampaignController::index()` search uses `LIKE` queries instead of `MATCH...AGAINST`. The index is not being utilized.

**Fix (Optional):** Refactor search to use `MATCH...AGAINST` or remove the unused index.

### 9. Campaign Image Deletion Atomicity Bug (Low)

In `CampaignImageService::deleteMany()`:
1. Physical files are deleted from storage
2. Then the system checks if any images remain
3. If only 1 image remains, a `ValidationException` is thrown

This means files are deleted but DB records remain in the database (soft delete is not called) because the exception is thrown before `$image->delete()` is executed for all items.

**Fix (Recommended):** Wrap in a DB transaction and validate the count **before** deleting any physical files.

### 10. Sanctum Stateful Middleware Disabled (Low)

The `EnsureFrontendRequestsAreStateful` middleware is commented out in the `api` group in `routes/api.php`. This means cookie-based session authentication for SPAs is **not active** — only bearer token authentication works.

**Fix (Optional):** Uncomment the middleware if building a cookie-based SPA frontend.

### 11. No API Versioning (Low)

All routes are under `/api` with no version prefix (e.g., `/api/v1`). This makes future breaking changes harder to manage.

### 12. No Rate Limit on Sensitive Endpoints

While auth endpoints have rate limiting, wallet deposit/withdraw and campaign creation do not have dedicated rate limiters beyond the global 60 req/min `throttle:api`.

### 13. No Input Validation for `per_page`

Pagination allows arbitrary `per_page` values (e.g., `per_page=99999`), which could cause memory issues. Consider enforcing a max limit.

### 14. No Pagination Limit on Statistics Top Campaigns (Low)

`StatisticsController::index()` always returns the top 5 campaigns without a configurable limit.

### 15. Campaign Status Transitions Not Enforced (Low)

There is no status machine constraint enforcing valid state transitions. E.g., an admin could approve a campaign that is in FAILED status (status would change to ACTIVE).

---

## 13. Postman Setup

### Environment Variables

Create a Postman environment with the following variables:

| Variable | Value | Description |
|----------|-------|-------------|
| `base_url` | `http://localhost:8000/api` | API base URL |
| `admin_email` | `admin@example.com` | Admin user email (from seeder) |
| `admin_password` | `password` | Admin user password |
| `creator_email` | `creator1@example.com` | Creator user email |
| `creator_password` | `password` | Creator user password |
| `backer_email` | `backer@example.com` | Backer user email |
| `backer_password` | `password` | Backer user password |
| `admin_token` | *(auto-saved)* | Saved after login |
| `creator_token` | *(auto-saved)* | Saved after login |
| `backer_token` | *(auto-saved)* | Saved after login |

### Authentication Flow (Postman)

1. **Login as Admin:**
   - `POST {{base_url}}/login`
   - Body: `{ "email": "{{admin_email}}", "password": "{{admin_password}}" }`
   - In "Tests" tab, add:
     ```js
     pm.environment.set("admin_token", pm.response.json().token)
     ```

2. **Login as Creator:**
   - `POST {{base_url}}/login`
   - Body: `{ "email": "{{creator_email}}", "password": "{{creator_password}}" }`
   - In "Tests" tab, add:
     ```js
     pm.environment.set("creator_token", pm.response.json().token)
     ```

3. **Login as Backer:**
   - Same as above, but with backer credentials and `backer_token` variable.

4. **Use tokens** in subsequent requests:
   - Headers: `Authorization: Bearer {{admin_token}}`

### Request Collection

A full Postman collection export is available at: `docs/CoFund-API.postman_collection.json`

---

## 14. Development Commands

### Running Migrations

```bash
# Run all migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Reset all migrations
php artisan migrate:reset

# Refresh (rollback + migrate)
php artisan migrate:refresh

# Seed after migration
php artisan db:seed
```

### Running Seeds

```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=CategorySeeder
```

### Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan serve` | Run dev server |
| `php artisan tinker` | Interactive REPL |
| `php artisan migrate` | Run migrations |
| `php artisan db:seed` | Run seeders |
| `php artisan queue:work` | Start queue worker |
| `php artisan schedule:run` | Run scheduled tasks |
| `php artisan route:list` | List all routes |
| `php artisan test` | Run PHPUnit tests |

### Console Commands (Custom)

| Command | Description |
|---------|-------------|
| `php artisan campaign:check-expired` | Check and process expired campaigns |
| `php artisan campaign:notify-deadline` | Send deadline approaching notifications |

### Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=ExampleTest

# Run with coverage
php artisan test --coverage
```

### Debugging

```bash
# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear all caches
php artisan optimize:clear

# Show last SQL queries
php artisan tinker
>>> DB::enableQueryLog();
>>> // ... trigger actions ...
>>> dd(DB::getQueryLog());
```

### Storage Link

Ensure storage files are accessible:

```bash
php artisan storage:link
```

### Scheduled Tasks Debugging

```bash
# Check schedule
php artisan schedule:list

# Run a specific command in the schedule
php artisan campaign:check-expired

# Simulate a specific date for testing
php artisan schedule:run --date="2026-08-31 00:05:00"
```

---

## API Response Conventions

### Success Responses

Standard success responses use the HTTP status code conventions:

- `200 OK` — For successful GET, PUT, DELETE
- `201 Created` — For successful POST
- `204 No Content` — (not currently used)

### Pagination

All list endpoints return paginated responses with the following structure:

```json
{
  "data": [...],
  "links": {
    "first": "http://localhost/api/endpoint?page=1",
    "last": "http://localhost/api/endpoint?page=10",
    "prev": null,
    "next": "http://localhost/api/endpoint?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "http://localhost/api/endpoint",
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

### Timestamps

All timestamps are returned in ISO 8601 format (UTC):
```
2026-08-26T10:00:00.000000Z
```

### Decimal Fields

All monetary values are returned as strings with 2 decimal places:
```json
"amount": "100000.00"
```

This prevents floating-point precision issues on the client side.
