<?php

namespace App\Models;

use Database\Factories\EnquiryResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'enquiry_id',
    'user_id',
    'body',
    'visible_to_buyer',
])]
class EnquiryResponse extends Model
{
    /** @use HasFactory<EnquiryResponseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'visible_to_buyer' => 'boolean',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
