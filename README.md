```markdown
# Dispatch-CX

A PHP-based web application for **commission monitoring** — featuring user authentication, role-based access control, contractor management, project tracking, and an admin dashboard.

## Overview

**Dispatch-CX** (Dispatch Commission Monitoring Tool) is a lightweight PHP application built around a modular structure with dedicated directories for authentication, configuration, database migrations, and business logic. It lets managers register contractors, assign them to projects, and track project updates against due dates — while each contractor is automatically linked to a user account for authentication and auditing.

The system also includes user management with security safeguards (password verification, last-role protection on deletion) and a full activity log so every commission-relevant action is traceable.

## Project Structure

```
dispatch-cx/
├── app/          # Application logic (user management, contractor/project modules)
│   ├── admin/    # Admin-only pages (users.php)
│   └── manager/  # Manager pages (index.php — contractors, projects, updates)
├── auth/         # Authentication handling (login, signout, redirects)
├── config/       # Configuration files
├── database/     # SQL migrations and schema
├── functions/    # Reusable helper functions
├── tests/        # Test files
└── index.php     # Application entry point
```

### Directory Details

| Directory | Purpose |
|-----------|---------|
| `app/admin/` | Admin-only features: user management (`admin/users.php`), password reset requests |
| `app/manager/` | Manager features: contractor, project, and project update management (`index.php`) |
| `auth/` | Login, sign-out, and authentication redirects |
| `config/` | Application configuration settings |
| `database/` | Database migrations (e.g., `003_transactions.sql`, `004_password_resets.sql`) |
| `functions/` | Shared utility functions (CSRF, activity logging, role checks) |
| `tests/` | Test coverage for application features |

## Features

- **User Authentication** — Login page with redirect handling and sign-out support
- **User Management** — Admin interface for managing users (`admin/users.php`)
- **Role-Based Access Control** — Roles: `admin`, `manager`, `user`
- **Secure User Deletion** — Requires password confirmation and prevents removal of the last remaining role
- **Password Resets** — Dedicated schema support via `004_password_resets.sql`
- **Contractor Management** — Managers register contractors; each contractor is automatically linked to a new `user` account in the same transaction
- **Project Management** — Track projects with title, manager, and category
- **Commission / Project Updates** — Assign contractors to projects with due dates; enforce one active update per contractor and per project
- **Contractor Verification** — Managers can verify/unverify the linked user account of a contractor
- **Activity Logging** — Every create/update/delete/verify action is recorded in `activity_logs`
- **Transaction Support** — Multi-step writes (contractor + user creation, deletion) are wrapped in DB transactions
- **Database Migrations** — Versioned SQL schema changes

## Database Schema

| Table | Purpose |
|---|---|
| `users` | Application accounts (admin, manager, user) with verification flag |
| `contractors` | Contractor records, optionally linked to a `users` row via `user_id` FK |
| `projects` | Project records (title, manager, category) |
| `project_updates` | Join table between contractors and projects with due/completion dates — the core of dispatch/commission tracking |
| `activity_logs` | Audit trail of user actions (action + status) |
| `password_reset_requests` | Password reset workflow |

**Key relationships:**

- `contractors.user_id` → `users.user_id` (`ON DELETE SET NULL`, `ON UPDATE CASCADE`)
- `project_updates.contractor_id` → `contractors.contractor_id` (`ON DELETE RESTRICT`)
- `project_updates.project_id` → `projects.project_id` (`ON DELETE RESTRICT`)

## Requirements

- PHP (version compatible with the codebase)
- MySQL / MariaDB
- A web server (Apache, Nginx, or PHP's built-in server)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/cliffamadeus/dispatch-cx.git
   cd dispatch-cx
   ```

2. **Configure the application**
   - Update the files in `config/` with your database credentials and environment settings.

3. **Run database migrations**
   - Apply the SQL files in `database/` in order to set up the schema (users, activity logs, contractors, projects, project updates, password resets).
   - Seed data (default admin, manager, and user accounts) is included in the migration files.

4. **Start the application**
   ```bash
   php -S localhost:8000
   ```
   Then navigate to `http://localhost:8000` in your browser.

## Usage

### Admin

- Access `admin/users.php` to create, verify, update, reset passwords for, and delete users.
- Review and resolve password reset requests.
- User deletion requires password confirmation and cannot remove the last user of a role.

### Manager

- Access `manager/index.php` to manage:
  - **Contractors** — Register a contractor, which also creates its linked `user` account (email, username, password). Verify or unverify the linked account.
  - **Projects** — Create and update project records.
  - **Project Updates (Dispatch/Commission)** — Assign a contractor to a project with a due date; mark updates as completed.

### Flow: Contractor → User Link

When a manager registers a contractor:

1. A new row is inserted into `users` with `user_role = 'user'` and `user_verified = 0`.
2. A new row is inserted into `contractors` referencing the new `user_id`.
3. Both inserts are wrapped in a transaction — either both succeed or neither does.
4. The manager verifies the linked user from the contractor list.

## Security

- CSRF tokens required on all state-changing requests (`requireCsrfToken()`)
- Password hashing via `password_hash()` / `password_verify()`
- Role-based guards (`requireRole('admin')`, `requireRole('manager')`)
- Foreign key constraints enforce referential integrity:
  - Deleting a contractor is blocked if it has project updates.
  - Deleting a user clears `contractors.user_id` (contractor is preserved).
- All mutating actions logged to `activity_logs` with `success` / `failed` status.

## Recent Changes

- **Oct 13, 2026** — Manager module refactored to **contractors / projects / project_updates**; contractor registration now also creates and links a `users` account in a single transaction; manager can verify/unverify linked user accounts; project update completion replaces book return
- **Oct 10, 2026** — Hardened user deletion with password verification and last-role checks
- **Oct 2, 2026** — Added transaction migration (`003_transactions.sql`), password reset schema (`004_password_resets.sql`), and initial user management for `admin/users.php`
- **Sep 9, 2026** — Added login page, redirects, and sign-out

## Testing

Tests are located in the `tests/` directory. Run them using your preferred PHP testing tool (e.g., PHPUnit) as configured in the repository.

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes (`git commit -m 'Add your feature'`)
4. Push to the branch (`git push origin feature/your-feature`)
5. Open a Pull Request

## License

No license specified. Please contact the repository owner for usage terms.

## Author

**cliffamadeus** — [GitHub Profile](https://github.com/cliffamadeus)

---

> **Note:** This README was generated based on the repository structure and commit history. Some details (such as exact PHP version, database engine, and test framework) may need verification against the source code.
```