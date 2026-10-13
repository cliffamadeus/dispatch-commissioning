# Dispatch-CX

> **Dispatch Commission Monitoring Tool** — a lightweight PHP application for managing contractors, assigning them to projects, and tracking project updates against due dates.

![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MySQL%2FMariaDB-supported-003545?logo=mariadb&logoColor=white)
![License](https://img.shields.io/badge/license-unspecified-lightgrey)

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [Getting Started](#getting-started)
- [Usage](#usage)
- [Security](#security)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)
- [Author](#author)

## Overview

Dispatch-CX provides user authentication, role-based access control, contractor management, project tracking, and an admin dashboard. Managers register contractors and assign them to projects; each contractor is automatically linked to a user account for authentication and auditing.

Every commission-relevant action is written to an activity log, and sensitive operations (such as user deletion) are protected by password verification and last-role safeguards.

## Features

### Access & Security
- **Authentication** — login with redirect handling and sign-out
- **Role-based access control** — roles: `admin`, `manager`, `user`
- **Secure user deletion** — requires password confirmation and blocks removal of the last user in a role
- **Password resets** — request/resolve workflow (`004_password_resets.sql`)

### Contractors & Projects
- **Contractor management** — registering a contractor automatically creates a linked `user` account in the same transaction
- **Contractor verification** — managers can verify or unverify the linked user account
- **Project management** — projects have a title, manager, and category
- **Project updates (dispatch/commission)** — assign contractors to projects with due dates, mark them complete, and enforce **one active update per contractor and per project**

### Auditing & Data Integrity
- **Activity logging** — every create/update/delete/verify action is recorded in `activity_logs` with a `success` / `failed` status
- **Transactions** — multi-step writes (contractor + user creation, deletion) are atomic
- **Versioned migrations** — SQL schema changes tracked in `database/`

## Project Structure

```
dispatch-cx/
├── app/
│   ├── admin/      # Admin-only pages (users.php)
│   └── manager/    # Manager pages (index.php: contractors, projects, updates)
├── auth/           # Login, sign-out, redirects
├── config/         # Application configuration
├── database/       # SQL migrations and schema
├── functions/      # Shared helpers (CSRF, activity logging, role checks)
├── tests/          # Test files
└── index.php       # Application entry point
```

## Database Schema

| Table | Purpose |
|---|---|
| `users` | Application accounts (admin, manager, user) with a verification flag |
| `contractors` | Contractor records, optionally linked to a `users` row via `user_id` |
| `projects` | Project records (title, manager, category) |
| `project_updates` | Links contractors to projects with due/completion dates — the core of dispatch/commission tracking |
| `activity_logs` | Audit trail of user actions (action + status) |
| `password_reset_requests` | Password reset workflow |

**Key relationships**

| Foreign key | References | On delete / update |
|---|---|---|
| `contractors.user_id` | `users.user_id` | `SET NULL` / `CASCADE` |
| `project_updates.contractor_id` | `contractors.contractor_id` | `RESTRICT` |
| `project_updates.project_id` | `projects.project_id` | `RESTRICT` |

## Getting Started

### Requirements

- PHP (version compatible with the codebase)
- MySQL or MariaDB
- A web server (Apache, Nginx, or PHP's built-in server)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/cliffamadeus/dispatch-cx.git
   cd dispatch-cx
   ```

2. **Configure the application**
   Update the files in `config/` with your database credentials and environment settings.

3. **Run the database migrations**
   Apply the SQL files in `database/` in order. Seed data (default admin, manager, and user accounts) is included in the migration files.

   > ⚠️ Change the seeded default credentials before deploying to any shared or production environment.

4. **Start the application**
   ```bash
   php -S localhost:8000
   ```
   Then open <http://localhost:8000>.

## Usage

### Admin

Open `admin/users.php` to:
- Create, verify, update, and delete users
- Reset passwords
- Review and resolve password reset requests

User deletion requires password confirmation and cannot remove the last user of a role.

### Manager

Open `manager/index.php` to manage:

| Area | What you can do |
|---|---|
| **Contractors** | Register a contractor (also creates its linked `user` account with email, username, password); verify or unverify the linked account |
| **Projects** | Create and update project records |
| **Project updates** | Assign a contractor to a project with a due date; mark updates as completed |

### Contractor → User Link

When a manager registers a contractor:

1. A new row is inserted into `users` with `user_role = 'user'` and `user_verified = 0`.
2. A new row is inserted into `contractors` referencing the new `user_id`.
3. Both inserts run in one transaction — either both succeed or neither does.
4. The manager then verifies the linked user from the contractor list.

## Security

- **CSRF protection** on all state-changing requests (`requireCsrfToken()`)
- **Password hashing** via `password_hash()` / `password_verify()`
- **Role guards** — `requireRole('admin')`, `requireRole('manager')`
- **Referential integrity**
  - Deleting a contractor is blocked if it has project updates
  - Deleting a user clears `contractors.user_id`; the contractor record is preserved
- **Audit trail** — all mutating actions are logged to `activity_logs`

## Testing

Tests live in the `tests/` directory. Run them with your preferred PHP testing tool (e.g., PHPUnit) as configured in the repository.

## Changelog

| Date | Change |
|---|---|
| Oct 13, 2026 | Manager module refactored around contractors / projects / project updates; contractor registration now creates and links a `users` account in one transaction; managers can verify/unverify linked accounts; completing a project update replaces the earlier return flow |
| Oct 10, 2026 | Hardened user deletion with password verification and last-role checks |
| Oct 2, 2026 | Added transaction migration (`003_transactions.sql`), password reset schema (`004_password_resets.sql`), and user management for `admin/users.php` |
| Sep 9, 2026 | Added login page, redirects, and sign-out |

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit your changes: `git commit -m "Add your feature"`
4. Push to the branch: `git push origin feature/your-feature`
5. Open a Pull Request

## License

No license specified. Please contact the repository owner for usage terms.

## Author

**cliffamadeus** — [GitHub](https://github.com/cliffamadeus)
