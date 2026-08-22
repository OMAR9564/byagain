<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailDeliveries\Pages;

use App\Filament\Resources\EmailDeliveries\EmailDeliveryResource;
use Filament\Resources\Pages\ListRecords;

final class ListEmailDeliveries extends ListRecords
{
    protected static string $resource = EmailDeliveryResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
