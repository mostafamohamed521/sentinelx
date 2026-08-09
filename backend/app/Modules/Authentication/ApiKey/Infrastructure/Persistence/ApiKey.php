<?php

namespace App\Modules\Authentication\ApiKey\Infrastructure\Persistence;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'agent_id',
        'key_prefix',
        'key_hash',
        'status',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected $attributes = [
        'status' => ApiKeyStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => ApiKeyStatus::class,
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Enforce the "one ACTIVE key per Agent" business rule (see ADR-004).
     * The database allows multiple rows per Agent by design, to support
     * rotation — the application layer is responsible for the invariant.
     */
    protected static function booted(): void
    {
        static::saved(function (self $apiKey) {
            if ($apiKey->status !== ApiKeyStatus::Active) {
                return;
            }

            static::query()
                ->where('agent_id', $apiKey->agent_id)
                ->where('id', '!=', $apiKey->id)
                ->where('status', ApiKeyStatus::Active)
                ->update(['status' => ApiKeyStatus::Revoked]);
        });
    }

    // ======= Relationships =======

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
