# NOWRD CMS v17 — Role-Based Access Control

## Roles

- **Administrator**: full access to content, media, taxonomies, settings, user management, and maintenance.
- **Editor**: manages all stories, Media Library, categories, and tags; cannot manage accounts, global settings, or maintenance.
- **Author**: views the dashboard and creates, edits, restores, or deletes only their own stories. Authors cannot access media management, taxonomy management, global settings, account management, or maintenance.

## Upgrade

1. Back up the database, `config.php`, and `uploads/` before upgrading.
2. Upload the v17 files over the existing NOWRD installation, preserving the existing configuration and uploaded files.
3. Sign in as an administrator. The first permission check adds the `role` column to `users` if needed. Existing users are assigned `administrator` by the migration to avoid unexpectedly removing existing access.
4. Open **Admin accounts**. Assign the least-privileged role that matches each person's work.
5. Test each role in a separate private browser session before relying on it in production.

## Notes

- The migration is additive and idempotent; it does not recreate existing users or articles.
- The application role check is performed on the server for each protected route. Hiding a navigation link is not considered access control.
- Authors are restricted to stories whose `posts.author_id` matches their account ID. Existing stories with no author assigned are not editable by Authors.
- This release has not been tested against a live hosting database. Back up before deployment.
