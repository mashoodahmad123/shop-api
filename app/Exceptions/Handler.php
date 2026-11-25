<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if (!$this->shouldReturnJson($request, $e)) {
                return null;
            }

            return $this->apiResponse($e);
        });
    }

    protected function shouldReturnJson($request, Throwable $e)
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }

    protected function apiResponse(Throwable $e): ?JsonResponse
    {
        if ($e instanceof BusinessException) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return $this->json($e->getMessage(), $e->status, $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return $this->json('Unauthenticated.', 401);
        }

        if ($e instanceof AuthorizationException) {
            return $this->json($e->getMessage() ?: 'This action is unauthorized.', 403);
        }

        $previous = $e->getPrevious();

        if ($e instanceof ModelNotFoundException || $previous instanceof ModelNotFoundException) {
            return $this->json('Resource not found.', 404);
        }

        if ($previous instanceof AuthorizationException) {
            return $this->json($previous->getMessage() ?: 'This action is unauthorized.', 403);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: (JsonResponse::$statusTexts[$status] ?? 'Error');

            return $this->json($message, $status, null, $e->getHeaders());
        }

        return $this->json(
            config('app.debug') ? $e->getMessage() : 'Server error.',
            500
        );
    }

    protected function json(string $message, int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        $body = [
            'status' => false,
            'message' => $message,
        ];

        if ($errors) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, $headers);
    }
}
