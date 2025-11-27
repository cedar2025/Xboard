# High-Volume Mail Dispatch

This guide explains how to configure the new bulk mail pipeline so Xboard can safely deliver campaigns with more than 100k recipients.

## Overview

- Every email is pushed to the queue instead of being sent inline, which keeps the admin panel responsive.
- Recipients are streamed from the database with `chunkById`, so memory usage stays constant even with hundreds of thousands of rows.
- Queue workers can be scaled horizontally (Horizon/Laravel `queue:work`) to process the backlog.
- Optional rate limiting prevents SMTP providers from throttling the application.

## Configuration

Set the environment variables that control the dispatcher (defaults shown below):

```
MAIL_BULK_CHUNK_SIZE=1000            # How many users are read per DB chunk
MAIL_BULK_MAX_CHUNK_SIZE=5000        # Upper guard for the chunk size
MAIL_BULK_QUEUE=send_email           # Reminder/automation queue
MAIL_BULK_MASS_QUEUE=send_email_mass # Admin broadcast queue
MAIL_BULK_MEMORY_FLUSH_INTERVAL=2500 # How often gc_collect_cycles() runs
MAIL_BULK_RATE_PER_MINUTE=0          # Set > 0 to enable rate limiting
MAIL_BULK_RATE_LIMIT_BACKOFF=5       # Seconds to wait when throttled
```

> Tip: keep `QUEUE_CONNECTION=redis` and start enough Horizon workers to match the size of your campaigns (for example 10 workers × 5 processes will process 3k+ mails/minute on a typical SMTP provider).

## Sending 100k+ Emails

1. Filter the audience from **Admin → User Management** and click **Send Mail**. The API now accepts optional `chunk_size` and `queue` parameters to fine-tune throughput.
2. For automated reminders run `php artisan send:remindMail --chunk-size=2000 --force`. The command streams users in batches and only schedules jobs that still need notifications.
3. Keep `php artisan queue:work redis --queue=send_email,send_email_mass` (or Horizon) running. Workers honor the `MAIL_BULK_RATE_PER_MINUTE` limit so SMTP providers stay happy.
4. Track progress inside Horizon or by querying the `v2_mail_log` table.

With this pipeline a single worker can comfortably schedule 100k emails in a couple of minutes while the queue workers handle the actual delivery at the rate your SMTP provider allows.
