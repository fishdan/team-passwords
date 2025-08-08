# Team Passwords Plugin

[![Version](https://img.shields.io/badge/version-0.1-blue.svg)](#)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-%5E6.0-blue)](https://wordpress.org/)
[![Download](https://img.shields.io/github/v/release/fishdan/Utilities?display_name=tag&sort=semver)](https://github.com/fishdan/Utilities/releases/latest)


A lightweight WordPress plugin for securely storing and sharing passwords with your team.

---

## ✨ Features
- **AES-256 encryption** for all stored passwords
- **Frontend vault page** via `[team_passwords]` shortcode
- **Bootstrap-styled** table and forms
- **Role-based access control**
  - Custom role: `team_passwords_user` (view-only by default)
  - Admins have full access
  - Optional capability to allow adding passwords from frontend
- **Auto-creates vault page** (`/team-passwords`) on activation
- Passwords masked by default with “Show” button
- Works on frontend and admin dashboard

---


## 📦 Installation
1. Copy the `team-passwords` folder into your WordPress `wp-content/plugins` directory.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. On activation:
   - Creates the database table `wp_team_passwords`.
   - Creates the `/team-passwords` page with the `[team_passwords]` shortcode if it doesn't exist.
   - Adds the `team_passwords_user` role.

Alternatively download the latest zip file from the button above and install it via your wordpress "add plugin"

---

## 🚀 Usage

### Frontend Vault
- Visit `/team-passwords` while logged in.
- If not logged in, you’ll see a login form.
- **Admins** see:
  - Add Password form
  - Password list
- **Team Passwords Users** see:
  - Password list only (unless granted add capability)

### Shortcode
To embed the vault on another page:

```text
[team_passwords]
