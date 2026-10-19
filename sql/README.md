# sql/

Database schema files for MySQL and MariaDB. Repositories own every query that runs against these tables; this folder holds only the definitions.

## Layout

- `sql/<prefix>.sql`: the complete schema, executable on an empty database. Packages name it after themselves, like `citomni_jobrunner.sql`, and the app imports their schemas into its own database next to its own, from `vendor/<vendor>/<package>/sql/`.
- `sql/migrations/YYYYMMDD_NN_<name>.sql`: one migration per change to an existing schema, applied in name order. When a migration is added, the complete schema file is updated to match.

## Rules

- InnoDB and `utf8mb4`, with `utf8mb4_unicode_ci` for text.
- Machine values compare byte by byte. Hex hashes, tokens and UUIDs are `CHARACTER SET ascii COLLATE ascii_bin`, like `jobrun_jobs.worker_token_hash`. JSON is `longtext` with `utf8mb4_bin` and a `json_valid()` check, like `jobrun_jobs.payload_json`.
- A status or other code is preferably text: `varchar` in `ascii_bin`, with a check constraint that lists the cases of a string-backed enum, like `demo_articles.status` and `ArticleStatus`. The stored value then means the same in SQL, dumps and logs as in code. A numeric code is allowed, as `tinyint unsigned` with a check constraint and an int-backed enum, for example for a flag that never gets a third state or to match an existing schema. Either way, a code is compared with named cases, never with a range such as `status > 0`.
- snake_case names. Every table carries the package or app prefix (`jobrun_jobs`, `auth_password_history`); this app uses `demo_`.
- `bigint unsigned` surrogate ids when a surrogate key fits; natural keys are allowed.
- Name every index, unique key, foreign key and check constraint explicitly, with its kind and table first: `uq_jobrun_jobs_job_uuid`, `fk_auth_audit_identity`, `ix_jobrun_jobs_status_created`, `chk_jobrun_jobs_claim_mode`.
- A table whose rows are entities or events carries `created_at datetime NOT NULL DEFAULT current_timestamp()`, also when no code reads it yet, because the moment a row was created cannot be recovered later.
- Design only the requested schema. Constraints, foreign keys, unique keys, indexes, other timestamps and lifecycle fields only when the requirements justify them. No invented tables, columns, audit fields, soft deletion or denormalization.
- Existing schemas are compatibility context, not templates to copy. Match their conventions where compatibility matters.
- No seed data unless the task asks for it.
- When a material domain rule or relationship is unclear, stop and ask instead of guessing.

## Example

[`citomni_demo-app.sql`](citomni_demo-app.sql): the `demo_articles` table of the article example. One check constraint limits `status` to the cases of `ArticleStatus`, another requires `published_at` on published rows, and the index serves the published list of `ArticleRepository`. The `demo_contact_messages` table of the contact form has no index beyond its key, because nothing reads it by another column.
