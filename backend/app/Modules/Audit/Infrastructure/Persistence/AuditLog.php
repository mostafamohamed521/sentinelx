<?php

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Domain\ActorType;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Write-once: an audit_logs row is never updated or deleted, by anyone,
 * ever — see 02-domain.md §4, invariant 1. There is deliberately no
 * update()/delete() method anywhere in this module's Repository.
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
        'created_at',
    ];

    protected $attributes = [
        'actor_type' => ActorType::User->value,
    ];

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $auditLog) {
            $auditLog->created_at ??= now();
        });
    }

    // ======= Relationships =======

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
