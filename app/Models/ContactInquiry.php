<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'website_key',
    'submitted_host',
    'customer_id',
    'order_id',
    'source',
    'status',
    'name',
    'email',
    'phone',
    'subject',
    'route_summary',
    'message',
    'page_url',
    'locale',
    'ip_address',
    'user_agent',
    'submitted_at',
])]
class ContactInquiry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
