<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\CompanyPayment;
use App\Models\Company;
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
use Illuminate\Database\Eloquent\Builder;

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
                ->description('Ensure the Reference No matches the M-Pesa or Bank statement.')
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->live() // Allows other fields to react to company selection
                        ->required(),
                    
                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->prefix('KES')
                        ->minValue(1)
                        ->required(),

                    Forms\Components\DatePicker::make('payment_date')
                        ->default(now())
                        ->required(),

                    Forms\Components\Select::make('payment_method')
                        ->options([
                            'Bank Transfer' => 'Bank Transfer',
                            'M-Pesa' => 'M-Pesa',
                            'Cash' => 'Cash',
                        ])
                        ->native(false)
                        ->required(),

                    Forms\Components\TextInput::make('reference_no')
                        ->label('Ref / Receipt No')
                        ->placeholder('e.g. RCK1234567')
                        ->unique(ignoreRecord: true)
                        ->required(fn (Forms\Get $get) => $get('payment_method') === 'M-Pesa'),
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

                // 1. Total Sales / Expected from this Company
                TextColumn::make('company.total_purchases')
                    ->label('Total Sales (Expected)')
                    ->money('KES')
                    ->color('gray'),

                // 2. This Specific Payment Amount
                TextColumn::make('amount')
                    ->label('Amount Paid')
                    ->money('KES')
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Sum::make()->label('Total Received')),

                // 3. Current Outstanding Balance for this Company
                TextColumn::make('company.balance')
                    ->label('Current Balance')
                    ->money('KES')
                    ->weight('font-medium')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->icon(fn ($state) => $state > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-check-circle')
                    ->description(fn ($state) => $state > 0 ? 'Outstanding Owed' : 'Account Cleared'),

                TextColumn::make('payment_method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'M-Pesa' => 'success',
                        'Bank Transfer' => 'info',
                        'Cash' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('reference_no')
                    ->label('Ref')
                    ->copyable()
                    ->searchable(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->headerActions([
                ExportAction::make()->label('Export Excel')->color('success'),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Filter by Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->indicator('Company'),

                Filter::make('payment_date')
                    ->form([
                        DatePicker::make('from')->label('Date From'),
                        DatePicker::make('until')->label('Date To'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('payment_date', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('payment_date', '<=', $date))
                    )
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CompanyPaymentResource\Pages\ListCompanyPayments::route('/'),
            'create' => CompanyPaymentResource\Pages\CreateCompanyPayment::route('/create'),
            'view' => CompanyPaymentResource\Pages\ViewCompanyPayment::route('/{record}'),
            'edit' => CompanyPaymentResource\Pages\EditCompanyPayment::route('/{record}/edit'),
        ];
    }
}