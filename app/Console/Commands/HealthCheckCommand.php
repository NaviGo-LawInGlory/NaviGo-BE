<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthCheckCommand extends Command
{
    protected $signature = 'health:check';
    protected $description = 'Check the health of the application';

    public function handle()
    {
        try {
            DB::connection()->getPdo();
            $this->info('Database connection: OK');
            
            $storageDirectories = ['app', 'logs', 'framework/cache', 'framework/sessions', 'framework/views'];
            $allDirectoriesWritable = true;
            
            foreach ($storageDirectories as $directory) {
                $path = storage_path($directory);
                if (!is_writable($path)) {
                    $this->error("Directory not writable: $path");
                    $allDirectoriesWritable = false;
                }
            }
            
            if ($allDirectoriesWritable) {
                $this->info('Storage directories: OK');
            }
            
            if (Cache::put('health_check_test', true, 10)) {
                $this->info('Cache system: OK');
            } else {
                $this->error('Cache system: FAIL');
            }
            
            $this->info('Application is healthy');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

