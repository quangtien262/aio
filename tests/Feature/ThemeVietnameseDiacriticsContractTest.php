<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ThemeVietnameseDiacriticsContractTest extends TestCase
{
    public function test_public_theme_copy_does_not_contain_common_unaccented_vietnamese_phrases(): void
    {
        $paths = [
            base_path('themes'),
            app_path('Core/Themes/Demo'),
            app_path('Support/LandingPages/LandingPageBuilder.php'),
        ];
        $pattern = '/\b(?:Trang chu|Gioi thieu|Dich vu|San pham|Du an|Tin tuc|Lien he|Chung toi|Khach hang|Dang cap nhat|Xem them|Xem chi tiet|Quy trinh|Doi tac|Van chuyen|Hang hoa|Tu van|Dia chi|So dien thoai|Kien tao|Giai phap|Kien thuc|Thi truong|Phan hoi|Nam kinh nghiem|Mang luoi|Vat tu|Dong goi|Ho tro|Bao hanh|Nhap thu cong|Nguon du lieu|Danh muc|Chi lay noi bat|So item hien thi)\b/iu';
        $violations = [];
        $checkedFiles = 0;

        foreach ($this->sourceFiles($paths) as $file) {
            $checkedFiles++;
            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $index => $line) {
                // These ASCII tokens are deliberate compatibility keys after labels are normalized.
                if (str_contains($line, 'in_array') || str_contains($line, '$normalizeLabel')) {
                    continue;
                }

                if (preg_match($pattern, $line, $matches) === 1) {
                    $relative = str_replace('\\', '/', substr($file, strlen(base_path()) + 1));
                    $violations[] = sprintf('%s:%d (%s)', $relative, $index + 1, $matches[0]);
                }
            }
        }

        $this->assertGreaterThan(0, $checkedFiles);
        $this->assertSame([], $violations, "Phát hiện nội dung tiếng Việt không dấu:\n".implode("\n", $violations));
    }

    /** @return array<int, string> */
    private function sourceFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'json'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
