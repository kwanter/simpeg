<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PkpErrorResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Laravel's routing pipeline renders exceptions before route middleware unwinds.
        $exception = $response->exception ?? null;

        if ($exception instanceof ValidationException) {
            $response = $this->error('validation_failed', 'The submitted PKP data is invalid.', 422, $exception->errors());
        } elseif ($exception instanceof QueryException) {
            $response = $this->error('database_unavailable', 'The PKP database operation is temporarily unavailable.', 503);
        } elseif ($exception instanceof FilesystemException) {
            $response = $this->error('document_storage_failed', 'The PKP document could not be stored.', 503);
        } elseif ($exception instanceof AuthorizationException) {
            $response = $this->error('forbidden', 'You are not authorized to access this PKP resource.', 403);
        } elseif ($exception instanceof ModelNotFoundException) {
            $response = $this->error('resource_not_found', 'The PKP resource could not be found.', 404);
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $code = preg_match('/^[a-z][a-z0-9_]+$/', $exception->getMessage()) === 1
                ? $exception->getMessage()
                : match ($status) {
                    403 => 'forbidden',
                    404 => 'resource_not_found',
                    409 => 'resource_conflict',
                    503 => 'service_unavailable',
                    default => 'request_failed',
                };
            $response = $this->error($code, 'The PKP request could not be completed.', $status);
        }

        if ($response instanceof JsonResponse && $response->getStatusCode() === 422) {
            $data = $response->getData(true);
            $fields = $data['error']['fields'] ?? [];
            if ($fields === [] && in_array($data['error']['code'] ?? null, ['invalid_pdf', 'pdf_validation_limit'], true)) {
                $fields = ['file' => [$data['error']['message']]];
            }
            if ($fields !== []) {
                $data['error']['fields'] = $fields;
                $data['errors'] = $fields;
                $response->setData($data);
            }
            if (! $request->expectsJson() && ! $request->isMethod('GET')) {
                return back()->withErrors($fields ?: ['pkp' => [$data['error']['message'] ?? 'The submitted PKP data is invalid.']])
                    ->withInput($request->except(array_keys($request->allFiles())));
            }
        }

        return $response;
    }

    private function error(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return response()->json(['error' => $error], $status);
    }
}
