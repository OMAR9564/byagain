<?php

declare(strict_types=1);

namespace App\Filament\Resources\Highlights\Pages;

use App\Filament\Resources\Highlights\HighlightResource;
use Filament\Resources\Pages\ListRecords;

final class ListHighlights extends ListRecords
{
    protected static string $resource = HighlightResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
