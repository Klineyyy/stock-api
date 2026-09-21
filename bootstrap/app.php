<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every error from the API is JSON with a `message`, and a stable `code` a client can switch on.
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // These are the caller's mistakes (unknown item, not enough stock), not faults worth an ERROR in the log.
        $exceptions->dontReport(ApiException::class);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Unauthenticated.', 'code' => 'unauthenticated'], 401)
            : null);

        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'You are not allowed to do that.', 'code' => 'forbidden'], 403)
            : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Not found.', 'code' => 'not_found'], 404)
            : null);

        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Too many requests. Try again shortly.', 'code' => 'too_many_requests'], 429, $e->getHeaders())
            : null);

        $exceptions->render(fn (ValidationException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => $e->getMessage(), 'code' => 'validation_failed', 'errors' => $e->errors()], 422)
            : null);
    })->create();
