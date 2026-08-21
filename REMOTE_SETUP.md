# Remote development & server notes

## A. Private file protection & deploy (canonical)

**See [docs/deploy.md](docs/deploy.md)** for:

- Durable storage under `wp-content/mjb-private/` and `mjb-brand/`
- nginx / Apache / IIS / Local WP deny rules
- Cache and CDN guidance
- Update and backup workflow
- Verification checklist

Quick nginx snippet (full context in deploy.md):

```nginx
location ~* /wp-content/mjb-private/ {
    deny all;
    return 403;
}
location ~* /wp-content/plugins/modern-job-board/mjb-private/ {
    deny all;
    return 403;
}
location ~* /wp-content/uploads/mjb-(private|resumes)/ {
    deny all;
    return 403;
}
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Resumes are only available via the plugin’s authenticated download endpoint (`?mjb_download=…`). A direct private file URL must return **403**.

---

## B. Remote development via SSH (VS Code)

Useful when editing production/staging over SSH.

### Prerequisites

1. VS Code extension: [Remote - SSH](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-ssh)

### Configure host

1. Command Palette → **Remote-SSH: Open Configuration File**
2. Add (replace placeholders):

```ssh
Host my-plugin-server
    HostName <YOUR_SERVER_IP_OR_DOMAIN>
    User <YOUR_SSH_USERNAME>
    # IdentityFile "C:\Path\To\Your\private_key.pem"
```

### Connect

1. **Remote-SSH: Connect to Host…** → `my-plugin-server`
2. **File → Open Folder** → e.g. `/var/www/html/wp-content/plugins/modern-job-board`

You are editing files on the server. Prefer git deploys over live-editing production when possible.
