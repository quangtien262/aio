<?php

namespace App\Core\Themes;

readonly class ThemeManifest
{
    /**
     * @param  array<int, string>  $blocks
     */
    public function __construct(
        public string $name,
        public string $key,
        public string $version,
        public string $description,
        public string $websiteType,
        public array $blocks = [],
    ) {
    }

    /**
     * @param  array{name:string,key:string,version:string,description?:string,website_type:string,blocks?:array<int,string>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            key: $payload['key'],
            version: $payload['version'],
            description: $payload['description'] ?? '',
            websiteType: $payload['website_type'],
            blocks: $payload['blocks'] ?? [],
        );
    }
}
