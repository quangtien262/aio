<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $translation,
        public readonly string $locale,
    ) {}
}
