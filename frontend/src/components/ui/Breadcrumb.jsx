import React from "react";
import { Link, useLocation } from "react-router-dom";
import { ChevronRight } from "lucide-react";
import { matchRoute } from "../../lib/routes.js";

export default function Breadcrumb() {
  const location = useLocation();
  const { route, parent } = matchRoute(location.pathname);

  if (!route) return null;

  return (
    <nav className="mb-2 flex items-center gap-1.5 text-xs text-white/50">
      {parent && (
        <>
          <Link to={parent.path} className="transition hover:text-white/60">
            {parent.label}
          </Link>
          <ChevronRight className="h-3 w-3" />
        </>
      )}
      <span className="text-white/55">{route.label}</span>
    </nav>
  );
}
