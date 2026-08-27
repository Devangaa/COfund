# Database Schema & Relations

Database documentation for the CoFund crowdfunding platform backend.

**Database Engine:** MySQL 8.0+  
**Charset:** `utf8mb4`  
**Collation:** `utf8mb4_unicode_ci` (default)  
**Strict Mode:** Enabled (by default in MySQL 8)

---

## Migration Order

Migrations should be run in the order of their filenames (Laravel handles this automatically via the `migrations` table):

| No | Migration File | Table | Description |
|----|---------------|-------|-------------|
| 1 | `2014_10_12_000000_create_users_table.php` | `users` | User accounts |
| 2 | `2014_10_12_100000_create_password_reset_tokens_table.php` | `password_reset_tokens` | Password reset tokens |
| 3 | `2019_08_19_000000_create_failed_jobs_table.php` | `failed_jobs` | Failed queue jobs |
| 4 | `2019_12_14_000001_create_personal_access_tokens_table.php` | `personal_access_tokens` | Sanctum API tokens |
| 5 | `2024_01_01_000001_create_categories_table.php` | `categories` | Campaign categories |
| 6 | `2024_01_02_000000_create_campaigns_table.php` | `campaigns` | Crowdfunding campaigns |
| 7 | `2024_01_02_100000_create_campaign_images_table.php` | `campaign_images` | Campaign image assets |
| 8 | `2024_01_03_000000_create_campaign_tiers_table.php` | `campaign_tiers` | Reward tiers |
| 9 | `2024_01_03_100000_create_campaign_updates_table.php` | `campaign_updates` | Campaign update posts |
| 10 | `2024_01_04_000000_create_backings_table.php` | `backings` | User backing pledges |
| 11 | `2024_01_04_100000_create_transactions_table.php` | `transactions` | Financial transaction records |
| 12 | `2024_01_05_000000_create_notifications_table.php` | `notifications` | In-app notifications |
| 13 | `2026_08_24_063448_add_deleted_at_to_backings_and_transactions_table.php` | — | Soft-delete columns |
| 14 | `2026_08_26_165000_add_fulltext_search_to_campaigns_table.php` | — | FULLTEXT index |

---

## Table Schemas

### `users`

Stores user accounts across all roles (backer, creator, admin).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `name` | `varchar(255)` | NOT NULL | — | Full name |
| `email` | `varchar(255)` | NOT NULL, UNIQUE | — | Email address |
| `password` | `varchar(255)` | NOT NULL | — | Hashed password |
| `role` | `enum` | NOT NULL | `'backer'` | One of: `backer`, `creator`, `admin` |
| `balance` | `decimal(15,2)` | NOT NULL | `0.00` | Wallet balance |
| `email_verified_at` | `timestamp` | NULLABLE | NULL | Email verification timestamp |
| `is_suspended` | `tinyint(1)` | NOT NULL | `0` | Whether account is suspended |
| `suspended_at` | `timestamp` | NULLABLE | NULL | When account was suspended |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |
| `remember_token` | `varchar(100)` | NULLABLE | NULL | Remember me token |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`email`)

---

### `password_reset_tokens`

Standard Laravel password reset tokens.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `email` | `varchar(255)` | PK | — | User email |
| `token` | `varchar(255)` | NOT NULL | — | Reset token |
| `created_at` | `timestamp` | NULLABLE | NULL | Token creation timestamp |

**Indexes:**
- PRIMARY KEY (`email`)

---

### `failed_jobs`

Stores failed queue jobs for debugging.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `uuid` | `varchar(255)` | NOT NULL, UNIQUE | — | UUID |
| `connection` | `varchar(255)` | NOT NULL | — | Queue connection name |
| `queue` | `varchar(255)` | NOT NULL | — | Queue name |
| `payload` | `longtext` | NOT NULL | — | Serialized payload |
| `exception` | `longtext` | NOT NULL | — | Serialized exception |
| `failed_at` | `timestamp` | NOT NULL | CURRENT_TIMESTAMP | When job failed |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`uuid`)

---

### `personal_access_tokens`

