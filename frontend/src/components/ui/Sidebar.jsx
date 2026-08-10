import React from "react";
import { NavLink, useNavigate } from "react-router-dom";
import { Settings, LogOut, X as CloseIcon } from "lucide-react";
import { useAuth } from "../../lib/AuthContext.jsx";
import { navItems } from "../../lib/routes.js";
import Logo from "./Logo.jsx";

export default function Sidebar({ open = false, onClose }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  // Audit/Security Logs are Owner/Admin only server-side (403 for Member) —
  // hidden from Member's nav rather than letting them click into a 403.
  const visibleNavItems = navItems.filter((item) => !item.roles || item.roles.includes(user?.role));

  async function handleLogout() {
    await logout();
    onClose?.();
    navigate("/login", { replace: true });
  }

  return (
    <>
      {/* backdrop, mobile only, shown while the drawer is open */}
      {open && (
        <div
          className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
          onClick={onClose}
          aria-hidden="true"
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-white/[0.06] bg-[#07080f]/95 backdrop-blur-xl transition-transform duration-300 lg:translate-x-0 ${
          open ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="flex items-center justify-between gap-2.5 px-6 py-6">
          <div className="flex items-center gap-2.5">
            <Logo size={32} />
            <span className="text-[15px] font-semibold tracking-tight text-white">
              Sentinel<span className="text-indigo-400">X</span>
            </span>
          </div>
          <button
            onClick={onClose}
            className="flex h-7 w-7 items-center justify-center rounded-md text-white/55 transition hover:bg-white/[0.06] hover:text-white lg:hidden"
            aria-label="Close navigation"
          >
            <CloseIcon className="h-4 w-4" />
          </button>
        </div>

        <nav className="flex-1 space-y-1 px-3">
          {visibleNavItems.filter((item) => item.path !== "/settings").map(({ path, label, icon: Icon }) => (
            <NavLink
              key={path}
              to={path}
              onClick={onClose}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium transition ${
                  isActive
                    ? "bg-white/[0.07] text-white"
                    : "text-white/60 hover:bg-white/[0.04] hover:text-white/80"
                }`
              }
            >
              <Icon className="h-4 w-4" />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-white/[0.06] px-3 py-4">
          {user && (
            <div className="mb-2 flex items-center gap-2.5 rounded-lg px-3.5 py-2">
              <div className="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-500/15 text-xs font-semibold text-indigo-300">
                {user.full_name?.[0]?.toUpperCase() || "?"}
              </div>
              <div className="min-w-0">
                <div className="truncate text-xs font-medium text-white/80">{user.full_name}</div>
                <div className="truncate text-[10px] text-white/45">{user.email}</div>
              </div>
            </div>
          )}
          <NavLink
            to="/settings"
            onClick={onClose}
            className="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-white/60 transition hover:bg-white/[0.04] hover:text-white/80"
          >
            <Settings className="h-4 w-4" /> Settings
          </NavLink>
          <button
            onClick={handleLogout}
            className="flex w-full items-center gap-3 rounded-lg px-3.5 py-2.5 text-left text-sm font-medium text-white/60 transition hover:bg-white/[0.04] hover:text-white/80"
          >
            <LogOut className="h-4 w-4" /> Log out
          </button>
        </div>
      </aside>
    </>
  );
}
