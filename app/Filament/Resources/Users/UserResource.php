<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Accounts, for operational purposes only.
 *
 * There is no create, no edit and no delete. An administrator can see who
 * exists, suspend somebody, and re-send a verification mail — and that is the
 * whole list. In particular there is no impersonation: being able to sign in
 * as a reader means being able to read their highlights, and no operational
 * need justifies that (FR-070).
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Accounts are suspended, never deleted from here. Only the account
        // holder can delete their own (Constitution art. III, FR-008).
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
