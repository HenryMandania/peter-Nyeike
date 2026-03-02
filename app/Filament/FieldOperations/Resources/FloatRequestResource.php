<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\FloatRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section; // Fixed import path
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action; // Fixed namespace for table actions
use Filament\Tables\Actions\EditAction; // Fixed namespace for table actions
use App\Filament\FieldOperations\Resources\FloatRequestResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FloatRequestResource extends Resource
{
    protected static ?string $model = FloatRequest::class;
    protected static ?string $navigationLabel = 'Float Requests';
    protected static ?string $navigationGroup = 'Shifts';     
    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([ // Changed from components() to schema()
                Section::make('Request Details')
                    ->description('Request a top-up. The default is your last closing balance.')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount to Request')
                            ->required()
                            ->numeric()
                            ->default(fn () => FloatRequest::getLastClosingBalance(Auth::id()))
                            ->prefix('KES'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Requested By')
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('KES')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('approver.name')
                    ->label('Approved By'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([ // Changed from recordActions() to actions()
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FloatRequest $record) => $record->status === 'pending')
                    ->action(fn (FloatRequest $record) => $record->update([
                        'status' => 'approved',
                        'approved_by' => Auth::id(),
                    ])),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FloatRequest $record) => $record->status === 'pending')
                    ->action(fn (FloatRequest $record) => $record->update([
                        'status' => 'rejected',
                    ])),
                
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFloatRequests::route('/'),
            'create' => Pages\CreateFloatRequest::route('/create'),
            'edit' => Pages\EditFloatRequest::route('/{record}/edit'),
        ];
    }
}