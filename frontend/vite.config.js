import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  server: {
    // Forwards every /api/* request from the Vite dev server (localhost:5173)
    // to the local Laravel backend on :8000 — the browser only ever talks
    // to :5173, so no CORS setup is needed on the Laravel side for local dev.
    // Requires VITE_API_BASE_URL to stay a relative path ("/api/v1", the
    // default) rather than an absolute http://localhost:8000/... URL.
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
});
