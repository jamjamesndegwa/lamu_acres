<?php

namespace App\Support;

use Illuminate\Http\Request;

class Turbo
{
    public static function frame(Request $request, ?string $id = null): ?string
    {
        $frame = $request->header('Turbo-Frame');

        if (! is_string($frame) || $frame === '') {
            return null;
        }

        if ($id !== null && $frame !== $id) {
            return null;
        }

        return $frame;
    }

    public static function wantsModal(Request $request): bool
    {
        return self::frame($request, 'modal') !== null;
    }
}
