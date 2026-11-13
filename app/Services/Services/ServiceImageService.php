<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ServiceImageService
{
    public function store(Service $service, UploadedFile $file, int $userId): Attachment
    {
        $path = $file->store('attachments/'.$service->tenant_id.'/services', 'private');
        if ($path === false) {
            throw new RuntimeException('Não foi possível armazenar a imagem do serviço.');
        }

        try {
            $attachment = DB::transaction(function () use ($service, $file, $userId, $path): Attachment {
                $attachment = $service->attachments()->create([
                    'tenant_id' => $service->tenant_id,
                    'uploaded_by' => $userId,
                    'disk' => 'private',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                ]);
                ActivityLog::create([
                    'tenant_id' => $service->tenant_id,
                    'actor_user_id' => $userId,
                    'event' => 'service.image_uploaded',
                    'subject_type' => Service::class,
                    'subject_id' => $service->id,
                    'properties_json' => ['attachment_id' => $attachment->id],
                ]);

                return $attachment;
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }

        return $attachment;
    }
}
