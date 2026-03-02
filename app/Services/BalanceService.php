<?php

namespace App\Services;

use App\Models\Shift;

class BalanceService
{
    public function calculate(Shift $shift): float
    {
        $opening = (float) $shift->opening_balance;

        $topups = (float) $shift->floatRequests()
            ->where('status', 'approved')
            ->sum('amount');

        $purchases = (float) $shift->purchases()
            ->sum('total_amount');

        $fees = (float) $shift->purchases()
            ->sum('transaction_fee');

        $expenses = (float) $shift->expenses()
            ->sum('amount');

        return ($opening + $topups) - ($purchases + $fees + $expenses);
    }
}