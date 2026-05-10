<?php

namespace App\Support\ThemeBlocks;

final readonly class ThemeBlockEntry
{
    public function __construct(
        public string $key,
        public string $label,
        public string $sourceValue,
    ) {
    }

    /**
     * @return array{key:string,label:string,source_value:string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'source_value' => $this->sourceValue,
        ];
    }
}