Sanctum API token storage.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `tokenable_type` | `varchar(255)` | NOT NULL | — | Model class name |
| `tokenable_id` | `bigint UNSIGNED` | NOT NULL | — | Model ID |
| `name` | `varchar(255)` | NOT NULL | — | Token name |
| `token` | `varchar(64)` | NOT NULL, UNIQUE | — | Hashed token value |
| `abilities` | `text` | NULLABLE | NULL | JSON abilities array |
| `last_used_at` | `timestamp` | NULLABLE | NULL | Last used timestamp |
| `expires_at` | `timestamp` | NULLABLE | NULL | Expiration (currently NULL) |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`token`)
- KEY (`tokenable_type`, `tokenable_id`) — polymorphic

---

### `categories`

Campaign categories (soft-deletable).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `name` | `varchar(255)` | NOT NULL | — | Category name |
| `slug` | `varchar(255)` | NOT NULL, UNIQUE | — | URL-friendly slug |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`slug`)

---

### `campaigns`

Crowdfunding campaigns (soft-deletable).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id, ON DELETE CASCADE | — | Campaign creator |
| `category_id` | `bigint UNSIGNED` | FK → categories.id | — | Campaign category |
| `title` | `varchar(100)` | NOT NULL | — | Campaign title |
| `slug` | `varchar(255)` | NOT NULL, UNIQUE | — | URL-friendly slug |
| `description` | `text` | NOT NULL | — | Campaign description |
| `target_amount` | `decimal(15,2)` | NOT NULL | — | Funding target |
| `collected_amount` | `decimal(15,2)` | NOT NULL | `0.00` | Collected so far |
| `deadline` | `date` | NOT NULL | — | Campaign deadline |
| `status` | `enum` | NOT NULL | `draft` | One of: `draft`, `review`, `active`, `success`, `failed` |
| `video_url` | `varchar(255)` | NULLABLE | NULL | Video embed URL |
| `rejection_note` | `text` | NULLABLE | NULL | Admin rejection reason |
| `reviewed_by` | `bigint UNSIGNED` | FK → users.id | NULL | Admin who reviewed |
| `reviewed_at` | `timestamp` | NULLABLE | NULL | Review timestamp |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`slug`)
- FOREIGN KEY (`user_id`) ON DELETE CASCADE
- FOREIGN KEY (`category_id`)
- FOREIGN KEY (`reviewed_by`)
- FULLTEXT KEY (`fulltext_search`) on (`title`, `description`) — added in migration 14

---

### `campaign_images`

Campaign image assets (soft-deletable).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, ON DELETE CASCADE | — | Owning campaign |
| `url` | `varchar(255)` | NOT NULL | — | File path on disk |
| `is_primary` | `tinyint(1)` | NOT NULL | `0` | Whether primary image |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`campaign_id`) ON DELETE CASCADE

---

### `campaign_tiers`

Reward tiers for campaigns (soft-deletable).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, ON DELETE CASCADE | — | Owning campaign |
| `name` | `varchar(255)` | NOT NULL | — | Tier name |
| `min_amount` | `decimal(15,2)` | NOT NULL | — | Minimum backing amount |
| `quota` | `integer` | NOT NULL | `0` | Max backers (0 = unlimited) |
| `remaining_quota` | `integer` | NOT NULL | `0` | Remaining slots |
| `reward_description` | `text` | NULLABLE | NULL | Reward description |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`campaign_id`) ON DELETE CASCADE

---

### `campaign_updates`

Campaign update posts (soft-deletable).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, ON DELETE CASCADE | — | Owning campaign |
| `title` | `varchar(255)` | NOT NULL | — | Update title |
| `content` | `text` | NOT NULL | — | Update content |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`campaign_id`) ON DELETE CASCADE

---

### `backings`

User backing pledges (soft-deletable).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id | — | Backing user |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id | — | Campaign being backed |
| `tier_id` | `bigint UNSIGNED` | FK → campaign_tiers.id, NULLABLE | NULL | Reward tier (if any) |
| `amount` | `decimal(15,2)` | NOT NULL | — | Backing amount |
| `status` | `enum` | NOT NULL | `pending` | One of: `pending`, `completed`, `refunded` |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete — added in migration 13 |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`user_id`)
- FOREIGN KEY (`campaign_id`)
- FOREIGN KEY (`tier_id`)

---

### `transactions`

