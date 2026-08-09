// Organization API — matches the provided endpoint contract exactly:
//   GET   /v1/organization
//   PATCH /v1/organization
//
// GET is available to any authenticated role (Owner/Admin/Member); PATCH
// (name only — slug/status are `prohibited`, rejected with 422 rather than
// silently stripped) is Owner only.

import { apiFetch, MOCK_MODE, ApiError } from "../apiClient.js";
import { companyInfo } from "../mockDb.js";

function delay(ms = 350) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export async function getOrganization() {
  if (MOCK_MODE) {
    await delay();
    return {
      id: companyInfo.id,
      name: companyInfo.name,
      slug: companyInfo.name.toLowerCase().replace(/\s+/g, "-"),
      status: "ACTIVE",
      created_at: "2026-06-01T09:00:00Z",
      updated_at: "2026-06-01T09:00:00Z",
    };
  }
  const res = await apiFetch("/organization");
  return res.data;
}

export async function updateOrganization({ name }) {
  if (MOCK_MODE) {
    await delay();
    if (!name) throw new ApiError("VALIDATION_ERROR", "Name is required");
    companyInfo.name = name;
    return {
      id: companyInfo.id,
      name,
      slug: name.toLowerCase().replace(/\s+/g, "-"),
      status: "ACTIVE",
      created_at: "2026-06-01T09:00:00Z",
      updated_at: new Date().toISOString(),
    };
  }
  const res = await apiFetch("/organization", { method: "PATCH", body: { name } });
  return res.data;
}
