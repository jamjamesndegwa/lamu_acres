<turbo-frame id="modal">
    <x-modal title="Change status" :close="route('staff.properties.show', $property)">
        <p class="text-sm text-stone-600">{{ $property->reference }} is {{ $property->status->label() }}. Only the next allowed status can be applied.</p>
        @if ($property->status->transitions() === [])
            <p class="mt-4 text-sm text-stone-700">No further status change is available from {{ $property->status->label() }}.</p>
        @else
            <form method="POST" action="{{ route('staff.properties.transition', $property) }}" data-turbo-frame="_top" data-confirm="Apply this status?" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <label class="block text-sm font-medium">Next status
                    <select name="status" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                        @foreach ($property->status->transitions() as $next)
                            <option value="{{ $next->value }}">{{ $next->label() }}</option>
                        @endforeach
                    </select>
                </label>
                @error('status')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror
                <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Apply status</button>
            </form>
        @endif
    </x-modal>
</turbo-frame>
