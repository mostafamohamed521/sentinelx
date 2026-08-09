// Single source of truth for the app's navigable routes.
// Sidebar, breadcrumbs, and route definitions all read from this file
// instead of hard-coding paths/labels in multiple places.

import { LayoutDashboard, Bot, Activity, AlertTriangle, Settings, ScrollText } from "lucide-react";

export const routes = {
  dashboard: { path: "/dashboard", label: "Dashboard", icon: LayoutDashboard, inNav: true },
  agents: { path: "/agents", label: "Agents", icon: Bot, inNav: true },
  agentDetails: { path: "/agents/:agentId", label: "Agent Details", parent: "agents", inNav: false },
  observations: { path: "/observations", label: "Observations", icon: Activity, inNav: true },
  observationDetails: { path: "/observations/:observationId", label: "Observation Details", parent: "observations", inNav: false },
  alerts: { path: "/alerts", label: "Alerts", icon: AlertTriangle, inNav: true },
  alertDetails: { path: "/alerts/:alertId", label: "Alert Details", parent: "alerts", inNav: false },
  // GET /v1/audit-logs and GET /v1/security-logs are Owner/Admin only
  // server-side (403 for Member) — gated here so Member never sees the nav
  // entry in the first place.
  auditLogs: { path: "/audit-logs", label: "Audit Logs", icon: ScrollText, inNav: true, roles: ["OWNER", "ADMIN"] },
  settings: { path: "/settings", label: "Settings", icon: Settings, inNav: true },
};

export const navItems = Object.values(routes).filter((r) => r.inNav);

// Given a real pathname like "/agents/agt_789", find the matching route
// definition (handles both exact and :param routes) plus its parent, so
// breadcrumbs and page titles can be derived automatically.
export function matchRoute(pathname) {
  const segments = pathname.split("/").filter(Boolean);

  for (const route of Object.values(routes)) {
    const routeSegments = route.path.split("/").filter(Boolean);
    if (routeSegments.length !== segments.length) continue;

    const isMatch = routeSegments.every(
      (seg, i) => seg.startsWith(":") || seg === segments[i]
    );
    if (isMatch) {
      const parent = route.parent ? routes[route.parent] : null;
      return { route, parent };
    }
  }
  return { route: null, parent: null };
}
