<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'sku', 'price', 'stock', 'website_key', 'owner_key', 'tenant_key'])]
class CatalogProduct extends Model
{
    use HasFactory;

    protected $table = 'catalog_products';
}
