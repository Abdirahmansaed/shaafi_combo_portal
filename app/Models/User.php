<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** The portal database is deliberately separate from live business data. */
    protected $connection = 'mysql_portal';

    protected $table = 'users';

    /** The portal users schema deliberately has no created_at/updated_at fields. */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstName',
        'last_name',
        'number',
        'status',
        'username',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    public function displayName(): string
    {
        return trim(implode(' ', array_filter([$this->firstName, $this->last_name]))) ?: $this->username;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'SUPERADMIN';
    }

    public function roleLabel(): string
    {
        return $this->isSuperAdmin() ? 'Super Admin' : 'Agent';
    }
}
