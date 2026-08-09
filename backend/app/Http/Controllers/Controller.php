<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    // PERF-003: every collection endpoint's per_page had a default but no
    // maximum, letting a caller request an unbounded page size — one shared
    // helper so all six call sites enforce the same ceiling identically,
    // rather than six independent copies of the same clamp. See
    // docs/09-api-reference/08-PAGINATION.md.
    protected const MAX_PER_PAGE = 100;

    protected function perPage(Request $request, int $default = 20): int
    {
        return min((int) $request->integer('per_page', $default), self::MAX_PER_PAGE);
    }
}
