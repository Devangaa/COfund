# Redis Queue Driver Setup & Operations

Step-by-step guide for configuring and running Laravel Queue Worker with Redis on a Windows/Laragon development environment.

## 1. Prerequisites

### Redis Server (via Laragon)

Redis is provided as a Laragon service. To enable it:

1. Open **Laragon**
2. Go to **Tools** → **Quick app** → **Add "redis"** (URL: `https://redis.io`, name: `Redis`)
3. Or start Redis directly from the Laragon services panel (port 6379)

Verify Redis is running:

```bash
redis-cli ping
# Expected: PONG
```

No manual installation needed if Redis is started through Laragon.

### PHP Redis Client (via Composer)

Laravel supports two Redis client libraries:

| Library | Installation Method | Description |
|---------|--------------------|-------------|
| `phpredis` | PHP extension (C extension) | Faster, requires compiling the extension |
| `predis` | Composer package | Pure PHP, zero-extension, recommended for this setup |

This project uses `predis/predis`:

```bash
cd backend
composer require predis/predis
```

> No additional PHP extensions are required when using `predis`.

## 2. Environment Configuration

### .env

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DBNAME=0
```

Laravel automatically uses `predis` client when the `composer require predis/predis` is installed and no `phpredis` extension is loaded — no extra config needed.

## 3. Database Migration — Queue Tables

Create tables for tracking queued and failed jobs:

```bash
php artisan queue:table
php artisan migrate
```

Tables created:
- `jobs` — queued job storage
- `failed_jobs` — records of jobs that failed processing

## 4. Starting the Queue Worker

```bash
php artisan queue:work redis --tries=3 --timeout=90
```

### Command Options

| Option | Description |
|--------|-------------|
| `redis` | Queue connection name to process |
| `--tries=3` | Maximum number of retry attempts on job failure |
| `--timeout=90` | Maximum seconds a job can run before being killed |
| `--sleep=3` | Seconds to sleep when no new jobs are available (optional) |
| `--memory=128` | Memory limit in MB before worker restarts (optional) |

Leave the worker running — it continuously polls Redis for new jobs.

## 5. Jobs Processed via Queue

| Job | Trigger | Description |
|-----|---------|-------------|
| `DisburseCampaignJob` | `CampaignFunded` event | Disburses funds from escrow to campaign creator |
| `RefundBackersJob` | `CampaignFailed` event | Refunds backers of unsuccessful campaigns |

## 6. Production Deployment — Process Monitoring

For production, use a process monitor to keep workers running:

### Supervisor (Linux)

Create `/etc/supervisor/conf.d/cofund-worker.conf`:

```ini
[program:cofund-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cofund/backend/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/cofund-worker.log
stopwaitsecs=3600
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cofund-worker:*
```

### Windows — Service via NSSM

```bash
nssm install CofundWorker "C:\php\php.exe" "C:\laragon\www\COfund\backend\artisan" "queue:work" "redis" "--tries=3" "--timeout=90"
nssm set CofundWorker AppDirectory "C:\laragon\www\COfund\backend"
nssm start CofundWorker
```

## 7. Monitoring & Debugging

### Checking Queue Length

```bash
# Via Redis CLI
redis-cli LLEN queue:default  # Pending job count

# Via Laravel
php artisan tinker
>>> \Queue::connection('redis')->size()
```

### Viewing Failed Jobs

```bash
php artisan queue:failed
```

### Retrying / Clearing Jobs

```bash
php artisan queue:retry {id}        # Retry a specific failed job
php artisan queue:clear redis       # Clear all pending jobs
php artisan queue:flush             # Delete all failed jobs
```

### Deploying Without Downtime

```bash
php artisan queue:restart
```

Workers will finish current job then gracefully exit; the process monitor will automatically restart them.

## 8. Troubleshooting

### Redis Not Running

```bash
redis-cli ping
# If error: Connection refused
# → Start Redis in Laragon or via Windows service
```

### predis Not Found

```bash
composer require predis/predis
```

### Worker Shows “sync” Behavior

Ensure `QUEUE_CONNECTION=redis` in `.env`, then:

```bash
php artisan config:clear
php artisan cache:clear
```

Verify:

```bash
php artisan tinker
>>> config('queue.default')  // must return 'redis'
```

### Jobs Timing Out

Increase `--timeout` or investigate job logic:

```bash
php artisan queue:work redis --tries=3 --timeout=120
```

## 9. Verification

```bash
# 1. Redis running
redis-cli ping  # Expected: PONG

# 2. predis installed
php artisan tinker
>>> class_exists('Predis\Client')  // Expected: true

# 3. Queue connection works
>>> \Queue::connection('redis')->size()  // Expected: integer (0 if empty)

# 4. Dispatch a test job
>>> \Queue::push(new \App\Jobs\DisburseCampaignJob(1));
>>> \Queue::connection('redis')->size()  // Expected: 1 (queued, not run inline)

# 5. Process it
# Terminal 2:
php artisan queue:work redis --once
```
