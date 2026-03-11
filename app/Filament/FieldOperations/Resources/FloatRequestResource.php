<?php

namespace App\Filament\FieldOperations\Resources;

use App\Models\FloatRequest;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use App\Filament\FieldOperations\Resources\FloatRequestResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Closure;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use App\Models\User;

class FloatRequestResource extends Resource
{
    protected static ?string $model = FloatRequest::class;
    protected static ?string $navigationLabel = 'Float Requests';
    protected static ?string $navigationGroup = 'Shifts';     
    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';
    protected static ?int $navigationSort = 3;  
    /**
     * Prevents the "Create" button from appearing if no active shift exists.
     */
    public static function canCreate(): bool
    {
        return Shift::where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Request Details')
                    ->description('Request a top-up. You must have an active shift to proceed.')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount to Request')
                            ->required()
                            ->numeric()
                            ->default(fn () => FloatRequest::getLastClosingBalance(Auth::id()))
                            ->prefix('KES')
                            // Hard validation check during submission
                            ->rules([
                                fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                    $hasShift = Shift::where('user_id', Auth::id())
                                        ->where('status', 'open')
                                        ->exists();

                                    if (!$hasShift) {
                                        $fail("You cannot request float without an active shift. Please open a shift first.");
                                    }
                                },
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
            

            ->headerActions([
                ExportAction::make()
                    ->label('Export Excel')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->name("Float-Requests-" . date('Y-m-d')),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FloatRequest $record, User $user) => $user->can('approve', $record) && $record->status === 'pending')
                    ->action(fn (FloatRequest $record) => $record->update([
                        'status' => 'approved',
                        'approved_by' => Auth::id(),
                    ])),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FloatRequest $record, User $user) =>$user->can('reject', $record) && $record->status === 'pending')
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