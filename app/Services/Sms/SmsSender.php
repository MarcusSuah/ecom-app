<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * Send a text message to a phone number in international format (+250…).
     */
    public function send(string $to, string $message): void;
}
