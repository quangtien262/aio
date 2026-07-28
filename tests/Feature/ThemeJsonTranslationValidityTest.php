<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use JsonException;
use Tests\TestCase;

class ThemeJsonTranslationValidityTest extends TestCase
{
    public function test_all_theme_json_translation_files_are_valid_utf8_json_objects(): void
    {
        $errors = [];

        foreach (File::glob(base_path('themes/*/lang/*.json')) as $path) {
            $contents = (string) File::get($path);
            $relative = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));

            if (! mb_check_encoding($contents, 'UTF-8')) {
                $errors[] = $relative.' is not valid UTF-8';
                continue;
            }

            if (str_starts_with($contents, "\xEF\xBB\xBF")) {
                $errors[] = $relative.' contains a UTF-8 BOM';
                continue;
            }

            try {
                $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $errors[] = $relative.': '.$exception->getMessage();
                continue;
            }

            if (! is_array($decoded) || array_is_list($decoded)) {
                $errors[] = $relative.' must contain a JSON object';
            }
        }

        $this->assertSame([], $errors, "Invalid theme translation files:\n".implode("\n", $errors));
    }
}
