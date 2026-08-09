<?php

namespace App\Modules\Agent\Infrastructure\Persistence;

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Database\Factories\AgentFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model implements Authenticatable
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'framework',
        'framework_version',
        'description',
        'status',
        'last_seen_at',
    ];

    protected $attributes = [
        'status' => AgentStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    // ======= Relationships =======

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    // ======= Authenticatable =======

    /**
     * An Agent has no password or remember-token concept — its Credential
     * is the API Key, hashed and verified entirely inside the
     * "agent-api-key" guard (see Authentication\AuthServiceProvider::boot()),
     * never through these framework-contract methods. They exist only so
     * Agent can be returned from Auth::viaRequest() as a valid
     * Authenticatable — see 05-api-keys.md.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
