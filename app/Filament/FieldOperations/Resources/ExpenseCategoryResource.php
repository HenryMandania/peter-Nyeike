<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\ExpenseCategory;
use Filament\Forms;
use Filament\Forms\Form; 
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction; 
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\FieldOperations\Resources\ExpenseCategoryResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationLabel = 'Expense Categories';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([  
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                // created_by is handled automatically in the CreatePage mutation
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                // Display the creator's name via the relationship
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([   
                ViewAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'view' => Pages\ViewExpenseCategory::route('/{record}'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}