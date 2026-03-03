<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Form;  
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;  
use Filament\Tables\Actions\BulkActionGroup;  
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
                TextColumn::make('unit'),
                TextColumn::make('department'),
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
                //
            ])
            ->actions([ 
                EditAction::make(),
            ])
            ->bulkActions([ 
               
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
            'view' => Pages\ViewItem::route('/view'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}