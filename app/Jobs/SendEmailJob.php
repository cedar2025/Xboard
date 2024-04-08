<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\MailLog;
use Postal\Client;
use Postal\Send\Message;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $params;

    public $tries = 3;
    public $timeout = 10;
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
        $driver = "";
        if (admin_setting('email_host')) {
            $driver = "SMTP";
            Config::set('mail.host', admin_setting('email_host', config('mail.host')));
            Config::set('mail.port', admin_setting('email_port', config('mail.port')));
            Config::set('mail.encryption', admin_setting('email_encryption', config('mail.encryption')));
            Config::set('mail.username', admin_setting('email_username', config('mail.username')));
            Config::set('mail.password', admin_setting('email_password', config('mail.password')));
        } elseif (admin_setting('email_postal_host')) {
            $driver = "Postal";
        }
        Config::set('mail.from.address', admin_setting('email_from_address', config('mail.from.address')));
        Config::set('mail.from.name', admin_setting('app_name', 'XBoard'));
        $params = $this->params;
        $email = $params['email'];
        $subject = $params['subject'];
        $params['template_name'] = 'mail.' . admin_setting('email_template', 'default') . '.' . $params['template_name'];
        try {
            switch ($driver) {
                case 'SMTP':
                    Mail::send(
                        $params['template_name'],
                        $params['template_value'],
                        function ($message) use ($email, $subject) {
                            $message->to($email)->subject($subject);
                        }
                    );
                    break;
                case 'Postal':
                    $senderName = Config::get('mail.from.name');
                    $senderAddress = Config::get('mail.from.address');
                    $client = new Client(admin_config('email_postal_host'), admin_config('email_postal_key'));
                    $message = new Message();
                    $message->to($email);
                    $message->from("$senderName <$senderAddress>");
                    $message->sender($senderAddress);
                    $message->subject($subject);
                    $message->htmlBody(view($params['template_name'], $params['template_value'])->render());
                    $client->send->message($message);
                    break;
                default:
                    break;
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $log = [
            'email' => $params['email'],
            'subject' => $params['subject'],
            'template_name' => $params['template_name'],
            'error' => isset($error) ? $error : NULL
        ];

        MailLog::create($log);
        $log['config'] = config('mail');
        return $log;
    }
}
