<?php

namespace App\Support\Localization;

use App\Enums\TranslationStatus;
use App\Events\TranslationPublished;
use App\Events\TranslationUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TranslationWorkflowManager
{
    /**
     * @param  array<string, mixed>  $translatedPayload
     */
    public function saveDraft(
        Model $translation,
        array $translatedPayload,
        string $sourceRevision,
        bool $machineTranslated = false,
        array $metadata = [],
    ): Model {
        $status = $machineTranslated
            ? TranslationStatus::MachineDraft
            : TranslationStatus::Draft;

        $translation->forceFill(array_merge($translatedPayload, [
            'translation_status' => $status,
            'source_revision' => $sourceRevision,
            'translation_revision' => TranslationRevision::fingerprint($translatedPayload),
            'is_machine_translated' => $machineTranslated,
            'translation_meta' => $metadata ?: null,
            'translated_at' => now(),
            'reviewed_at' => null,
            'translation_published_at' => null,
        ]))->save();

        TranslationUpdated::dispatch(
            $translation,
            (string) $translation->getAttribute('locale'),
            $status->value,
        );

        return $translation->refresh();
    }

    public function transition(Model $translation, TranslationStatus $target): Model
    {
        $current = $translation->getAttribute('translation_status');
        $current = $current instanceof TranslationStatus
            ? $current
            : TranslationStatus::tryFrom((string) $current) ?? TranslationStatus::Missing;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'translation_status' => sprintf(
                    'Không thể chuyển trạng thái bản dịch từ %s sang %s.',
                    $current->value,
                    $target->value,
                ),
            ]);
        }

        $attributes = ['translation_status' => $target];

        if ($target === TranslationStatus::Ready) {
            $attributes['reviewed_at'] = now();
        }

        if ($target === TranslationStatus::Published) {
            $attributes['translation_published_at'] = now();
        }

        if ($target !== TranslationStatus::Published) {
            $attributes['translation_published_at'] = null;
        }

        $translation->forceFill($attributes)->save();
        $translation->refresh();

        TranslationUpdated::dispatch(
            $translation,
            (string) $translation->getAttribute('locale'),
            $target->value,
        );

        if ($target === TranslationStatus::Published) {
            TranslationPublished::dispatch(
                $translation,
                (string) $translation->getAttribute('locale'),
            );
        }

        return $translation;
    }

    /**
     * @param  array<string, mixed>  $currentSourcePayload
     */
    public function markOutdatedWhenSourceChanges(
        Model $translation,
        array $currentSourcePayload,
    ): bool {
        $revision = TranslationRevision::fingerprint($currentSourcePayload);

        if ($translation->getAttribute('source_revision') === $revision) {
            return false;
        }

        $status = $translation->getAttribute('translation_status');
        $status = $status instanceof TranslationStatus
            ? $status
            : TranslationStatus::tryFrom((string) $status) ?? TranslationStatus::Missing;

        if (in_array($status, [TranslationStatus::Missing, TranslationStatus::NeedsTranslation], true)) {
            $translation->forceFill(['source_revision' => $revision])->save();

            return false;
        }

        $translation->forceFill([
            'translation_status' => TranslationStatus::Outdated,
        ])->save();

        TranslationUpdated::dispatch(
            $translation,
            (string) $translation->getAttribute('locale'),
            TranslationStatus::Outdated->value,
        );

        return true;
    }
}
