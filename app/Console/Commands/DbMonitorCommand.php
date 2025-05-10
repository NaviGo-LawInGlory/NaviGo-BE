<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DbMonitorCommand extends Command
{
    protected $signature = 'db:monitor';
    protected $description = 'Check database connection status';

    public function handle()
    {
        try {
            $this->info('Testing connection to database...');
            DB::connection()->getPdo();
            $this->info('Database connection established successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
