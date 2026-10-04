CASTRO'S READY - INSTALLER / DATABASE PATCH

Only changed files are included.

1) install/index.php
   - 3-step responsive installation assistant.
   - Step X of 3 indicator.
   - Database -> CMS Ready -> Administrator visual progress.
   - Buttons have pointer/hand cursor, hover/focus states and arrow CTA.

2) admin/setup.php
   - Shows Step 3 of 3 during first-time installation.
   - Responsive step status.
   - Final create-administrator CTA includes pointer cursor and arrow.
   - Reset-owner flow remains separate and unchanged in behavior.

3) database-update.sql
   - Cumulative update including the latest CMS/client media changes.
   - Safe re-run guards for tables, columns/indexes, seeds, permissions,
     videos and Mission/Vision artwork records.
   - Use this file on an EXISTING database.

4) database.sql
   - Complete fresh-install schema and current default data.
   - Includes badges, videos, About artwork gallery and approved YouTube videos.
   - Use this file ONLY for a fresh installation (the installer runs it automatically).