Financial transaction records (soft-deletable).

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id | — | Owning user |
| `backing_id` | `bigint UNSIGNED` | FK → backings.id, NULLABLE | NULL | Backer (if backing-related) |
| `campaign_id` | `bigint UNSIGNED` | FK → campaigns.id, NULLABLE | NULL | Campaign (if campaign-related) |
| `type` | `enum` | NOT NULL | `pending` | One of: `payment`, `refund`, `disbursement`, `platform_fee`, `deposit`, `withdrawal` |
| `amount` | `decimal(15,2)` | NOT NULL | — | Transaction amount |
| `status` | `enum` | NOT NULL | `pending` | One of: `pending`, `success`, `failed` |
| `reference` | `varchar(255)` | NULLABLE | NULL | External reference (payment gateway ID) |
| `deleted_at` | `timestamp` | NULLABLE | NULL | Soft delete — added in migration 13 |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**⚠️ Critical Issue:** The migration for `type` column only defines: `ENUM('payment', 'refund', 'disbursement', 'platform_fee')`. The values `deposit` and `withdrawal` are missing from the database enum, though they exist in the `TransactionType` PHP enum. This will cause INSERT failures under MySQL strict mode.

**Indexes:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`user_id`)
- FOREIGN KEY (`backing_id`)
- FOREIGN KEY (`campaign_id`)

---

### `notifications`

In-app notifications for users.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | `bigint UNSIGNED` | PK, auto-increment | — | Primary key |
| `user_id` | `bigint UNSIGNED` | FK → users.id, ON DELETE CASCADE | — | Recipient user |
| `type` | `varchar(255)` | NOT NULL | — | Notification type (e.g., `campaign_approved`, `backing_created`, `deposit`, `withdrawal`) |
| `title` | `varchar(255)` | NOT NULL | — | Notification title |
| `body` | `text` | NOT NULL | — | Notification body |
| `data` | `json` | NULLABLE | NULL | Additional JSON data |
| `read_at` | `timestamp` | NULLABLE | NULL | When notification was read |
| `created_at` | `timestamp` | NULLABLE | NULL | Created timestamp |
| `updated_at` | `timestamp` | NULLABLE | NULL | Updated timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- FOREIGN KEY (`user_id`) ON DELETE CASCADE

---

## Foreign Key Constraint Summary

| Child Table | Column | Parent Table | Parent Column | On Delete | On Update |
|------------|--------|--------------|---------------|-----------|-----------|
| `campaigns` | `user_id` | `users` | `id` | CASCADE | RESTRICT |
| `campaigns` | `category_id` | `categories` | `id` | RESTRICT | RESTRICT |
| `campaigns` | `reviewed_by` | `users` | `id` | SET NULL | RESTRICT |
| `campaign_images` | `campaign_id` | `campaigns` | `id` | CASCADE | RESTRICT |
| `campaign_tiers` | `campaign_id` | `campaigns` | `id` | CASCADE | RESTRICT |
| `campaign_updates` | `campaign_id` | `campaigns` | `id` | CASCADE | RESTRICT |
| `backings` | `user_id` | `users` | `ids` | RESTRICT | RESTRICT |
| `backings` | `campaign_id` | `campaigns` | `id` | RESTRICT | RESTRICT |
| `backings` | `tier_id` | `campaign_tiers` | `id` | SET NULL | RESTRICT |
| `transactions` | `user_id` | `users` | `id` | RESTRICT | RESTRICT |
| `transactions` | `backing_id` | `backings` | `id` | SET NULL | RESTRICT |
| `transactions` | `campaign_id` | `campaigns` | `id` | SET NULL | RESTRICT |
| `notifications` | `user_id` | `users` | `id` | CASCADE | RESTRICT |

> **Note on FK behavior:** Unlike many Laravel defaults, most foreign keys in this project use `RESTRICT` (no action) on delete — meaning related records prevent deletion of the parent. Only `campaign_images`, `campaign_tiers`, `campaign_updates` use `CASCADE` (deletion cascades), and `notifications` uses `CASCADE`.

## Eloquent Relationship Map

