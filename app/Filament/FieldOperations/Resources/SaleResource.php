<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Sale;
use App\Models\Purchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\Auth;
use App\Filament\FieldOperations\Resources\SaleResource\Pages;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;

class SaleResource extends Resource
{

    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Select::make('purchase_id')
                ->label('Purchase Batch')
                ->relationship('purchase','id')
                ->searchable()
                ->required(),

            Placeholder::make('purchase_info')
                ->label('Purchase Details')
                ->content(function (Get $get){

                    $purchase = Purchase::find($get('purchase_id'));

                    if(!$purchase) return '-';

                    return
                        $purchase->item->name
                        .' | Supplier: '.$purchase->vendor->name
                        .' | Cost: KES '.number_format($purchase->unit_price,2);
                }),

            TextInput::make('quantity')
                ->numeric()
                ->required(),

            TextInput::make('selling_unit_price')
                ->numeric()
                ->prefix('KES')
                ->required(),

            Placeholder::make('sales_preview')
                ->label('Sales Value')
                ->content(fn(Get $get)=>

                    'KES '.number_format(
                        ($get('quantity') ?? 0)
                        *
                        ($get('selling_unit_price') ?? 0)
                    ,2)
                ),

        ])->columns(2);
    }

    public static function table(Table $table): Table
    {

        return $table
        ->columns([

            TextColumn::make('created_at')
                ->label('Date')
                ->dateTime('d M Y'),

            TextColumn::make('purchase.vendor.name')
                ->label('Supplier'),

            TextColumn::make('purchase.item.name')
                ->label('Item'),
                
            TextColumn::make('company.name')
                ->label('Company')
                ->sortable()
                ->searchable(),

            TextColumn::make('quantity')
                ->numeric(2)
                ->summarize(Sum::make()->label('Total Sold')),

            TextColumn::make('selling_unit_price')
                ->label('Selling Price')
                ->money('KES'),

            TextColumn::make('sales_amount')
                ->label('Revenue')
                ->money('KES')
                ->color('success')
                ->weight('bold')
                ->summarize(Sum::make()->label('Total Revenue')),

            TextColumn::make('cost_amount')
                ->label('Cost')
                ->money('KES')
                ->color('warning')
                ->summarize(Sum::make()->label('Total Cost')),

            TextColumn::make('profit')
                ->label('Profit')
                ->money('KES')
                ->weight('bold')
                ->color(fn ($state)=> $state >=0 ? 'success':'danger')
                ->summarize(Sum::make()->label('Net Profit')),

            TextColumn::make('seller.name')
                ->label('Sold By'),

        ])
        ->defaultSort('created_at','desc')
        ->filters([
            // Supplier Filter
            SelectFilter::make('purchase.vendor_id')
                ->label('Supplier')
                ->relationship('purchase.vendor', 'name')
                ->searchable()
                ->preload(),

            // Item Filter
            SelectFilter::make('purchase.item_id')
                ->label('Item')
                ->relationship('purchase.item', 'name')
                ->searchable()
                ->preload(),

            // Company Filter
            SelectFilter::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->searchable()
                ->preload(),

            // Date Range Filter
            Filter::make('created_at')
                ->label('Date Range')
                ->form([
                    DatePicker::make('from')->label('From'),
                    DatePicker::make('until')->label('Until'),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['from'] ?? null, fn($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'] ?? null, fn($q) => $q->whereDate('created_at', '<=', $data['until']));
                }),
        ])

        ->headerActions([
            // ✅ Export to Excel button
            ExportAction::make()
                ->label('Export Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success'),
        ])

        ->actions([

            Tables\Actions\ViewAction::make(),

            Tables\Actions\EditAction::make(),

        ]);
    }

    public static function getPages(): array
    {
        return [

            'index' => Pages\ListSales::route('/'),

            //'create' => Pages\CreateSale::route('/create'),

            'edit' => Pages\EditSale::route('/{record}/edit'),

        ];
    }
}