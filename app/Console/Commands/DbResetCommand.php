<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DbResetCommand extends Command
{
    protected $signature = 'db:statement {statement}';
    protected $description = 'Run a specific DB statement directly';

    public function handle()
    {
        $statement = $this->argument('statement');
        $this->info("Executing: {$statement}");
        
        try {
            DB::statement($statement);
            $this->info('Statement executed successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error executing statement: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
