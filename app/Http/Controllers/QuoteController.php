<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Models\CatalogItem;
use App\Models\Quote;
use App\Services\Quotes\QuoteService;
use App\Services\Quotes\QuoteMediaService;
use App\Services\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

final class QuoteController extends Controller
{
    public function index(Request $r, TenantContext $c): Response
    {
        $this->authorize('viewAny', Quote::class);

        $sort = $r->string('sort')->toString();
        $sort = in_array($sort, ['number', 'total_cents', 'valid_until', 'created_at'], true) ? $sort : 'created_at';
        $direction = $r->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $quotes = Quote::forTenant($c->id())->with(['client', 'service:id,quote_id'])->when($r->filled('status'), fn ($q) => $q->where('status', $r->string('status')))
            ->when($r->filled('focus'), fn ($q) => $q->whereKey($r->integer('focus')))
            ->when($r->filled('search'), function ($q) use ($r): void {
                $search = trim($r->string('search')->toString());
                preg_match('/^0*(\d+)-(\d{2})$/', $search, $matches);
                $q->where(function ($query) use ($search, $matches): void {
                    $query->where('number', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%'.$search.'%'));
                    if ($matches !== []) {
                        $year = 2000 + (int) $matches[2];
                        $query->orWhere(fn ($number) => $number->where('number', (int) $matches[1])
                            ->whereBetween('created_at', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"]));
                    }
                });
            })
            ->orderBy($sort, $direction)->paginate(15)->withQueryString()
            ->through(fn (Quote $quote): array => [...$quote->toArray(), 'public_url' => $quote->publicUrl(), 'public_link_legacy' => $quote->publicIsAvailable() && $quote->publicUrl() === null]);

        return Inertia::render('Quotes/Index', ['quotes' => $quotes, 'search' => $r->string('search')->toString(), 'focus' => $r->integer('focus'), 'status' => $r->string('status')->toString(), 'sort' => $sort, 'direction' => $direction]);
    }

    public function store(QuoteRequest $r, QuoteService $s, QuoteMediaService $media): RedirectResponse
    {
        $data = $r->validated();
        unset($data['media']);
        $quote = $this->saveWithMedia(fn (): Quote => $s->create($data, $r->user()->id), $media, $r);

        return redirect()->route('quotes.index', ['focus' => $quote->id, 'search' => $quote->display_number])
            ->with('success', 'Orçamento criado com sucesso!');
    }

    public function update(QuoteRequest $r, Quote $quote, QuoteService $s, QuoteMediaService $media): RedirectResponse
    {
        $this->authorize('update', $quote);
        $data = $r->validated();
        unset($data['media']);
        $quote = $this->saveWithMedia(fn (): Quote => $s->update($quote, $data), $media, $r);

        return redirect()->route('quotes.index', ['focus' => $quote->id, 'search' => $quote->display_number])
            ->with('success', 'Orçamento editado com sucesso!');
    }

    public function send(Quote $quote, QuoteService $s): RedirectResponse
    {
        $this->authorize('send', $quote);
        $sent = $s->send($quote);

        return back()->with('success', 'Orçamento enviado por e-mail com sucesso!');
    }

    public function publicLink(Quote $quote, QuoteService $service): RedirectResponse
    {
        $this->authorize('view', $quote);
        $service->createPublicLink($quote);

        return back()->with('success', 'Link público disponível para copiar.');
    }

    public function download(Quote $quote): HttpResponse
    {
        $this->authorize('view', $quote);
        $quote->load(['client', 'items', 'tenant']);

        return Pdf::loadView('quotes.pdf', ['quote' => $quote])
            ->setPaper('a4')
            ->download('orcamento-'.$quote->display_number.'.pdf');
    }

    public function show(Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $quote->load(['client', 'items', 'events', 'versions', 'attachments', 'service:id,quote_id']);

        $publicUrl = $quote->publicUrl();

        return Inertia::render('Quotes/Show', ['quote' => $quote, 'publicUrl' => $publicUrl, 'legacyLink' => $quote->publicIsAvailable() && $publicUrl === null]);
    }

    public function create(): Response
    {
        $this->authorize('create', Quote::class);

        $tenant = app(TenantContext::class)->get();

        return Inertia::render('Quotes/Create', [
            'clients' => $tenant->clients()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'catalog' => CatalogItem::query()->forTenant($tenant->id)->where('active', true)->orderBy('name')->get(['id', 'name', 'unit', 'default_unit_price_cents']),
        ]);
    }

    public function edit(Quote $quote): Response
    {
        $this->authorize('update', $quote);

        $tenant = app(TenantContext::class)->get();

        return Inertia::render('Quotes/Edit', [
            'quote' => $quote->load(['client', 'items']),
            'clients' => $tenant->clients()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'catalog' => CatalogItem::query()->forTenant($tenant->id)->where('active', true)->orderBy('name')->get(['id', 'name', 'unit', 'default_unit_price_cents']),
        ]);
    }

    public function versions(Quote $quote): Response
    {
        $this->authorize('view', $quote);
        $quote->loadMissing('client');
        $active = $quote->publicIsAvailable();
        $publicUrl = $quote->publicUrl();

        return Inertia::render('Quotes/Versions', ['quote' => $quote, 'versions' => $quote->versions()->with('creator')->get(), 'events' => $quote->events, 'attachments' => $quote->attachments, 'link' => ['active' => $active, 'legacy' => $active && $publicUrl === null, 'status' => $active ? 'active' : 'expired', 'url' => $publicUrl, 'actions' => ['resend' => route('quotes.resend-link', $quote), 'revoke' => route('quotes.revoke-link', $quote)]]]);
    }

    public function revoke(Quote $quote): RedirectResponse
    {
        $this->authorize('revoke', $quote);
        $quote->update(['public_revoked_at' => now()]);

        return back()->with('success', 'Link público revogado.');
    }

    public function resend(Quote $quote, QuoteService $service): RedirectResponse
    {
        $this->authorize('resend', $quote);
        $sent = $service->resend($quote);

        return back()->with('success', 'Orçamento reenviado por e-mail com sucesso!');
    }

    /** @param callable(): Quote $save */
    private function saveWithMedia(callable $save, QuoteMediaService $media, QuoteRequest $request): Quote
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($save, $media, $request, &$storedPaths): Quote {
                $quote = $save();
                foreach ($request->file('media', []) as $file) {
                    $attachment = $media->store($quote, $file, $request->user()->id);
                    $storedPaths[] = $attachment->path;
                }

                return $quote;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('private')->delete($storedPaths);
            }
            throw $exception;
        }
    }
}
