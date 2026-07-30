<?php

use App\Enums\TranslationStatus;
use App\Support\Localization\TranslationRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_page_translations')) {
            Schema::create('cms_page_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
                $table->string('website_key');
                $table->string('locale', 35);
                $table->string('title');
                $table->string('slug');
                $table->text('excerpt')->nullable();
                $table->longText('body')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->text('meta_keywords')->nullable();
                $table->string('translation_status', 40)->default(TranslationStatus::Missing->value);
                $table->string('source_revision', 64)->nullable();
                $table->string('translation_revision', 64)->nullable();
                $table->boolean('is_machine_translated')->default(false);
                $table->json('translation_meta')->nullable();
                $table->timestamp('translated_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('translation_published_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['cms_page_id', 'locale'],
                    'cms_page_translations_page_locale_unique',
                );
                $table->unique(
                    ['website_key', 'locale', 'slug'],
                    'cms_page_translations_website_locale_slug_unique',
                );
                $table->index(
                    ['website_key', 'locale', 'translation_status'],
                    'cms_page_translations_public_lookup_idx',
                );
            });
        }

        $this->backfillSourceTranslations();
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_translations');
    }

    private function backfillSourceTranslations(): void
    {
        if (! Schema::hasTable('cms_pages') || ! Schema::hasTable('cms_page_translations')) {
            return;
        }

        $sourceLocale = (string) config('localization.source_locale', 'vi');

        DB::table('cms_pages')
            ->orderBy('id')
            ->eachById(function (object $page) use ($sourceLocale): void {
                $payload = [
                    'title' => (string) $page->title,
                    'slug' => (string) $page->slug,
                    'excerpt' => $page->excerpt,
                    'body' => $page->body,
                    'meta_title' => $page->meta_title,
                    'meta_description' => $page->meta_description,
                    'meta_keywords' => $page->meta_keywords ?? null,
                ];
                $isPublished = (string) $page->status === 'published';
                $timestamp = $page->updated_at ?? now();

                DB::table('cms_page_translations')->updateOrInsert(
                    [
                        'cms_page_id' => $page->id,
                        'locale' => $sourceLocale,
                    ],
                    [
                        'website_key' => $page->website_key ?: 'website-main',
                        ...$payload,
                        'translation_status' => $isPublished
                            ? TranslationStatus::Published->value
                            : TranslationStatus::Draft->value,
                        'source_revision' => TranslationRevision::fingerprint($payload),
                        'translation_revision' => TranslationRevision::fingerprint($payload),
                        'is_machine_translated' => false,
                        'translation_meta' => json_encode([
                            'migration' => 'cms_page_translations_v1',
                            'legacy_status' => $page->status,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'translated_at' => $timestamp,
                        'reviewed_at' => $isPublished ? $timestamp : null,
                        'translation_published_at' => $isPublished
                            ? ($page->publish_at ?? $timestamp)
                            : null,
                        'created_at' => $page->created_at ?? $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
            });
    }
};
