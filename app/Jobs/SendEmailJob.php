<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $params;

    public $tries = 3;
    public $timeout = 30;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params, $queue = 'send_email')
    {
        $this->onQueue($queue);
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $mailLog = MailService::sendEmail($this->params);
        if ($mailLog['error']) {
            $this->release(); //发送失败将触发重试
        }
    }

    public function middleware(): array
    {
        $limitPerMinute = (int) config('mail.bulk.rate_limit_per_minute', 0);
        if ($limitPerMinute <= 0) {
            return [];
        }

        $releaseAfter = (int) config('mail.bulk.rate_limit_backoff', 5);

        return [
            (new RateLimited('mail-sender'))->backoff($releaseAfter),
        ];
    }
}
