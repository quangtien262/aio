<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class RouteCentralizationTest extends TestCase
{
    public function test_react_admin_api_urls_are_kept_in_the_shared_route_config(): void
    {
        $violations = $this->filesContaining(
            resource_path('admin/src'),
            ['/admin/api/'],
            ['js', 'jsx'],
        );

        $this->assertSame([], $violations, $this->formatViolations(
            'React must build admin API URLs through shared/config/routes.js.',
            $violations,
        ));
    }

    public function test_blade_does_not_hardcode_admin_application_urls(): void
    {
        $violations = array_merge(
            $this->filesContaining(resource_path('views'), ['/admin/api/', '/admin/cms/'], ['php']),
            $this->filesContaining(base_path('themes'), ['/admin/api/', '/admin/cms/'], ['php']),
        );

        $this->assertSame([], $violations, $this->formatViolations(
            'Blade must generate admin URLs with named routes.',
            $violations,
        ));
    }

    /**
     * @param  list<string>  $needles
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function filesContaining(string $directory, array $needles, array $extensions): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($needles as $needle) {
                if (is_string($contents) && str_contains($contents, $needle)) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                    break;
                }
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }

    /**
     * @param  list<string>  $violations
     */
    private function formatViolations(string $message, array $violations): string
    {
        return $message.($violations === [] ? '' : PHP_EOL.implode(PHP_EOL, $violations));
    }
}
