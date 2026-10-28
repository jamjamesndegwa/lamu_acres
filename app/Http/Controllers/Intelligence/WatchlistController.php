<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentProject;
use App\Models\Locality;
use App\Models\PublicationDelivery;
use App\Models\SubscriberWatch;
use App\Services\Research\PublicationArchive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasActiveInvestorPass(), 403);

        return view('investor.watchlists.index', [
            'watches' => SubscriberWatch::query()
                ->where('user_id', $request->user()->id)
                ->with(['locality.county', 'project'])
                ->latest()
                ->get(),
            'localities' => Locality::query()->active()->with('county')->orderBy('name')->get(),
            'projects' => DevelopmentProject::query()->released()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasActiveInvestorPass(), 403);

        $data = $request->validate([
            'locality_id' => ['nullable', 'integer', 'exists:localities,id', 'required_without:development_project_id'],
            'development_project_id' => ['nullable', 'integer', 'exists:development_projects,id', 'required_without:locality_id'],
        ]);

        if (! empty($data['development_project_id'])) {
            abort_unless(DevelopmentProject::query()->released()->whereKey($data['development_project_id'])->exists(), 422);
        }

        $attributes = [
            'user_id' => $request->user()->id,
            'locality_id' => $data['locality_id'] ?? null,
            'development_project_id' => $data['development_project_id'] ?? null,
        ];

        $watch = SubscriberWatch::query()->firstOrCreate($attributes);

        return back()->with(
            'status',
            $watch->wasRecentlyCreated ? 'Item added to your watchlist.' : 'You are already watching this item.',
        );
    }

    public function destroy(Request $request, SubscriberWatch $watch): RedirectResponse
    {
        abort_unless($request->user()->hasActiveInvestorPass(), 403);
        abort_unless((int) $watch->user_id === (int) $request->user()->id, 403);

        $watch->delete();

        return back()->with('status', 'Item removed from your watchlist.');
    }

    public function archive(Request $request, PublicationArchive $archive): View
    {
        abort_unless($request->user()->hasActiveInvestorPass(), 403);

        $deliveries = PublicationDelivery::query()
            ->where('user_id', $request->user()->id)
            ->with(['subject', 'assignment'])
            ->latest('delivered_at')
            ->paginate(20)
            ->withQueryString();

        return view('investor.archive.index', [
            'deliveries' => $deliveries,
            'archive' => $archive,
        ]);
    }
}
