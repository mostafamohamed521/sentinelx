<?php

namespace App\Modules\Authentication\Identity\Infrastructure\Persistence;

use App\Modules\Authentication\Identity\Domain\UserRole;
use App\Modules\Authentication\Identity\Domain\UserStatus;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'organization_id',
        'full_name',
        'email',
        'password_hash',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $attributes = [
        'status' => UserStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Users authenticate with password_hash — this platform has no
     * `password` column (see naming-conventions.md).
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * The JWT `sub` claim — the Identity ID (see contracts/jwt-claims.md).
     */
    public function getJWTIdentifier(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Custom JWT claims, restricted to exactly what contracts/jwt-claims.md
     * allows: identity_type and organization_id. Role, name, and email are
     * never embedded — see adr/ADR-003-jwt-claims.md.
     *
     * @return array<string, string>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'identity_type' => 'HUMAN',
            'organization_id' => (string) $this->organization_id,
        ];
    }

    // ======= Relationships =======

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
