# ngrok Tunnel Setup Guide (`shai_salon`)

This guide explains how to expose your local Symfony application to the internet using your static ngrok domain: **`https://jet-acronym-opposing.ngrok-free.dev`**.

---

## 1. Prerequisites & Authentication

1. **Install ngrok** (if not already installed):
   ```powershell
   winget install Ngrok.Ngrok
   ```
   *(Alternatively, download it from [dashboard.ngrok.com](https://dashboard.ngrok.com/get-started/setup/windows)).*

2. **Add your ngrok Authtoken** from the [ngrok Dashboard](https://dashboard.ngrok.com/get-started/your-authtoken):
   ```powershell
   ngrok config add-authtoken <YOUR_AUTHTOKEN>
   ```

---

## 2. Start Your Local Server

Ensure the `shai_salon` Symfony server is running locally before launching ngrok.

> **Note:** Standard Symfony/PHP servers run on port **`8000`**. If your server is configured on port **`800`**, replace `8000` with `800` in the commands below.

* **Using Symfony CLI:**
  ```powershell
  cd z:\wedev\shai_salon
  symfony server:start --port=8000
  ```
* **Using PHP Built-in Server:**
  ```powershell
  cd z:\wedev\shai_salon
  php -S 127.0.0.1:8000 -t public
  ```

---

## 3. Start the ngrok Tunnel

Run the following command in a separate terminal to forward your static domain to your local port:

### For Port `8000` (Standard Symfony Port)
```powershell
ngrok http --url=jet-acronym-opposing.ngrok-free.dev 8000
```

### For Port `800`
```powershell
ngrok http --url=jet-acronym-opposing.ngrok-free.dev 800
```

### With Host Header Rewrite (if using local virtual hosts or HTTPS)
```powershell
ngrok http --url=jet-acronym-opposing.ngrok-free.dev --host-header=rewrite 8000
```

When active, ngrok will display:
```text
Forwarding    https://jet-acronym-opposing.ngrok-free.dev -> http://localhost:8000
```

---

## 4. Symfony `.env` Configuration

`DEFAULT_URI` in `.env` is configured to use the ngrok static URL so Symfony generates accurate public links for webhooks, emails, and CLI routing:

```dotenv
DEFAULT_URI=https://jet-acronym-opposing.ngrok-free.dev
```

---

## 5. Optional: One-Command Startup via `ngrok.yml`

To avoid typing the full command every time:

1. Open your ngrok configuration file:
   ```powershell
   ngrok config edit
   ```
2. Add the `shai_salon` tunnel profile:
   ```yaml
   version: "2"
   authtoken: <YOUR_AUTHTOKEN>
   tunnels:
     shai_salon:
       proto: http
       addr: 8000 # Change to 800 if using port 800
       domain: jet-acronym-opposing.ngrok-free.dev
   ```
3. Start the tunnel anytime with:
   ```powershell
   ngrok start shai_salon
   ```
