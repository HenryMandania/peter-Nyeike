<?php

namespace App\Filament\FieldOperations\Resources\SupplierResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class SupplierRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';
    protected static ?string $title = 'Purchase History';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric(2)
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total Quantity')
                    ),

                TextColumn::make('total_amount')
                    ->label('Total (KES)')
                    ->money('KES')
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total Spent')
                    ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'waiting'  => 'warning',
                        default    => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}