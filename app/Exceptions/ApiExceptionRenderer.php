<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Every error under /api/* comes back in the standard envelope (Phase 4):
 *
 *   401 { "success": false, "message": "Unauthenticated." }
 *   403 { "success": false, "message": "You are not authorized to perform this action." }
 *   422 { "success": false, "message": "Validation failed.", "errors": {…} }
 *
 * Server errors (500) never reveal the exception, SQL or file paths, even
 * with APP_DEBUG on; the details go to the log with a reference the user
 * can quote to the office.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        return match (true) {
            // Already a finished response (for example our rate-limit answers): keep it.
            $e instanceof HttpResponseException => self::keep($e->getResponse()),
            $e instanceof ValidationException => ApiResponse::error('Validation failed.', 422, $e->errors()),
            $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
            $e instanceof AuthorizationException => ApiResponse::error('You are not authorized to perform this action.', 403),
            $e instanceof ModelNotFoundException => ApiResponse::error('The requested record was not found.', 404),
            $e instanceof TokenMismatchException => ApiResponse::error('Your session expired. Refresh the page and try again.', 419),
            $e instanceof ThrottleRequestsException => ApiResponse::error('Too many requests. Please wait and try again.', 429, [], [], $e->getHeaders()),
            $e instanceof HttpExceptionInterface => self::http($e),
            default => self::serverError($e),
        };
    }

    private static function keep(Response $response): JsonResponse
    {
        if ($response instanceof JsonResponse) {
            return $response;
        }

        return ApiResponse::error('The request could not be completed.', $response->getStatusCode(), [], [], $response->headers->all());
    }

    private static function http(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $message = match ($status) {
            401 => 'Unauthenticated.',
            403 => 'You are not authorized to perform this action.',
            404 => 'The requested resource was not found.',
            405 => 'This method is not allowed for this endpoint.',
            419 => 'Your session expired. Refresh the page and try again.',
            429 => 'Too many requests. Please wait and try again.',
            503 => 'The system is under maintenance. Please try again shortly.',
            default => $status >= 500 ? 'Something went wrong on our side. Please try again.' : 'The request could not be completed.',
        };

        return ApiResponse::error($message, $status, [], [], $e->getHeaders());
    }

    private static function serverError(Throwable $e): JsonResponse
    {
        $reference = strtoupper(bin2hex(random_bytes(4)));
        // The exception itself was already reported by the handler; this line ties it to the reference.
        Log::error("API error reference {$reference}", [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            // Path only: query strings can carry tokens or signatures.
            'path' => request()->path(),
            'user_id' => request()->user()?->getAuthIdentifier(),
        ]);

        return ApiResponse::error('Something went wrong on our side. Please try again.', 500, [], ['reference' => $reference]);
    }
}
