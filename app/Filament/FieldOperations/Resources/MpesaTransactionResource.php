<?php

namespace App\Filament\FieldOperations\Resources;

use App\Filament\FieldOperations\Resources\MpesaTransactionResource\Pages;
use App\Filament\FieldOperations\Resources\MpesaTransactionResource\RelationManagers;
use App\Models\MpesaTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MpesaTransactionResource extends Resource
{
    protected static ?string $model = MpesaTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'M-PESA Transactions';

    protected static ?string $pluralLabel = 'M-PESA Transactions';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Information')
                    ->schema([
                        Forms\Components\Select::make('transactionable_type')
                            ->label('Related To')
                            ->options([
                                'App\Models\Purchase' => 'Purchase',
                                'App\Models\Expense' => 'Expense',
                                'App\Models\FloatRequest' => 'Float Request',
                            ])
                            ->searchable()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('transactionable_id')
                            ->label('Related Record ID')
                            ->numeric()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\Select::make('type')
                            ->options([
                                'purchase' => 'Purchase',
                                'expense' => 'Expense',
                                'float_request' => 'Float Request',
                            ])
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('mpesa_receipt_number')
                            ->label('M-PESA Receipt Number')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('checkout_request_id')
                            ->label('Checkout Request ID')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('KES')
                            ->maxValue(999999.99),

                        Forms\Components\TextInput::make('phone_number')
                            ->required()
                            ->tel()
                            ->maxLength(15)
                            ->placeholder('2547XXXXXXXX'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'requested' => 'Requested',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->native(false)
                            ->extraAttributes([
                                'class' => 'status-select',
                            ]),

                        Forms\Components\TextInput::make('result_desc')
                            ->label('Result Description')
                            ->maxLength(255),

                        Forms\Components\DateTimePicker::make('completed_at'),
                    ])->columns(2),

                Forms\Components\Section::make('Callback Data')
                    ->schema([
                        Forms\Components\KeyValue::make('raw_callback_payload')
                            ->label('Raw Callback Payload')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('transactionable_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('transactionable_id')
                    ->label('Related ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'purchase' => 'primary',
                        'expense' => 'warning',
                        'float_request' => 'info',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('mpesa_receipt_number')
                    ->label('Receipt No.')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Receipt number copied')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('KES')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('phone_number')
                    ->searchable()
                    ->icon('heroicon-m-phone'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'requested' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'requested',
                        'heroicon-o-check-circle' => 'completed',
                        'heroicon-o-x-circle' => 'failed',
                        'heroicon-o-ban' => 'cancelled',
                    ])
                    ->searchable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'purchase' => 'Purchase',
                        'expense' => 'Expense',
                        'float_request' => 'Float Request',
                    ])
                    ->label('Transaction Type')
                    ->indicator('Type'), // Added Indicator
            
                SelectFilter::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->label('Status')
                    ->indicator('Status'), // Added Indicator
            
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('From'),
                        Forms\Components\DatePicker::make('created_until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'From: ' . $data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Until: ' . $data['created_until'];
                        }
                        return $indicators;
                    }),
            
                Filter::make('has_receipt')
                    ->query(fn (Builder $query) => $query->whereNotNull('mpesa_receipt_number'))
                    ->label('Has Receipt Number')
                    ->indicator('Receipt Status') // Added Indicator
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markCompleted')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'requested')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('markFailed')
                    ->label('Mark Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'requested')
                    ->form([
                        Forms\Components\TextInput::make('result_desc')
                            ->label('Failure Reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'failed',
                            'result_desc' => $data['result_desc'],
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('markBulkCompleted')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->status === 'requested') {
                                    $record->update([
                                        'status' => 'completed',
                                        'completed_at' => now(),
                                    ]);
                                }
                            });
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transaction Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Transaction ID'),

                        Infolists\Components\TextEntry::make('transactionable_type')
                            ->label('Related To')
                            ->formatStateUsing(fn ($state) => class_basename($state)),

                        Infolists\Components\TextEntry::make('transactionable_id')
                            ->label('Related Record ID'),

                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'purchase' => 'primary',
                                'expense' => 'warning',
                                'float_request' => 'info',
                            }),

                        Infolists\Components\TextEntry::make('mpesa_receipt_number')
                            ->label('M-PESA Receipt Number')
                            ->copyable()
                            ->copyMessage('Receipt number copied'),

                        Infolists\Components\TextEntry::make('checkout_request_id')
                            ->label('Checkout Request ID')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('amount')
                            ->money('KES')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success'),

                        Infolists\Components\TextEntry::make('phone_number')
                            ->icon('heroicon-m-phone'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'requested' => 'warning',
                                'completed' => 'success',
                                'failed' => 'danger',
                                'cancelled' => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('result_desc')
                            ->label('Result Description'),

                        Infolists\Components\TextEntry::make('completed_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ])->columns(2),

                Infolists\Components\Section::make('Callback Payload')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('raw_callback_payload')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->state(function ($state): array {
                                if (is_string($state) && $state !== '') {
                                    $decoded = json_decode($state, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        return $decoded;
                                    }
                                }
                                // Fallback: null, invalid JSON, or empty string → empty array
                                return is_array($state) ? $state : [];
                            })
                            ->default([]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMpesaTransactions::route('/'),
            'create' => Pages\CreateMpesaTransaction::route('/create'),
            
            'edit' => Pages\EditMpesaTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'requested')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}