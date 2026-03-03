<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Shift;
use App\Services\BalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\Action;
use App\Filament\FieldOperations\Resources\ShiftResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\FieldOperations\Resources\ShiftResource\RelationManagers;
use Closure;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static ?string $navigationLabel = 'Work Shifts';
    protected static ?string $navigationGroup = 'Shifts';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Shift Information')
                    ->description('Overview of the selected shift session.')
                    ->schema([
                        Placeholder::make('operator_name')
                            ->label('Operator')
                            ->content(fn ($record) => $record?->user?->name ?? Auth::user()->name),

                        Placeholder::make('status_display')
                            ->label('Current Status')
                            ->content(fn ($record) => strtoupper($record?->status ?? 'NEW')),

                        TextInput::make('opening_balance')
                            ->label('Opening Cash Balance')
                            ->numeric()
                            ->prefix('KES')
                            ->required()
                            ->default(function () {
                                $lastShift = Shift::where('user_id', Auth::id())
                                    ->where('status', 'closed')
                                    ->orderBy('closed_at', 'desc')
                                    ->first();
                                return $lastShift ? $lastShift->closing_balance : 0;
                            })
                            ->readOnly()
                            ->helperText('Automatically fetched from your last closed shift.')
                            ->rules([
                                fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                    $activeShiftExists = Shift::where('user_id', Auth::id())
                                        ->where('status', 'open')
                                        ->exists();
                        
                                    if ($activeShiftExists && !request()->route('record')) {
                                        $fail("You still have an active shift. Please close it before starting a new one.");
                                    }
                                },
                            ]),

                        TextInput::make('system_balance')
                            ->label('System Calculated Balance')
                            ->numeric()
                            ->prefix('KES')
                            ->readOnly(),

                        TextInput::make('closing_balance')
                            ->label('Closing Balance')
                            ->numeric()
                            ->prefix('KES')
                            ->formatStateUsing(function ($record) {
                                if ($record && $record->status === 'open') {
                                    return 0;
                                }
                                return $record?->closing_balance ?? 0;
                            })
                            ->readOnly(),

                        Placeholder::make('times')
                            ->label('Duration')
                            ->content(function ($record) {
                                if (!$record) return 'Starts on Create';
                                $start = $record->opened_at?->format('H:i');
                                $end = $record->closed_at?->format('H:i') ?? 'Active';
                                return "{$start} - {$end}";
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Operator')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('opening_balance')
                    ->label('Opening')
                    ->money('KES')
                    ->summarize(Sum::make()->label('Total Opening')),

                TextColumn::make('purchases_count')
                    ->label('Purchases')
                    ->counts('purchases')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('expenses_sum_amount')
                    ->label('Expenses')
                    ->sum('expenses', 'amount')
                    ->money('KES')
                    ->summarize(Sum::make()->label('Total Exp')),

                TextColumn::make('purchases_sum_total_amount')
                    ->label('Purchased Amount')
                    ->sum('purchases', 'total_amount')
                    ->money('KES')
                    ->summarize(Sum::make()->label('Total Spent')),

                TextColumn::make('purchases_sum_transaction_fee')
                    ->label('Transaction Fees')
                    ->sum('purchases', 'transaction_fee')
                    ->money('KES')
                    ->summarize(Sum::make()->label('Total Fees')),

                TextColumn::make('running_balance')
                    ->label('Running Balance')
                    ->state(fn (Shift $record, BalanceService $service) => $service->calculate($record))
                    ->money('KES')
                    ->weight('bold')
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('closing_balance')
                    ->label('Closing')
                    ->state(fn ($record) => $record->status === 'closed' ? $record->closing_balance : 0)
                    ->money('KES')
                    ->color(fn ($state) => $state == 0 ? 'gray' : 'success'),

                TextColumn::make('variance')
                    ->label('Variance')
                    ->money('KES')
                    ->sortable()
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray'))
                    ->description(fn ($state) => match(true) {
                        $state < 0 => 'Shortage (Reconcile Needed)',
                        $state > 0 => 'Overage',
                        default => 'Balanced',
                    }),

                TextColumn::make('opened_at')
                    ->label('Started')
                    ->dateTime('M d, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Operator')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),

                Filter::make('opened_at')
                    ->form([ // <--- FIXED: Changed from schema() to form()
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('opened_at', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('opened_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            
                Action::make('close_shift')
                    ->label('Close Shift')
                    ->icon('heroicon-m-lock-closed')
                    ->color('danger')
                    // Only show for open shifts
                    ->visible(fn (Shift $record): bool => $record->status === 'open')
                    ->requiresConfirmation()
                    ->modalHeading('Closing Shift Session')
                    ->modalDescription('Please verify all transactions and float requests before closing.')
                    ->mountUsing(function (Forms\ComponentContainer $form, Shift $record, BalanceService $service) {
                        $form->fill([
                            'system_balance' => $service->calculate($record),
                        ]);
                    })
                    ->form([
                        Placeholder::make('warning_info')
                            ->label('Important')
                            ->content('Ensure all physical cash is counted. You cannot close this shift if there are pending float requests.')
                            ->columnSpanFull(),
            
                        TextInput::make('system_balance')
                            ->label('Expected System Balance')
                            ->numeric()
                            ->prefix('KES')
                            ->readOnly(),
            
                        TextInput::make('closing_balance')
                            ->label('Actual Cash Counted')
                            ->numeric()
                            ->prefix('KES')
                            ->required()
                            ->minValue(0)
                            ->hint('Physical cash in hand')
                            ->autofocus(),
                    ])
                    ->action(function (Shift $record, array $data, BalanceService $service): void {
                        // 1. Loophole Fix: Check for Pending Float Requests
                        $hasPendingFloats = $record->floatRequests()
                            ->where('status', 'pending')
                            ->exists();
            
                        if ($hasPendingFloats) {
                            Notification::make()
                                ->title('Cannot Close Shift')
                                ->body('There are pending float requests. Please approve or reject them before closing the shift.')
                                ->danger()
                                ->persistent()
                                ->send();
                            
                            return;
                        }
            
                        // 2. Re-calculate balance inside action to prevent stale data
                        $expected = (float) $service->calculate($record);
                        $actual = (float) $data['closing_balance'];
                        $variance = $actual - $expected;
            
                        // 3. Informative Balance Verification
                        if ($variance < 0) {
                            Notification::make()
                                ->title('Reconciliation Needed')
                                ->body("Insufficient Balance. There is a shortage of KES " . number_format(abs($variance), 2) . ". Available balance does not match purchase/expense records.")
                                ->danger()
                                ->persistent()
                                ->send();
            
                            return;
                        }
            
                        // 4. Atomic Update
                        \DB::transaction(function () use ($record, $expected, $actual, $variance) {
                            $record->update([
                                'system_balance'  => $expected,
                                'closing_balance' => $actual,
                                'variance'        => $variance,
                                'status'          => 'closed',
                                'closed_at'       => now(),
                            ]);
                        });
            
                        Notification::make()
                            ->title('Shift Closed Successfully')
                            ->body("There was enough to make this Purchase/Closure. Variance: KES " . number_format($variance, 2))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('opened_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PurchasesRelationManager::class,
            RelationManagers\ExpensesRelationManager::class,
            RelationManagers\FloatRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}