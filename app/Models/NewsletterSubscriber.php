<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'email',
    'name',
    'phone',
    'source',
    'subscribed_at',
    'metadata',
])]
class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}