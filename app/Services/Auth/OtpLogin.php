<?php

namespace App\Services\Auth;

use App\Contracts\Auth\OtpSender;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpLogin
{
    public function __construct(private OtpSender $sender) {}

    public function request(string $phone): OtpDeliveryResult
    {
        if (! $this->sender->available()) {
            return new OtpDeliveryResult(delivered: false, provider: 'unconfigured');
        }

        $code = (string) random_int(100000, 999999);
        $result = $this->sender->send($phone, $code);

        if (! $result->delivered) {
            return $result;
        }

        OtpChallenge::query()->create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
        ]);

        return $result;
    }

    public function verify(string $phone, string $code): ?User
    {
        $challenge = OtpChallenge::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($challenge === null || ! Hash::check($code, $challenge->code_hash)) {
            return null;
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            return null;
        }

        $challenge->forceFill(['consumed_at' => now()])->save();
        $user->forceFill(['phone_verified_at' => now()])->save();

        return $user;
    }

    public function consume(string $phone, string $code): bool
    {
        $challenge = OtpChallenge::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($challenge === null || ! Hash::check($code, $challenge->code_hash)) {
            return false;
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        return true;
    }
}
