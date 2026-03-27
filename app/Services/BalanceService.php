<?php

namespace App\Services;

use App\Models\Shift;

class BalanceService
{
   
    public function calculate(Shift $shift): float
    {
        
        $opening = (float) ($shift->opening_balance ?? 0);

     
        $topups = (float) ($shift->total_float ?? $shift->floatRequests()
            ->where('status', 'approved')
            ->sum('amount'));

        
        $purchases = (float) ($shift->total_purchased ?? $shift->purchases()
            ->sum('total_amount'));

      
        $fees = (float) ($shift->total_fees ?? $shift->purchases()
            ->sum('transaction_fee'));

      
        $expenses = (float) ($shift->total_expenses ?? $shift->expenses()
            ->sum('amount'));
       
        return ($opening + $topups) - ($purchases + $fees + $expenses);
    }
}