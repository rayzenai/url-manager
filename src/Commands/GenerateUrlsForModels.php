<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use RayzenAI\UrlManager\Models\Url;
use RayzenAI\UrlManager\Traits\HasUrl;

class GenerateUrlsForModels extends Command
{
    protected $signature = 'urls:generate {model? : The model class to generate URLs for}';

    protected $description = 'Generate URL records for existing models that use the HasUrl trait';

    public function handle()
    {
        $modelClass = $this->argument('model');
        
        if ($modelClass) {
            $this->generateForModel($modelClass);
        } else {
            $this->generateForAllModels();
        }
        
        return 0;
    }
    
    protected function generateForModel(string $modelClass)
    {
        if (!class_exists($modelClass)) {
            $this->error("Model class {$modelClass} does not exist.");
            return;
        }
        
        if (!in_array(HasUrl::class, class_uses_recursive($modelClass))) {
            $this->error("Model {$modelClass} does not use the HasUrl trait.");
            return;
        }
        
        $this->info("Generating URLs for {$modelClass}...");
        
        $model = new $modelClass;
        $count = 0;
        
        // Check if model has is_active field
        $hasIsActive = Schema::hasColumn($model->getTable(), 'is_active');
        
        $query = $modelClass::query();
        
        if ($hasIsActive) {
            $query->where('is_active', true);
        }
        
        $query->whereDoesntHave('url')->chunk(100, function ($models) use (&$count, $modelClass) {
            foreach ($models as $model) {
                if (!method_exists($model, 'webUrlPath')) {
                    continue;
                }
                
                $path = $model->webUrlPath();
                $type = $this->getUrlType($modelClass);
                
                // Check if URL already exists with this slug
                $existingUrl = Url::where('slug', $path)->first();
                
                if ($existingUrl) {
                    // If URL exists but not linked to this model, update it
                    if (!$existingUrl->urable_id || $existingUrl->urable_id != $model->id) {
                        $this->warn("URL with slug '{$path}' already exists for different model. Skipping...");
                    }
                    continue;
                }
                
                try {
                    Url::create([
                        'slug' => $path,
                        'urable_type' => $modelClass,
                        'urable_id' => $model->id,
                        'type' => $type,
                        'status' => $model->isActiveForUrl() ? Url::STATUS_ACTIVE : Url::STATUS_INACTIVE,
                        'last_modified_at' => $model->updated_at ?? now(),
                    ]);
                    
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Failed to create URL for {$modelClass} ID {$model->id}: " . $e->getMessage());
                }
            }
        });
        
        $this->info("Generated {$count} URLs for {$modelClass}");
    }
    
    protected function generateForAllModels()
    {
        $this->info('Scanning for models with HasUrl trait...');
        
        // Get all model files from app/Models directory
        $modelsPath = app_path('Models');
        
        if (!is_dir($modelsPath)) {
            $this->error('Models directory not found.');
            return;
        }
        
        $modelFiles = glob($modelsPath . '/*.php');
        
        foreach ($modelFiles as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');
            
            if (!class_exists($className)) {
                continue;
            }
            
            if (!in_array(HasUrl::class, class_uses_recursive($className))) {
                continue;
            }
            
            $this->generateForModel($className);
        }
        
        $this->info('URL generation complete!');
    }
    
    protected function getUrlType(string $modelClass): string
    {
        $className = class_basename($modelClass);
        
        $typeMap = [
            'Product' => Url::TYPE_ENTITY,
            'Entity' => Url::TYPE_ENTITY,
            'Category' => Url::TYPE_CATEGORY,
            'Seller' => Url::TYPE_SELLER,
            'Brand' => Url::TYPE_BRAND,
            'Blog' => Url::TYPE_BLOG,
            'Page' => Url::TYPE_PAGE,
        ];
        
        return $typeMap[$className] ?? Url::TYPE_PAGE;
    }
}