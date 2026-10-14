<?php

namespace App\Services\Media;

use App\Enums\Amenity;
use App\Enums\AmenityState;
use App\Enums\DocumentCheckState;
use App\Enums\RoadAccess;
use App\Models\Property;

class PropertyPresentation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Property $property, array $data): void
    {
        $property->road_access = RoadAccess::from($data['road_access']);
        $property->infrastructure_note = filled($data['infrastructure_note'] ?? null)
            ? trim((string) $data['infrastructure_note'])
            : null;
        $property->save();

        foreach (Amenity::cases() as $amenity) {
            $state = AmenityState::from($data['amenities'][$amenity->value] ?? AmenityState::Unknown->value);
            $property->amenityStates()->updateOrCreate(
                ['amenity' => $amenity->value],
                ['state' => $state->value],
            );
        }

        $property->highlights()->delete();
        $lines = preg_split("/\r\n|\r|\n/", (string) ($data['highlights'] ?? '')) ?: [];
        $position = 1;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $property->highlights()->create([
                'body' => $line,
                'position' => $position,
            ]);
            $position++;
        }

        foreach (['title_search', 'survey_plan'] as $code) {
            $state = DocumentCheckState::from($data['documents'][$code] ?? DocumentCheckState::Unknown->value);
            $property->documentChecks()->updateOrCreate(
                ['code' => $code],
                ['state' => $state->value],
            );
        }
    }
}
