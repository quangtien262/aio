<?php

namespace App\Console\Commands;

use App\Models\ContentApiToken;
use Illuminate\Console\Command;

class RevokeContentApiTokenCommand extends Command
{
    protected $signature = 'content-api:revoke-token {id : ID token cần thu hồi}';

    protected $description = 'Thu hồi content API token';

    public function handle(): int
    {
        $token = ContentApiToken::query()->find($this->argument('id'));
        if ($token === null) {
            $this->error('Không tìm thấy token.');

            return self::FAILURE;
        }

        $token->update(['revoked_at' => now()]);
        $this->info('Đã thu hồi token #'.$token->id.' ('.$token->name.').');

        return self::SUCCESS;
    }
}
