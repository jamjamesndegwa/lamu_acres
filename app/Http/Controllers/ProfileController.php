<?php

namespace App\Http\Controllers;

use App\Enums\Workspace;
use App\Http\Requests\ProfileUpdateRequest;
use App\Notifications\AccountEmailChanged;
use App\Services\Account\AccountAvatar;
use App\Services\Auth\OtpLogin;
use App\Services\Communications\AccountSecurityNotifier;
use App\Services\Communications\MessageDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('workspaces.profile-index', [
            'user' => $request->user(),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('workspaces.profile', [
            'workspace' => Workspace::from((string) $request->session()->get('workspace')),
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request, OtpLogin $otp): RedirectResponse
    {
        $user = $request->user();
        $phone = $request->input('phone');
        $phoneChanged = $phone !== $user->phone;

        if ($phoneChanged && filled($phone)) {
            $delivery = $otp->request((string) $phone);

            if (! $delivery->delivered) {
                throw ValidationException::withMessages([
                    'phone' => 'That phone number stays unchanged until a code can be delivered.',
                ]);
            }

            $user->pending_phone = $phone;
        }

        if ($phoneChanged && blank($phone)) {
            $user->phone = null;
            $user->phone_verified_at = null;
            $user->pending_phone = null;
        }

        $emailChanged = $user->email !== $request->string('email')->value();
        $user->name = $request->string('name')->value();
        $user->email = $request->string('email')->value();

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($request->exists('notify_by_email')) {
            $user->notify_by_email = $request->boolean('notify_by_email');
        }

        $channels = [];

        if ($request->exists('notify_by_sms')) {
            $channels['sms'] = $request->boolean('notify_by_sms');
        }

        if ($request->exists('notify_by_whatsapp')) {
            $channels['whatsapp'] = $request->boolean('notify_by_whatsapp');
        }

        if ($request->exists('marketing_email_consent')) {
            $channels['marketing_email'] = $request->boolean('marketing_email_consent');
        }

        if ($request->exists('marketing_sms_consent')) {
            $channels['marketing_sms'] = $request->boolean('marketing_sms_consent');
        }

        if ($request->filled('password')) {
            Auth::logoutOtherDevices($request->string('current_password')->value());
            $user->password = $request->string('password')->value();
        }

        $user->save();

        if ($channels !== []) {
            app(MessageDesk::class)->savePreferences($user, $channels);
            $user->save();
        }

        if ($emailChanged) {
            $user->notify(new AccountEmailChanged);
        }

        if ($request->filled('password')) {
            app(AccountSecurityNotifier::class)->passwordChanged($user->fresh());
            $request->session()->regenerate();
        }

        $workspace = Workspace::from((string) $request->session()->get('workspace'));
        $status = $user->pending_phone && $phoneChanged
            ? 'Profile updated. Confirm the code sent to the new phone number.'
            : 'Profile updated.';

        return redirect()->route($workspace->homeRoute())->with('status', $status);
    }

    public function showAvatar(Request $request): StreamedResponse
    {
        $path = $request->user()->avatar_path;
        $disk = (config('filesystems.disks.r2.bucket') && Storage::disk('r2')->exists($path)) ? 'r2' : 'local';

        abort_unless(is_string($path) && $path !== '' && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, ['Content-Type' => 'image/jpeg']);
    }

    public function storeAvatar(Request $request, AccountAvatar $avatars): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $avatars->store($request->user(), $request->file('avatar'));

        return back()->with('status', 'Profile photo updated.');
    }

    public function destroyAvatar(Request $request, AccountAvatar $avatars): RedirectResponse
    {
        $avatars->delete($request->user());

        return back()->with('status', 'Profile photo removed.');
    }

    public function confirmPhone(Request $request, OtpLogin $otp): RedirectResponse
    {
        $user = $request->user();
        $code = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ])['code'];

        if ($user->pending_phone === null || ! $otp->consume($user->pending_phone, $code)) {
            throw ValidationException::withMessages([
                'code' => 'That code could not be confirmed.',
            ]);
        }

        $user->forceFill([
            'phone' => $user->pending_phone,
            'pending_phone' => null,
            'phone_verified_at' => now(),
        ])->save();

        return back()->with('status', 'Phone number confirmed.');
    }

    public function requestEmailVerification(Request $request): RedirectResponse
    {
        if ($request->user()->email_verified_at !== null) {
            return back()->with('status', 'Your email is already verified.');
        }
        if (! AccountEmailChanged::canDeliverEmail()) {
            throw ValidationException::withMessages(['email_delivery' => 'Email delivery is not configured. Ask the desk to configure email delivery before requesting a verification link.']);
        }
        $request->user()->notify(new AccountEmailChanged(emailChanged: false));

        return back()->with('status', 'Verification email sent. Open the link in your inbox while signed in to this account.');
    }

    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = $request->user();

        abort_unless((string) $user->id === $id, 403);
        abort_unless(hash_equals($hash, sha1($user->email)), 403);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()->to($user->workspaceHomeUrl())->with('status', 'Email confirmed.');
    }
}
