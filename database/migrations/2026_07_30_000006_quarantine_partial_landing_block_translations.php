<?php

use App\Enums\TranslationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('landing_page_block_data')
            || ! str_starts_with(
                strtolower((string) config('localization.source_locale', 'vi')),
                'vi',
            )
        ) {
            return;
        }

        $sourceLocale = (string) config('localization.source_locale', 'vi');

        DB::table('landing_page_block_data')
            ->where('locale', '!=', $sourceLocale)
            ->where('translation_status', TranslationStatus::Published->value)
            ->orderBy('id')
            ->chunkById(200, function ($translations): void {
                foreach ($translations as $translation) {
                    if (! $this->containsVietnameseText($translation)) {
                        continue;
                    }

                    $metadata = json_decode(
                        (string) ($translation->translation_meta ?? '{}'),
                        true,
                    );
                    $metadata = is_array($metadata) ? $metadata : [];
                    $metadata['requires_human_translation'] = true;
                    $metadata['quality_gate'] = 'source_language_detected';
                    $metadata['migration'] = 'landing_block_partial_translation_quarantine_v1';

                    DB::table('landing_page_block_data')
                        ->where('id', $translation->id)
                        ->update([
                            'translation_status' => TranslationStatus::NeedsTranslation->value,
                            'translation_meta' => json_encode(
                                $metadata,
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ),
                            'reviewed_at' => null,
                            'translation_published_at' => null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Quality quarantine is intentionally non-destructive and is not reversed.
    }

    private function containsVietnameseText(object $translation): bool
    {
        $text = implode(' ', [
            (string) ($translation->title ?? ''),
            (string) ($translation->subtitle ?? ''),
            (string) ($translation->description ?? ''),
            (string) ($translation->button_label ?? ''),
            (string) ($translation->content ?? ''),
        ]);

        return preg_match(
            '/[\x{0103}\x{0102}\x{00E2}\x{00C2}\x{0111}\x{0110}\x{00EA}\x{00CA}'
            .'\x{00F4}\x{00D4}\x{01A1}\x{01A0}\x{01B0}\x{01AF}'
            .'\x{00C0}\x{00C1}\x{00C3}\x{00C8}\x{00C9}\x{00CC}\x{00CD}'
            .'\x{00D2}\x{00D3}\x{00D5}\x{00D9}\x{00DA}\x{00DD}'
            .'\x{00E0}\x{00E1}\x{00E3}\x{00E8}\x{00E9}\x{00EC}\x{00ED}'
            .'\x{00F2}\x{00F3}\x{00F5}\x{00F9}\x{00FA}\x{00FD}'
            .'\x{1EA0}-\x{1EF9}]/u',
            $text,
        ) === 1;
    }
};
