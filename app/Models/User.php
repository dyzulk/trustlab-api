<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_CUSTOMER = 'customer';

    /**
     * The channels the user receives notification broadcasts on.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'App.Models.User.' . $this->id;
    }

    /**
     * Get the API keys for the user.
     */
    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Get the login history for the user.
     */
    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class);
    }

    /**
     * Get the tickets for the user.
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = \App\Helpers\UuidHelper::generate();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'pending_email',
        'password',
        'avatar',
        'role',
        'phone',
        'bio',
        'job_title',
        'location',
        'country',
        'city_state',
        'postal_code',
        'tax_id',
        'settings_email_alerts',
        'settings_certificate_renewal',
        'default_landing_page',
        'theme',
        'language',
        'email_verified_at',
    ];

    /**
     * Get the social accounts for the user.
     */
    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Check if user is owner.
     */
    public function isOwner(): bool
    {
        return strtolower($this->role) === self::ROLE_OWNER;
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return strtolower($this->role) === self::ROLE_ADMIN;
    }

    /**
     * Check if user is admin or owner.
     */
    public function isAdminOrOwner(): bool
    {
        $role = strtolower($this->role);
        return in_array($role, [self::ROLE_OWNER, self::ROLE_ADMIN]);
    }

    /**
     * Get the avatar URL with cache busting timestamp.
     */
    public function getAvatarAttribute($value)
    {
        if (!$value) return $value;

        // If it's already a full R2 URL, append timestamp
        if (str_contains($value, 'cdn.trustlab.dyzulk.com')) {
            $separator = str_contains($value, '?') ? '&' : '?';
            return $value . $separator . 't=' . ($this->updated_at ? $this->updated_at->timestamp : time());
        }

        return $value;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings_email_alerts' => 'boolean',
            'settings_certificate_renewal' => 'boolean',
        ];
    }
    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }

    /**
     * Route notifications for the mail channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return array|string
     */
    public function routeNotificationForMail($notification)
    {
        if ($notification instanceof \App\Notifications\PendingEmailVerificationNotification) {
            return $this->pending_email;
        }

        return $this->email;
    }
}
