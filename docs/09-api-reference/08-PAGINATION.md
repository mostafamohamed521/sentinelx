---
title: Pagination
category: API Reference
status: Approved
version: v1
---

# Pagination

## Overview

All collection endpoints return paginated results.

Examples include:

- Agents
- Observations
- Alerts

---

# Request Parameters

| Parameter | Description |
|-----------|-------------|
|page|Page number (starts at 1).|
|per_page|Number of items per page. Default 20, **maximum 100** — a value above 100 is silently clamped down to 100, not rejected (PERF-003; see `App\Http\Controllers\Controller::perPage()`, the one shared helper applied identically across all six collection endpoints).|

---

# Response Format

```json
{
  "data": [],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total_items": 245,
    "total_pages": 13
  }
}
```

---

# Design Principles

- Predictable response format.
- Consistent across all collection endpoints.
- Metadata separated from business data.