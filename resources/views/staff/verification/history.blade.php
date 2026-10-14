<x-workspace-layout :heading="$property->title">
    <p class="mt-3 text-sm text-stone-600">Property verification history</p>
    <p class="mt-2 text-sm">{{ $property->passport?->level->label() ?? 'Level 0 — Advertised' }} @if ($property->passport?->assignee) · assigned to {{ $property->passport->assignee->name }} @endif</p>
    @if ($property->passport)
        <form method="POST" action="{{ route('staff.verification.assign', $property) }}" data-turbo-frame="_top" data-confirm="Assign this review?" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <select name="assigned_to" class="rounded-lg border border-sand-200 bg-white px-3 py-2" required>
                @foreach ($officers as $officer)
                    <option value="{{ $officer->id }}">{{ $officer->name }}</option>
                @endforeach
            </select>
            <input type="datetime-local" name="deadline_at" required class="rounded-lg border border-sand-200 px-3 py-2">
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Assign review</button>
        </form>
    @endif
    <div class="mt-8 space-y-4">
        @foreach ($property->verificationItems as $item)
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">{{ $item->category->label() }}</h2>
                <p class="mt-1 text-sm text-stone-600">{{ $item->state->label() }}</p>
                <form method="POST" action="{{ route('staff.verification.decide', $item) }}" data-turbo-frame="_top" data-confirm="Record this reasoned decision?" class="mt-3 grid gap-2">
                    @csrf
                    @method('PUT')
                    <select name="state" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                        @foreach (\App\Enums\VerificationItemState::cases() as $state)
                            <option value="{{ $state->value }}" @selected($item->state === $state)>{{ $state->label() }}</option>
                        @endforeach
                    </select>
                    <textarea name="reason" required minlength="8" class="rounded-lg border border-sand-200 px-3 py-2" placeholder="Reason for the decision"></textarea>
                    <input type="date" name="expires_at" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Expiry date">
                    <input type="date" name="review_due_at" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Review deadline">
                    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save decision</button>
                </form>
            </article>
        @endforeach
    </div>
    <h2 class="mt-8 font-semibold text-lamu-900">Reviewer log</h2>
    <ol class="mt-3 space-y-3">
        @forelse ($property->verificationReviews as $log)
            <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p class="font-medium text-lamu-900">{{ $log->actor->name }} · {{ $log->decision }}</p>
                <p class="mt-1">{{ $log->reason }}</p>
                <p class="mt-1 text-stone-500">{{ $log->created_at->toDayDateTimeString() }}</p>
            </li>
        @empty
            <li class="text-sm text-stone-600">No decisions have been recorded.</li>
        @endforelse
    </ol>
</x-workspace-layout>
