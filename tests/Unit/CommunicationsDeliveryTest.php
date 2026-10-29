<?php

namespace Tests\Unit;

use App\Support\CommunicationsDelivery;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CommunicationsDeliveryTest extends TestCase
{
    public function test_defaults_to_immediate_delivery_when_database_queue_and_env_unset(): void
    {
        putenv('COMMUNICATIONS_SYNC_DELIVERY');
        unset($_ENV['COMMUNICATIONS_SYNC_DELIVERY'], $_SERVER['COMMUNICATIONS_SYNC_DELIVERY']);

        Config::set('queue.default', 'database');
        Config::set('communications.deliver_synchronously', true);

        $this->assertTrue(CommunicationsDelivery::deliverSynchronously());
    }

    public function test_explicit_false_disables_immediate_delivery(): void
    {
        Config::set('communications.deliver_synchronously', false);

        $this->assertFalse(CommunicationsDelivery::deliverSynchronously());
    }
}
