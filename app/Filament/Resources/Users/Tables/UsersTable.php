<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\Admin\AdminActionLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        User::STATUS_ACTIVE => 'success',
                        User::STATUS_SUSPENDED => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('role')->badge(),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),

                TextColumn::make('current_streak')->label('Streak')->numeric()->sortable(),
                TextColumn::make('timezone')->searchable()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('consecutive_unopened_emails')
                    ->label('Unopened')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    User::STATUS_ACTIVE => 'Active',
                    User::STATUS_SUSPENDED => 'Suspended',
                    User::STATUS_DELETED => 'Deleted',
                ]),
                SelectFilter::make('role')->options([
                    User::ROLE_USER => 'User',
                    User::ROLE_ADMIN => 'Admin',
                ]),
            ])
            ->recordActions([
                self::suspendAction(),
                self::restoreAction(),
                self::resendVerificationAction(),
            ])
            // No bulk actions on purpose: nothing here should be easy to do to
            // a hundred accounts at once by accident.
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    private static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->icon('heroicon-o-no-symbol')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->status === User::STATUS_ACTIVE)
            ->action(function (User $record, AdminActionLogger $logger): void {
                // Suspension hides the account from itself. Not one highlight
                // is touched (Constitution art. III).
                $record->status = User::STATUS_SUSPENDED;
                $record->save();

                $logger->record(AdminActionLogger::SUSPEND_USER, $record);

                Notification::make()->title('Account suspended')->success()->send();
            });
    }

    private static function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (User $record): bool => $record->status === User::STATUS_SUSPENDED)
            ->action(function (User $record, AdminActionLogger $logger): void {
                $record->status = User::STATUS_ACTIVE;
                $record->save();

                $logger->record(AdminActionLogger::RESTORE_USER, $record);

                Notification::make()->title('Account restored')->success()->send();
            });
    }

    private static function resendVerificationAction(): Action
    {
        return Action::make('resendVerification')
            ->label('Resend verification')
            ->icon('heroicon-o-envelope')
            ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
            ->action(function (User $record, AdminActionLogger $logger): void {
                $record->sendEmailVerificationNotification();

                $logger->record(AdminActionLogger::RESEND_VERIFICATION, $record);

                Notification::make()->title('Verification email sent')->success()->send();
            });
    }
}
