<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['landing_page_id', 'locale', 'title', 'excerpt', 'meta_title', 'meta_description'])]
class LandingPageData extends Model
{
    use HasFactory;

    protected $table = 'landing_page_data';

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
