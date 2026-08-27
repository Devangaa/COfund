# Architecture & Infrastructure

Detailed documentation on the backend architecture, background jobs, queue system, cron-based schedulers, and infrastructure requirements for the CoFund platform.

---

## Table of Contents

1. [Architecture Layers](#1-architecture-layers)
2. [Event-Driven System](#2-event-driven-system)
3. [Background Jobs](#3-background-jobs)
4. [Queue System](#4-queue-system)
5. [Cron Scheduler](#5-cron-scheduler)
6. [Email System](#6-email-system)
7. [File Storage](#7-file-storage)
8. [Production Deployment](#8-production-deployment)
9. [Infrastructure Checklist](#9-infrastructure-checklist)

---

## 1. Architecture Layers

The CoFund backend follows a layered architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────┐
│                    HTTP Router                           │
│  Laravel Routes (routes/api.php)                        │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Middleware Layer                          │
│  - auth:sanctum, role:*, verified, throttle:*           │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Controller Layer                          │
│  - Thin controllers, delegate to services               │
│  - Form Requests handle validation                      │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Service Layer (Business Logic)            │
│  - AuthService, CampaignService, BackingService         │
│  - WalletService, TransactionService, UserService        │
│  - TierService, CampaignUpdateService, ImageService     │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│               Event System                              │
│  Events → Listeners → Notifications / Emails / Jobs     │
└───────────────┬─────┬─────┬───────────────────────────────┘
                │     │     │
    ┌───────────▼──┐ ┌▼───┐ ┌▼──────────────┐
    │   Database   │ │Filesystem│ │Email Service│
    │   (MySQL)    │ │(Local)    │ │(SMTP/Mail)│
    └──────────────┘ └──────────┘ └─────────────┘
```

### Layer Details

| Layer | Responsibility | Key Files |
|-------|----------------|-----------|
| **Router** | Maps URLs to controllers; applies middleware | `routes/api.php` |
| **Middleware** | Auth, role-checking, rate limiting, email verification | `app/Http/Middleware/*.php` |
| **Controllers** | Request handling; delegates to services; returns Resources | `app/Http/Controllers/Api/**/*.php` |
| **Form Requests** | Validation and authorization per request | `app/Http/Requests/*.php` |
| **Services** | Core business logic; DB transactions; event firing | `app/Services/*.php` |
| **Events/Listeners** | Decoupled side-effects (notifications, emails, jobs) | `app/Events/*`, `app/Listeners/*` |
| **Jobs** | Long-running or background tasks | `app/Jobs/*.php` |
| **Models** | Data access; relationships; accessors/mutators | `app/Models/*.php` |
| **Resources** | JSON transformation for API responses | `app/Http/Resources/*.php` |

---

## 2. Event-Driven System

The application uses Laravel's event system to decouple side-effects from core business logic. This follows the **Observer Pattern** with the Publish-Subscribe variant.

### Available Events

Events are defined in `app/Events/*.php` and registered in `app/Providers/EventServiceProvider.php`.

```php
// Event structure
class CampaignApproved extends ShouldDispatch
{
    use Dispatchable, SerializesModels;

    public function __construct(public Campaign $campaign) {}
}
```

### Event Registration

All events are explicitly registered (no auto-discovery):

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    CampaignApproved::class => [
        HandleCampaignApproved::class,
    ],
    CampaignRejected::class => [
        HandleCampaignRejected::class,
    ],
    CampaignFunded::class => [
        HandleCampaignFunded::class,
    ],
    BackingCreated::class => [
        HandleBackingCreated::class,
    ],
    DepositProcessed::class => [
        [HandleWalletTransaction::class, 'handleDeposit'],
    ],
    WithdrawalProcessed::class => [
        [HandleWalletTransaction::class, 'handleWithdrawal'],
    ],
];
```

### Method-Based Listeners

`DepositProcessed` and `WithdrawalProcessed` both use the same listener class `HandleWalletTransaction`, but route to different methods:

```php
// EventServiceProvider
DepositProcessed::class => [
    [HandleWalletTransaction::class, 'handleDeposit'],
],
WithdrawalProcessed::class => [
    [HandleWalletTransaction::class, 'handleWithdrawal'],
],
```

### Transactional Event Safety

Events are fired using `DB::afterCommit()` to ensure they only execute after the database transaction commits successfully:

```php
DB::transaction(function () use ($campaign) {
    // ... update campaign ...
    
    // Fire events after commit
    $campaign->load('creator');
    event(new CampaignApproved($campaign));
});
```

This prevents firing events for rolled-back transactions.

### ⚠️ Unregistered Events

`UserSuspended` and `UserUnsuspended` events are dispatched in `UserService` but **not registered** in `EventServiceProvider::$listen`. Since `shouldDiscoverEvents()` returns `false`, no listeners will fire.

---

## 3. Background Jobs

Jobs are used for operations that:
- May take more than 1-2 seconds
- Can run independently of the HTTP request
- Should retry on failure

### Job List

| Job | File | Implements | Dispatched By | Purpose |
|-----|------|------------|---------------|---------|
| `DisburseCampaignJob` | `app/Jobs/DisburseCampaignJob.php` | `ShouldQueue` | `HandleCampaignFunded` listener | Disburse funds to campaign creator |
| `RefundBackersJob` | `app/Jobs/RefundBackersJob.php` | `ShouldQueue` | `CheckExpiredCampaigns` command | Refund all backers of failed campaign |

### Job Flow

```
CampaignFunded Event
    ↓
HandleCampaignFunded Listener (synchronous)
    ↓
DisburseCampaignJob::dispatch($campaign)
    ↓
(If QUEUE_CONNECTION=sync: runs immediately)
    ↓
TransactionService::disburseCampaign($campaign)
    ↓
- Calculate 5% platform fee
- Deposit 95% to creator balance
- Create DISBURSEMENT + PLATFORM_FEE transactions
- Create in-app notification + email to creator
```

```
CheckExpiredCampaigns Command
    ↓
For each FAILED campaign:
    RefundBackersJob::dispatch($campaign)
    ↓
TransactionService::refundBackers($campaign)
    ↓
- Get all non-refunded backings
- Deposit full amount to each backer
- Create REFUND transactions
- Update backing status to 'refunded'
- Create in-app notifications + emails
```

### Running Jobs

#### With Sync Driver (Default)

Jobs run inline during the HTTP request. No worker process needed.

```bash
php artisan campaign:check-expired
```

#### With Database Queue Driver

Jobs are stored in the `jobs` table and processed by worker processes.

```bash
# Configure in .env
QUEUE_CONNECTION=database

# Run migrations for jobs table
php artisan queue:table
php artisan migrate

# Start worker
php artisan queue:work
```

### Job Retry & Failure Handling

Each job can be configured with:
- `$tries` property (max retry attempts)
- `$timeout` property (max seconds per attempt)
- `$backoff` property (delay between retries)

Currently these are not explicitly set, so defaults apply:
- `tries`: 1 (no retry)
- `timeout`: 0 (no limit)
- Failed jobs are stored in `failed_jobs` table

### Monitoring Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry a failed job
php artisan queue:retry {job-id-or-uuid}

# Flush all failed jobs
php artisan queue:flush
```

---

## 4. Queue System

Currently configured as `sync` by default, but designed to be swappable for production.

### Current Configuration

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'sync'),

'connections' => [
    'sync' => [
        'driver' => 'sync',
    ],
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
    // ... redis, beanstalkd, sqs configs
],
```

### Queue Table Schema

When using the `database` or `redis` driver, the following table is required (for database driver only):

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | PK |
| `queue` | varchar | Queue name |
| `payload` | longtext | Serialized job data |
| `attempts` | integer | Number of attempts |
| `reserved_at` | timestamp | When job was reserved |
| `available_at` | timestamp | When job becomes available |
| `created_at` | timestamp | Creation timestamp |

### Switching to Async Queue

To use asynchronous queue processing:

1. **Set `.env`**:
   ```env
   QUEUE_CONNECTION=redis
   ```

2. **Create queue table** (only needed for database driver):
   ```bash
   php artisan queue:table
   php artisan migrate
   ```

3. **Start worker**:
   ```bash
   php artisan queue:work redis
   ```

> Note: This project is currently configured to use Redis as the default queue connection. The `queue-setup.md` file in the docs folder provides detailed instructions for setting up Redis as the queue driver.

### Supervisor Configuration (Linux Production)

To keep the queue worker running in production:

```ini
# /etc/supervisor/conf.d/cofund-worker.conf
[program:cofund-worker]
command=php /var/www/cofund/backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/cofund-worker.log
stdout_logfile_maxbytes=10MB
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cofund-worker:*
```

### Queue Worker Options

| Option | Description |
|--------|-------------|
| `--sleep=3` | Seconds to sleep when no jobs are available |
| `--tries=3` | Max retry attempts per job |
| `--timeout=60` | Max seconds per job attempt |
| `--queue=high,default` | Process queues in priority order |

### Queue Monitoring

View queue stats via artisan commands:

```bash
# Check job processing statistics
php artisan queue:work --verbose

# View failed jobs
php artisan queue:failed

# Check queue size (database driver only)
php artisan tinker
>>> DB::table('jobs')->count()
```

---

## 5. Cron Scheduler

Laravel's task scheduler allows defining scheduled tasks in code, requiring only **one** cron entry on the server.

### Scheduler Configuration

Defined in `app/Console/Kernel.php` → `schedule()` method:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('campaign:check-expired')
        ->dailyAt('00:05');

    $schedule->command('campaign:notify-deadline')
        ->dailyAt('09:00');
}
```

### Scheduled Tasks

| Task | Frequency | Description |
|------|-----------|-------------|
| `campaign:check-expired` | Daily at 00:05 | Process campaigns that passed deadline |
| `campaign:notify-deadline` | Daily at 09:00 | Send deadline warnings to backers |

### Task Details

#### `campaign:check-expired` (`CheckExpiredCampaigns` command)

**File**: `app/Console/Commands/CheckExpiredCampaigns.php`

**Logic**:
1. Query campaigns where `status = 'active'` AND `deadline < NOW()`
2. For each expired campaign:
   - **If funded** (`collected_amount >= target_amount`):
     - Set `status = 'success'`
     - Dispatch `DisburseCampaignJob` → credits creator's balance (95%) + records 5% platform fee
   - **If not funded**:
     - Set `status = 'failed'`
     - Dispatch `RefundBackersJob` → refunds all backers

**✅ Bug fixed**: The `NotifyDeadlineApproaching` command previously referenced undefined variables `$countH3` and `$countH1`. This has been fixed — the command now outputs the total count of notifications sent.

#### `campaign:notify-deadline` (`NotifyDeadlineApproaching` command)

**File**: `app/Console/Commands/NotifyDeadlineApproaching.php`

**Logic**:
1. Find campaigns with deadline exactly 3 days and 1 day ahead
2. For each campaign, collect distinct backer user IDs
3. Mass insert `Notification` records with `type = 'deadline_approaching'`

---

### Cron Entry Requirement

Only **one** cron entry is needed on the production server:

```bash
* * * * * cd /var/www/cofund/backend && php artisan schedule:run >> /dev/null 2>&1
```

This is the **only** cron entry that must be configured. Laravel's scheduler handles the rest internally based on `Kernel::schedule()`.

### Manual Execution

You can run scheduled commands manually for testing:

```bash
# Run check-expired manually
php artisan campaign:check-expired

# Run notify-deadline manually
php artisan campaign:notify-deadline

# Simulate running the scheduler (for debugging)
php artisan schedule:run

# List scheduled tasks
php artisan schedule:list
```

### Time Zone Considerations

The scheduler uses the application's default timezone (`UTC` per `config/app.php`).

To run a specific task at a specific timezone:

```php
$schedule->command('campaign:check-expired')
    ->dailyAt('00:05')
    ->timezone('Asia/Jakarta');
```

### Debugging the Scheduler

```bash
# View all scheduled commands
php artisan schedule:list

# View the scheduler's cron entries
crontab -l

# Clear scheduler cache if changes aren't reflected
php artisan schedule:clear-cache
```

---

## 6. Email System

### Mail Configuration

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=hello@cofund.test
```

For local development, Mailpit is used (a debugging SMTP server that captures emails without sending them).

### Mail Driver Switch to Production

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

### Email Queueing

Currently, all emails are sent **synchronously** during the request lifecycle:

```php
// In listeners
Mail::to($user->email)->send(new CampaignApproved($creator, $campaign));
```

For production, consider queuing emails:

```php
// Add to mailable class
class CampaignApproved extends Mailable implements ShouldQueue
{
    // ...
}

// Or configure in config/queue.php
'failed' => [
    'driver' => 'database-uuids',
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

### Available Mailables

| Mailable | File | Template | Sent When |
|----------|------|----------|-----------|
| `CampaignApproved` | `app/Mail/CampaignApproved.php` | `mail.campaign-approved` | Admin approves a campaign |
| `CampaignRejected` | `app/Mail/CampaignRejected.php` | `mail.campaign-rejected` | Admin rejects a campaign |
| `BackingConfirmation` | `app/Mail/BackingConfirmation.php` | `mail.backing-confirmation` | Backer creates a backing |
| `DisbursementProcessed` | `app/Mail/DisbursementProcessed.php` | `mail.disbursement` | Campaign funds are disbursed |
| `RefundProcessed` | `app/Mail/RefundProcessed.php` | `mail.refund` | Backer is refunded |

### Email Suppression Rules

Emails are only sent if the recipient has `email_verified_at` set:

```php
if ($creator->email_verified_at) {
    Mail::to($creator->email)->send(new CampaignApproved(...));
}
```

---

## 7. File Storage

### Storage Disks

| Disk | Driver | Path | Use Case |
|------|--------|------|----------|
| `local` | local | `storage/app/` | Private files |
| `public` | local | `storage/app/public/` | Publicly accessible files |
| `campaigns` | local | `storage/app/public/campaigns/` | Campaign images |

### Storage Configuration

```php
// config/filesystems.php
'default' => env('FILESYSTEM_DISK', 'local'),

'disks' => [
    'local' => ['driver' => 'local', 'root' => storage_path('app')],
    'public' => [
        'driver' => 'local',
        'root' => storage_path('public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
    'campaigns' => [
        'driver' => 'local',
        'root' => storage_path('public/campaigns'),
        'url' => env('APP_URL').'/storage/campaigns',
        'visibility' => 'public',
    ],
],
```

### Storage Commands

```bash
# Create storage symlink
php artisan storage:link

# Create custom symlink for campaigns disk
php artisan storage:link campaigns
```

### Cleanup Orphaned Files

When the "delete all images" validation bug occurs (see Known Issues in general docs), some image files may be deleted from disk but their database records remain. To clean up:

```bash
# Find and delete files that have no DB record
php artisan tinker
>>> $images = CampaignImage::whereNull('deleted_at')->get();
>>> foreach($images as $img) {
...     if (!Storage::disk('campaigns')->exists(basename($img->url))) {
...         $img->forceDelete(); // permanently remove orphaned DB record
...     }
... }
```

### File Validation

Image uploads are validated:
- Max size: 2MB (`max:2048`)
- Allowed formats: `jpeg`, `png`, `jpg`, `gif`
- MIME type checking via PHP's `fileinfo`

---

## 8. Production Deployment

### Server Requirements

| Component | Minimum |
|-----------|---------|
| PHP | 8.1+ with: `ctype`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `session`, `tokenizer`, `xml` |
| Composer | 2.x |
| Web Server | Apache 2.4+ or Nginx 1.14+ |
| Database | MySQL 8.0+ or MariaDB 10.4+ |
| Cache | Redis (recommended) or file |
| Queue | Redis (recommended) or database |

### Deployment Steps

1. **Clone and install dependencies**:
   ```bash
   git clone <repo>
   composer install --optimize-autoloader --no-dev
   npm install && npm run build
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   # Edit .env with production values
   ```

3. **Set up storage symlinks**:
   ```bash
   php artisan storage:link
   ```

4. **Run migrations**:
   ```bash
   php artisan migrate --force
   ```

5. **Optimize**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. **Set up cron**:
   ```bash
   * * * * * cd /var/www/cofund/backend && php artisan schedule:run >> /dev/null 2>&1
   ```

7. **Start queue workers (if using async queue)**:
   ```bash
   # Use supervisor or systemd to manage workers
   php artisan queue:work --daemon
   ```

### Web Server Configuration

#### Apache (`public/.htaccess` exists by default)

```apache
<Directory /var/www/cofund/backend/public>
    AllowOverride All
    Require all granted
</Directory>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name cofund.test;
    root /var/www/cofund/backend/public;

    add_header X-Frame-Options "SAMEORIGIN");
    add_header X-Content-Type-Options "nosniff");

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### SSL (Production)

Ensure the application forces HTTPS:

```php
// AppServiceProvider::boot()
if (App::environment('production')) {
    \URL::forceScheme('https');
}

// Or in .env
FORCE_HTTPS=true
```

### Environment-Specific Config

```env
# .env.production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.cofund.com

DB_HOST=127.0.0.1
DB_DATABASE=cofund_prod
DB_USERNAME=cofund_user
DB_PASSWORD=secure_password

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@cofund.com
MAIL_PASSWORD=mail_password
```

---

## 9. Infrastructure Checklist

### ✅ Must-Have

- [ ] One cron entry for Laravel scheduler
- [ ] Queue worker process (if QUEUE_CONNECTION ≠ sync)
- [ ] Storage symlink (`php artisan storage:link`)
- [ ] APP_KEY generated
- [ ] SSL/TLS configured for HTTPS
- [ ] Database backups configured
- [ ] `.env` file excluded from version control (`.gitignore`)

### ⚠️ Critical Issues to Fix Before Production

- [ ] **Register `UserSuspended` and `UserUnsuspended` events in `EventServiceProvider`**
- [ ] **Create `config/cofund.php` with platform fee setting (currently 5% hardcoded vs 10% in config fallback)**
- [ ] **Fix `database.md` migration `down()` method for FULLTEXT index**

### 🛡️ Security

- [ ] `.env` is NOT in `.gitignore` (currently tracked in repo)
- [ ] APP_DEBUG=false in production
- [ ] HTTPS enforced
- [ ] Rate limiting active on auth endpoints
- [ ] Passwords hashed with bcrypt
- [ ] Sanctum tokens stored as hashes
- [ ] File uploads validated (MIME type + size)

### 📊 Monitoring

- [ ] Queue worker monitoring (Supervisor/Nova)
- [ ] Application logs (storage/logs)
- [ ] Failed jobs table monitoring
- [ ] Cron execution monitoring
- [ ] Database slow query log
- [ ] Uptime monitoring

### 📈 Scaling Considerations

| Component | Current | Recommended |
|-----------|---------|-------------|
| Cache | File | Redis |
| Queue | Sync | Redis |
| Session | File | Redis |
| Database | MySQL single | MySQL cluster (future) |
| Storage | Local | S3 compatible |
| Scheduler | Cron (1 node) | Multiple nodes with lock coordination |

### 🔄 Backup Strategy

1. **Database**: Daily full backups + hourly binlog
2. **Storage**: Sync `storage/app/public` to cloud storage
3. **Code**: Git repository + tags for releases
4. **Logs**: Rotate via logrotate or Laravel's daily log channel
