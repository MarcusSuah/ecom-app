<?php

use App\Actions\Billing\ProcessSubscriptionRenewals;
use App\Actions\Currency\FetchExchangeRates;
use App\Actions\Payments\AutoReleasePayments;
use App\Actions\Rfqs\ExpireRfqs;
use App\Models\UserActivity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:process-renewals', function (ProcessSubscriptionRenewals $processRenewals) {
    $counts = $processRenewals->handle();

    $this->info("Invoiced {$counts['invoiced']}, renewed {$counts['renewed']}, expired {$counts['expired']} subscriptions.");
})->purpose('Invoice ended subscription periods and expire unpaid or cancelled subscriptions');

Schedule::command('billing:process-renewals')->dailyAt('01:00')->withoutOverlapping()->onOneServer();

Artisan::command('rfqs:expire', function (ExpireRfqs $expireRfqs) {
    $this->info("Expired {$expireRfqs->handle()} requests.");
})->purpose('Mark open requests for quotation past their expiry date as expired');

Schedule::command('rfqs:expire')->hourly()->withoutOverlapping()->onOneServer();

Artisan::command('payments:release-due', function (AutoReleasePayments $autoReleasePayments) {
    $this->info("Released {$autoReleasePayments->handle()} payments.");
})->purpose('Release held payments for orders delivered long enough ago with no open dispute');

Schedule::command('payments:release-due')->hourly()->withoutOverlapping()->onOneServer();

Artisan::command('currency:fetch-rates', function (FetchExchangeRates $fetchExchangeRates) {
    try {
        $rates = $fetchExchangeRates->handle();
    } catch (RuntimeException $exception) {
        $this->error($exception->getMessage().' The previous rates are kept.');

        return 1;
    }

    foreach ($rates as $quote => $rate) {
        $this->info('1 '.config('currency.base')." = {$rate} {$quote}");
    }

    return 0;
})->purpose('Fetch the daily exchange rates used to show approximate prices');

Schedule::command('currency:fetch-rates')->dailyAt('02:00')->withoutOverlapping()->onOneServer();

// Activity older than marketplace.activity.retention_days (UserActivity::prunable).
Schedule::command('model:prune', ['--model' => [UserActivity::class]])->daily()->onOneServer();
