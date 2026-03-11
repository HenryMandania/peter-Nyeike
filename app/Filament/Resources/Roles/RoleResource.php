<?php

namespace App\Filament\Resources\Roles;

use Spatie\Permission\Models\Role;
use Filament\Forms;
use Filament\Forms\Form; 
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use App\Filament\Resources\Roles\RoleResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class RoleResource extends Resource
{     
    protected static ?string $model = Role::class;
    
    protected static ?string $navigationGroup = 'Users Group';
    
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function form(Form $form): Form 
    {
        return $form
            ->schema([ 
                Section::make('Role Management')
                    ->description('Define the role name and assign specific permissions.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->unique(
                                table: Role::class,
                                column: 'name',
                                ignorable: fn ($record) => $record,
                                modifyRuleUsing: function (Unique $rule, Forms\Get $get) {
                                    return $rule->where('guard_name', $get('guard_name'));
                                }
                            )
                            ->placeholder('e.g. Manager'),

                        Select::make('guard_name')
                            ->options([
                                'web' => 'web',
                                'api' => 'api',
                            ])
                            ->default('web')
                            ->required(),

                        CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->columns(3) 
                            ->searchable()
                            ->bulkToggleable()  
                            ->noSearchResultsMessage('No permissions found.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('guard_name')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('success'),
            ])
            ->actions([ 
                EditAction::make(),                
            ])
            ->bulkActions([
                BulkActionGroup::make([                 
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}