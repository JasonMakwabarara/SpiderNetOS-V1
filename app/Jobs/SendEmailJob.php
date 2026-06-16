<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $action;
    
    public function __construct($action)
    {
        $this->action = $action;
    }
    
    public function handle()
    {
        $to = $this->action['to'] ?? '';
        $subject = $this->action['subject'] ?? 'Flow Executed';
        $body = $this->action['body'] ?? 'The flow was executed successfully.';
        
        if ($to) {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject)
                        ->from(env('MAIL_FROM_ADDRESS', 'noreply@spidernetos.com'), 'SpiderNetOS');
            });
        }
    }
}
