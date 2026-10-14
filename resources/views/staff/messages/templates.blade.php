<x-workspace-layout heading="Message templates">
    <div class="mt-6 space-y-4">
        @foreach ($templates as $template)
            <form method="POST" action="{{ route('staff.messages.templates.update', $template) }}" data-turbo-frame="_top" data-confirm="Save this message template?" class="rounded-2xl border border-sand-200 bg-white p-4">
                @csrf
                @method('PUT')
                <p class="text-sm font-medium text-lamu-900">{{ $template->event->label() }}</p>
                <label class="mt-3 block text-sm" for="subject-{{ $template->id }}">Subject</label>
                <input id="subject-{{ $template->id }}" name="subject" value="{{ old('subject', $template->subject) }}" required maxlength="160" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <label class="mt-3 block text-sm" for="body-{{ $template->id }}">Message</label>
                <textarea id="body-{{ $template->id }}" name="body" required maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">{{ old('body', $template->body) }}</textarea>
                <p class="mt-1 text-xs text-stone-600">Placeholders: {name} {title} {reference} {status} {summary} {url}</p>
                <button class="mt-3 rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Save template</button>
            </form>
        @endforeach
    </div>
</x-workspace-layout>
