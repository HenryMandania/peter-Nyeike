<?php

namespace App\Filament\FieldOperations\Resources\CompanyPaymentResource\Widgets;

use App\Filament\FieldOperations\Resources\CompanyPaymentResource\Pages\ListCompanyPayments;
use App\Models\CompanyPayment; // Added this import
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        // Get the current page's filters from the request
        $filters = request()->get('tableFilters', []);
        
        // Safely extract date range from filters
        $from = null;
        $until = null;
        
        if (isset($filters['payment_date'])) {
            $from = $filters['payment_date']['from'] ?? null;
            $until = $filters['payment_date']['until'] ?? null;
        }
        
        // Also check for company filter if needed
        $companyId = null;
        if (isset($filters['company_id'])) {
            $companyId = $filters['company_id']['value'] ?? null;
        }
        
        // Build queries with the same filters
        $paymentsReceived = CompanyPayment::query()
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('payment_date', '<=', $until))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->sum('amount') ?? 0;
        
        $totalSales = Sale::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('created_at', '<=', $until))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->sum('sales_amount') ?? 0;
        
        $totalPurchases = Purchase::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('created_at', '<=', $until))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->sum('total_amount') ?? 0;
        
        $totalExpenses = Expense::query()
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('date', '<=', $until))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->sum('amount') ?? 0;
        
        $netProfit = ($totalSales - $totalPurchases) - $totalExpenses;
        $outstandingBalance = $totalSales - $paymentsReceived;
        
        return [
            Stat::make('Total Revenue', 'KES ' . number_format($totalSales, 2))
                ->description('From filtered sales')
                ->color('success'),
            
            Stat::make('Payments Received', 'KES ' . number_format($paymentsReceived, 2))
                ->description('From company payments')
                ->color('info'),
            
            Stat::make('Outstanding Balance', 'KES ' . number_format(max($outstandingBalance, 0), 2))
                ->description('Unpaid amount in filtered period')
                ->color($outstandingBalance > 0 ? 'danger' : 'success'),
            
            Stat::make('Total Expenses', 'KES ' . number_format($totalExpenses, 2))
                ->description('Operating expenses')
                ->color('warning'),
            
            Stat::make('Net Profit', 'KES ' . number_format($netProfit, 2))
                ->description('After purchases & expenses')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
        ];
    }
}