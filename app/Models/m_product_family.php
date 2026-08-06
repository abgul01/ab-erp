<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Keluarga produk: pengelompokan komersial beberapa part sejenis. */
class m_product_family extends Model
{
    protected $connection = 'mysql';

    protected $table = 'm_product_family';

    protected $fillable = ['code', 'name', 'descrip', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(m_item::class, 'family_id', 'id');
    }
}
