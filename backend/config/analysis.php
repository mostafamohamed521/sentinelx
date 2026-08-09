<?php

// PERF-002: the Poller's global throughput ceiling was previously a bare
// `--limit=10` CLI default with no environment override and no documented
// capacity assumption anywhere — see
// docs/integration-audit/06-performance-assumptions-audit.md and
// docs/05-analysis/03-processing-pipeline.md §"Capacity". This is a
// platform-wide, global limit (not per-Organization) — see the same
// section for why per-Organization fairness was deliberately deferred.

return [

    'poll_batch_limit' => env('ANALYSIS_POLL_BATCH_LIMIT', 10),

];
