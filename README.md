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

## CI

GitHub Actions runs on PHP 8.2 & 8.3 — it clones wawoo-cms core, links this plugin via `php bin/wawoo plugin:link --copy`, and runs `phpunit tests/Plugin/Comments*Test.php`.

## Dependencies

Runtime: `wawoo-cms >= 1.0.0` (this plugin's manifest requires core).

Stores runtime data under `cache/comments/` inside wawoo-cms (web-denied cache volume). No cross-plugin dependency.

## Release

Manifests require `core >=1.0.0`; this repo is tagged `v1.0.0`; bump manifest + tag together on releases.

## Versioning, update & rollback

- **Compatible core**: this plugin targets wawoo-cms `>= 1.0.0`
  (`plugin.json` → `requires.core`). Using it with a newer core is fine as
  long as the manifest constraint is satisfied.
- **Pin a deployment**: each standalone repo is versioned independently.
  Record the exact combination you run, e.g. wawoo-cms @ v1.0.0 + this
  plugin @ v1.0.0. To pin, install from a specific tag/path rather than a
  moving branch:

      cd /path/to/wawoo-cms
      git -C /home/git/wawoo-plugin-comments checkout v1.0.0
      php bin/wawoo plugin:link /home/git/wawoo-plugin-comments

  For self-contained Docker images use `--copy` (symlinks dangle in an
  image):

      php bin/wawoo plugin:link /home/git/wawoo-plugin-comments --copy

- **Configuration & persistent data**: Comments (incl. pending moderation,
  author email/IP/UA) are stored under the cache state volume at
  `cache/comments/<post-slug>.json`. Configure moderation/limits via
  `$GLOBALS['WAWOO_COMMENTS']` (keys: moderation, max_length, per_page,
  rate_limit_per_hour). No env vars. Back this cache volume up before
  updates because it holds unapproved comments + PII.
- **Before updating**: back up the cache state volume that holds this
  plugin's runtime state, and test against a wawoo-cms checkout:

      # backup (example): cache volume = wawoo_state (prod) or wawoo_dev_cache (dev)
      docker run --rm -v <cache-volume>:/data -v $PWD:/backup alpine \
        tar czf /backup/cache.tgz -C /data .
      cd /path/to/wawoo-cms
      php bin/wawoo plugin:link /home/git/wawoo-plugin-comments   # new version
      phpunit tests/Plugin/Comments*Test.php

- **Rollback**: `git checkout` the previously recorded tag in this repo,
  re-run `plugin:link` (with `--copy` for Docker), then restore the cache
  volume backup from step above and restart.
