<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CatalogItem;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Arr;

final class CreateCatalogItem
{
    public function __construct(private readonly TenantContext $context) {}

    public function execute(array $data): CatalogItem
    {
        return CatalogItem::query()->create([...Arr::except($data, ['tenant_id']), 'tenant_id' => $this->context->id()]);
    }
}
