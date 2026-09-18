<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_key', 'website_key', 'resource_type', 'external_id', 'resource_id', 'payload_hash', 'last_token_id'])]
class ContentApiResourceLink extends Model
{
    // Generic integration identity; resource_id intentionally has no foreign key.
}
