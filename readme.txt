=== ORBIT Task Management ===
Contributors: ilorininnovationhub
Requires at least: 5.6
Tested up to: 6.x
Requires PHP: 7.0
Stable tag: 0.1.8
License: GPLv2 or later

Minimal tasks, public submissions thread, moderation, and native points. BuddyBoss/BuddyPress optional. Each task has a pretty permalink under /tasks and displays a public submission thread and an adaptive submission form.

== Description ==
ORBIT Task Management (OTM) is a minimal, native WordPress plugin for running tasks and collecting intern submissions. Moderators create tasks; interns submit on the task’s public URL. BuddyBoss/BuddyPress groups are treated as Streams (optional).

Key features:
* Custom Post Type `otm_task` with archive at /tasks
* Public single task page with submission thread and form
* One submission per user per task; moderation with points
* Optional BuddyBoss/BuddyPress Streams integration (group tab lists tasks and links to each task page)
* Native points service (no GamiPress dependency)

== Installation ==
1. Upload the `orbit-task-management` folder to `/wp-content/plugins/`.
2. Activate the plugin through the ‘Plugins’ screen.
3. Visit OTM → Settings to configure defaults.
4. Permalinks: activation flushes rewrites, but if URLs 404, save Settings → Permalinks.

== Usage ==
Create a task (CPT `OTM Task`) and publish. Share its URL under `/tasks/{slug}`. Interns submit at the bottom of the task page if eligible.

== Shortcodes ==
Note: Submissions occur only on single task pages.
* `[otm_task_submit]` – Only renders on single `otm_task` pages (otherwise shows a guidance message).
* `[otm_task_create stream_id="12"]` – Quick-create form for moderators.
* `[otm_leaderboard ...]` – Placeholder for leaderboards.

== Screenshots ==
1. Single task page with public submission thread and form.
2. Group tab listing tasks with “Open Task” links.
3. Admin submissions list with approve/reject and points.

== Changelog ==
= 0.1.8 =
* Updated plugin author to Ilorin Innovation Hub
* Fixed GamiPress settings persistence and sanitization
* Enhanced settings validation and error handling

= 0.1.5 =
* Make `otm_task` public with archive `/tasks` and slug rewrites.
* Add template loader and plugin template for single task pages.
* Render public submission thread (newest first) and adaptive form.
* Restrict `[otm_task_submit]` to single task pages.
* Group tab: remove inline submission; add “Open Task” link to single pages.
* Settings: default max points, require membership to submit (BuddyBoss only).
* Activation: flush rewrite rules.

= 0.1.4 =
* Admin submissions screen and scoring flow.
* Native points service (log and totals).

= 0.1.3 =
* Initial CPT, settings skeleton, and assets scaffolding.

== Upgrade Notice ==
= 0.1.8 =
Plugin author updated to Ilorin Innovation Hub. GamiPress settings now persist correctly.

= 0.1.5 =
Public tasks now live under `/tasks`. After updating, visit Settings → Permalinks and click Save if you see 404s.
