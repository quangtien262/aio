<?php

namespace App\Enums;

enum TranslationStatus: string
{
    case Missing = 'missing';
    case Draft = 'draft';
    case MachineDraft = 'machine_draft';
    case InReview = 'in_review';
    case Ready = 'ready';
    case Published = 'published';
    case Outdated = 'outdated';
    case NeedsTranslation = 'needs_translation';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Missing => [self::Draft, self::MachineDraft, self::NeedsTranslation],
            self::Draft, self::MachineDraft => [self::InReview, self::Ready, self::Missing],
            self::InReview => [self::Draft, self::Ready],
            self::Ready => [self::Draft, self::Published],
            self::Published => [self::Draft, self::Outdated],
            self::Outdated => [self::Draft, self::MachineDraft, self::InReview, self::Ready],
            self::NeedsTranslation => [self::Draft, self::MachineDraft, self::Missing],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $status === $this || in_array($status, $this->allowedTransitions(), true);
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
