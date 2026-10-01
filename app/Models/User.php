<?php

namespace App\Models;

use App\Notifications\LienMotDePasse;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_GERANT = 'gerant';

    public const ROLE_COMMERCIAL = 'commercial';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invitation_sha256',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'preferences' => 'array',
            'invitation_expire_at' => 'datetime',
            'desactive_at' => 'datetime',
        ];
    }

    public function invitationEnAttente(): bool
    {
        return $this->invitation_sha256 !== null;
    }

    public function preference(string $cle, mixed $defaut = null): mixed
    {
        return data_get($this->preferences, $cle, $defaut);
    }

    public function estGerant(): bool
    {
        return $this->role === self::ROLE_GERANT;
    }

    public function nomAffiche(): string
    {
        return $this->name ?: $this->email;
    }

    public function libelleRole(): string
    {
        return $this->estGerant() ? 'Gérant' : 'Commercial';
    }

    public function appareils(): HasMany
    {
        return $this->hasMany(Appareil::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeGerantsActifs(Builder $query): void
    {
        $query->where('role', self::ROLE_GERANT)->where('is_active', true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new LienMotDePasse($token));
    }
}
