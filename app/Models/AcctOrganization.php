<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'tax_code',
    'legal_name',
    'email',
    'phone',
    'address',
    'default_currency',
    'settings',
    'is_default',
    'default_slot',
    'status',
])]
class AcctOrganization extends Model
{
    use HasFactory;

    protected $table = 'acct_organizations';

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AcctItem::class, 'organization_id');
    }

    public function websites(): HasMany
    {
        return $this->hasMany(AcctOrganizationWebsite::class, 'organization_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(AcctParty::class, 'organization_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AcctDocument::class, 'organization_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $organization): void {
            $organization->default_slot = $organization->is_default ? 'default' : null;
        });
    }
}
