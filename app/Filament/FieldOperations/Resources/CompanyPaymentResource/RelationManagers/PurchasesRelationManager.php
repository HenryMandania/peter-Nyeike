<?php

namespace App\Filament\FieldOperations\Resources\CompanyPaymentResource\RelationManagers;

use App\Models\Purchase;
use App\Filament\FieldOperations\Resources\PurchaseResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'company';
    
    protected static ?string $title = 'Company Purchases';
    
    protected static ?string $icon = 'heroicon-o-shopping-cart';
    
    protected static ?string $navigationLabel = 'Purchases';
    
    protected static ?int $navigationSort = 1;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->company()->exists();
    }

    protected function getTableQuery(): Builder
    {
        $companyPayment = $this->getOwnerRecord();
        
        // Remove 'sale' from the with() array since the relationship doesn't exist
        return Purchase::query()
            ->where('company_id', $companyPayment->company_id)
            ->with(['item', 'vendor', 'operator', 'seller', 'approver']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item.name')
            ->columns([
                // Date & Time
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                // Item Details
                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Supplier/Vendor
                TextColumn::make('vendor.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                // Weight/Quantity
                TextColumn::make('quantity')
                    ->label('Weight/Qty')
                    ->numeric(2)
                    ->summarize(Sum::make()->label('Total Weight')),

                // Cost/Price Columns (PENDING)
                TextColumn::make('unit_price')
                    ->label('Unit Cost')
                    ->money('KES')
                    ->color('gray'),

                TextColumn::make('fruit_cost')
                    ->label('Fruit Cost')
                    ->money('KES')
                    ->color('warning')
                    ->summarize(Sum::make()->label('Total Fruit Cost')),

                TextColumn::make('transaction_fee')
                    ->label('Trans. Fee')
                    ->money('KES')
                    ->summarize(Sum::make()->label('Total Fees')),

                TextColumn::make('total_amount')
                    ->label('Total Cost')
                    ->money('KES')
                    ->color('danger')
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Grand Cost')),

                // Selling Price Columns (SOLD)
                TextColumn::make('selling_unit_price')
                    ->label('Selling Price')
                    ->money('KES')
                    ->placeholder('—')
                    ->color('success'),

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

                // Status Indicators
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('is_sold')
                    ->label('Sold Status')
                    ->badge()
                    ->color(fn ($record): string => $record->is_sold ? 'success' : 'warning')
                    ->formatStateUsing(fn ($record): string => $record->is_sold ? 'Sold' : 'Pending'),

                // Operator/Personnel
                TextColumn::make('operator.name')
                    ->label('Operator')
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('seller.name')
                    ->label('Sold By')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->icon(fn ($record) => $record->approved_by ? 'heroicon-m-check-circle' : null)
                    ->iconColor('success')
                    ->color(fn ($record) => $record->approved_by ? 'success' : 'gray')
                    ->placeholder('Not Approved')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Date Range Filter
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

                // Item Filter
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload(),

                // Supplier Filter
                SelectFilter::make('vendor_id')
                    ->label('Supplier')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),

                // Purchase Status Filter
                SelectFilter::make('status')
                    ->label('Purchase Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                // Sold/Pending Filter
                SelectFilter::make('is_sold')
                    ->label('Sale Status')
                    ->options([
                        '1' => 'Sold',
                        '0' => 'Pending',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        return $query->where('is_sold', $data['value']);
                    }),

                // Operator Filter
                SelectFilter::make('created_by')
                    ->label('Operator')
                    ->relationship('operator', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export to Excel')
                    ->color('success'),
            ])
            ->actions([
                // View Purchase Action - Using Filament resource URL
                Tables\Actions\Action::make('view_purchase')
                    ->label('View Purchase')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->url(fn ($record): string => PurchaseResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export Selected'),
                ]),
            ]);
    }
}