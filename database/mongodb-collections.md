# MongoDB collections

`composer init-db` creates indexes and the application uses these collections:

- `users`: admin accounts, password hashes, roles, lockouts, and last login time.
- `leads`: enquiries, pipeline status, notification state, and submission IP for rate limiting.
- `plans`: editable pricing packages and features.
- `site_content`: editable website copy stored as key/value documents.
- `audit_logs`: append-only records for sensitive admin changes.

Passwords use Argon2id when available and bcrypt otherwise. Plain-text passwords and database credentials must never be committed.
