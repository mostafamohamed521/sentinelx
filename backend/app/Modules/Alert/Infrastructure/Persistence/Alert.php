<?php

namespace App\Modules\Alert\Infrastructure\Persistence;

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'prediction_id',
        'severity',
        'status',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
    ];

    protected $attributes = [
        'status' => AlertStatus::Open->value,
    ];

    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
            'status' => AlertStatus::class,
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // ======= Relationships =======

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
