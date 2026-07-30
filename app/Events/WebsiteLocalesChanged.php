<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteLocalesChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly string $websiteKey) {}
}
