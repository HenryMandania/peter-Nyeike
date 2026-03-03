<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\RelationManagers;

use App\Filament\FieldOperations\Resources\PurchaseResource;
use App\Services\BalanceService;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    public function form(Form $form): Form
    {
        // Reuses the quantity/price math from PurchaseResource
        return PurchaseResource::form($form);
    }

    public function table(Table $table): Table
    {
        return PurchaseResource::table($table)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        return $data;
                    })
                    ->before(function (Tables\Actions\CreateAction $action, array $data) {
                        // 1. Get the current shift (the owner of this relation)
                        $shift = $this->getOwnerRecord();
                        
                        // 2. Calculate current balance using your Service
                        $balanceService = app(BalanceService::class);
                        $currentBalance = $balanceService->calculate($shift);
                        
                        // 3. Check if purchase + fee exceeds balance
                        $totalCost = (float)$data['total_amount'] + (float)($data['transaction_fee'] ?? 0);
                        
                        if ($totalCost > $currentBalance) {
                            Notification::make()
                                ->title('Insufficient Shift Balance')
                                ->body("Current Balance: KES " . number_format($currentBalance, 2))
                                ->danger()
                                ->send();

                            // Halt the execution
                            $action->halt();
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Supplier')
                    ->relationship('vendor', 'name'),
                Tables\Filters\SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'name'),
            ]);
    }
}