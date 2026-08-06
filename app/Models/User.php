<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'name',
        'email',
        'identity',
        'password',
        'status_id',
        'ven_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function status()
    {
        return $this->belongsTo(status_id::class, 'status_id', 'id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(user_menu_permissions::class, 'user_id', 'id');
    }

    /** The supplier a portal account belongs to; null for staff. */
    public function ven()
    {
        return $this->belongsTo(m_contacts::class, 'ven_id', 'id');
    }

    /**
     * Portal accounts live entirely inside /api/v1/vendor and see only their own
     * supplier's documents. Staff accounts never have ven_id set.
     */
    public function isVendor(): bool
    {
        return $this->ven_id !== null;
    }

    /**
     * Whether the user is the super admin (username "admin" or status ADMIN).
     * Super admin bypasses granular permission checks.
     */
    public function isSuperAdmin(): bool
    {
        return $this->username === 'admin'
            || optional($this->status)->status === 'ADMIN';
    }

    /**
     * Check whether the user can perform $action on the menu identified by $link.
     * $action ∈ view|create|edit|delete|download|import
     */
    public function canDo(string $menuLink, string $action = 'view'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $column = 'can_'.$action;

        return $this->permissions()
            ->where('can_'.$action, 1)
            ->whereHas('menu', fn ($q) => $q->where('link', $menuLink))
            ->exists();
    }
}
