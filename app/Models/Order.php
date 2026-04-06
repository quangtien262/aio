<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_code',
    'customer_id',
    'status',
    'customer_name',
    'customer_phone',
    'customer_email',
    'delivery_address',
    'note',
    'payment_method',
    'payment_label',
    'subtotal',
    'item_count',
    'placed_at',
    'email_queued_at',
    'email_sent_at',
    'sms_sent_at',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'placed_at' => 'datetime',
            'email_queued_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'sms_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }
}
