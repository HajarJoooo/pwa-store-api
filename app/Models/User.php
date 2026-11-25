<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'phone',
        'bio',
        'avatar_url',
        'country',
        'city',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'full_name',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function pwas(): HasMany
    {
        return $this->hasMany(Pwa::class, 'developer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }

    public function installedPwas(): BelongsToMany
    {
        return $this->belongsToMany(Pwa::class, 'installations')
            ->withPivot('installed_at')
            ->withTimestamps();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoritePwas(): BelongsToMany
    {
        return $this->belongsToMany(Pwa::class, 'favorites')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
