<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\MpesaCheckout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class MpesaCallbackController extends Controller
{
    public function __invoke(Request $request, MpesaCheckout $checkout, ?string $token = null): Response
    {
        abort_unless($this->authorized($request, $token), 403);

        try {
            $checkout->acceptCallback($request->all());
        } catch (ValidationException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->noContent();
    }

    private function authorized(Request $request, ?string $token): bool
    {
        $secret = (string) config('mpesa.callback_secret');

        if ($secret === '') {
            return false;
        }

        if ($token !== null && hash_equals($secret, $token)) {
            return true;
        }

        $header = (string) $request->header('X-Callback-Secret', '');

        return $header !== '' && hash_equals($secret, $header);
    }
}
