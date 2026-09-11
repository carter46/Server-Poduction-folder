Private APK store — not publicly URL-accessible.

Official flow: Expo/EAS preview build → `Mobile_app` scripts download the artifact → validate → publish here → commit these files → pull/deploy production.

- `banking-companion.apk` — written by `Mobile_app/scripts/publish-apk.ps1` (via `publish-from-eas.ps1` / `build-and-publish-eas.ps1`) after a successful validated publish
- `apk-meta.json` — version / built_at / file_size / eas_build_id

These two files are **commit-able** so production can pull them. Do not rely on temporary Expo download URLs for admin.

Download via admin session: `api/mobile_apk_download.php`
