<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * Twilio Programmable Messaging API.
 *
 * @see https://www.twilio.com/docs/messaging/api/message-resource#create-a-message-resource
 */
class TwilioSmsSender implements SmsSender
{
    public function __construct(
        private string $accountSid,
        private string $authToken,
        private string $from,
    ) {}

    public function send(string $to, string $message): void
    {
        Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->timeout(10)
            ->retry(2, 500)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'To' => $to,
                'From' => $this->from,
                'Body' => $message,
            ])
            ->throw();
    }
}
