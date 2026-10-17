<?php

namespace App\Support;

use Illuminate\Http\Request;

class SafeListingReturn
{
    public function remember(Request $request): ?string
    {
        $returnTo = $request->query('return_to');

        if (! is_string($returnTo) || trim($returnTo) === '') {
            return null;
        }

        $parts = parse_url($returnTo);

        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if (is_string($host) && strcasecmp($host, $request->getHost()) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (
            ! is_string($path)
            || preg_match('~^/land/(?:item/[^/]+|[^/]+/[^/]+/[^/]+)$~', $path) !== 1
            || str_contains($path, '\\')
        ) {
            return null;
        }

        $destination = $path;

        if (isset($parts['query'])) {
            $destination .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $destination .= '#'.$parts['fragment'];
        }

        $intendedUrl = $request->getSchemeAndHttpHost().$destination;
        $request->session()->put('url.intended', $intendedUrl);

        return $intendedUrl;
    }
}
