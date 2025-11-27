<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $limit = (int) config('mail.bulk.rate_limit_per_minute', 0);
        RateLimiter::for('mail-sender', function () use ($limit) {
            if ($limit <= 0) {
                return Limit::none();
            }

            return Limit::perMinute($limit)->by('mail-sender');
        });
    }
}
