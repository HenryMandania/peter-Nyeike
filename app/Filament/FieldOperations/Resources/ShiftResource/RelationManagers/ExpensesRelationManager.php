<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\RelationManagers;

use App\Filament\FieldOperations\Resources\ExpenseResource;
use App\Services\BalanceService;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    public function form(Form $form): Form
    {
        // Reuses the schema from your main ExpenseResource
        return ExpenseResource::form($form);
    }

    public function table(Table $table): Table
    {
        return ExpenseResource::table($table)
            ->filters([
                // Filter by Expense Category
                SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                // Filter by Date Range
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Spent From'),
                        DatePicker::make('until')->label('Spent Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();
                        return $data;
                    })
                    ->before(function (Tables\Actions\CreateAction $action, array $data) {
                        // Validate Shift Balance
                        $shift = $this->getOwnerRecord();
                        $balanceService = app(BalanceService::class);
                        $currentBalance = $balanceService->calculate($shift);
                        
                        $expenseAmount = (float)$data['amount'];

                        if ($expenseAmount > $currentBalance) {
                            Notification::make()
                                ->title('Insufficient Balance')
                                ->body("The current shift balance is KES " . number_format($currentBalance, 2))
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action) {
                        // Optional: Prevent deletion if shift is closed
                        if ($this->getOwnerRecord()->status === 'closed') {
                            Notification::make()
                                ->title('Action Denied')
                                ->body('Cannot delete expenses from a closed shift.')
                                ->warning()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->groupedBulkActions([]);
    }
}