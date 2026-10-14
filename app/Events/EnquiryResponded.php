<?php

namespace App\Events;

use App\Models\EnquiryResponse;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnquiryResponded
{
    use Dispatchable, SerializesModels;

    public function __construct(public EnquiryResponse $response) {}
}
