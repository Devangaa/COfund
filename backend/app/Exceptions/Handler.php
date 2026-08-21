<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        if ($request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render an exception as a JSON response for API requests.
     *
     * Ensures that ALL errors on API routes return structured JSON
     * instead of redirecting to the web root.
     */
    protected function renderApiException($request, Throwable $e)
    {
        $e = $this->prepareException($e);

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Unauthenticated.',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
            ], 403);
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'The given data was invalid.',
                'errors' => $e->errors(),
            ], $e->status);
        }

        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error'),
            ], $status);
        }

        return response()->json([
            'message' => $e->getMessage() ?: 'Server Error',
        ], 500);
    }
}
