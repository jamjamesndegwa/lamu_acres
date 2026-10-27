<?php

namespace App\Services\Communications;

use Illuminate\Support\Facades\Redis;
use Predis\Client;
use Throwable;

class QueueConnectionProbe
{
    /**
     * @return array{available: bool, driver: string, detail: string}
     */
    public function redis(): array
    {
        $driver = (string) config('queue.default');

        if ((string) config('queue.connections.redis.driver') !== 'redis') {
            return [
                'available' => false,
                'driver' => $driver,
                'detail' => 'Redis queue connection is configured but not selected as the default queue driver.',
            ];
        }

        if (! extension_loaded('redis') && ! class_exists(Client::class)) {
            return [
                'available' => false,
                'driver' => $driver,
                'detail' => 'No PHP Redis client extension or Predis package is available.',
            ];
        }

        try {
            Redis::connection()->ping();

            return [
                'available' => true,
                'driver' => 'redis',
                'detail' => 'Redis responded to a ping.',
            ];
        } catch (Throwable) {
            return [
                'available' => false,
                'driver' => 'redis',
                'detail' => 'Redis did not respond. Check host, port and credentials.',
            ];
        }
    }
}
