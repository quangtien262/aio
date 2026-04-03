<?php

namespace App\Core\Themes;

readonly class ThemeManifest
{
    /**
     * @param  array<int, string>  $blocks
     * @param  array{thumbnail?:string,cover?:string}  $preview
     * @param  array<string, bool>  $supports
     * @param  array{content_path?:string,settings_path?:string}  $demo
     */
    public function __construct(
        public string $name,
        public string $key,
        public string $version,
        public string $description,
        public string $websiteType,
        public array $blocks = [],
        public ?string $parent = null,
        public array $preview = [],
        public array $supports = [],
        public array $demo = [],
    ) {
    }

    /**
     * @param  array{name:string,key:string,version:string,description?:string,website_type:string,blocks?:array<int,string>,parent?:string|null,preview?:array{thumbnail?:string,cover?:string},supports?:array<string,bool>,demo?:array{content_path?:string,settings_path?:string}}  $payload
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
            parent: $payload['parent'] ?? null,
            preview: $payload['preview'] ?? [],
            supports: $payload['supports'] ?? [],
            demo: $payload['demo'] ?? [],
        );
    }
}
