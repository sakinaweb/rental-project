<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Tables\Columns\TextColumn;

// Integer minor units shown in the row's currency (currency_code column), e.g. 354000 INR → ₹3,540.00.
class MoneyColumn
{
    public static function make(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(fn ($state, $record) => Money::format((int) $state, $record->currency_code))
            ->alignEnd()
            ->sortable();
    }
}
