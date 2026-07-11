<?php

namespace App\Core\Themes\Demo;

interface ThemeDemoContentProvider
{
    public function themeKey(): string;

    public function defaultPreset(): string;

    /** @return array{key:string,label:string,description:string} */
    public function preset(): array;

    /** @return array<string,mixed> */
    public function generate(string $presetKey): array;

    /** @return array<string,int> */
    public function delete(): array;
}
