<?php

namespace App\Jobs;

use App\Models\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected array $action)
    {
    }

    public function handle(): void
    {
        $to = $this->action['to'] ?? '';
        $subject = $this->action['subject'] ?? 'SpiderNetOS notification';
        $body = $this->action['body'] ?? 'The flow was executed successfully.';

        if ($to === '') {
            return;
        }

        $integrationId = $this->action['integration_id'] ?? null;
        if ($integrationId) {
            $integration = Integration::find($integrationId);
            if ($integration) {
                $this->sendViaIntegration($integration, $to, $subject, $body);

                return;
            }
        }

        Mail::raw($body, function ($message) use ($to, $subject) {
            $message->to($to)
                ->subject($subject)
                ->from(config('mail.from.address', 'noreply@spidernetos.com'), config('mail.from.name', 'SpiderNetOS'));
        });
    }

    protected function sendViaIntegration(Integration $integration, string $to, string $subject, string $body): void
    {
        $creds = $integration->credentials ?? [];
        $from = $creds['from'] ?? $creds['username'] ?? config('mail.from.address');
        $fromName = $integration->name ?: config('mail.from.name', 'SpiderNetOS');

        config([
            'mail.mailers.integration_smtp' => [
                'transport' => 'smtp',
                'host' => $creds['host'] ?? config('mail.mailers.smtp.host'),
                'port' => (int) ($creds['port'] ?? config('mail.mailers.smtp.port', 587)),
                'encryption' => $creds['encryption'] ?? config('mail.mailers.smtp.encryption'),
                'username' => $creds['username'] ?? $from,
                'password' => $creds['password'] ?? '',
                'timeout' => 15,
            ],
        ]);

        Mail::mailer('integration_smtp')->raw($body, function ($message) use ($to, $subject, $from, $fromName) {
            $message->to($to)->subject($subject)->from($from, $fromName);
        });
    }
}
