<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ThemeStorefrontEncodingRegressionTest extends TestCase
{
    public function test_recent_xd_theme_storefronts_are_utf8_without_mojibake(): void
    {
        $themeKeys = ['XD0307', 'XD0308', 'XD0309', 'XD0310', 'XD0318', 'XD0320'];
        $extensions = ['php', 'css', 'js', 'json'];
        $markers = [
            'TÃ', 'Ãƒ', 'Ã‚', 'Ã„', 'Ä', 'Ä‘',
            'áº', 'á»', 'â€', 'Æ°', 'Æ¡',
        ];
        $badFiles = [];
        $checkedFiles = 0;

        foreach ($themeKeys as $themeKey) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path('themes/'.$themeKey), RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), $extensions, true)) {
                    continue;
                }

                $checkedFiles++;
                $contents = file_get_contents($file->getPathname());
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

                if (! mb_check_encoding($contents, 'UTF-8')) {
                    $badFiles[] = $relativePath.' (không phải UTF-8 hợp lệ)';
                    continue;
                }

                foreach ($markers as $marker) {
                    if (str_contains($contents, $marker)) {
                        $badFiles[] = $relativePath.' (chứa dấu hiệu '.$marker.')';
                        break;
                    }
                }
            }
        }

        foreach ([
            app_path('Core/Themes/Demo/Xd0307DemoContentProvider.php'),
            app_path('Core/Themes/Demo/Xd0308DemoContentProvider.php'),
            app_path('Core/Themes/Demo/Xd0310DemoContentProvider.php'),
        ] as $providerPath) {
            $checkedFiles++;
            $contents = file_get_contents($providerPath);

            if (! mb_check_encoding($contents, 'UTF-8')) {
                $badFiles[] = str_replace('\\', '/', substr($providerPath, strlen(base_path()) + 1)).' (không phải UTF-8 hợp lệ)';
                continue;
            }

            foreach ($markers as $marker) {
                if (str_contains($contents, $marker)) {
                    $badFiles[] = str_replace('\\', '/', substr($providerPath, strlen(base_path()) + 1)).' (chứa dấu hiệu '.$marker.')';
                    break;
                }
            }
        }

        $this->assertGreaterThan(0, $checkedFiles);
        $this->assertSame([], $badFiles, "Phát hiện storefront lỗi encoding:\n".implode("\n", $badFiles));
    }

    public function test_xd0318_defaults_use_accented_vietnamese_and_xd0320_footer_is_responsive(): void
    {
        $builder = file_get_contents(app_path('Support/LandingPages/LandingPageBuilder.php'));
        $footerStyles = file_get_contents(base_path('themes/XD0320/views/partials/styles.blade.php'));

        $this->assertStringContainsString('Vận chuyển mọi lúc mọi nơi', $builder);
        $this->assertStringContainsString('Giải pháp logistics toàn cầu tốt nhất', $builder);
        $this->assertStringContainsString('Câu hỏi thường gặp', $builder);
        $this->assertStringContainsString('grid-template-columns:minmax(0,1.35fr)', $footerStyles);
        $this->assertStringContainsString('.foot-footer__grid>section{min-width:0}', $footerStyles);
        $this->assertStringContainsString('@media(max-width:560px)', $footerStyles);
    }
}
