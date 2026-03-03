<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\RelationManagers;

use App\Filament\FieldOperations\Resources\FloatRequestResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class FloatRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'floatRequests';

    public function form(Form $form): Form
    {
        // Reuses the form schema from your main FloatRequestResource
        return FloatRequestResource::form($form);
    }

    public function table(Table $table): Table
    {
        return FloatRequestResource::table($table)
            ->filters([
                // Status Filter (Pending, Approved, Rejected)
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                // Date Range Filter
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Requested From'),
                        DatePicker::make('until')->label('Requested Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id(); // Assign the operator making the request
                        $data['status'] = 'pending';   // Default to pending for new requests
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Only allow editing if the request hasn't been processed yet
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
            ])
            ->groupedBulkActions([]);
    }
}