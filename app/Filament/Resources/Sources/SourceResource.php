<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages\ListSources;
use App\Models\Source;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Sources across all accounts, read only (FR-071).
 */
final class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->label('Account')->searchable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('author')->searchable()->placeholder('—'),
                TextColumn::make('type')->badge(),
                TextColumn::make('frequency')->badge(),
                TextColumn::make('highlights_count')->label('Highlights')->numeric()->sortable(),
                IconColumn::make('is_archived')->label('Archived')->boolean(),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('frequency')->options(
                    collect(\App\Http\Requests\StoreSourceRequest::frequencies())
                        ->mapWithKeys(fn (string $f): array => [$f => __('library.frequency.'.$f)])
                        ->all(),
                ),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope('owned_by_user')
            ->with('user');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSources::route('/'),
        ];
    }
}
