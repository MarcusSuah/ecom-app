<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * Africa's Talking bulk SMS API.
 *
 * @see https://developers.africastalking.com/docs/sms/sending
 */
class AfricasTalkingSmsSender implements SmsSender
{
    public function __construct(
        private string $username,
        private string $apiKey,
        private ?string $senderId = null,
        private bool $sandbox = false,
    ) {}

    public function send(string $to, string $message): void
    {
        $host = $this->sandbox ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';

        Http::asForm()
            ->acceptJson()
            ->withHeaders(['apiKey' => $this->apiKey])
            ->timeout(10)
            ->retry(2, 500)
            ->post("{$host}/version1/messaging", array_filter([
                'username' => $this->username,
                'to' => $to,
                'message' => $message,
                'from' => $this->senderId,
            ]))
            ->throw();
    }
}
