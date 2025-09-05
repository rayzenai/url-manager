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
            ->disabledOn('edit')
            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old, ?Model $record) {
                // Slugify the manually entered value
                if ($state !== null && $state !== '') {
                    $set('slug', Str::slug($state));
                }
            })
            ->unique(ignoreRecord: true)
            ->rules([
                function (Get $get, ?Model $record) {
                    return function (string $attribute, $value, Closure $fail) use ($record) {
                        // Also check if slug will conflict in URLs table
                        // Build the full URL path based on model type
                        $urlPath = $value;

                        if ($this->modelClass) {
                            $modelClass = $this->evaluate($this->modelClass);
                            $modelBasename = class_basename($modelClass);

                            // Determine URL prefix based on model type
                            $urlPath = match ($modelBasename) {
                                'Brand' => "brand/{$value}",
                                'Category' => $value,
                                'Seller' => "sellers/{$value}",
                                'Entity' => ($record && $record->category ? $record->category->slug : 'category')."/{$value}",
                                default => $value,
                            };
                        }

                        // Check if this URL path exists (for active URLs)
                        $query = Url::where('slug', $urlPath)
                            ->where('status', Url::STATUS_ACTIVE);

                        // If updating, ignore the current record's URL
                        if ($record?->exists && method_exists($record, 'url') && $record->url) {
                            $query->where('id', '!=', $record->url->id);
                        }

                        if ($query->exists()) {
                            $fail('This URL slug is already in use.');
                        }
                    };
                },
            ]);
    }

    public function sourceField(string|Closure $field): static
    {
        $this->sourceField = $field;

        // Set up auto-generation from source field when creating new records
        $this->afterStateHydrated(function (Get $get, Set $set, ?string $state, ?Model $record) use ($field) {
            // Only auto-generate if slug is empty and we're creating a new record
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