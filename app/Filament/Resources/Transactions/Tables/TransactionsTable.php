<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Transaction;
use App\Models\TransactionEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transactionCategory.name')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('transactionEntries')
                    ->label('Wallet Allocation')
                    ->state(fn (Transaction $record): array => $record->transactionEntries
                        ->map(fn (TransactionEntry $entry): string => sprintf(
                            '%s: %s',
                            $entry->wallet?->name ?? 'Unknown wallet',
                            Number::currency((float) $entry->amount, 'IDR', 'id'),
                        ))
                        ->all()
                    )
                    ->listWithLineBreaks()
                    ->placeholder('No allocation')
                    ->wrap(),
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
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
