<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class menus extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'menus';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'name',
        'link',
        'parent_id',
        'icon'
    ];

    public function children()
    {
        return $this->hasMany(menus::class, 'parent_id', 'id')->orderBy('id');
    }

    public function parent()
    {
        return $this->belongsTo(menus::class, 'parent_id', 'id');
    }
}
