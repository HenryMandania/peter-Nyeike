<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Form; 
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction; 
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\FieldOperations\Resources\ItemResource\Pages; 
use App\Filament\FieldOperations\Resources\ItemResource\RelationManagers\ItemRelationManager; 
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationLabel = 'Items';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([ 
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('unit')
                    ->required(),
                TextInput::make('department')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('unit')
                    ->searchable(),
                TextColumn::make('department')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->options(Item::query()->pluck('department', 'department')->toArray())
                    ->searchable()
                    ->indicator('Department'),
                    
                SelectFilter::make('unit')
                    ->options(Item::query()->pluck('unit', 'unit')->toArray())
                    ->searchable()
                    ->indicator('Unit'),
            ])
            ->actions([ 
                Tables\Actions\ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([ 
                BulkActionGroup::make([ 
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'view' => Pages\ViewItem::route('/{record}'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}