<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use App\Filament\FieldOperations\Resources\SupplierResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static ?string $navigationLabel = 'Suppliers';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Vendor Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            // 1. Prevents the SQL crash by checking before saving
                            // 2. ignoreRecord: true allows saving when editing the SAME supplier
                            ->unique(ignoreRecord: true) 
                            // 3. Custom message instead of the technical SQL error
                            ->validationMessages([
                                'unique' => 'This phone number is already registered to another supplier.',
                            ])
                            // Optional: Ensures standard 10-digit Kenyan format
                            ->length(10)
                            ->placeholder('07xxxxxxxx'),

                        TextInput::make('location'),

                        Hidden::make('created_by')
                            ->default(Auth::id()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('location')
                    ->placeholder('N/A'),
                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->label('Registered On'),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}