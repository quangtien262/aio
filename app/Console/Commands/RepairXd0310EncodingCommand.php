<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\LegacyTextEncoding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class RepairXd0310EncodingCommand extends Command
{
    protected $signature = 'themes:repair-xd0310-encoding {domain} {--write : Lưu thay đổi sau khi sao lưu}';
    protected $aliases = ['themes:repair-encoding'];

    protected $description = 'Sửa tiếng Việt mã hóa sai trong dữ liệu XD0310/XD0307; mặc định chỉ xem trước.';

    public function handle(LegacyTextEncoding $repair): int
    {
        $site = Site::query()->where('domain', $this->argument('domain'))->first();
        if (! $site || ! in_array($site->theme_key, ['XD0310', 'XD0307'], true)) {
            $this->error('Domain không tồn tại hoặc không dùng theme XD0310/XD0307.');
            return self::FAILURE;
        }

        $fields = [
            'site_profiles' => ['site_name', 'description', 'branding'],
            'site_theme_profiles' => ['branding'],
            'cms_menus' => ['name', 'items'],
            'site_banners' => ['title', 'subtitle', 'badge', 'metadata'],
            'cms_services' => ['title', 'summary', 'content', 'button_label'],
            'cms_projects' => ['title', 'summary', 'content', 'button_label'],
            'cms_partners' => ['title', 'description', 'image_alt'],
            'cms_posts' => ['title', 'excerpt', 'body', 'meta_title', 'meta_description'],
            'cms_categories' => ['name', 'description'],
            'cms_team_members' => ['name', 'role', 'department', 'summary', 'bio'],
            'cms_testimonials' => ['name', 'role', 'company', 'quote'],
            'content_translations' => ['payload'],
            'landing_page_data' => ['title', 'excerpt', 'meta_title', 'meta_description'],
            'landing_page_block_data' => ['title', 'subtitle', 'description', 'button_label', 'content'],
        ];
        $jsonFields = ['branding', 'items', 'metadata', 'payload', 'content'];
        $changes = [];
        DB::transaction(function () use ($site, $fields, $jsonFields, $repair, &$changes): void {
            $pages = DB::table('landing_pages')->where('website_key', $site->website_key)->pluck('id');
            $blocks = DB::table('landing_page_blocks')->whereIn('landing_page_id', $pages)->pluck('id');
            foreach ($fields as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $columns = array_values(array_intersect($columns, Schema::getColumnListing($table)));
                $query = DB::table($table);
                if ($table === 'landing_page_data') {
                    $query->whereIn('landing_page_id', $pages);
                } elseif ($table === 'landing_page_block_data') {
                    $query->whereIn('landing_page_block_id', $blocks);
                } else {
                    $query->where('website_key', $site->website_key);
                }
                if (in_array($table, ['site_theme_profiles', 'site_banners'], true)) {
                    $query->where('theme_key', $site->theme_key);
                }
                foreach ($query->lockForUpdate()->get() as $row) {
                    $before = $after = [];
                    foreach ($columns as $column) {
                        $old = $row->$column;
                        if (! is_string($old)) {
                            continue;
                        }
                        $decoded = in_array($column, $jsonFields, true) ? json_decode($old, true) : null;
                        $new = is_array($decoded) ? $repair->value($decoded) : $repair->repair($old);
                        if (is_array($decoded)) {
                            $new = $new === $decoded ? $old : json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                        }
                        if ($old !== $new) {
                            $before[$column] = $old;
                            $after[$column] = $new;
                        }
                    }
                    if ($after !== []) {
                        $changes[] = ['table' => $table, 'id' => $row->id, 'before' => $before, 'after' => $after];
                        $this->line($table.' #'.$row->id.': '.implode(', ', array_keys($after)));
                    }
                }
            }
            if ($this->option('write') && $changes !== []) {
                $directory = storage_path('app/private/encoding-backups');
                File::ensureDirectoryExists($directory);
                $path = $directory.'/'.strtolower($site->theme_key).'-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
                if (File::put($path, json_encode(['domain' => $site->domain, 'website_key' => $site->website_key, 'changes' => $changes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                    throw new \RuntimeException('Không thể tạo bản sao lưu. Chưa cập nhật dữ liệu.');
                }
                $this->info('Bản sao lưu: '.$path);
                foreach ($changes as $change) {
                    DB::table($change['table'])->where('id', $change['id'])->update($change['after']);
                }
            }
        });
        $this->info(count($changes).' bản ghi '.($this->option('write') ? 'đã sửa.' : 'cần sửa. Dùng --write để lưu.'));
        return self::SUCCESS;
    }
}
