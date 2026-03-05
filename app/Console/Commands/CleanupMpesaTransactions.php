<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MpesaTransaction;

class CleanupMpesaTransactions extends Command
{
    protected $signature = 'mpesa:cleanup';

    protected $description = 'Mark stale mpesa transactions as failed';

    public function handle()
    {
        $count = MpesaTransaction::where('status','requested')
            ->where('created_at','<',now()->subMinutes(5))
            ->update([
                'status'=>'failed',
                'result_desc'=>'Timeout waiting for Safaricom callback'
            ]);

        $this->info("Cleaned {$count} stale transactions.");
    }
}