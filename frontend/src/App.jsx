import React, { useState } from "react";
import { Routes, Route, Navigate, useLocation } from "react-router-dom";

import { AuthProvider } from "./lib/AuthContext.jsx";
import AuthLayout from "./layouts/AuthLayout.jsx";
import AppLayout from "./layouts/AppLayout.jsx";
import ProtectedRoute from "./components/ui/ProtectedRoute.jsx";
import WelcomeIntro from "./components/ui/WelcomeIntro.jsx";

import Landing from "./pages/Landing.jsx";
import Login from "./pages/Login.jsx";
import Signup from "./pages/Signup.jsx";
import ForgotPassword from "./pages/ForgotPassword.jsx";
import ResetPassword from "./pages/ResetPassword.jsx";
import VerifyEmail from "./pages/VerifyEmail.jsx";
import Dashboard from "./pages/Dashboard.jsx";
import Agents from "./pages/Agents.jsx";
import AgentDetails from "./pages/AgentDetails.jsx";
import Observations from "./pages/Observations.jsx";
import ObservationDetails from "./pages/ObservationDetails.jsx";
import Alerts from "./pages/Alerts.jsx";
import AlertDetails from "./pages/AlertDetails.jsx";
import AuditLogs from "./pages/AuditLogs.jsx";
import Settings from "./pages/Settings.jsx";

const INTRO_KEY = "sentinelx_intro_seen";

export default function App() {
  const location = useLocation();
  const [showIntro, setShowIntro] = useState(
    () => typeof window !== "undefined" && !sessionStorage.getItem(INTRO_KEY)
  );

  // The shield's fly-to position is calibrated to the Landing page's nav bar,
  // so only ever play the intro when the user actually lands on "/" — playing
  // it on /login or /dashboard would send the shield to the wrong spot since
  // those layouts don't share the same header geometry.
  const shouldShowIntro = showIntro && location.pathname === "/";

  function handleIntroDone() {
    sessionStorage.setItem(INTRO_KEY, "1");
    setShowIntro(false);
  }

  return (
    <AuthProvider>
      {shouldShowIntro && <WelcomeIntro onDone={handleIntroDone} />}

      <Routes>
        {/* Public marketing page */}
        <Route path="/" element={<Landing />} />

        {/* Auth (public) */}
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<Login />} />
          <Route path="/signup" element={<Signup />} />
          <Route path="/forgot-password" element={<ForgotPassword />} />
          <Route path="/reset-password" element={<ResetPassword />} />
          <Route path="/verify-email/:id/:hash" element={<VerifyEmail />} />
        </Route>

        {/* Authenticated app shell — protected */}
        <Route element={<ProtectedRoute />}>
          <Route element={<AppLayout />}>
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/agents" element={<Agents />} />
            <Route path="/agents/:agentId" element={<AgentDetails />} />
            <Route path="/observations" element={<Observations />} />
            <Route path="/observations/:observationId" element={<ObservationDetails />} />
            <Route path="/alerts" element={<Alerts />} />
            <Route path="/alerts/:alertId" element={<AlertDetails />} />
            <Route path="/audit-logs" element={<AuditLogs />} />
            <Route path="/settings" element={<Settings />} />
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </AuthProvider>
  );
}
