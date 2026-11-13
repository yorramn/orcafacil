<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Quote;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicQuoteController extends Controller
{
    public function show(string $token): Response
    {
        $quote = $this->findByToken($token);
        if ($quote === null) {
            return self::publicError($token);
        }
        if ($quote->public_revoked_at !== null) {
            return Inertia::render('Public/Quote', ['state' => 'revoked', 'token' => $token, 'quote' => null]);
        }
        if ($quote->public_token_expires_at?->isPast()) {
            return Inertia::render('Public/Quote', ['state' => 'expired', 'token' => $token, 'quote' => null]);
        }

        $quote = DB::transaction(function () use ($quote): Quote {
            app(TenantContext::class)->set($quote->tenant);

            if ($quote->status === QuoteStatus::Sent) {
                $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === QuoteStatus::Sent) {
                    $locked->update(['status' => QuoteStatus::Viewed, 'viewed_at' => now()]);
                    $locked->events()->create([
                        'actor_user_id' => null,
                        'event' => 'viewed',
                        'metadata_json' => ['channel' => 'public'],
                        'occurred_at' => now(),
                    ]);
                }
                $quote = $locked;
            }

            return $quote;
        });

        return Inertia::render('Public/Quote', ['quote' => $this->publicData($quote, $token), 'state' => self::publicState($quote), 'token' => $token]);
    }

    public function approve(Request $r, string $token): Response
    {
        return $this->decide($r, $token, QuoteStatus::Approved);
    }

    public function reject(Request $r, string $token): Response
    {
        return $this->decide($r, $token, QuoteStatus::Rejected);
    }

    public function attachment(string $token, Attachment $attachment): StreamedResponse
    {
        $quote = $this->findByToken($token);
        abort_unless($quote !== null && $quote->publicIsAvailable(), 404);
        abort_unless($attachment->attachable_type === Quote::class && (int) $attachment->attachable_id === (int) $quote->id && (int) $attachment->tenant_id === (int) $quote->tenant_id, 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    private function decide(Request $r, string $token, QuoteStatus $status): Response
    {
        $quote = $this->findByToken($token);
        if ($quote === null) {
            return self::publicError($token);
        }
        if ($quote->public_revoked_at !== null) {
            return Inertia::render('Public/Quote', ['state' => 'revoked', 'token' => $token, 'quote' => null]);
        }
        if ($quote->public_token_expires_at?->isPast()) {
            return Inertia::render('Public/Quote', ['state' => 'expired', 'token' => $token, 'quote' => null]);
        }
        $r->validate(['name' => ['required', 'string', 'max:255']]);
        $locked = DB::transaction(function () use ($quote, $status, $r, $token): Quote {
            $locked = Quote::query()->with('client')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            app(TenantContext::class)->set($locked->tenant);
            if (! $locked->publicIsAvailable() || $locked->public_token_hash !== hash('sha256', $token)) {
                throw ValidationException::withMessages(['quote' => 'Este link não está mais disponível.']);
            }
            if ($locked->status === $status) {
                return $locked;
            } if (! $locked->status->canTransitionTo($status)) {
                throw ValidationException::withMessages(['quote' => 'Esta proposta já foi finalizada.']);
            } if (strcasecmp(trim($r->string('name')->toString()), trim($locked->client->name)) !== 0) {
                throw ValidationException::withMessages(['name' => 'O nome não confere com o cliente da proposta.']);
            } $locked->update(['status' => $status, $status === QuoteStatus::Approved ? 'approved_at' : 'rejected_at' => now()]);
            $locked->events()->create(['tenant_id' => $locked->tenant_id, 'event' => $status->value, 'metadata_json' => ['name' => $r->string('name')->toString()], 'occurred_at' => now()]);
            ActivityLog::create(['tenant_id' => $locked->tenant_id, 'event' => 'quote.'.$status->value, 'subject_type' => Quote::class, 'subject_id' => $locked->id, 'properties_json' => ['name' => $r->string('name')->toString()]]);

            return $locked->refresh();
        });

        return Inertia::render('Public/Quote', ['quote' => $this->publicData($locked, $token), 'state' => $status->value, 'token' => $token]);
    }

    private function findByToken(string $token): ?Quote
    {
        return Quote::query()->with(['client', 'tenant'])->where('public_token_hash', hash('sha256', $token))->first();
    }

    /** @return array<string, mixed> */
    private function publicData(Quote $quote, string $token): array
    {
        $quote->loadMissing(['items', 'attachments']);
        $eventLabels = [
            'sent' => 'Orçamento enviado',
            'resent' => 'Orçamento reenviado',
            'viewed' => 'Orçamento visualizado',
            'approved' => 'Orçamento aprovado',
            'rejected' => 'Orçamento recusado',
        ];

        return [
            'provider_name' => $quote->tenant?->name,
            'number' => $quote->display_number,
            'title' => $quote->title,
            'description' => $quote->description,
            'status' => $quote->status->value,
            'created_at' => $quote->created_at?->toIso8601String(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'payment_terms' => $quote->payment_terms,
            'public_notes' => $quote->public_notes,
            'total_cents' => (int) $quote->total_cents,
            'total' => self::formatCents((int) $quote->total_cents),
            'items' => $quote->items->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->description_snapshot,
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit,
                'subtotal_cents' => (int) $item->subtotal_cents,
                'subtotal' => self::formatCents((int) $item->subtotal_cents),
            ])->values()->all(),
            'attachments' => $quote->attachments->map(fn (Attachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => (int) $attachment->size_bytes,
                'size_human' => self::formatBytes((int) $attachment->size_bytes),
                'url' => route('public.quotes.attachments.show', ['token' => $token, 'attachment' => $attachment]),
            ])->values()->all(),
            'history' => $quote->events()->whereIn('event', array_keys($eventLabels))->orderBy('occurred_at')->orderBy('id')->get(['id', 'event', 'occurred_at'])
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'description' => $eventLabels[$event->event],
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    private static function formatCents(int $cents): string
    {
        $whole = intdiv($cents, 100);
        $fraction = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return number_format($whole, 0, ',', '.').','.$fraction;
    }

    private static function publicState(Quote $quote): string
    {
        return match ($quote->status) {
            QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Viewed => 'valid',
            QuoteStatus::Approved => 'approved',
            QuoteStatus::Rejected => 'rejected',
            default => 'error',
        };
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return intdiv($bytes + 512, 1024).' KB';
        }

        return intdiv($bytes + (512 * 1024), 1024 * 1024).' MB';
    }

    private static function publicError(string $token): Response
    {
        return Inertia::render('Public/Quote', [
            'state' => 'invalid',
            'message' => 'Este link público não está disponível.',
            'token' => $token,
            'quote' => null,
        ]);
    }
}
