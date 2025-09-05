<?php

namespace RayzenAI\UrlManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RayzenAI\UrlManager\Models\Url;
use RayzenAI\UrlManager\Models\UrlVisit;

class RecordUrlVisit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Url $url,
        public ?int $userId = null,
        public ?array $metadata = []
    ) {
        // Set the queue if configured
        if ($queue = config('url-manager.visit_queue')) {
            $this->onQueue($queue);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Create detailed visit record using UrlVisit model
        $visit = UrlVisit::createFromRequest($this->url, $this->userId, $this->metadata);
        
        // Also record the basic URL visit count
        $this->url->recordVisit();
        
        // Get the related model
        $model = $this->url->urable;
        
        if (!$model) {
            return;
        }
        
        // Fire event for custom handling (e.g., entity view tracking)
        event('url-manager.visit.recorded', [
            'url' => $this->url,
            'visit' => $visit,
            'model' => $model,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
        ]);
        
        // If the model has a recordVisit method, call it
        if (method_exists($model, 'recordVisit')) {
            $model->recordVisit($this->userId, $this->metadata);
        }
        
        // Handle model-specific visit tracking
        $this->handleModelSpecificTracking($model);
    }
    
    /**
     * Handle model-specific visit tracking
     */
    protected function handleModelSpecificTracking($model): void
    {
        // Check if the model defines a view count column
        if (method_exists($model, 'getViewCountColumn')) {
            $viewCountColumn = $model->getViewCountColumn();
            if ($viewCountColumn) {
                $model->increment($viewCountColumn);
            }
        }
    }
}