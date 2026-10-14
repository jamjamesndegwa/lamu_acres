<?php

namespace App\Models;

use App\Enums\LandUse;
use App\Enums\SourcingRequestStatus;
use App\Support\Geo\Wkt;
use Database\Factories\SourcingRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'reference',
    'buyer_id',
    'assigned_to',
    'status',
    'location_label',
    'centre_latitude',
    'centre_longitude',
    'radius_km',
    'centre_wkt',
    'boundary_wkt',
    'boundary_points',
    'min_acreage',
    'max_acreage',
    'maximum_budget',
    'intended_use',
    'notes',
    'submitted_at',
    'closed_at',
])]
class SourcingRequest extends Model
{
    /** @use HasFactory<SourcingRequestFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (SourcingRequest $request): void {
            $request->syncMysqlGeometry();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SourcingRequestStatus::class,
            'centre_latitude' => 'decimal:7',
            'centre_longitude' => 'decimal:7',
            'radius_km' => 'decimal:2',
            'boundary_points' => 'array',
            'min_acreage' => 'decimal:4',
            'max_acreage' => 'decimal:4',
            'maximum_budget' => 'integer',
            'intended_use' => LandUse::class,
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function amenities(): HasMany
    {
        return $this->hasMany(SourcingRequestAmenity::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SourcingMatch::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SourcingActivity::class)->latest();
    }

    public function scopeVisibleToStaff(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('staff.sourcing')) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->buyer_id === (int) $user->id;
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    public function ring(): array
    {
        return collect($this->boundary_points ?? [])
            ->map(fn (array $point): array => [(float) $point['lat'], (float) $point['lng']])
            ->all();
    }

    public function syncMysqlGeometry(): void
    {
        $connection = $this->getConnection();

        if ($connection->getDriverName() !== 'mysql' || ! Schema::hasColumn($this->getTable(), 'centre_point')) {
            return;
        }

        $centre = is_string($this->centre_wkt) && Wkt::isPoint($this->centre_wkt)
            ? DB::raw(Wkt::geomFromText($this->centre_wkt))
            : null;
        $area = is_string($this->boundary_wkt) && Wkt::isPolygon($this->boundary_wkt)
            ? DB::raw(Wkt::geomFromText($this->boundary_wkt))
            : null;

        $connection->table($this->getTable())->where('id', $this->id)->update([
            'centre_point' => $centre,
            'search_area' => $area,
        ]);
    }
}
