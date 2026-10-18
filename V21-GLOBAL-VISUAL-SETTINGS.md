# NOWRD CMS v21 — Global Visual Settings

Visual preferences now persist in the `settings` table as `nowrd_visual_preferences` and are applied to public pages (`index.php`, `article.php`) as well as authenticated admin pages. Only authorized admin users can change global preferences through `admin/visual-settings.php`; public visitors cannot write settings. Existing sites use an additive settings row, so no table migration is required. Settings are saved automatically from the admin visual panel. Test on staging first and back up files/database before upgrading.

Included preferences: Classic/Glassmorphism, light/dark/system, palette, motion, blur, dock size/position and dock opacity. Public pages apply global theme and palette without showing the admin navigation dock.
