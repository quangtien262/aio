<?php

namespace App\Core\Modules;

readonly class ModuleManifest
{
    /**
     * @param  array<int, string>  $dependencies
     * @param  array<int, string>  $websiteTypes
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public string $name,
        public string $key,
        public string $version,
        public string $description,
        public array $websiteTypes = [],
        public array $dependencies = [],
        public array $permissions = [],
    ) {
    }

    /**
     * @param  array{name:string,key:string,version:string,description?:string,website_type?:array<int,string>,dependencies?:array<int,string>,permissions?:array<int,string>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            key: $payload['key'],
            version: $payload['version'],
            description: $payload['description'] ?? '',
            websiteTypes: $payload['website_type'] ?? [],
            dependencies: $payload['dependencies'] ?? [],
            permissions: $payload['permissions'] ?? [],
        );
    }
}
