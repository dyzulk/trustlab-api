<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\CaCertificate;

class MigrateCaCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ca:migrate-data {--f|force : Force migration even if target table is not empty}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate CA data from default database to the new CA database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting CA data migration...');

        // Check if source table exists
        if (!DB::connection('mysql')->getSchemaBuilder()->hasTable('ca_certificates')) {
            $this->error('Source table "ca_certificates" does not exist in the default connection.');
            return 1;
        }

        // Check if target table is empty
        $count = CaCertificate::count();
        if ($count > 0 && !$this->option('force')) {
            $this->error("Target table is not empty (contains $count records). Use --force to proceed.");
            return 1;
        }

        // Fetch from old DB
        $oldCerts = DB::connection('mysql')->table('ca_certificates')->get();
        
        $this->info("Found {$oldCerts->count()} certificates to migrate.");

        $bar = $this->output->createProgressBar($oldCerts->count());
        $bar->start();

        foreach ($oldCerts as $cert) {
            // We use the Model to insert into the new DB (since it's now bound to 'mysql_ca')
            // Using replicate() or manual array creation
            
            $data = (array) $cert;
            
            // Ensure we don't duplicate if it already exists (upsert-like behavior or strict check)
            if (CaCertificate::where('uuid', $data['uuid'])->exists()) {
                 if ($this->option('force')) {
                     // Update existing
                     CaCertificate::where('uuid', $data['uuid'])->update($data);
                 } else {
                     // Skip
                 }
            } else {
                CaCertificate::create($data);
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Data migration completed successfully.');
        
        return 0;
    }
}
