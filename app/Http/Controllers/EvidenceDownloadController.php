<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EvidenceDownloadController extends Controller
{
    public function __invoke(Request $request, EvidenceFile $evidenceFile, VerificationDesk $desk): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($request->user() !== null, 403);

        return $desk->download($evidenceFile, $request->user());
    }
}
