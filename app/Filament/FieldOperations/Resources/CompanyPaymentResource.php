<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\CompanyPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class CompanyPaymentResource extends Resource
{
    protected static ?string $model = CompanyPayment::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Accounts';
    protected static ?string $navigationLabel = 'Company Payments';
    protected static ?int $navigationSort = 2;
    
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Record Payment')
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->prefix('KES')
                        ->required(),
                    Forms\Components\DatePicker::make('payment_date')
                        ->default(now())
                        ->required(),
                    Forms\Components\Select::make('payment_method')
                        ->options([
                            'Bank Transfer' => 'Bank Transfer',
                            'M-Pesa' => 'M-Pesa',
                            'Cash' => 'Cash',
                        ])->required(),
                    Forms\Components\TextInput::make('reference_no')
                        ->label('Ref / Receipt No'),
                ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('KES')
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Total Received')),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('reference_no')->label('Ref'),
            ])
            ->headerActions([
                ExportAction::make()->label('Export Excel')->color('success'),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->indicator('Company'), // Displays "Company: [Name]" in the ribbon
            
                Filter::make('payment_date')
                    ->form([
                        DatePicker::make('from')->label('Date From'),
                        DatePicker::make('until')->label('Date To'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '<=', $date))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        
                        return $indicators; // These will appear as individual pills in the ribbon
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),                
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CompanyPaymentResource\RelationManagers\PurchasesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => CompanyPaymentResource\Pages\ListCompanyPayments::route('/'),
            'create' => CompanyPaymentResource\Pages\CreateCompanyPayment::route('/create'),
            'view' => CompanyPaymentResource\Pages\ViewCompanyPayment::route('/{record}'),
            'edit' => CompanyPaymentResource\Pages\EditCompanyPayment::route('/{record}/edit'),
        ];
    }
}