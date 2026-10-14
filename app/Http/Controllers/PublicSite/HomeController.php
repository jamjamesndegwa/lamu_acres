<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\Catalog\PropertyCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(PropertyCatalog $catalog): View
    {
        return view('public.home', [
            'featured' => $catalog->featured(),
            'counties' => $catalog->counties(),
        ]);
    }
}
