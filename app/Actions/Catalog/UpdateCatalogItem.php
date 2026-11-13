<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CatalogItem;
use Illuminate\Support\Arr;

final class UpdateCatalogItem
{
    public function execute(CatalogItem $item, array $data): CatalogItem
    {
        $item->update(Arr::except($data, ['tenant_id']));

        return $item->refresh();
    }
}
