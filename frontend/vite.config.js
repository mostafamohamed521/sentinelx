import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  server: {
    // Forwards every /api/* request from the Vite dev server (localhost:5173)
    // to the Laravel backend served by Herd at http://sentinel.test — the
    // browser only ever talks to :5173, so no CORS setup is needed on the
    // Laravel side for local dev. The ML/FastAPI service (localhost:8000) is
    // called directly by the backend, not by this frontend, and is untouched
    // by this proxy. Requires VITE_API_BASE_URL to stay a relative path
    // ("/api/v1", the default) rather than an absolute URL.
    proxy: {
      "/api": {
        target: "http://sentinel.test",
        changeOrigin: true,
      },
    },
  },
});
