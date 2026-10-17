<x-workspace-layout :heading="$property->title">
    <p class="mt-2 text-sm text-stone-600">{{ $property->reference }} · Original files stay on the private disk. Public pages use a separate copy.</p>
    @include('staff.properties.partials.tabs', ['tab' => 'media'])

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-lamu-900">Photos</h2>
        <div class="mt-4">
            <x-property-gallery :photos="$photos" />
        </div>
        <form method="POST" action="{{ route('staff.properties.photos.store', $property) }}" enctype="multipart/form-data" class="mt-4 max-w-xl space-y-3">
            @csrf
            <x-upload-manager id="photo" name="photo" label="Add photo" accept="image/jpeg,image/png,image/webp" required />
            @error('photo')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
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
        <h2 class="text-lg font-semibold text-lamu-900">Videos</h2>
        <form method="POST" action="{{ route('staff.properties.videos.store', $property) }}" enctype="multipart/form-data" class="mt-4 space-y-3 rounded-2xl border border-sand-200 bg-white p-4">
            @csrf
            <h3 class="font-medium text-lamu-900">Upload a video</h3>
            <x-upload-manager name="video" label="Video file" accept="video/mp4,video/webm" required help="Choose an MP4 or WebM video file." />
            <p class="text-sm text-stone-600">Maximum file size on this server: {{ number_format(\App\Services\Media\PropertyMediaLibrary::maxVideoKilobytes() / 1024, 1) }} MB. Uploaded videos appear on the public listing after publication.</p>
            @error('video')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Upload video</button>
        </form>
        <h3 class="mt-6 font-medium text-lamu-900">Or add a video link</h3>
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
                    <span>{{ $media->video_url ?: $media->original_name }}</span>
                    <form method="POST" action="{{ route('staff.properties.media.destroy', [$property, $media]) }}" data-confirm="Remove this video?">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-700">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>
</x-workspace-layout>
