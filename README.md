# Nalu AI WordPress plugin

Adds a Nalu AI v2 chatbot to a WordPress site. Setup and user-facing docs are in [readme.txt](readme.txt).

## Releasing a new version

Sites update from the latest GitHub release (`includes/updater.php`), so a release is all it takes
to ship an update to every site running 2.0.0 or later.

1. Bump the version in all three places, which `bin/build-zip.sh` checks:
   - `Version:` in the `nalu-ai.php` header
   - `NALU_AI_VERSION` in `nalu-ai.php`
   - `Stable tag:` in `readme.txt` (and add a changelog entry)
2. Merge to `main`.
3. Tag and push:

   ```bash
   git tag v2.1.0 && git push origin v2.1.0
   ```

The Release workflow builds `nalu-ai.zip` and publishes the GitHub release. Sites see the update
within about 12 hours, or right away from **Dashboard > Updates > Check again**.

Requirements for sites to find the update:

- The repository must be public, since sites download without credentials.
- The release must not be a draft or pre-release, its tag must be `vX.Y.Z`, and it must have the
  `nalu-ai.zip` asset.

To build the zip locally: `bin/build-zip.sh`. It packages the committed `HEAD`, not uncommitted changes.
