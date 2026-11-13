<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Http\Requests\QuoteAttachmentRequest;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Quote;
use App\Models\Service;
use App\Services\Quotes\QuoteMediaService;
use App\Services\Services\ServiceImageService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentController extends Controller
{
    public function store(AttachmentRequest $request, Service $service, ServiceImageService $images): RedirectResponse
    {
        $this->authorize('update', $service);
        $images->store($service, $request->file('file'), $request->user()->id);

        return back()->with('success', 'Imagem enviada.');
    }

    public function storeQuote(QuoteAttachmentRequest $request, Quote $quote, QuoteMediaService $media): RedirectResponse
    {
        $media->store($quote, $request->file('file'), $request->user()->id);

        return back()->with('success', 'Anexo enviado.');
    }

    public function view(Attachment $attachment): StreamedResponse
    {
        abort_unless((int) $attachment->tenant_id === app(TenantContext::class)->id(), 404);
        $this->authorize('view', $attachment);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $context = app(TenantContext::class);
        abort_unless((int) $attachment->tenant_id === $context->id(), 404);
        $this->authorize('delete', $attachment);
        $parent = $attachment->attachable;
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
        ActivityLog::create(['tenant_id' => $context->id(), 'actor_user_id' => request()->user()->id, 'event' => ($parent instanceof Quote ? 'quote' : 'service').'.attachment_deleted', 'subject_type' => Attachment::class, 'subject_id' => $attachment->id, 'properties_json' => ['attachable_type' => $parent::class, 'attachable_id' => $parent->id]]);

        return back()->with('success', 'Anexo removido.');
    }
}
