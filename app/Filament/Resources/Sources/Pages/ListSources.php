<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use Filament\Resources\Pages\ListRecords;

final class ListSources extends ListRecords
{
    protected static string $resource = SourceResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
