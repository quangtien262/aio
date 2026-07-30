<?php

namespace App\Models\Concerns;

use App\Enums\TranslationStatus;
use Illuminate\Database\Eloquent\Builder;

trait HasTranslationWorkflow
{
    protected function initializeHasTranslationWorkflow(): void
    {
        $this->mergeCasts([
            'translation_status' => TranslationStatus::class,
            'is_machine_translated' => 'boolean',
            'translation_meta' => 'array',
            'translated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'translation_published_at' => 'datetime',
        ]);
    }

    public function scopePublishedTranslation(Builder $query): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('translation_status'),
            TranslationStatus::Published->value,
        );
    }

    public function isPublishedTranslation(): bool
    {
        return $this->translation_status === TranslationStatus::Published;
    }
}
