<x-workspace-layout heading="New bulk campaign">
    <form method="POST" action="{{ route('staff.messages.campaigns.store') }}" data-turbo-frame="_top" data-confirm="Save this campaign draft?" class="mt-6 max-w-xl space-y-4">
        @csrf
        <label class="block text-sm font-medium" for="channel">Channel</label>
        <select id="channel" name="channel" required class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="{{ \App\Enums\MessageChannel::Sms->value }}">SMS</option>
            <option value="{{ \App\Enums\MessageChannel::Email->value }}">Email</option>
        </select>
        <label class="block text-sm font-medium" for="subject">Subject</label>
        <input id="subject" name="subject" required maxlength="160" class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        <label class="block text-sm font-medium" for="body">Message</label>
        <textarea id="body" name="body" required maxlength="1000" rows="5" class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2"></textarea>
        <p class="text-xs text-stone-600">Only accounts with marketing consent and a valid destination receive this message. An unsubscribe link is appended automatically.</p>
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save draft</button>
    </form>
</x-workspace-layout>
