import React, { createContext, useContext, useEffect, useState } from "react";
import * as authApi from "./api/auth.js";

const AuthContext = createContext(null);

// No stored refresh token — the real Backend issues a single JWT access
// token only, re-issued via the Authorization header itself, never a
// second, separately-stored token.
const STORAGE_KEYS = {
  accessToken: "sentinelx_access_token",
  user: "sentinelx_user",
};

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const raw = localStorage.getItem(STORAGE_KEYS.user);
    return raw ? JSON.parse(raw) : null;
  });
  const [accessToken, setAccessToken] = useState(() => localStorage.getItem(STORAGE_KEYS.accessToken));
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    // On first load, if we have a token, validate the session against /auth/me.
    async function bootstrap() {
      const token = localStorage.getItem(STORAGE_KEYS.accessToken);
      if (!token) {
        setLoading(false);
        return;
      }
      try {
        const me = await authApi.getCurrentUser();
        localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(me));
        setUser(me);
      } catch {
        clearSession();
      } finally {
        setLoading(false);
      }
    }
    bootstrap();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function persistSession(accessTokenValue, userValue) {
    localStorage.setItem(STORAGE_KEYS.accessToken, accessTokenValue);
    localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(userValue));
    setAccessToken(accessTokenValue);
    setUser(userValue);
  }

  function clearSession() {
    localStorage.removeItem(STORAGE_KEYS.accessToken);
    localStorage.removeItem("sentinelx_refresh_token"); // one-time cleanup of a removed key for existing sessions
    localStorage.removeItem(STORAGE_KEYS.user);
    setAccessToken(null);
    setUser(null);
  }

  // POST /auth/login returns only a token — the Backend never embeds the
  // User in the login response, so `me` has to be fetched as a second
  // step before the session can be considered "signed in".
  async function login(email, password) {
    setError(null);
    try {
      const tokenRes = await authApi.login({ email, password });
      localStorage.setItem(STORAGE_KEYS.accessToken, tokenRes.access_token);
      setAccessToken(tokenRes.access_token);
      const me = await authApi.getCurrentUser();
      persistSession(tokenRes.access_token, me);
      return { ok: true };
    } catch (e) {
      clearSession();
      setError(e.message);
      return { ok: false, code: e.code, message: e.message };
    }
  }

  // POST /auth/register never returns a token — the account requires email
  // verification before LoginUserAction will issue one, so signup can only
  // ever end in "check your email", never an authenticated session.
  async function signup(fields) {
    setError(null);
    try {
      const res = await authApi.signup(fields);
      return { ok: true, message: res.message, user: res.data };
    } catch (e) {
      setError(e.message);
      return { ok: false, code: e.code, message: e.message };
    }
  }

  async function logout() {
    try {
      await authApi.logout();
    } finally {
      clearSession();
    }
  }

  async function forgotPassword(email) {
    try {
      const res = await authApi.forgotPassword({ email });
      return { ok: true, message: res.message };
    } catch (e) {
      return { ok: false, message: e.message };
    }
  }

  async function resetPassword(token, newPassword) {
    try {
      const res = await authApi.resetPassword({ token, new_password: newPassword });
      return { ok: true, message: res.message };
    } catch (e) {
      return { ok: false, message: e.message };
    }
  }

  async function resendVerificationEmail() {
    try {
      const res = await authApi.resendVerificationEmail();
      return { ok: true, message: res.message };
    } catch (e) {
      return { ok: false, message: e.message };
    }
  }

  async function updateProfile(fullName) {
    try {
      const updated = await authApi.updateProfile({ full_name: fullName });
      localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(updated));
      setUser(updated);
      return { ok: true };
    } catch (e) {
      return { ok: false, message: e.message };
    }
  }

  async function changePassword(currentPassword, newPassword, newPasswordConfirmation) {
    try {
      const res = await authApi.changePassword({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation,
      });
      return { ok: true, message: res.message };
    } catch (e) {
      return { ok: false, message: e.message };
    }
  }

  const value = {
    user,
    accessToken,
    isAuthenticated: !!user && !!accessToken,
    loading,
    error,
    login,
    signup,
    logout,
    forgotPassword,
    resetPassword,
    resendVerificationEmail,
    updateProfile,
    changePassword,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within an AuthProvider");
  return ctx;
}
