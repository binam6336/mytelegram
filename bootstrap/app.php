<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // خطای دیتابیس روی هاست (اتصال یا جدول‌های ساخته‌نشده) با راهنمای فارسی به‌جای 500 خالی
        $exceptions->render(function (\PDOException|\Illuminate\Database\QueryException $e, Request $request) {
            if (config('app.debug')) {
                return null; // صفحه‌ی خطای کامل لاراول
            }
            $msg = $e->getMessage();
            if (preg_match('/no such table|Base table or view not found|doesn\'t exist|42S02/i', $msg)) {
                $title = 'جدول‌های دیتابیس ساخته نشده‌اند';
                $hint = 'دستور «php artisan migrate --force» را در پوشه‌ی پروژه اجرا کنید (اگر ترمینال ندارید، یک بار از بخش Cron Jobs هاست).';
            } elseif (preg_match('/Access denied|Unknown database|Connection refused|could not find driver|SQLSTATE\[HY000\] \[\d+\]/i', $msg)) {
                $title = 'اتصال به دیتابیس برقرار نشد';
                $hint = 'مقادیر DB_HOST، DB_DATABASE، DB_USERNAME و DB_PASSWORD در فایل .env را بررسی کنید (در cPanel نام دیتابیس و کاربر معمولاً پیشوند نام کاربری هاست را دارند) و کاربر را به دیتابیس اضافه کنید.';
            } else {
                return null;
            }
            report($e);

            return response('<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.'</title>'
                .'<style>body{font:15px/1.8 Tahoma,sans-serif;background:#EFF4F9;margin:0;padding:24px}div{max-width:640px;margin:0 auto;background:#fff;border-radius:16px;padding:24px}h1{color:#B91C1C;font-size:19px}</style></head>'
                .'<body><div><h1>'.e($title).'</h1><p>'.e($hint).'</p><p style="color:#6B7C8C;font-size:13px">جزئیات در storage/logs/laravel.log ثبت شد.</p></div></body></html>', 503);
        });
    })->create();
