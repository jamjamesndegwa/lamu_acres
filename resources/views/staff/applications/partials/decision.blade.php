<p class="text-sm text-stone-600">{{ $application->user->name }} · {{ $application->capability->label() }} · {{ $application->status->label() }}</p>
<p class="mt-3 text-sm text-stone-800">{{ $application->statement }}</p>
@if ($application->organization)
    <p class="mt-2 text-sm text-stone-600">{{ $application->organization }}</p>
@endif

<form method="POST" action="{{ route('staff.applications.update', $application) }}" data-turbo-frame="_top" data-confirm="Record this decision?" class="mt-5 space-y-4">
    @csrf
    @method('PUT')
    <div>
        <label for="status" class="block text-sm font-medium">Decision</label>
        <select id="status" name="status" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @foreach (['approved' => 'Approve', 'information_required' => 'Ask for information', 'rejected' => 'Reject', 'suspended' => 'Suspend capability'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="decision_reason" class="block text-sm font-medium">Reason</label>
        <textarea id="decision_reason" name="decision_reason" rows="3" required maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('decision_reason') }}</textarea>
        @error('decision_reason')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Record decision</button>
</form>
