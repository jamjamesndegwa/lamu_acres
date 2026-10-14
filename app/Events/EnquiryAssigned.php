<?php

namespace App\Events;

use App\Models\Enquiry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnquiryAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}
}
