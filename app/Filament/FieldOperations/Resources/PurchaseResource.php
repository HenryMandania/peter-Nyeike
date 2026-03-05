<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Purchase;
use App\Models\Shift;
use App\Services\BalanceService;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set; 
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction; 
use Filament\Tables\Actions\EditAction; 
use Filament\Tables\Actions\Action;   
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;  
use App\Filament\FieldOperations\Resources\PurchaseResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\Auth;
use Closure;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use App\Services\MpesaService;

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
            TextColumn::make('operator.name')
                ->label('Operator')
                ->toggleable(),
            // Date & Time
            TextColumn::make('created_at')
                ->label('Date & Time')
                ->dateTime('d M Y, H:i')
                ->sortable(),

            // Supplier & Item
            TextColumn::make('vendor.name')
                ->label('Supplier')
                ->sortable()
                ->searchable(),

            TextColumn::make('item.name')
                ->label('Item')
                ->searchable(),

            // Weight/Qty
            TextColumn::make('quantity')
                ->label('Weight/Qty')
                ->numeric(2)
                ->summarize(Sum::make()->label('Total Weight')),

            // --- BUYING SIDE (COST) ---
            TextColumn::make('unit_price')
                ->label('Unit Cost')
                ->money('KES')
                ->color('gray'),

            TextColumn::make('transaction_fee')
                ->label('Trans. Fee')
                ->money('KES')
                ->summarize(Sum::make()->label('Total Fees')),

            TextColumn::make('total_amount')
                ->label('Total Cost')
                ->money('KES')
                ->color('info')
                ->weight('bold')
                ->summarize(Sum::make()->label('Grand Cost')),

            TextColumn::make('approver.name')
                ->label('Approved By')
                ->icon(fn ($record) => $record->approved_by ? 'heroicon-m-check-circle' : null)
                ->iconColor('success')
                ->color(fn ($record) => $record->approved_by ? 'success' : 'gray')
                ->placeholder('Waiting...'),

            // --- SELLING SIDE (PRICE & PROFIT) ---
            TextColumn::make('selling_unit_price')
                ->label('Selling Price')
                ->money('KES')
                ->placeholder('—')
                ->color('#6366f1'),

            TextColumn::make('sales_amount')
                ->label('Sales Value')
                ->money('KES')
                ->color('success')
                ->weight('bold')
                ->placeholder('Pending Sale')
                ->summarize(Sum::make()->label('Total Revenue')),

            TextColumn::make('gross_profit')
                ->label('Profit/Loss')
                ->money('KES')
                ->weight('bold')
                ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                ->summarize(Sum::make()->label('Net Profit')),

            TextColumn::make('seller.name')
                ->label('Sold By')                
                ->placeholder('—'),

            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'gray',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'gray',
                }),
        ])
        ->defaultSort('created_at', 'desc')
        ->filters([
            // 1. Date Filter (Range)
            Filter::make('created_at')
                ->form([
                    DatePicker::make('from')->label('Purchased From'),
                    DatePicker::make('until')->label('Purchased Until'),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                }),

            // 2. Item Filter
            SelectFilter::make('item_id')
                ->label('Item')
                ->relationship('item', 'name')
                ->searchable()
                ->preload(),

            // 3. Supplier Filter
            SelectFilter::make('vendor_id')
                ->label('Supplier')
                ->relationship('vendor', 'name')
                ->searchable()
                ->preload(),

            // 4. Company Operator Filter
            SelectFilter::make('created_by')
                ->label('Operator')
                ->relationship('operator', 'name') // Assumes relationship 'operator' exists in Purchase model
                ->searchable()
                ->preload(),

            // 5. Status Filter
            SelectFilter::make('status')
                ->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
        ])
        ->headerActions([
            // Top Right Export Button
            \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                ->label('Export Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success'),
        ])        
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn (Purchase $record) => 
                        $record->status !== 'pending' || 
                        ($record->shift && $record->shift->status !== 'open')
                    ),
            
                Tables\Actions\EditAction::make()
                    ->visible(fn (Purchase $record) => 
                        $record->status === 'pending' && 
                        (!$record->shift || $record->shift->status === 'open')
                    ),
            
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Purchase $record) => 
                        $record->status === 'pending' && 
                        (!$record->shift || $record->shift->status === 'open')
                    )
                    ->action(fn (Purchase $record) => $record->update([
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'status' => 'approved',
                    ])),

                Tables\Actions\Action::make('pay_vendor')
                    ->label('Pay M-Pesa')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'approved' && !$record->mpesa_receipt_number)
                    // No form here—we let the service find the phone number
                    ->action(function ($record, MpesaService $service) {
                        $response = $service->processPayment($record);
                
                        if ($response['status']) {
                            Notification::make()
                                ->title('STK Push Sent')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Payment Failed')
                                ->body($response['message'])
                                ->danger()
                                ->send();
                        }
                    }),
                                        
                Tables\Actions\Action::make('sell')
                    ->label('Sell')
                    ->icon('heroicon-m-banknotes')
                    ->color('warning')
                    ->visible(fn (Purchase $record) => $record->status === 'approved' && !$record->is_sold)
                    ->form([
                        Forms\Components\TextInput::make('selling_unit_price')
                            ->label('Selling Price per Unit')
                            ->numeric()
                            ->prefix('KES')
                            ->required()
                            ->rules([
                                fn (Purchase $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                                    $sellingPrice = floatval($value);
                                    $costPrice = floatval($record->unit_price);
            
                                    if ($sellingPrice < $costPrice) {
                                        $fail("Selling price (KES " . number_format($sellingPrice, 2) . ") cannot be lower than the cost price (KES " . number_format($costPrice, 2) . ").");
                                    }
                                },
                            ]),
                    ])
                    ->action(function (Purchase $record, array $data): void {
                        $totalSales = $record->quantity * floatval($data['selling_unit_price']);
                        
                        $record->update([
                            'selling_unit_price' => $data['selling_unit_price'],
                            'sales_amount' => $totalSales,
                            'gross_profit' => $totalSales - $record->total_amount,
                            'is_sold' => true,
                            'sold_at' => now(),
                            'sold_by' => auth()->id(),
                        ]);
                    }),
            ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                
                Tables\Actions\BulkAction::make('bulk_reject')
                    ->label('Reject Selected')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update([
                        'status' => 'rejected',
                        'approved_by' => auth()->id(),
                        'notes' => $data['reason'],
                    ])),
            ]),
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