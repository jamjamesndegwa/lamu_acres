<turbo-frame id="modal">
    @if ($property->status->transitions() === [])
        <x-modal title="Change status" :close="route('staff.properties.show', $property)">
            <p class="text-sm text-stone-600">{{ $property->reference }} is {{ $property->status->label() }}. Only the next allowed status can be applied.</p>
            <p class="mt-4 text-sm text-stone-700">No further status change is available from {{ $property->status->label() }}.</p>
        </x-modal>
    @else
        <x-approval-dialog
            title="Change status"
            :action="route('staff.properties.transition', $property)"
            method="PUT"
            confirm="Apply this status?"
            :close="route('staff.properties.show', $property)"
            submit="Apply status"
        >
            <p class="text-sm text-stone-600">{{ $property->reference }} is {{ $property->status->label() }}. Only the next allowed status can be applied.</p>
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
        </x-approval-dialog>
    @endif
</turbo-frame>
