<?php

namespace Lunar\Admin\Filament\Resources;

use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Filament\Resources\SessionReplayResource\CustomerLabel;
use Lunar\Admin\Filament\Resources\SessionReplayResource\Pages\ListReplaySessions;
use Lunar\Admin\Filament\Resources\SessionReplayResource\Pages\ViewReplaySession;
use Lunar\Admin\Support\Resources\BaseResource;
use Packstub\SessionReplay\Models\ReplaySession;

class SessionReplayResource extends BaseResource
{
    protected static ?string $permission = 'settings:view-session-replays';

    protected static ?string $model = ReplaySession::class;

    protected static ?string $slug = 'session-replays';

    protected static ?int $navigationSort = 2;

    public static function getLabel(): string
    {
        return __('lunarpanel::session-replay.label');
    }

    public static function getPluralLabel(): string
    {
        return __('lunarpanel::session-replay.plural_label');
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::session-replay') ?? 'heroicon-o-video-camera';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.analytics::plugin.navigation.group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::hasPermission() ? Response::allow() : Response::deny();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::hasPermission() ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->label(__('lunarpanel::session-replay.table.customer'))
                    ->getStateUsing(fn (ReplaySession $record): string => static::customerLabel($record)),
                TextColumn::make('started_at')
                    ->label(__('lunarpanel::session-replay.table.started'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('active_ms')
                    ->label(__('lunarpanel::session-replay.table.length'))
                    ->formatStateUsing(fn (mixed $state): string => static::lengthForHumans($state))
                    ->sortable(),
                TextColumn::make('page_count')
                    ->label(__('lunarpanel::session-replay.table.pages'))
                    ->sortable(),
                TextColumn::make('error_count')
                    ->label(__('lunarpanel::session-replay.table.errors'))
                    ->sortable(),
                TextColumn::make('entry_url')
                    ->label(__('lunarpanel::session-replay.table.first_page'))
                    ->limit(60)
                    ->tooltip(fn (ReplaySession $record): ?string => $record->entry_url)
                    ->searchable(),
            ])
            ->recordUrl(fn (ReplaySession $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('started_at', 'desc')
            ->toolbarActions([]);
    }

    /**
     * Active recording time for the length column, from the stored milliseconds.
     */
    public static function lengthForHumans(mixed $milliseconds): string
    {
        $seconds = intdiv(max(0, (int) $milliseconds), 1000);

        return match (true) {
            $seconds >= 3600 => sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)),
            $seconds >= 60 => sprintf('%dm %02ds', intdiv($seconds, 60), $seconds % 60),
            default => $seconds.'s',
        };
    }

    /**
     * Email, Anonymous, or Guest for the customer column.
     */
    public static function customerLabel(ReplaySession $record): string
    {
        $email = $record->user?->email;

        return CustomerLabel::for(
            is_string($email) ? $email : null,
            $record->user_id,
            is_array($record->properties) ? $record->properties : null,
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => ListReplaySessions::route('/'),
            'view' => ViewReplaySession::route('/{record}'),
        ];
    }
}
