<?php

namespace App\Exceptions;

use App\Traits\ResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    use ResponseTrait;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        AuthenticationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        ThrottleRequestsException::class,
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        if (!$this->shouldntReport($exception)) {
            Log::error("Exception: {$exception->getMessage()}", [
                'exception' => $exception,
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTraceAsString(),
            ]);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @param  Throwable  $exception
     * @return JsonResponse
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception): JsonResponse
    {
        if ($exception instanceof ModelNotFoundException) {
            return $this->errorResponse('Resource not found', 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->errorResponse('Endpoint not found', 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse('Method Not Allowed', 405, [
                'error' => 'The requested method is not allowed for this resource.'
            ]);
        }

        if ($exception instanceof AuthenticationException) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->errorResponse('You do not have permission to perform this action', 403);
        }

        if ($exception instanceof ValidationException) {
            return $this->validationErrorResponse($exception);
        }

        if ($exception instanceof ThrottleRequestsException) {
            return $this->errorResponse('Too many requests, please slow down', 429);
        }

        if ($exception instanceof HttpException) {
            return $this->errorResponse($exception->getMessage(), $exception->getStatusCode());
        }

        // Log unexpected exceptions for debugging
        Log::error("Unhandled Exception: {$exception->getMessage()}", [
            'exception' => $exception,
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $exception->getTraceAsString(),
        ]);

        return $this->errorResponse('An internal server error occurred', 500);
    }

    /**
     * Handle validation errors properly.
     */
    protected function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $exception->errors(),
        ], 422);
    }
}