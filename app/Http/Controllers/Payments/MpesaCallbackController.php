<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\MpesaCheckout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class MpesaCallbackController extends Controller
{
    public function __invoke(Request $request, MpesaCheckout $checkout): Response
    {
        $secret = (string) config('mpesa.callback_secret');
        $given = (string) $request->header('X-Callback-Secret', '');

        abort_if($secret === '' || ! hash_equals($secret, $given), 403);

        try {
            $checkout->acceptCallback($request->all());
        } catch (ValidationException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->noContent();
    }
}
