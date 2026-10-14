<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mpesa:reconcile')->everyMinute()->description('M-Pesa reconciliation');
Schedule::command('research:commitments')->daily()->description('Research commitments');
