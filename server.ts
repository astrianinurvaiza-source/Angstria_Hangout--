import express from "express";
import path from "path";
import fs from "fs";
import { createServer as createViteServer } from "vite";

const app = express();
const PORT = 3000;

// Local DB mock definitions have been removed for pure phpMyAdmin connectivity.

// Middleware to parse bodies
app.use(express.json({ limit: "50mb" }));
app.use(express.urlencoded({ extended: true, limit: "50mb" }));

// Enable CORS for all requests just to match php headers
app.use((req, res, next) => {
  res.header("Access-Control-Allow-Origin", "*");
  res.header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Requested-With");
  res.header("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
  if (req.method === "OPTIONS") {
    return res.sendStatus(200);
  }
  next();
});

// Path to persist custom phpMyAdmin api.php target
const PHP_CONFIG_FILE = path.join(process.cwd(), "php_config.json");

function readPhpConfig() {
  let config = {
    target_api_url: "http://localhost/Angstria_Hangout--/api.php",
    use_live: true // Default to true as the user wants PHPMyAdmin connectivity prioritised!
  };
  if (fs.existsSync(PHP_CONFIG_FILE)) {
    try {
      const parsed = JSON.parse(fs.readFileSync(PHP_CONFIG_FILE, "utf-8"));
      config = {
        ...config,
        ...parsed
      };
    } catch (e) {
      // Ignore
    }
  }
  return config;
}

function savePhpConfig(config: any) {
  try {
    fs.writeFileSync(PHP_CONFIG_FILE, JSON.stringify(config, null, 2), "utf-8");
  } catch (e) {
    console.error("Gagal menyimpan konfigurasi PHP:", e);
  }
}

// API Endpoint to manage live phpMyAdmin config from the user interface
app.get("/api/backend-url", (req, res) => {
  return res.json(readPhpConfig());
});

app.post("/api/backend-url", (req, res) => {
  const body = req.body || {};
  let config = readPhpConfig();
  if (body.target_api_url !== undefined) {
    config.target_api_url = body.target_api_url.trim();
  }
  if (body.use_live !== undefined) {
    config.use_live = !!body.use_live;
  }
  savePhpConfig(config);
  return res.json({ success: true, config });
});

// Implement the exactly action mapping matching api.php or PROXY to actual phpMyAdmin database
app.all("/api.php", async (req, res) => {
  const config = readPhpConfig();
  let fallbackReason = "";

  if (config.use_live && config.target_api_url && config.target_api_url !== "/api.php") {
    try {
      const targetUrl = new URL(config.target_api_url);
      
      // Fast check for localhost/private IP when accessed remotely
      let isLocalhost = false;
      try {
        const hostname = targetUrl.hostname;
        if (
          hostname === "localhost" || 
          hostname === "127.0.0.1" || 
          hostname.startsWith("192.168.") || 
          hostname.startsWith("10.") || 
          hostname.startsWith("172.")
        ) {
          isLocalhost = true;
        }
      } catch (e) {}

      // Allow localhost proxying if the request is originating from a local developer access as well
      const reqHost = req.headers.host || "";
      const isRequestFromLocal = reqHost.includes("localhost") || 
                                 reqHost.includes("127.0.0.1") || 
                                 reqHost.startsWith("192.168.") || 
                                 reqHost.startsWith("10.") || 
                                 reqHost.startsWith("172.");

      if (isLocalhost && !isRequestFromLocal) {
        fallbackReason = `Alamat database Anda saat ini adalah lokal (${config.target_api_url}) yang tidak dapat diakses langsung oleh server preview cloud kami. Gunakan ngrok/tunneling atau hosting online, atau periksa kembali file api.php Anda.`;
        throw new Error("localhost_restricted_remote");
      }

      // Copy all query parameters from original request
      Object.keys(req.query).forEach((key) => {
        targetUrl.searchParams.set(key, String(req.query[key]));
      });

      const options: any = {
        method: req.method,
        headers: {
          "Accept": "application/json"
        }
      };

      if (req.method !== "GET" && req.method !== "HEAD") {
        options.headers["Content-Type"] = "application/json";
        options.body = JSON.stringify(req.body);
      }

      console.log(`[DATABASE PROXY] Forwarding request to live phpMyAdmin: ${targetUrl.toString()}`);
      
      // Fetch directly from their PHPMyAdmin web server
      const response = await fetch(targetUrl.toString(), options);
      
      if (!response.ok) {
        fallbackReason = `Server phpMyAdmin Anda gagal merespon (HTTP ${response.status} ${response.statusText}). Mohon pastikan file api.php aktif di XAMPP/htdocs.`;
        throw new Error("remote_http_fail");
      }

      const text = await response.text();
      try {
        const json = JSON.parse(text);
        if (typeof json === "object" && json !== null) {
          json.fallback_used = false;
        }
        return res.json(json);
      } catch (err) {
        fallbackReason = "Database phpMyAdmin tidak mengembalikan tanggapan JSON yang valid. Pastikan file api.php diletakkan dengan benar di XAMPP/htdocs.";
        throw new Error("invalid_json_response");
      }
    } catch (error: any) {
      if (!fallbackReason) {
        fallbackReason = `Gagal terhubung ke database live phpMyAdmin (${config.target_api_url}). Pastikan Apache/XAMPP aktif dan terhubung internet, atau periksa kembali file api.php Anda.`;
      }
      return res.status(500).json({
        success: false,
        error: true,
        fallback_used: false,
        message: fallbackReason,
        details: error.message
      });
    }
  } else {
    return res.status(400).json({
      success: false,
      error: true,
      fallback_used: false,
      message: "Koneksi ke database live phpMyAdmin dinonaktifkan atau belum dikonfigurasi dalam php_config.json."
    });
  }
});

// Configure Vite or Static Fallback
async function startServer() {
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa"
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Server running on port ${PORT}`);
  });
}

startServer();
