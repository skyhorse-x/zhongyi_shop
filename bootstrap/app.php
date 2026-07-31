<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS 中间件
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // 注册路由中间件别名
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
        ]);

        // 全局API请求日志中间件
        $middleware->append(\App\Http\Middleware\RequestLogMiddleware::class);

        // API 速率限制
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API请求返回JSON格式错误
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 验证异常处理
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::channel('request')->warning('Validation failed', [
                    'url' => $request->fullUrl(),
                    'errors' => $e->errors(),
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'code' => 422,
                    'message' => '参数验证失败',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 认证异常处理
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::channel('request')->warning('Authentication failed', [
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'code' => 401,
                    'message' => '未登录或登录已过期',
                ], 401);
            }
        });

        // 模型未找到异常处理
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());
                Log::channel('request')->warning('Model not found', [
                    'url' => $request->fullUrl(),
                    'model' => $model,
                ]);

                return response()->json([
                    'code' => 404,
                    'message' => "请求的资源不存在: {$model}",
                ], 404);
            }
        });

        // 路由未找到异常处理
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::channel('request')->warning('Route not found', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);

                return response()->json([
                    'code' => 404,
                    'message' => '请求的接口不存在',
                ], 404);
            }
        });

        // 方法不允许异常处理
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::channel('request')->warning('Method not allowed', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);

                return response()->json([
                    'code' => 405,
                    'message' => '请求方法不允许',
                ], 405);
            }
        });

        // 通用异常处理
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::error('Unhandled exception', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $isLocal = app()->isLocal();
                return response()->json([
                    'code' => 500,
                    'message' => $isLocal ? $e->getMessage() : '服务器内部错误',
                    'debug' => $isLocal ? [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(5)->toArray(),
                    ] : null,
                ], 500);
            }
        });
    })->create();
