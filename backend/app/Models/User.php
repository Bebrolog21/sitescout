<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'password_change_required',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_change_required' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAnalyst(): bool
    {
        return $this->role === 'analyst';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Может ли пользователь создавать/редактировать контент площадки
     * (сама площадка, чеклист, риски, финмодель, визиты, файлы, ЖК).
     */
    public function canEditSiteContent(): bool
    {
        return in_array($this->role, ['admin', 'analyst'], true);
    }

    /**
     * Может ли пользователь переводить площадку между статусами.
     */
    public function canChangeSiteStatus(): bool
    {
        return in_array($this->role, ['admin', 'manager'], true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
