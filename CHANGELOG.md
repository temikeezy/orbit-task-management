## Changelog

### 0.1.7
- **Enhanced Submissions Screen**: Complete overhaul with modern WordPress admin best practices
- **Advanced Filtering**: Filter by task name (dropdown) instead of ID, status filter with counts
- **Search Functionality**: Full-text search across task titles, user names, emails, and usernames
- **Flexible Pagination**: Configurable items per page (10, 20, 50, 100) with proper pagination
- **Sortable Columns**: Click-to-sort on all columns (ID, Task, User, Status, Points, Submitted)
- **Enhanced User Display**: User column shows name, email, and links to user profiles
- **Status Badges**: Color-coded status indicators with proper styling
- **Responsive Design**: Mobile-friendly layout with adaptive controls
- **Modern UI**: WordPress-standard styling with improved accessibility

### 0.1.6
- **Dashboard Redesign**: Complete overhaul with modern card-based design
- **Visual Statistics**: Added animated stat cards with icons and color-coded themes  
- **Interactive Charts**: Implemented submission status overview with animated progress bars
- **Recent Activity**: Added recent tasks and submissions cards with detailed metadata
- **Enhanced UX**: Improved button styling, hover effects, and responsive design
- **Dark Mode Support**: Added dark mode compatibility for better accessibility

### 0.1.5
- Make `otm_task` public with archive `/tasks` and slug rewrites.
- Add template loader and plugin template for single task pages.
- Render public submission thread (newest first) and adaptive submission form.
- Enforce one submission per user; add deadline and membership checks.
- Restrict `[otm_task_submit]` to single task pages.
- Group tab: remove inline submission; add “Open Task” button linking to single pages.
- Settings: default max points, require membership to submit (BuddyBoss only).
- Activation: flush rewrite rules.

### 0.1.4
- Admin submissions screen with approve/reject and points.
- Native points service (log and totals), cache buster for leaderboards.

### 0.1.3
- Initial CPT, settings skeleton, and asset scaffolding.



