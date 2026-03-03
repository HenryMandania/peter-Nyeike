<?php

namespace App\Filament\FieldOperations\Resources\ItemResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;

class ItemRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    protected static ?string $title = 'Procurement History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Purchase Date')
                    ->dateTime('d M Y')
                    ->sortable(),

                TextColumn::make('vendor.name')
                    ->label('Supplier')
                    ->searchable(),

                // ✅ FIXED HERE
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric(2)
                    ->summarize(
                        Sum::make()->label('Total Quantity')
                    ),

                TextColumn::make('total_amount')
                    ->label('Total Cost')
                    ->money('KES')
                    ->summarize(
                        Sum::make()->label('Total Invested')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}