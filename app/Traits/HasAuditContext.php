<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

trait HasAuditContext
{
    protected static function bootHasAuditContext(): void
    {
        static::creating(function (object $model): void {
            if ($model->getAttribute('request_id') === null) {
                $model->setAttribute('request_id', self::requestId());
            }

            if ($model->getAttribute('ip_hash') === null && self::clientIp() !== null) {
                $model->setAttribute('ip_hash', hash_hmac('sha256', self::clientIp(), (string) config('app.key')));
            }
        });
    }

    private static function requestId(): string
    {
        if (! app()->bound('request')) {
            return (string) Str::uuid();
        }

        $request = request();
        $id = $request->attributes->get('request_id') ?? $request->header('X-Request-Id');
        $id = preg_replace('/[^A-Za-z0-9._:-]/', '', (string) $id);

        return Str::limit($id !== '' ? $id : (string) Str::uuid(), 100, '');
    }

    private static function clientIp(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request()->getClientIp();
    }
}
