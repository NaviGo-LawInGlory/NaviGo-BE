<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class MigrationDebugCommand extends Command
{
    protected $signature = 'migrate:debug';
    protected $description = 'Debug migration issues';

    public function handle()
    {
        try {
            $this->info('Testing database connection...');
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            $this->info('Database connection established successfully.');
            
            $this->info('Database details:');
            $this->info('- Driver: ' . $connection->getDriverName());
            $this->info('- Database: ' . $connection->getDatabaseName());
            
            $this->info('Checking migrations table...');
            if (Schema::hasTable('migrations')) {
                $this->info('Migration table exists.');
                $migrations = DB::table('migrations')->get();
                $this->info('Applied migrations: ' . $migrations->count());
                foreach ($migrations as $migration) {
                    $this->line("- {$migration->migration}");
                }
            } else {
                $this->warn('Migration table does not exist.');
            }
            
            $this->info('Checking existing tables...');
            $tables = collect($connection->getDoctrineSchemaManager()->listTableNames());
            $this->info('Tables: ' . $tables->count());
            foreach ($tables as $table) {
                $this->line("- {$table}");
            }
            
            $this->info('Checking migration files...');
            $migrationFiles = File::glob(database_path('migrations/*.php'));
            $this->info('Found ' . count($migrationFiles) . ' migration files');
            foreach ($migrationFiles as $file) {
                $this->line('- ' . basename($file));
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Migration debug error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
