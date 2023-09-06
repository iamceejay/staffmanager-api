<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Twilio\Rest\Client;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sid = '';
    protected $token = '';
    protected $sender = '';

    protected $recipient = NULL;
    protected $message = '';

    /**
     * Create a new job instance.
     */
    public function __construct($recipient, $message)
    {
        $this->recipient = $recipient;
        $this->message = $message;

        $this->sid = getenv('TWILIO_SID');
        $this->token = getenv('TWILIO_AUTH_TOKEN');
        $this->sender = getenv('TWILIO_NUMBER');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = new Client($this->sid, $this->token);
        $client->messages->create(
            $this->recipient,
            [
                'body' => $this->message,
                'from' => $this->sender
            ]
        );
    }
}
