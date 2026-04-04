<?php

namespace App\Models;

use App\Models\CustomerFavorite;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(CustomerFavorite::class);
    }

    public function newsletterSubscriptions(): HasMany
    {
        return $this->hasMany(NewsletterSubscriber::class);
    }
}
