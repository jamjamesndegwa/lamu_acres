<x-workspace-layout :heading="$property->title">
    <p class="mt-2 text-sm text-stone-600">{{ $property->reference }} · Original files stay on the private disk. Public pages use a separate copy.</p>
    @include('staff.properties.partials.tabs', ['tab' => 'media'])

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-lamu-900">Photos</h2>
        <div class="mt-4">
            <x-property-gallery :photos="$photos" />
        </div>
        <form method="POST" action="{{ route('staff.properties.photos.store', $property) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="photo" class="block text-sm font-medium">Add photo</label>
                <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="mt-1 block text-sm">
                @error('photo')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Upload</button>
        </form>
        @if ($photos !== [])
            <form method="POST" action="{{ route('staff.properties.media.order', $property) }}" class="mt-4 space-y-2">
                @csrf
                @method('PUT')
                @foreach ($photos as $photo)
                    <label class="flex items-center gap-3 text-sm">
                        <span class="w-40 truncate">{{ $photo['name'] }}</span>
                        <input type="number" min="1" name="positions[{{ $photo['id'] }}]" value="{{ $photo['position'] }}" class="w-24 rounded-lg border border-sand-200 px-2 py-1">
                    </label>
                @endforeach
                <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Save order</button>
            </form>
        @endif
        <div class="mt-4 space-y-2">
            @foreach ($property->media->where('kind', 'photo') as $media)
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span>{{ $media->original_name }}</span>
                    @if ($media->is_cover)
                        <span class="text-stone-500">Cover</span>
                    @else
                        <form method="POST" action="{{ route('staff.properties.media.cover', [$property, $media]) }}">
                            @csrf
                            @method('PUT')
                            <button class="font-medium text-lamu-800">Make cover</button>
                        </form>
                    @endif
                    <a href="{{ route('staff.properties.media.original', [$property, $media]) }}" class="font-medium text-lamu-800">Private original</a>
                    <form method="POST" action="{{ route('staff.properties.media.destroy', [$property, $media]) }}" data-confirm="Remove this photo?">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-700">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-lg font-semibold text-lamu-900">Video link</h2>
        <form method="POST" action="{{ route('staff.properties.videos.store', $property) }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-72 flex-1">
                <label for="video_url" class="block text-sm font-medium">YouTube or Vimeo https link</label>
                <input id="video_url" name="video_url" type="url" value="{{ old('video_url') }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('video_url')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Save link</button>
        </form>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($property->media->where('kind', 'video') as $media)
                <li class="flex items-center gap-3">
                    <span>{{ $media->video_url }}</span>
                    <form method="POST" action="{{ route('staff.properties.media.destroy', [$property, $media]) }}" data-confirm="Remove this video link?">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-700">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>
</x-workspace-layout>
