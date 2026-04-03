<?php

namespace App\Models;

use App\Models\AdminRoleScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'key', 'description'])]
class Role extends Model
{
    use HasFactory;

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function adminScopes(): HasMany
    {
        return $this->hasMany(AdminRoleScope::class);
    }
}
