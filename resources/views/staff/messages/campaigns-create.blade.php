<x-workspace-layout heading="New bulk campaign">
    <form method="POST" action="{{ route('staff.messages.campaigns.store') }}" data-turbo-frame="_top" data-confirm="Save this campaign draft?" id="campaign-create-form" class="mt-6 grid gap-6 lg:grid-cols-5">
        @csrf

        <div class="space-y-4 lg:col-span-2">
            <label class="block text-sm font-medium text-lamu-900" for="channel">Channel</label>
            <select id="channel" name="channel" required class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="{{ \App\Enums\MessageChannel::Sms->value }}" @selected(old('channel', \App\Enums\MessageChannel::Sms->value) === \App\Enums\MessageChannel::Sms->value)>SMS</option>
                <option value="{{ \App\Enums\MessageChannel::Email->value }}" @selected(old('channel') === \App\Enums\MessageChannel::Email->value)>Email</option>
            </select>
            <label class="block text-sm font-medium text-lamu-900" for="subject">Subject</label>
            <input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="160" class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
            <label class="block text-sm font-medium text-lamu-900" for="body">Message</label>
            <textarea id="body" name="body" required maxlength="1000" rows="6" class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2">{{ old('body') }}</textarea>
            <p class="text-xs leading-relaxed text-stone-700">Only opted-in buyers, landowners, and brokers appear here (staff and admin accounts are excluded). Use the quick filters or search, then save.</p>
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-semibold text-white">Save draft</button>
        </div>

        <section class="rounded-2xl border border-lamu-200 bg-lamu-50/30 p-5 lg:col-span-3" aria-labelledby="create-recipients-heading">
            <h2 id="create-recipients-heading" class="home-serif text-lg font-semibold text-lamu-900">Select recipients</h2>
            <p class="mt-1 text-sm text-stone-800">Choose who should receive this campaign. You can change the list later on the campaign page.</p>

            @include('staff.messages.partials.recipient-picker', [
                'audience' => $smsAudience,
                'selectedIds' => array_map(intval(...), old('recipient_ids', [])),
                'search' => '',
                'channelFieldId' => 'channel',
                'panelSuffix' => '-sms',
            ])

            @include('staff.messages.partials.recipient-picker', [
                'audience' => $emailAudience,
                'selectedIds' => array_map(intval(...), old('recipient_ids', [])),
                'search' => '',
                'channelFieldId' => 'channel',
                'panelSuffix' => '-email',
            ])
        </section>
    </form>

    @include('staff.messages.partials.recipient-picker-scripts', ['channelToggle' => true])
</x-workspace-layout>
