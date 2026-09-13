<?php

namespace Modules\FnbPos\Models;

final class FnbTerminal extends FnbModel
{
    protected $table = 'fnb_terminals';

    protected $hidden = ['device_uid'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'last_seen_at' => 'datetime', 'version' => 'integer'];
    }
}
