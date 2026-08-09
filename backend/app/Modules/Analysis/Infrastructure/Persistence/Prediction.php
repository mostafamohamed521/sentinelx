<?php

namespace App\Modules\Analysis\Infrastructure\Persistence;

use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Domain\Verdict;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Database\Factories\PredictionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prediction extends Model
{
    /** @use HasFactory<PredictionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'observation_id',
        'verdict',
        'confidence',
        'risk_score',
        'summary',
        'model_version',
        'prediction_json',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'verdict' => Verdict::class,
            'confidence' => 'decimal:2',
            'risk_score' => 'integer',
            'prediction_json' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    // ======= Relationships =======

    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    public function alert(): HasOne
    {
        return $this->hasOne(Alert::class);
    }
}
