# Releasing to WordPress.org

Tango Form Wire lives in two places: this GitHub repo (source of truth for
development) and a separate WordPress.org SVN repo (what actually gets
published and installed by users). Pushing to GitHub does **not** publish
anything - you need an extra step to release.

## One-time setup

You need the `svn` command line tool:

```bash
brew install subversion
```

You'll also need your WordPress.org **SVN password** (Users > Profile on
wordpress.org - this is separate from your normal login password, especially
if you have two-factor authentication on). The first time you commit, `svn`
will ask for it; after that, macOS Keychain remembers it for you.

## Releasing a new version

1. **Bump the version** in two places, and make sure they match exactly:
   - `Version:` in `tango-form-wire.php`
   - `Stable tag:` in `readme.txt`

   (The release script checks this and refuses to run if they don't match.)

2. **Commit your code changes to git as normal:**
   ```bash
   git add ...
   git commit -m "..."
   git push origin main
   ```

3. **Run the release script**, from a real terminal (not through an
   assistant - it needs your password interactively):
   ```bash
   bin/release-to-svn.sh
   ```

   This will:
   - check out (or update) a local SVN working copy at `.svn-wc/`
   - copy your current plugin files into `trunk`
   - cut a new `tags/<version>` snapshot
   - show you exactly what's about to change (`svn status`)
   - ask **`Commit and publish version X.Y.Z to WordPress.org now? [y/N]`**

4. Type `y` and enter your SVN password if asked. You'll see a
   `Committed revision NNNNNN.` line when it's done - that's your
   confirmation it actually went live.

5. Check `https://wordpress.org/plugins/tango-form-wire/` after a minute or
   two. Code and readme changes tend to show up almost immediately.

### Just want to check what would change, without publishing?

```bash
bin/release-to-svn.sh --no-commit
```

Stops right before the commit step and tells you the exact command to run
later when you're ready.

## What this script does NOT do

- **Icon, banner and screenshots** (`assets/` on WordPress.org) aren't
  tracked in this git repo, so the script never touches them. Updating
  those is a manual step: copy the new file(s) into
  `.svn-wc/assets/`, then `cd .svn-wc && svn commit -m "..." --username tangodevelopment`.
- **It won't let you re-tag a version that's already published.** If you
  forgot to bump the version number, it'll tell you instead of silently
  overwriting an existing release tag.
- **It won't commit anything without you typing `y`.** Reviewing the
  `svn status` output before confirming is worth doing every time.

## Caching quirks worth knowing about

WordPress.org's plugin page renders images through a separate CDN layer
(`i0.wp.com`, Jetpack's "Photon"), which caches very aggressively (up to
2 years per exact URL). If you update an asset and the raw SVN file is
already fixed but the plugin page still shows the old one, that's this
CDN layer, not your commit. It resolves itself once WordPress.org
regenerates the plugin page with a new revision-tagged image URL, which
can take longer than the raw SVN update itself. If it's still stale after
24-48 hours, email plugins@wordpress.org rather than committing again.
