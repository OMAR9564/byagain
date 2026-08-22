<?php

declare(strict_types=1);

namespace App\Filament\Resources\Highlights;

use App\Filament\Resources\Highlights\Pages\ListHighlights;
use App\Models\Highlight;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Highlights across all accounts, read only.
 *
 * Only a 120-character plain-text preview is shown, never the full passage
 * (FR-071). An administrator needs to answer "is this account real, is this
 * content broken" — not to read what somebody has been underlining.
 *
 * This is one of the few places allowed to drop the ownership scope, and the
 * only kind of place: the admin panel, by design (Constitution art. III).
 */
final class HighlightResource extends Resource
{
    protected static ?string $model = Highlight::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->label('Account')->searchable(),
                TextColumn::make('source.title')->label('Source')->searchable(),

                TextColumn::make('content_text')
                    ->label('Preview')
                    ->limit((int) config('byagain.content.admin_preview_chars'))
                    ->wrap(),

                TextColumn::make('char_count')->label('Chars')->numeric()->sortable(),
                TextColumn::make('shown_count')->label('Shown')->numeric()->sortable(),
                TextColumn::make('last_shown_at')->label('Last shown')->dateTime()->placeholder('Never')->sortable(),
                IconColumn::make('is_discarded')->label('Discarded')->boolean(),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_discarded')->label('Discarded'),
                TernaryFilter::make('contains_code')->label('Contains code'),
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
            ->with(['user', 'source']);
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
            'index' => ListHighlights::route('/'),
        ];
    }
}
