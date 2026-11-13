<?php

namespace App\Jobs;

use App\Enums\ReceivableStatus;
use App\Models\Receivable;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class MarkOverdueReceivables implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        Tenant::query()->select('id')->chunkById(100, function ($tenants): void {
            foreach ($tenants as $tenant) {
                DB::transaction(function () use ($tenant): void {
                    Receivable::query()->where('tenant_id', $tenant->id)->where('status', ReceivableStatus::Pending)->whereDate('due_date', '<', CarbonImmutable::today('America/Sao_Paulo'))->update(['status' => ReceivableStatus::Overdue]);
                });
            }
        });
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('receivables.overdue_job_failed', ['error' => $e->getMessage()]);
    }
}
