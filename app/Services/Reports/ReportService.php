<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportService
{
    /** @return array<string, mixed> */
    public function build(Tenant $tenant, ?string $from = null, ?string $to = null): array
    {
        $start = $from !== null ? CarbonImmutable::parse($from, 'America/Sao_Paulo')->startOfDay() : CarbonImmutable::now('America/Sao_Paulo')->startOfMonth();
        $end = $to !== null ? CarbonImmutable::parse($to, 'America/Sao_Paulo')->endOfDay() : CarbonImmutable::now('America/Sao_Paulo')->endOfMonth();
        $quotes = $tenant->quotes()->whereBetween('created_at', [$start->utc(), $end->utc()])->get(['id', 'status', 'total_cents', 'created_at']);
        $receivables = $tenant->receivables()->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])->get(['status', 'amount_cents', 'paid_amount_cents', 'due_date']);
        $services = $tenant->services()->whereBetween('created_at', [$start->utc(), $end->utc()])->get(['status']);

        $approved = $quotes->where('status', 'approved');
        $quoteStatus = collect(['draft', 'sent', 'viewed', 'approved', 'rejected', 'expired', 'canceled'])
            ->mapWithKeys(fn (string $status): array => [$status => $quotes->where('status', $status)->count()])->all();
        $salesByPeriod = $approved->groupBy(fn ($quote): string => $quote->created_at->timezone('America/Sao_Paulo')->toDateString())
            ->map(fn ($rows, string $date): array => ['date' => $date, 'count' => $rows->count(), 'total_cents' => (int) $rows->sum('total_cents')])
            ->values()->all();
        $expectedCents = (int) $receivables->where('status', '!=', 'canceled')->sum('amount_cents');
        $receivedCents = (int) $tenant->receivables()->where('status', 'paid')->whereBetween('paid_at', [$start->utc(), $end->utc()])->sum('paid_amount_cents');
        $overdueCents = (int) $receivables->where('status', 'overdue')->sum(fn ($row): int => max(0, (int) $row->amount_cents - (int) $row->paid_amount_cents));
        $totalQuotes = $quotes->count();
        $approvedCount = $approved->count();

        $clients = $tenant->clients()
            ->withCount(['quotes', 'services', 'receivables'])
            ->withSum('receivables', 'amount_cents')
            ->orderByDesc('receivables_sum_amount_cents')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn ($client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'quotes_count' => (int) $client->quotes_count,
                'services_count' => (int) $client->services_count,
                'receivables_count' => (int) $client->receivables_count,
                'volume_cents' => (int) ($client->receivables_sum_amount_cents ?? 0),
            ])->values()->all();

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'sales_by_period' => $salesByPeriod,
            'quote_status' => $quoteStatus,
            'conversion' => [
                'total_quotes' => $totalQuotes,
                'approved_quotes' => $approvedCount,
                'rate_basis_points' => $totalQuotes > 0 ? intdiv($approvedCount * 10000, $totalQuotes) : 0,
            ],
            'receivables' => ['expected_cents' => $expectedCents, 'received_cents' => $receivedCents, 'overdue_cents' => $overdueCents],
            'services_by_status' => $services->groupBy('status')->map->count()->all(),
            'clients_by_volume' => $clients,
            'metrics' => [
                ['key' => 'expected', 'label' => 'Previsto', 'value_cents' => $expectedCents, 'value' => self::formatCents($expectedCents)],
                ['key' => 'received', 'label' => 'Recebido', 'value_cents' => $receivedCents, 'value' => self::formatCents($receivedCents)],
                ['key' => 'overdue', 'label' => 'Vencido', 'value_cents' => $overdueCents, 'value' => self::formatCents($overdueCents)],
            ],
        ];
    }

    public function exportCsv(Tenant $tenant, ?string $from = null, ?string $to = null): StreamedResponse
    {
        $report = $this->build($tenant, $from, $to);
        $period = $report['period'];

        return response()->streamDownload(function () use ($report, $period): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['section', 'key', 'label', 'count', 'value_integer', 'from', 'to']);
            foreach ($report['sales_by_period'] as $row) {
                fputcsv($output, ['sales_by_period', $row['date'], 'approved', (int) $row['count'], (int) $row['total_cents'], $period['from'], $period['to']]);
            }
            foreach ($report['quote_status'] as $status => $count) {
                fputcsv($output, ['quote_status', $status, $status, (int) $count, 0, $period['from'], $period['to']]);
            }
            fputcsv($output, ['conversion', 'approved_quotes', 'approved_quotes', (int) $report['conversion']['approved_quotes'], (int) $report['conversion']['rate_basis_points'], $period['from'], $period['to']]);
            foreach ($report['receivables'] as $key => $cents) {
                fputcsv($output, ['receivables', $key, $key, 0, (int) $cents, $period['from'], $period['to']]);
            }
            foreach ($report['services_by_status'] as $status => $count) {
                fputcsv($output, ['services_by_status', $status, $status, (int) $count, 0, $period['from'], $period['to']]);
            }
            foreach ($report['clients_by_volume'] as $client) {
                fputcsv($output, ['clients_by_volume', $client['name'], 'client_volume', (int) $client['quotes_count'], (int) $client['volume_cents'], $period['from'], $period['to']]);
            }
            fclose($output);
        }, 'reports-'.$period['from'].'-'.$period['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    private static function formatCents(int $cents): string
    {
        $whole = intdiv($cents, 100);
        $fraction = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return 'R$ '.number_format($whole, 0, ',', '.').','.$fraction;
    }
}
