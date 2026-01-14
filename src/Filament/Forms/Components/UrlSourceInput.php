<?php

namespace RayzenAI\UrlManager\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RayzenAI\UrlManager\Models\Url;

class UrlSourceInput extends TextInput
{
    protected string|Closure $slugField = 'slug';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state, ?Model $record) {
                $slugField = $this->getSlugField();
                $currentSlug = $get($slugField) ?? '';
                $oldSlug = $old ? Str::slug($old) : '';

                // Preserve manual edits
                if ($currentSlug !== '' && $currentSlug !== $oldSlug) {
                    return;
                }

                $baseSlug = Str::slug($state ?? '');
                if (empty($baseSlug)) {
                    return;
                }

                // Generate unique slug
                $uniqueSlug = $this->generateUniqueSlug($baseSlug, $record);
                $set($slugField, $uniqueSlug);
            });
    }

    public function slug(string|Closure $field): static
    {
        $this->slugField = $field;

        return $this;
    }

    public function getSlugField(): string
    {
        return $this->evaluate($this->slugField);
    }

    protected function generateUniqueSlug(string $baseSlug, ?Model $record): string
    {
        $slug = $baseSlug;
        $count = 2;
        $modelClass = $record ? get_class($record) : $this->getModel();

        while ($this->slugExists($slug, $record, $modelClass)) {
            $slug = $baseSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?Model $record, ?string $modelClass): bool
    {
        // Check model's own table
        if ($modelClass && class_exists($modelClass)) {
            $query = $modelClass::where('slug', $slug);

            if ($record?->exists) {
                $query->where('id', '!=', $record->id);
            }

            if ($query->exists()) {
                return true;
            }
        }

        // Check URLs table
        $urlQuery = Url::where('slug', $slug)
            ->where('status', Url::STATUS_ACTIVE);

        if ($record?->exists && method_exists($record, 'url') && $record->url) {
            $urlQuery->where('id', '!=', $record->url->id);
        }

        return $urlQuery->exists();
    }
}
