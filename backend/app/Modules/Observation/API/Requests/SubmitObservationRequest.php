<?php

namespace App\Modules\Observation\API\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately carries no `rules()`. 04-validation.md §4 explicitly
 * rejects splitting the five structural checks between a FormRequest and
 * the Domain-layer ObservationValidator — "keeps the full definition of
 * 'valid ASES' in exactly one place." All five checks live in
 * ObservationValidator; this class exists only to give the API layer a
 * typed request object, consistent with every other module's Controllers.
 */
class SubmitObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
