<?php

namespace App\Console\Commands;

use App\Services\Payments\MpesaCheckout;
use Illuminate\Console\Command;

class ReconcileMpesaPayments extends Command
{
    protected $signature = 'mpesa:reconcile';

    protected $description = 'Reconcile M-Pesa payments that have passed the checkout timeout';

    public function handle(MpesaCheckout $checkout): int
    {
        $count = $checkout->reconcileExpired();
        $this->info('Reconciled '.$count.' payment'.($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
