# wawoo-plugin-comments

Adds file-based comments to posts: an inline submit form and approved-comment list rendered into each post page, POST handling with CSRF protection, rate limiting and spam-style moderation (manual/auto), plus an admin moderation panel. Comments are stored as JSON under `CACHE_DIR/comments/`, one file per post.

## Install (requires wawoo-cms core)

```
cd /path/to/wawoo-cms
php bin/wawoo plugin:link /home/git/wawoo-plugin-comments
```

Then enable it: add `comments` to ENABLED_PLUGINS in config.local.php (or use the admin Plugins page).

## Test (testing contract)

Tests must run from inside a wawoo-cms checkout so core constants/classes are available:

```
cd /path/to/wawoo-cms
php bin/wawoo plugin:link /home/git/wawoo-plugin-comments   # symlinks plugins/comments + tests
phpunit tests/Plugin/CommentsInjectFormTest.php tests/Plugin/CommentsSecurityTest.php tests/Plugin/CommentsStoreTest.php
```

## Requirements

`wawoo-cms >= 1.0.0` (core manifest requires). PHP 8.3, no runtime deps.

### Test dependencies

The copied tests exercise only core + the comments plugin classes (`Form`, `Store`, `AdminPanel`, `Config`, `InjectForm`) — no other plugin needs to be enabled or linked for them to pass. `CommentsSecurityTest` spawns CLI subprocesses (`PHP_BINARY` + `shell_exec`) that need a working `session_save_path` and PHP session support.
