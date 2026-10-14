<x-workspace-layout heading="Message templates">
    <div class="mt-6 space-y-4">
        @foreach ($templates as $template)
            <article class="flex items-center justify-between gap-3 rounded-2xl border border-sand-200 bg-white p-4">
                <p class="text-sm font-medium text-lamu-900">{{ $template->event->label() }}</p>
                <button type="button" class="text-sm font-medium text-lamu-800" onclick="this.nextElementSibling.showModal()">Edit</button>
                <dialog class="w-full max-w-lg rounded-2xl p-6 backdrop:bg-stone-900/50">
                    <form method="dialog" class="text-right"><button class="text-sm text-stone-500">Close</button></form>
                    <h2 class="text-xl font-semibold text-lamu-900">{{ $template->event->label() }}</h2>
                    <form method="POST" action="{{ route('staff.messages.templates.update', $template) }}" data-turbo-frame="_top" data-confirm="Save this message template?" class="mt-4 space-y-3">
                        @csrf
                        @method('PUT')
                        <label class="block text-sm" for="subject-{{ $template->id }}">Subject</label>
                        <input id="subject-{{ $template->id }}" name="subject" value="{{ old('subject', $template->subject) }}" required maxlength="160" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <label class="block text-sm" for="body-{{ $template->id }}">Message</label>
                        <textarea id="body-{{ $template->id }}" name="body" required maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">{{ old('body', $template->body) }}</textarea>
                        <p class="text-xs text-stone-600">Placeholders: {name} {title} {reference} {status} {summary} {url}</p>
                        <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Save template</button>
                    </form>
                </dialog>
            </article>
        @endforeach
    </div>
</x-workspace-layout>
