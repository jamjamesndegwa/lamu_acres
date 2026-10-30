<?php

namespace App\Concerns;

use Illuminate\Validation\ValidationException;

trait GuardsEarnedCommercialTerms
{
    /**
     * @param  array<int, string>  $lockedAttributes
     */
    protected static function guardEarnedCommercialTerms(array $lockedAttributes): void
    {
        static::updating(function ($model) use ($lockedAttributes): void {
            if ($model->getOriginal('earned_amount') === null) {
                return;
            }

            foreach ($lockedAttributes as $attribute) {
                if ($model->isDirty($attribute)) {
                    throw ValidationException::withMessages([
                        $attribute => 'Commercial terms cannot be changed after an obligation has been earned.',
                    ]);
                }
            }
        });
    }
}
