<?php

namespace App\Core\Modules;

readonly class ModuleManifest
{
    /**
     * @param  array<int, string>  $dependencies
     * @param  array<int, string>  $websiteTypes
     * @param  array<int, string>  $permissions
    * @param  array<int, string>  $hooks
     * @param  array<int, array<string, mixed>>  $menus
     * @param  array<int, array<string, mixed>>  $changelog
     * @param  array<string, mixed>  $package
     * @param  array<string, bool>  $lifecycle
     */
    public function __construct(
        public string $name,
        public string $key,
        public string $version,
        public string $description,
        public array $websiteTypes = [],
        public array $dependencies = [],
        public array $permissions = [],
        public array $hooks = [],
        public array $menus = [],
        public array $changelog = [],
        public array $package = [],
        public array $lifecycle = [],
    ) {
    }

    /**
     * @param  array{name:string,key:string,version:string,description?:string,website_type?:array<int,string>,dependencies?:array<int,string>,permissions?:array<int,string>,hooks?:array<int,string>,menus?:array<int,array<string,mixed>>,changelog?:array<int,array<string,mixed>>,package?:array<string,mixed>,lifecycle?:array<string,bool>}  $payload
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
            hooks: $payload['hooks'] ?? [],
            menus: $payload['menus'] ?? [],
            changelog: $payload['changelog'] ?? [],
            package: $payload['package'] ?? [],
            lifecycle: $payload['lifecycle'] ?? [],
        );
    }
}
