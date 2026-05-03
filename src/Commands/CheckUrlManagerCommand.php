<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use RayzenAI\UrlManager\Models\Url;
use RayzenAI\UrlManager\Traits\HasUrl;

class CheckUrlManagerCommand extends Command
{
    protected $signature = 'url-manager:check {model? : Specific model class to check}';

    protected $description = 'Check all models with HasUrl trait for missing configuration';

    public function handle(): int
    {
        $modelClass = $this->argument('model');

        if ($modelClass) {
            if (! class_exists($modelClass)) {
                $this->error("Model class {$modelClass} does not exist.");

                return 1;
            }

            if (! in_array(HasUrl::class, class_uses_recursive($modelClass))) {
                $this->error("Model {$modelClass} does not use the HasUrl trait.");

                return 1;
            }

            $this->checkModel($modelClass);
        } else {
            $this->checkAllModels();
        }

        return 0;
    }

    protected function checkAllModels(): void
    {
        $this->info('🔍 Scanning for models with HasUrl trait...');
        $this->newLine();

        $modelsPath = app_path('Models');

        if (! is_dir($modelsPath)) {
            $this->error('Models directory not found.');

            return;
        }

        $modelFiles = glob($modelsPath . '/*.php');
        $modelsFound = [];

        foreach ($modelFiles as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');

            if (! class_exists($className)) {
                continue;
            }

            if (! in_array(HasUrl::class, class_uses_recursive($className))) {
                continue;
            }

            $modelsFound[] = $className;
        }

        if (empty($modelsFound)) {
            $this->warn('⚠️  No models found using the HasUrl trait.');
            $this->newLine();
            $this->line('💡 Add the HasUrl trait to your models:');
            $this->line('   use RayzenAI\UrlManager\Traits\HasUrl;');

            return;
        }

        $this->info('Found ' . count($modelsFound) . ' model(s) using HasUrl trait:');
        $this->newLine();

        foreach ($modelsFound as $modelClass) {
            $this->checkModel($modelClass);
            $this->newLine();
        }

        $this->info('✅ Check complete!');
    }

    protected function checkModel(string $modelClass): void
    {
        $model = new $modelClass;
        $shortName = class_basename($modelClass);

        $this->line("📦 <fg=cyan>{$modelClass}</>");
        $this->line(str_repeat('─', 60));

        $issues = [];
        $warnings = [];
        $successes = [];

        // Try to get an actual model instance for testing
        $testModel = $modelClass::first();

        // Check 1: webUrlPath() method
        if (! method_exists($model, 'webUrlPath')) {
            $issues[] = '❌ Missing webUrlPath() method';
        } else {
            if ($testModel) {
                try {
                    $path = $testModel->webUrlPath();
                    $successes[] = "✅ webUrlPath() implemented → <fg=green>{$path}</>";
                } catch (\Exception $e) {
                    $issues[] = "❌ webUrlPath() throws error: {$e->getMessage()}";
                }
            } else {
                $successes[] = '✅ webUrlPath() method exists (no data to test)';
            }
        }

        // Check 2: is_active or custom active field
        $activeField = method_exists($model, 'activeUrlField') ? $model->activeUrlField() : 'is_active';

        if (! Schema::hasColumn($model->getTable(), $activeField)) {
            $issues[] = "❌ Missing '{$activeField}' column in database";
        } else {
            $successes[] = "✅ Active field '{$activeField}' exists";
        }

        // Check 3: view_count tracking
        if (method_exists($model, 'getViewCountColumn')) {
            $viewCountColumn = $model->getViewCountColumn();

            if ($viewCountColumn) {
                if (! Schema::hasColumn($model->getTable(), $viewCountColumn)) {
                    $issues[] = "❌ View count column '{$viewCountColumn}' defined but doesn't exist in database";
                } else {
                    $successes[] = "✅ View count tracking enabled → '{$viewCountColumn}' column";
                }
            } else {
                $warnings[] = '⚠️  getViewCountColumn() returns null - view counting disabled';
            }
        } else {
            $warnings[] = '⚠️  No getViewCountColumn() method - view counting not implemented';
        }

        // Check 4: ogTags() for SEO
        if (method_exists($model, 'ogTags')) {
            if ($testModel) {
                try {
                    $ogTags = $testModel->ogTags();
                    if (is_array($ogTags) && ! empty($ogTags)) {
                        $successes[] = '✅ ogTags() implemented with ' . count($ogTags) . ' tag(s)';
                    } else {
                        $warnings[] = '⚠️  ogTags() returns empty array';
                    }
                } catch (\Exception $e) {
                    $warnings[] = "⚠️  ogTags() throws error: {$e->getMessage()}";
                }
            } else {
                $successes[] = '✅ ogTags() method exists (no data to test)';
            }
        } else {
            $warnings[] = '⚠️  No ogTags() method - missing SEO metadata';
        }

        // Check 5: getSeoMetadata() for advanced SEO
        if (method_exists($model, 'getSeoMetadata')) {
            $successes[] = '✅ getSeoMetadata() implemented';
        }

        // Check 6: Existing URL records
        $urlCount = Url::where('urable_type', $modelClass)->count();
        $modelCount = $modelClass::count();

        if ($urlCount === 0 && $modelCount > 0) {
            $warnings[] = "⚠️  No URL records found for {$modelCount} model instance(s)";
            $warnings[] = "   💡 Run: php artisan urls:generate \"{$modelClass}\"";
        } elseif ($urlCount < $modelCount) {
            $warnings[] = "⚠️  Only {$urlCount}/{$modelCount} models have URL records";
            $warnings[] = "   💡 Run: php artisan urls:generate \"{$modelClass}\"";
        } else {
            $successes[] = "✅ {$urlCount} URL record(s) created";
        }

        // Display results
        foreach ($successes as $success) {
            $this->line("  {$success}");
        }

        foreach ($warnings as $warning) {
            $this->line("  {$warning}");
        }

        foreach ($issues as $issue) {
            $this->line("  {$issue}");
        }

        // Summary
        if (empty($issues) && empty($warnings)) {
            $this->line('  <fg=green>🎉 Perfect configuration!</>');
        } elseif (! empty($issues)) {
            $issueCount = count($issues);
            $this->line("  <fg=red>⚠️  {$issueCount} critical issue(s) found</>");
        }
    }
}
