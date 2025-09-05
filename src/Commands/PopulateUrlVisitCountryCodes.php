<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use RayzenAI\UrlManager\Models\UrlVisit;
use Stevebauman\Location\Facades\Location;

class PopulateUrlVisitCountryCodes extends Command
{
    protected $signature = 'url-manager:populate-country-codes 
                            {--chunk=100 : Number of records to process per batch}
                            {--dry-run : Show what would be processed without actually doing it}';
    
    protected $description = 'Populate country codes for existing URL visits based on IP addresses';
    
    public function handle(): void
    {
        $this->info('Starting country code population for URL visits...');
        
        $chunkSize = (int) $this->option('chunk');
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        $query = UrlVisit::whereNull('country_code')
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', 'UNKNOWN');
        
        $totalRecords = $query->count();
        
        if ($totalRecords === 0) {
            $this->info('No URL visits found that need country code population.');
            return;
        }
        
        $this->info("Found {$totalRecords} URL visits to process");
        
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();
        
        $processed = 0;
        $updated = 0;
        $failed = 0;
        
        $query->chunkById($chunkSize, function ($visits) use (&$processed, &$updated, &$failed, $bar, $isDryRun) {
            foreach ($visits as $visit) {
                try {
                    // Skip invalid IPs
                    if (!filter_var($visit->ip_address, FILTER_VALIDATE_IP)) {
                        $processed++;
                        $bar->advance();
                        continue;
                    }
                    
                    // Try to resolve location
                    $location = Location::get($visit->ip_address);
                    
                    if ($location && $location->countryCode) {
                        if (!$isDryRun) {
                            $visit->update(['country_code' => $location->countryCode]);
                        }
                        $updated++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                }
                
                $processed++;
                $bar->advance();
            }
        });
        
        $bar->finish();
        $this->newLine();
        
        $this->info('Processing completed!');
        $this->info("Total processed: {$processed}");
        $this->info("Total updated: {$updated}");
        if ($failed > 0) {
            $this->warn("Failed to resolve: {$failed}");
        }
    }
}