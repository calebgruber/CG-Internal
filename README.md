# CG Internal

A self-hosted internal portal for **internal.calebgruber.me** with SSO identity management, an academic hub (EDU Hub), and a global system administration panel.

---

## Architecture

```
internal.calebgruber.me/
├── /                  → App Launcher (requires login)
├── /id/auth/login.php → Central SSO login
├── /id/admin/         → Identity & access management (admin only)
├── /edu/              → EDU Hub (academic tracking)
└── /admin/            → Global system administration (admin only)
```

### Tech Stack
- **Backend:** PHP 8.1+ with PDO (MySQL)
- **Database:** MySQL 8 / MariaDB 10.6+
- **CSS:** Custom (no Bootstrap) — Material Symbols icons, CSS custom properties for theming
- **JS:** Vanilla JS (no framework dependencies)

---

## Quick Start

### 1. Requirements
- PHP 8.1+ with PDO MySQL extension
- MySQL 8 / MariaDB 10.6+
- Apache with `mod_rewrite` (or Nginx with equivalent config)

### 2. Database

```sql
CREATE DATABASE cg_internal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cg_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON cg_internal.* TO 'cg_user'@'localhost';
```

### 3. Configuration

Set environment variables (or create `config.local.php`):

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=cg_internal
export DB_USER=cg_user
export DB_PASS=your_password
export SSO_SECRET=your_random_secret_min_32_chars
```

Or create `config.local.php` in the root:

```php
<?php
// config.local.php – never commit this file
define('DB_HOST', '127.0.0.1');
define('DB_PASS', 'your_password');
// ... etc
```

### 4. Run Setup

Visit `https://internal.calebgruber.me/setup.php` in your browser.

This will:
1. Run the database schema (`db/schema.sql`)
2. Create your initial admin account

> ⚠️ **Delete `setup.php` immediately after completing setup.**

---

## Features

### 🔐 ID System (SSO)
- Central login at `/id/auth/login.php`
- Session-based SSO — log in once, access all permitted apps
- Per-user app access control
- User management (create, edit, disable, role assignment)
- App registration with Material Symbol icons

### 📚 EDU Hub
- **Dashboard** — upcoming deadlines, today's schedule, recent notes
- **Classes** — add courses with color coding, instructor, course code
- **Assignments** — track due dates with priority levels, status workflow (pending → in progress → completed)
- **Tasks** — standalone to-do list with priorities and due dates
- **Notes** — per-class or general notes with full text content
- **Schedule** — weekly class timetable builder
- **Email & SMS reminders** — trigger notifications manually or automatically for upcoming deadlines

### ⚙️ Global Admin
- **Overview** — system stats at a glance
- **Settings** — SMTP, Twilio SMS, notification email/phone
- **DB Migrations** — apply SQL migration files from `db/migrations/`
- **Maintenance Mode** — toggle site-wide maintenance (admins bypass it)
- **Alerts** — create/manage system-wide banners shown on all dashboards

---

## Directory Structure

```
/
├── .htaccess           Security headers, deny access to internals
├── .gitignore
├── index.php           App launcher (root)
├── setup.php           First-run wizard (delete after use)
├── config.local.php    Local overrides (gitignored)
│
├── shared/
│   ├── config.php      Global constants
│   ├── db.php          PDO singleton + settings helpers
│   ├── auth.php        SSO session, CSRF, access control
│   ├── ui.php          Shared UI rendering helpers
│   ├── email.php       Email + SMS notification helpers
│   └── assets/
│       ├── style.css   Shared stylesheet (Material Symbols, dark/light mode)
│       └── app.js      Shared JavaScript
│
├── db/
│   ├── schema.sql      Full DB schema
│   └── migrations/     SQL migration files (applied via /admin/migrations.php)
│
├── id/
│   ├── auth/
│   │   ├── login.php   SSO login page
│   │   └── logout.php  Session destroy
│   └── admin/
│       ├── index.php   ID dashboard
│       ├── users.php   User CRUD + app access
│       └── apps.php    App CRUD
│
├── edu/
│   ├── index.php       EDU Hub dashboard
│   ├── classes.php     Class management
│   ├── assignments.php Assignment tracking
│   ├── tasks.php       Task list
│   ├── notes.php       Notes
│   └── schedule.php    Weekly schedule
│
└── admin/
    ├── index.php       Global admin overview
    ├── settings.php    SMTP/Twilio/general settings
    ├── migrations.php  DB migration runner
    ├── maintenance.php Maintenance mode toggle
    └── alerts.php      System-wide alert manager
```

---

## Email & SMS Reminders

Configure in `/admin/settings.php`:

- **Email:** Set SMTP credentials. Reminders are sent via `send_due_notification()` in `shared/email.php`.
- **SMS:** Requires a [Twilio](https://twilio.com) account. Set Account SID, Auth Token, and From number.
- **Trigger:** Click the 🔔 button on any assignment or task to send an immediate reminder.
- **Automatic:** Use a cron job calling a reminder script (add to `edu/cron_reminders.php` as needed).

---

## UI Design

All pages share:
- **Material Symbols Outlined** icons from Google Fonts
- A fixed left sidebar with section labels, active state highlighting, and user footer
- Consistent card components with icon + title headers
- Info/warning/danger/success alert banners (dismissible or persistent)
- Light / dark theme toggle (persisted in `localStorage`)
- Responsive layout (sidebar collapses on mobile)

---

## Security Notes

- All DB queries use PDO prepared statements
- CSRF tokens on every POST form
- Session regeneration on login
- Password hashing via `password_hash(PASSWORD_DEFAULT)`
- Brute-force delay on failed login
- `.htaccess` blocks direct access to `shared/` and `db/`
- HTTP security headers set via `.htaccess`
- Input is HTML-escaped with `htmlspecialchars()` throughout

---

## License

Private / Internal use only.
