# Code Server Setup

## Installation

Install code-server on the server:

```bash
curl -fsSL https://code-server.dev/install.sh | sh
```

## Systemd Service

Create `/etc/systemd/system/code-server.service`:

```ini
[Unit]
Description=code-server
After=network.target

[Service]
Type=exec
User=ploi
WorkingDirectory=/home/ploi
ExecStart=/usr/bin/code-server --bind-addr 127.0.0.1:8443 --auth none
Restart=always
Environment=HOME=/home/ploi

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl enable code-server
sudo systemctl start code-server
```

## Nginx Configuration

Add this to your site's Nginx config in Ploi (before the main location block):

```nginx
# IDE Auth Check
location = /ide-auth-check {
    internal;
    proxy_pass http://127.0.0.1:8000/ide-auth-check;
    proxy_pass_request_body off;
    proxy_set_header Content-Length "";
    proxy_set_header X-Original-URI $request_uri;
    proxy_set_header Cookie $http_cookie;
}

# Code Server Proxy
location /ide-proxy/ {
    auth_request /ide-auth-check;

    proxy_pass http://127.0.0.1:8443/;
    proxy_http_version 1.1;

    # WebSocket support
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Longer timeouts for IDE
    proxy_read_timeout 86400;
    proxy_send_timeout 86400;
}
```

## Verification

1. Start code-server: `sudo systemctl start code-server`
2. Check status: `sudo systemctl status code-server`
3. Test locally: `curl http://127.0.0.1:8443` (should return HTML)
4. Test via proxy (when logged in): Navigate to `/ide-proxy/` in browser
