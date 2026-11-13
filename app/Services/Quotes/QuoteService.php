<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\QuoteStatus;
use App\Models\ActivityLog;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Notifications\QuoteSent;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class QuoteService
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(array $data, int $userId): Quote
    {
        $tenantId = $this->context->id();
        $this->assertTenantReferences($data, $tenantId);

        return DB::transaction(function () use ($data, $userId, $tenantId) {
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $count = $tenant->quotes()->where('created_at', '>=', now()->startOfMonth())->count();
            if ($tenant->subscriptions()->whereIn('status', ['trialing', 'active'])->latest()->value('plan') === 'basic' && $count >= 30) {
                throw ValidationException::withMessages(['title' => 'Limite mensal de orçamentos atingido.']);
            }
            $items = $data['items'];
            unset($data['items']);
            $number = ((int) $tenant->quotes()->lockForUpdate()->max('number')) + 1;
            $quote = $tenant->quotes()->create(array_merge($data, ['created_by' => $userId, 'number' => $number, 'status' => QuoteStatus::Draft]));
            $this->syncItems($quote, $items);

            return $quote->load(['client', 'items']);
        });
    }

    public function update(Quote $quote, array $data): Quote
    {
        abort_unless((int) $quote->tenant_id === $this->context->id(), 404);
        $this->assertTenantReferences($data, $this->context->id());

        return DB::transaction(function () use ($quote, $data) {
            $items = $data['items'];
            unset($data['items']);
            $quote->update($data);
            $this->syncItems($quote, $items);

            return $quote->refresh()->load(['client', 'items']);
        });
    }

    public function send(Quote $quote): Quote
    {
        $email = trim((string) $quote->loadMissing('client')->client?->email);
        if ($email === '') {
            throw ValidationException::withMessages(['client_id' => 'Cadastre o e-mail do cliente antes de enviar o orçamento.']);
        }

        return DB::transaction(function () use ($quote, $email) {
            if (! $quote->status->canTransitionTo(QuoteStatus::Sent)) {
                throw ValidationException::withMessages(['status' => 'Transição inválida.']);
            }
            $token = $this->ensurePublicToken($quote);
            $quote->load('items');
            $quote->versions()->create(['tenant_id' => $quote->tenant_id, 'version' => ((int) $quote->versions()->max('version')) + 1, 'snapshot_json' => ['title' => $quote->title, 'description' => $quote->description, 'items' => $quote->items->toArray(), 'subtotal_cents' => $quote->subtotal_cents, 'discount_cents' => $quote->discount_cents, 'extra_cents' => $quote->extra_cents, 'total_cents' => $quote->total_cents], 'total_cents' => $quote->total_cents, 'created_by' => auth()->id()]);
            $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
            $quote->events()->create(['tenant_id' => $quote->tenant_id, 'actor_user_id' => auth()->id(), 'event' => 'sent', 'occurred_at' => now()]);
            ActivityLog::create(['tenant_id' => $quote->tenant_id, 'actor_user_id' => auth()->id(), 'event' => 'quote.sent', 'subject_type' => Quote::class, 'subject_id' => $quote->id]);
            $quote->setAttribute('public_token', $token);

            $this->deliverQuoteEmail($quote, $email);

            return $quote;
        });
    }

    public function resend(Quote $quote): Quote
    {
        abort_unless((int) $quote->tenant_id === $this->context->id(), 404);
        $email = trim((string) $quote->loadMissing('client')->client?->email);
        if ($email === '') {
            throw ValidationException::withMessages(['client_id' => 'Cadastre o e-mail do cliente antes de reenviar o orçamento.']);
        }
        if (! in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Viewed], true)) {
            throw ValidationException::withMessages(['status' => 'Este orçamento não pode ser reenviado.']);
        }
        if ($quote->status === QuoteStatus::Draft) {
            return $this->send($quote);
        }

        return DB::transaction(function () use ($quote, $email): Quote {
            $token = $this->ensurePublicToken($quote);
            $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
            $quote->events()->create(['tenant_id' => $quote->tenant_id, 'actor_user_id' => auth()->id(), 'event' => 'resent', 'occurred_at' => now()]);
            $quote = $quote->refresh();
            $quote->setAttribute('public_token', $token);

            $this->deliverQuoteEmail($quote, $email);

            return $quote;
        });
    }

    private function deliverQuoteEmail(Quote $quote, string $email): void
    {
        try {
            Notification::route('mail', $email)->notifyNow(new QuoteSent(
                $quote->display_number,
                $quote->title,
                $quote->public_token,
            ));
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar orçamento por e-mail.', [
                'quote_id' => $quote->id,
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'email' => 'Não foi possível enviar o orçamento por e-mail. Tente novamente em instantes.',
            ]);
        }
    }

    public function createPublicLink(Quote $quote): string
    {
        abort_unless((int) $quote->tenant_id === $this->context->id(), 404);

        return DB::transaction(function () use ($quote): string {
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $token = $this->ensurePublicToken($locked);

            return route('public.quotes.show', ['token' => $token]);
        });
    }

    private function ensurePublicToken(Quote $quote): string
    {
        if ($quote->publicIsAvailable() && $quote->public_token_encrypted) {
            return $quote->public_token_encrypted;
        }

        if ($quote->valid_until?->endOfDay()->isPast()) {
            throw ValidationException::withMessages(['valid_until' => 'Atualize a validade do orçamento antes de disponibilizar o link.']);
        }

        $token = Str::random(64);
        $quote->update([
            'public_token_hash' => hash('sha256', $token),
            'public_token_encrypted' => $token,
            'public_token_expires_at' => $quote->valid_until?->endOfDay() ?? now()->addDays(30),
            'public_revoked_at' => null,
        ]);

        return $token;
    }

    private function syncItems(Quote $quote, array $items): void
    {
        $subtotal = 0;
        $rows = [];
        foreach ($items as $i => $item) {
            $qty = self::normalizeQuantity($item['quantity']);
            $unit = (int) $item['unit_price_cents'];
            $discount = (int) ($item['discount_cents'] ?? 0);
            $line = self::calculateLineTotalCents($qty, $unit, $discount);
            $subtotal += $line;
            $rows[] = ['tenant_id' => $quote->tenant_id, 'catalog_item_id' => $item['catalog_item_id'] ?? null, 'description_snapshot' => $item['description'], 'unit' => $item['unit'], 'quantity' => $qty, 'unit_price_cents' => $unit, 'discount_cents' => $discount, 'subtotal_cents' => $line, 'sort_order' => $i];
        } $discount = (int) ($quote->discount_cents ?? 0);
        $extra = (int) ($quote->extra_cents ?? 0);
        $quote->items()->delete();
        $quote->items()->createMany($rows);
        $quote->update(['subtotal_cents' => $subtotal, 'total_cents' => max(0, $subtotal - $discount + $extra)]);
    }

    private function assertTenantReferences(array $data, int $tenantId): void
    {
        if (! Client::query()->whereKey($data['client_id'] ?? null)->where('tenant_id', $tenantId)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Cliente inválido para o tenant atual.']);
        }

        foreach ($data['items'] ?? [] as $index => $item) {
            if (($item['catalog_item_id'] ?? null) !== null && ! CatalogItem::query()->whereKey($item['catalog_item_id'])->where('tenant_id', $tenantId)->exists()) {
                throw ValidationException::withMessages(["items.$index.catalog_item_id" => 'Item de catálogo inválido para o tenant atual.']);
            }
        }
    }

    public static function normalizeQuantity(string|int $quantity): string
    {
        $value = str_replace(',', '.', trim((string) $quantity));
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/', $value)) {
            throw ValidationException::withMessages(['items' => 'A quantidade deve ser um número positivo com até 3 casas decimais.']);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');
        $normalized = $whole.'.'.$fraction;
        if ((int) str_replace('.', '', $normalized) <= 0) {
            throw ValidationException::withMessages(['items' => 'A quantidade deve ser maior que zero.']);
        }

        return $normalized;
    }

    public static function calculateLineTotalCents(string|int $quantity, int $unitPriceCents, int $discountCents = 0): int
    {
        $quantityMilli = (int) str_replace('.', '', self::normalizeQuantity($quantity));

        return max(0, intdiv(($quantityMilli * $unitPriceCents) + 500, 1000) - $discountCents);
    }
}
