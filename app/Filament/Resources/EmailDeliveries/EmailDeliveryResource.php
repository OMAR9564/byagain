<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailDeliveries;

use App\Filament\Resources\EmailDeliveries\Pages\ListEmailDeliveries;
use App\Jobs\SendDailyReviewEmail;
use App\Jobs\SendEveningReminderEmail;
use App\Models\EmailDelivery;
use App\Models\Review;
use App\Services\Admin\AdminActionLogger;
use App\Services\Time\LocalDayResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every send byagain attempted, and what became of it (FR-072).
 *
 * This is the screen that answers "why did this reader get nothing this
 * morning?" — which is why skipped deliveries are kept with their reason
 * rather than deleted.
 */
final class EmailDeliveryResource extends Resource
{
    protected static ?string $model = EmailDelivery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Email';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->label('Account')->searchable(),
                TextColumn::make('type')->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        EmailDelivery::STATUS_SENT => 'success',
                        EmailDelivery::STATUS_FAILED => 'danger',
                        EmailDelivery::STATUS_SKIPPED => 'warning',
                        default => 'gray',
                    }),

                // The reason a send did not happen matters more than the fact
                // that it did not.
                TextColumn::make('error')->label('Detail')->limit(60)->wrap()->placeholder('—'),

                TextColumn::make('sent_at')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('opened_at')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Queued')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    EmailDelivery::TYPE_DAILY => 'Daily',
                    EmailDelivery::TYPE_REMINDER => 'Reminder',
                    'verification' => 'Verification',
                    'password_reset' => 'Password reset',
                ]),
                SelectFilter::make('status')->options([
                    EmailDelivery::STATUS_QUEUED => 'Queued',
                    EmailDelivery::STATUS_SENT => 'Sent',
                    EmailDelivery::STATUS_FAILED => 'Failed',
                    EmailDelivery::STATUS_SKIPPED => 'Skipped',
                ]),
            ])
            ->recordActions([self::resendAction()])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Re-queue a delivery that failed.
     *
     * Only offered for failures. Re-sending something that was skipped would
     * mean overriding a decision the system made for a reason — usually that
     * the reader had already finished, or turned these off.
     */
    private static function resendAction(): Action
    {
        return Action::make('resend')
            ->label('Resend')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->visible(fn (EmailDelivery $record): bool => $record->status === EmailDelivery::STATUS_FAILED)
            ->action(function (EmailDelivery $record, AdminActionLogger $logger, LocalDayResolver $days): void {
                $review = Review::query()
                    ->withoutGlobalScope('owned_by_user')
                    ->where('user_id', $record->user_id)
                    ->where('review_date', $days->localDayFor($record->user)->toDateString())
                    ->first();

                if ($review === null) {
                    Notification::make()->title('No review to send')->danger()->send();

                    return;
                }

                $record->status = EmailDelivery::STATUS_QUEUED;
                $record->error = null;
                $record->save();

                $record->type === EmailDelivery::TYPE_DAILY
                    ? SendDailyReviewEmail::dispatch($record->id, $review->id)
                    : SendEveningReminderEmail::dispatch($record->id, $review->id);

                $logger->record(AdminActionLogger::RESEND_EMAIL, $record, ['type' => $record->type]);

                Notification::make()->title('Re-queued')->success()->send();
            });
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
            'index' => ListEmailDeliveries::route('/'),
        ];
    }
}
