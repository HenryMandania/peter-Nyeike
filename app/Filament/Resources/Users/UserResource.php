<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Actions\EditAction;
use Illuminate\Support\Facades\Hash;
use App\Filament\Resources\Users\UserResource\Pages;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    
    protected static ?string $navigationGroup = 'Users Group';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                            
                        // Password: Visible only during user creation
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->revealable()
                            ->maxLength(255)
                            ->hiddenOn('edit'),
                            
                        // Reset Password Action: Opens a modal to enter a new password
                        Forms\Components\Actions::make([
                            Action::make('resetPassword')
                                ->label('Reset Password')
                                ->icon('heroicon-o-key')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                ->form([
                                    TextInput::make('new_password')
                                        ->password()
                                        ->label('New Password')
                                        ->required()
                                        ->revealable()
                                        ->minLength(8),
                                    TextInput::make('confirm_password')
                                        ->password()
                                        ->label('Confirm New Password')
                                        ->required()
                                        ->same('new_password')
                                        ->revealable(),
                                ])
                                ->action(function (User $record, array $data) {
                                    $record->update([
                                        'password' => Hash::make($data['new_password']),
                                    ]);

                                    Notification::make()
                                        ->title('Password updated successfully')
                                        ->success()
                                        ->send();
                                }),
                        ]),
                            
                        Toggle::make('status')
                            ->label('Active User')
                            ->default(true)
                            ->helperText('If disabled, the user will not be able to log in.'),

                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
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
                    
                TextColumn::make('email')
                    ->searchable(),
                    
                ToggleColumn::make('status')
                    ->label('Active'),
                    
                TextColumn::make('roles.name')
                    ->badge()
                    ->label('Roles')
                    ->color('warning'),
                    
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}