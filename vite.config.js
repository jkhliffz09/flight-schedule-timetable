import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      "/__fst_proxy": {
        target: "https://services.flightlookup.com",
        changeOrigin: true,
        secure: true,
        rewrite: (path) => path.replace(/^\/__fst_proxy/, "")
      }
    }
  }
});
