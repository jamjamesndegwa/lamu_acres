<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IntelligenceFile;
use App\Services\Intelligence\IntelligenceFiles;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function __invoke(Request $request, IntelligenceFile $file, IntelligenceFiles $files): Response
    {
        return $files->response($request->user(), $file);
    }
}
