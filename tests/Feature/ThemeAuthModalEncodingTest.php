<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ThemeAuthModalEncodingTest extends TestCase
{
    public function test_all_theme_auth_modals_are_valid_utf8_without_mojibake(): void
    {
        $themeRoot = base_path('themes');
        $badFiles = [];
        $checkedFiles = 0;
        $mojibakeMarkers = [
            'TÃ', 'khoÃ', 'nhÃ', 'Ãƒ', 'Ã‚', 'Ä', 'Ä‘',
            'áº', 'á»', 'â€', 'Æ°', 'Æ¡',
        ];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getFilename() !== 'auth-modal.blade.php') {
                continue;
            }

            $checkedFiles++;
            $contents = file_get_contents($file->getPathname());
            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

            if (! mb_check_encoding($contents, 'UTF-8')) {
                $badFiles[] = $relativePath.' (không phải UTF-8 hợp lệ)';
                continue;
            }

            foreach ($mojibakeMarkers as $marker) {
                if (str_contains($contents, $marker)) {
                    $badFiles[] = $relativePath.' (chứa dấu hiệu '.$marker.')';
                    break;
                }
            }
        }

        $this->assertGreaterThan(0, $checkedFiles, 'Không tìm thấy modal đăng nhập theme để kiểm tra.');
        $this->assertSame([], $badFiles, "Phát hiện modal đăng nhập lỗi encoding:\n".implode("\n", $badFiles));
    }
}
