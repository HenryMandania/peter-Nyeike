<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Expense;
use App\Models\Shift;
use App\Services\BalanceService;
use Filament\Forms;
use Filament\Forms\Form; 
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\EditAction; 
use App\Filament\FieldOperations\Resources\ExpenseResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Closure;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;
    protected static ?string $navigationLabel = 'Shift Expenses';
    protected static ?string $navigationGroup = 'Shifts';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Record Expense')
                    ->description('Expense will be deducted from your active shift balance.')
                    ->schema([
                        Placeholder::make('current_balance')
                            ->label('Current Shift Balance')
                            ->content(function (BalanceService $service) {
                                $activeShift = Shift::where('user_id', Auth::id())->where('status', 'open')->first();
                                return $activeShift ? 'KES ' . number_format($service->calculate($activeShift), 2) : 'No Active Shift';
                            })->extraAttributes(['class' => 'text-success-600 font-bold']),

                        Select::make('expense_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        TextInput::make('amount')
                            ->label('Amount to Spend')
                            ->required()
                            ->numeric()
                            ->prefix('KES')
                            ->live(onBlur: true)
                            ->rules([
                                fn (BalanceService $service): Closure => function (string $attribute, $value, Closure $fail) use ($service) {
                                    $activeShift = Shift::where('user_id', Auth::id())->where('status', 'open')->first();

                                    if (!$activeShift) {
                                        $fail("You cannot record an expense without an active shift.");
                                        return;
                                    }

                                    $currentBalance = $service->calculate($activeShift);
                                    if (floatval($value) > $currentBalance) {
                                        $fail("Insufficient funds! Your shift balance is KES " . number_format($currentBalance, 2));
                                    }
                                },
                            ]),

                        Textarea::make('description')
                            ->placeholder('What was this expense for?')
                            ->columnSpanFull(),
                        
                        Hidden::make('created_by')
                            ->default(Auth::id()),
                            
                        Hidden::make('shift_id')
                            ->default(fn () => Shift::where('user_id', Auth::id())->where('status', 'open')->first()?->id),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M, H:i')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('KES')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Expenses')),

                TextColumn::make('description')
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Operator')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Category'),
            ])
            ->actions([ 
                EditAction::make(),
            ])
            ->headerActions([               
                ExportAction::make()
                    ->label('Export to Excel')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}