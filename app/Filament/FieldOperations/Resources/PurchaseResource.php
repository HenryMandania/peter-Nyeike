<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Purchase;
use App\Models\Shift;
use App\Services\BalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get; // Correct v3 namespace
use Filament\Forms\Set; // Correct v3 namespace
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction; // Use Tables namespace for row actions
use Filament\Tables\Actions\EditAction; // Use Tables namespace for row actions
use Filament\Tables\Actions\Action;     // Use Tables namespace for row actions
use App\Filament\FieldOperations\Resources\PurchaseResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\Auth;
use Closure;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;
    protected static ?string $navigationLabel = 'Purchases';
    protected static ?string $navigationGroup = 'Shifts';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([ // Changed from components() to schema()
                Section::make('Purchase Transaction')
                    ->schema([
                        Placeholder::make('current_balance')
                            ->label('Current Shift Balance')
                            ->content(function (BalanceService $service) {
                                $activeShift = Shift::where('user_id', Auth::id())->where('status', 'open')->first();
                                return $activeShift ? 'KES ' . number_format($service->calculate($activeShift), 2) : 'No Active Shift';
                            })->extraAttributes(['class' => 'text-success-600 font-bold']),

                        Select::make('vendor_id')
                            ->label('Supplier')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        Select::make('item_id')
                            ->relationship('item', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        TextInput::make('quantity')
                            ->label('Weight / Qty')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->rules([
                                fn (Get $get, BalanceService $service): Closure => function (string $attribute, $value, Closure $fail) use ($get, $service) {
                                    $activeShift = Shift::where('user_id', Auth::id())->where('status', 'open')->first();

                                    if (!$activeShift) {
                                        $fail("You cannot record a purchase without an active work shift. Please start a shift first.");
                                        return;
                                    }

                                    $qty = floatval($value);
                                    $price = floatval($get('unit_price') ?? 0);
                                    $fee = $get('payment_method') === 'Cash' ? 0 : floatval($get('transaction_fee') ?? 0);
                                    $requestedTotal = ($qty * $price) + $fee;

                                    $currentBalance = $service->calculate($activeShift);
                                    if ($requestedTotal > $currentBalance) {
                                        $shortage = $requestedTotal - $currentBalance;
                                        $fail("Insufficient balance! Your shift only has KES " . number_format($currentBalance, 2) . ". You are short by KES " . number_format($shortage, 2));
                                    }
                                },
                            ]),

                        TextInput::make('unit_price')
                            ->numeric()
                            ->prefix('KES')
                            ->required()
                            ->live(onBlur: true),

                        Select::make('payment_method')
                            ->options(['Cash' => 'Cash', 'Mpesa' => 'Mpesa', 'Bank' => 'Bank'])
                            ->required()
                            ->native(false)
                            ->live(),

                        TextInput::make('transaction_fee')
                            ->numeric()
                            ->prefix('KES')
                            ->default(0)
                            ->required()
                            ->live(onBlur: true)
                            ->hidden(fn (Get $get) => $get('payment_method') === 'Cash')
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($get('payment_method') === 'Cash') {
                                    $set('transaction_fee', 0);
                                }
                            }),

                        Placeholder::make('total_preview')
                            ->label('Final Total (Incl. Fee)')
                            ->content(function (Get $get) {
                                $fee = $get('payment_method') === 'Cash' ? 0 : ($get('transaction_fee') ?? 0);
                                $total = (($get('quantity') ?? 0) * ($get('unit_price') ?? 0)) + $fee;
                                return 'KES ' . number_format($total, 2);
                            })->extraAttributes(['class' => 'text-xl font-bold text-primary-600']),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('vendor.name')->label('Supplier')->sortable(),
                TextColumn::make('item.name')->label('Item'),
                
                TextColumn::make('quantity')
                    ->label('Weight/Qty')
                    ->numeric(2)
                    ->summarize(Sum::make()->label('Total Weight')),

                TextColumn::make('unit_price')->label('Unit Price')->money('KES'),
                
                TextColumn::make('transaction_fee')
                    ->label('Transaction Fee')
                    ->money('KES')
                    ->summarize(Sum::make()->label('Total Fees')),
                
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('KES')
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Grand Total')),
                
                TextColumn::make('operator.name')->label('Operator'),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->icon(fn ($record) => $record->approved_by ? 'heroicon-m-check-circle' : null)
                    ->iconColor('success')
                    ->color(fn ($record) => $record->approved_by ? 'success' : 'gray')
                    ->placeholder('Waiting...'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                
                ViewAction::make()
                    ->visible(fn (Purchase $record) => 
                        $record->approved_by !== null || 
                        ($record->shift && $record->shift->status !== 'open')
                    ),

                
                EditAction::make()
                    ->visible(fn (Purchase $record) => 
                        $record->approved_by === null && 
                        (!$record->shift || $record->shift->status === 'open')
                    ),

                
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Purchase $record) => 
                        $record->approved_by === null && 
                        (!$record->shift || $record->shift->status === 'open')
                    )
                    ->action(fn (Purchase $record) => $record->update([
                        'approved_by' => Auth::id(),
                    ])),
            ]);
    }

   
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if ($record->shift) {
            return $record->shift->status === 'open' && $record->approved_by === null;
        }

        return $record->approved_by === null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
            'view' => Pages\ViewPurchase::route('/{record}'),
        ];
    }
}