<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['landing_page_block_id', 'locale', 'title', 'subtitle', 'description', 'button_label', 'content'])]
class LandingPageBlockData extends Model
{
    use HasFactory;

    protected $table = 'landing_page_block_data';

    public function landingPageBlock(): BelongsTo
    {
        return $this->belongsTo(LandingPageBlock::class);
    }
}
