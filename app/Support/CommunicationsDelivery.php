<?php

namespace App\Support;

class CommunicationsDelivery
{
    /**
     * Send MessageDesk / DeliverMessage email and SMS immediately instead of the queue worker.
     * Enable on cPanel hosts without Redis or Supervisor (COMMUNICATIONS_SYNC_DELIVERY=true).
     */
    public static function deliverSynchronously(): bool
    {
        return (bool) config('communications.deliver_synchronously');
    }
}
