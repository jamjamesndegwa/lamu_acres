<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogFileUploadFailures
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            $files = $this->fileDetails($request->allFiles());
            $isMultipartRequest = str_starts_with((string) $request->header('Content-Type'), 'multipart/form-data');

            if ($files !== [] || $isMultipartRequest) {
                $context = [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'user_id' => $request->user()?->getAuthIdentifier(),
                    'files' => $files,
                    'content_length' => $request->server('CONTENT_LENGTH'),
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ];

                if ($exception instanceof ValidationException) {
                    $context['validation_errors'] = $exception->errors();
                }

                Log::error('File upload request failed.', $context);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $files
     * @return array<int, array{field: string, size_bytes: int|false, mime: ?string, valid: bool, upload_error: int}>
     */
    private function fileDetails(array $files, string $prefix = ''): array
    {
        $details = [];

        foreach ($files as $field => $file) {
            $fieldName = $prefix === '' ? (string) $field : $prefix.'.'.$field;

            if (is_array($file)) {
                $details = [...$details, ...$this->fileDetails($file, $fieldName)];

                continue;
            }

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $details[] = [
                'field' => $fieldName,
                'size_bytes' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'valid' => $file->isValid(),
                'upload_error' => $file->getError(),
            ];
        }

        return $details;
    }
}
