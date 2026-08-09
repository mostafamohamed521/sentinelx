<?php

namespace App\Modules\Observation\Infrastructure\Persistence;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Database\Factories\ObservationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observation extends Model
{
    /** @use HasFactory<ObservationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'agent_id',
        'analysis_status',
        'raw_ases_json',
        'received_at',
        'processing_started_at',
        'processed_at',
    ];

    protected $attributes = [
        'analysis_status' => AnalysisStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'analysis_status' => AnalysisStatus::class,
            'raw_ases_json' => 'array',
            'received_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    // ======= Relationships =======

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