```
User
├── hasMany → Campaigns (as creator)
├── hasMany → Backings (as backer)
├── hasMany → Transactions
├── hasMany → Notifications
└── hasMany → Campaigns (as reviewer, via reviewed_by)

Campaign
├── belongsTo → User (creator)
├── belongsTo → Category
├── belongsTo → User (reviewer)
├── hasMany → CampaignImages
├── hasMany → CampaignTiers
├── hasMany → CampaignUpdates
├── hasMany → Backings
└── hasMany → Transactions

CampaignTier
└── belongsTo → Campaign
    └── hasMany → Backings

Backings
├── belongsTo → User
├── belongsTo → Campaign
└── belongsTo → CampaignTier

Transaction
├── belongsTo → User
├── belongsTo → Backing
└── belongsTo → Campaign

Category
└── hasMany → Campaigns

Notification
└── belongsTo → User
```

## Enum Column Reference

### `users.role`

| Value | Description |
|-------|-------------|
| `backer` | Default role; can browse, back, deposit, withdraw |
| `creator` | Can create and manage campaigns |
| `admin` | Full access; can moderate campaigns, manage users |

### `campaigns.status`

| Value | Description |
|-------|-------------|
| `draft` | Initial state after creation |
| `review` | Submitted for admin review |
| `active` | Published, accepting backings |
| `success` | Reached funding target |
| `failed` | Deadline passed without reaching target |

### `backings.status`

| Value | Description |
|-------|-------------|
| `pending` | Backing created but not finalized |
| `completed` | Payment confirmed |
| `refunded` | Backing refunded (campaign failed) |

### `transactions.type` ⚠️

| Value | Description | DB Enum? |
|-------|-------------|----------|
| `payment` | Backing payment | ✅ Yes |
| `disbursement` | Campaign funds released to creator | ✅ Yes |
| `refund` | Refunded backer amount | ✅ Yes |
| `platform_fee` | Platform commission | ✅ Yes |
| `deposit` | User wallet deposit | ❌ **MISSING** |
| `withdrawal` | User wallet withdrawal | ❌ **MISSING** |

### `transactions.status`

| Value | Description |
|-------|-------------|
| `pending` | Transaction pending |
| `success` | Transaction successful |
| `failed` | Transaction failed |

## FK Guessing Rules

Laravel's `Back` command (for generating models) uses these conventions to guess relationships:

1. **Foreign Key Discovery**: Looks at FK constraint names
   - `campaigns_user_id_foreign` → `User` model
   - `campaigns_category_id_foreign` → `Category` model

2. **Relationship Type Guessing**:
   - `hasMany`: If the foreign key name contains the parent model's class name
   - `belongsTo`: Reverse of `hasMany`
   - `hasOne`: Same as `hasMany` but singular
   - `belongsToMany`: If a pivot table exists with `_<related>_id` pattern

3. **Column-to-Model Mapping**:
   - `_id` suffix + known model name → uses that model
   - Unknown suffix → guesses based on column name

## Soft Deletes

The following tables use soft deletes (`deleted_at` column + `SoftDeletes` trait):

| Table | Model |
|-------|-------|
| `categories` | `Category` |
| `campaigns` | `Campaign` |
| `campaign_images` | `CampaignImage` |
| `campaign_tiers` | `CampaignTier` |
| `campaign_updates` | `CampaignUpdate` |
| `backings` | `Backing` |
| `transactions` | `Transaction` |

> **Note:** `users` and `notifications` tables do NOT use soft deletes. Deleting a user permanently removes the record, and their backings, transactions, and notifications will be orphaned.

## File Listing

| Path | Description |
|------|-------------|
| `database/migrations/` | All 14 migration files |
| `database/seeders/` | 6 seeder files + `DatabaseSeeder.php` |
| `database/factories/` | 1 factory file (`UserFactory.php`) |

### Seeders

| File | Creates |
|------|---------|
| `CategorySeeder.php` | 6 categories (Teknologi, Seni, Lingkungan, Sosial, Pendidikan, Kesehatan) |
| `UserSeeder.php` | 4 users (backer, creator1, creator2, admin) — all password: `password` |
| `CampaignSeeder.php` | 2 campaigns (1 per creator) with primary images |
| `CampaignTierSeeder.php` | 3 tiers total (2 for creator1, 1 for creator2) |
| `BackingSeeder.php` | 3 backings with manually adjusted `collected_amount` |

### Factory

| File | Model | Used By |
|------|-------|---------|
| `UserFactory.php` | `User` | `UserFactory` — used by `User::factory()` calls (none currently) |
