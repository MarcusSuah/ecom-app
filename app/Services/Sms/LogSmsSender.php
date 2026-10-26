<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the log instead of sending them. Used locally and in tests.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS', ['to' => $to, 'message' => $message]);
    }
}
