<?php

namespace RayzenAI\UrlManager\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RayzenAI\UrlManager\Models\Url;

class UrlInput extends TextInput
{
    protected string|Closure|null $sourceField = 'name';

    protected string|Closure|null $modelClass = null;

    protected string|Closure|null $urlType = null;

    protected bool $canUpdateSlug = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('URL Slug')
            ->maxLength(255)
            ->required()
            ->prefix('/')
            ->helperText('URL slug for this item. Leave empty to auto-generate from the name.')
            ->reactive()
            ->disabled(function (?Model $record) {
                // Allow editing on create, or on edit if canUpdateSlug is enabled
                if (! $record?->exists) {
                    return false;
                }

                return ! $this->canUpdateSlug;
            })
            ->afterStateUpdated(function (Set $set, ?string $state) {
                // Slugify the manually entered value
                if ($state !== null && $state !== '') {
                    $set('slug', Str::slug($state));
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? Str::slug($state) : null)
            ->rules([
                function (?Model $record) {
                    return function (string $attribute, $value, Closure $fail) use ($record) {
                        if (empty($value)) {
                            return;
                        }

                        // Get model class from record or from component's model
                        $modelClass = $record ? get_class($record) : $this->getModel();

                        // Check model's own table for uniqueness
                        if ($modelClass && class_exists($modelClass)) {
                            $query = $modelClass::where('slug', $value);

                            if ($record?->exists) {
                                $query->where('id', '!=', $record->id);
                            }

                            $existingModel = $query->first();
                            if ($existingModel) {
                                $modelName = class_basename($modelClass);
                                $fail("Already used by {$modelName} #{$existingModel->id}");

                                return;
                            }
                        }

                        // Also check URLs table
                        $urlPath = $value;

                        if ($this->modelClass) {
                            $evalModelClass = $this->evaluate($this->modelClass);
                            $modelBasename = class_basename($evalModelClass);

                            $urlPath = match ($modelBasename) {
                                'Brand' => "brand/{$value}",
                                'Category' => $value,
                                'Seller' => "sellers/{$value}",
                                'Entity' => ($record && $record->category ? $record->category->slug : 'category')."/{$value}",
                                default => $value,
                            };
                        }

                        $urlQuery = Url::where('slug', $urlPath)
                            ->where('status', Url::STATUS_ACTIVE);

                        if ($record?->exists && method_exists($record, 'url') && $record->url) {
                            $urlQuery->where('id', '!=', $record->url->id);
                        }

                        $existingUrl = $urlQuery->first();
                        if ($existingUrl) {
                            $modelName = class_basename($existingUrl->urable_type ?? 'Record');
                            $fail("Already used by {$modelName} #{$existingUrl->urable_id}");
                            return;
                        }

                        // Check for circular redirect chains when updating slugs
                        if ($this->canUpdateSlug && $record?->exists && method_exists($record, 'url') && $record->url) {
                            $oldPath = $record->url->slug;

                            // Get the new path by temporarily setting slug and calling webUrlPath()
                            // This ensures we use the same logic as HasUrl trait
                            $originalSlug = $record->slug;
                            $record->slug = $value;
                            $newPath = method_exists($record, 'webUrlPath')
                                ? $record->webUrlPath()
                                : $urlPath;
                            $record->slug = $originalSlug; // Restore original

                            if ($oldPath !== $newPath) {
                                $chain = Url::detectRedirectChain($oldPath, $newPath);

                                if ($chain) {
                                    $fail('Cannot update slug: This would create a circular redirect chain: ' . implode(' → ', $chain));
                                    return;
                                }
                            }
                        }
                    };
                },
            ]);
    }

    /**
     * Set the source field to auto-generate the slug from.
     * The source field must have ->live(onBlur: true) for real-time generation.
     */
    public function sourceField(string|Closure $field): static
    {
        $this->sourceField = $field;

        // Set up auto-generation from source field when creating new records (on initial load)
        $this->afterStateHydrated(function (Get $get, Set $set, ?string $state, ?Model $record) use ($field) {
            if (empty($state) && ! $record?->exists) {
                $sourceValue = $get($this->evaluate($field));
                if ($sourceValue) {
                    $set($this->getName(), $this->generateUniqueSlug($sourceValue, null));
                }
            }
        });

        return $this;
    }

    public function forModel(string|Closure $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    public function urlType(string|Closure $type): static
    {
        $this->urlType = $type;

        return $this;
    }

    /**
     * Allow updating the slug when editing existing records.
     * By default, slugs are disabled on edit to prevent breaking existing URLs.
     * When enabled, automatically creates redirects from old to new URLs via the HasUrl trait.
     * Circular redirect chains (A→B, B→A or A→B→C→A) are automatically detected and prevented.
     */
    public function allowUpdatingSlug(bool $condition = true): static
    {
        $this->canUpdateSlug = $condition;

        return $this;
    }

    protected function generateUniqueSlug(string $value, ?Model $record = null): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug;
        $count = 1;

        // Check uniqueness in URLs table
        while ($this->slugExists($slug, $record)) {
            $slug = $baseSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?Model $record = null): bool
    {
        $query = Url::where('slug', $slug)
            ->where('status', Url::STATUS_ACTIVE);

        // If updating, exclude the current record's URL
        if ($record?->exists && $record->url) {
            $query->where('id', '!=', $record->url->id);
        }

        return $query->exists();
    }

    public static function make(?string $name = 'slug'): static
    {
        $static = parent::make($name ?? 'slug');

        // Auto-setup common configurations
        $static->live(onBlur: true);

        return $static;
    }

    /**
     * Create a URL input that listens to a source field
     * This requires the source field to have ->live(onBlur: true) set
     */
    public static function createFrom(string $sourceField = 'name', ?string $name = 'slug'): static
    {
        return static::make($name)
            ->sourceField($sourceField)
            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? Str::slug($state) : null)
            ->afterStateHydrated(function (Get $get, Set $set, ?string $state, ?Model $record) use ($sourceField) {
                // Set up initial value if empty
                if (empty($state) && ! $record?->exists) {
                    $sourceValue = $get($sourceField);
                    if ($sourceValue) {
                        $set('slug', Str::slug($sourceValue));
                    }
                }
            });
    }
}
