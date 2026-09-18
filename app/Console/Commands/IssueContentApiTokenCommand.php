<?php

namespace App\Console\Commands;

use App\Models\ContentApiToken;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IssueContentApiTokenCommand extends Command
{
    protected $signature = 'content-api:issue-token
        {name : Tên nhận diện token}
        {--website=website-main : Website được phép ghi dữ liệu}
        {--source=tech-content : Mã nguồn tích hợp dùng cho external_id}
        {--abilities=posts.read,posts.write,posts.publish,media.write : Danh sách quyền, phân tách bằng dấu phẩy}
        {--expires= : Ngày hết hạn theo định dạng được PHP hỗ trợ}
        {--output= : Ghi cấu hình publisher vào file này}
        {--base-url=http://127.0.0.1:8000 : URL dùng khi ghi file cấu hình}';

    protected $description = 'Tạo API token để tích hợp công cụ xuất bản nội dung';

    public function handle(): int
    {
        $websiteKey = trim((string) $this->option('website'));
        $siteExists = Site::query()->where('website_key', $websiteKey)->where('status', 'active')->exists();

        if (! $siteExists) {
            $this->error('Website không tồn tại hoặc chưa active: '.$websiteKey);

            return self::FAILURE;
        }

        $abilities = collect(explode(',', (string) $this->option('abilities')))
            ->map(fn (string $ability): string => trim($ability))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allowed = ['*', 'posts.read', 'posts.write', 'posts.publish', 'media.write'];
        $invalid = array_values(array_diff($abilities, $allowed));
        if ($abilities === [] || $invalid !== []) {
            $this->error('Abilities không hợp lệ: '.implode(', ', $invalid));

            return self::FAILURE;
        }

        $rawToken = 'ctk_'.Str::random(64);
        $expiresAt = filled($this->option('expires')) ? Carbon::parse((string) $this->option('expires')) : null;
        $token = ContentApiToken::query()->create([
            'name' => trim((string) $this->argument('name')),
            'source_key' => Str::slug((string) $this->option('source')) ?: 'tech-content',
            'website_key' => $websiteKey,
            'token_hash' => hash('sha256', $rawToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        $this->info('Đã tạo content API token #'.$token->id.'. Token chỉ hiển thị một lần:');
        $this->newLine();
        $this->line($rawToken);
        $this->newLine();
        $this->table(['Website', 'Source', 'Abilities', 'Hết hạn'], [[
            $token->website_key,
            $token->source_key,
            implode(', ', $abilities),
            $token->expires_at?->toDateTimeString() ?? 'Không',
        ]]);

        if (filled($this->option('output'))) {
            $output = (string) $this->option('output');
            File::ensureDirectoryExists(dirname($output));
            File::put($output, implode(PHP_EOL, [
                'CONTENT_API_BASE_URL='.rtrim((string) $this->option('base-url'), '/'),
                'CONTENT_API_TOKEN='.$rawToken,
                'CONTENT_API_DEFAULT_STATUS=published',
                '',
            ]));
            $this->info('Đã ghi cấu hình publisher: '.$output);
        }

        return self::SUCCESS;
    }
}
