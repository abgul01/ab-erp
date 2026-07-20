<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_menu_permissions extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'user_menu_permissions';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'menu_id',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
        'can_download',
        'can_import'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function menu()
    {
        return $this->belongsTo(menus::class, 'menu_id', 'id');
    }
}
