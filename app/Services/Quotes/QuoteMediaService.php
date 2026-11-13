<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Quote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class QuoteMediaService
{
    public function store(Quote $quote, UploadedFile $file, int $userId): Attachment
    {
        $path = $file->store('attachments/'.$quote->tenant_id.'/quotes', 'private');
        if ($path === false) {
            throw new RuntimeException('Não foi possível armazenar o anexo do orçamento.');
        }

        try {
            $attachment = $quote->attachments()->create([
            'tenant_id' => $quote->tenant_id,
            'uploaded_by' => $userId,
            'disk' => 'private',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            ]);

            ActivityLog::create(['tenant_id' => $quote->tenant_id, 'actor_user_id' => $userId, 'event' => 'quote.attachment_uploaded', 'subject_type' => Attachment::class, 'subject_id' => $attachment->id, 'properties_json' => ['quote_id' => $quote->id]]);
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }

        return $attachment;
    }
}
