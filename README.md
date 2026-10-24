# omnidrive/gdrive

A folder of Google Drive - My Drive, a shared drive, or a folder shared with the account - as an
[omnidrive](https://github.com/glitchr-studio/omnidrive) drive, through
masbug/flysystem-google-drive-ext (google/apiclient): paths by name, as Drive's web page shows them.

```sh
composer require omniscient/omnidrive omnidrive/gdrive
```

```php
use Omnidrive\Gdrive\GdriveDriveFactory;
use Omnidrive\Registry;

$drives = new Registry([new GdriveDriveFactory()], [
    'drive' => ['factory' => 'gdrive', 'options' => ['client_id' => getenv('GOOGLE_CLIENT_ID'), 'client_secret' => getenv('GOOGLE_CLIENT_SECRET'), 'refresh_token' => getenv('GOOGLE_REFRESH_TOKEN'), 'root' => 'Site/uploads']],
    'team' => ['factory' => 'gdrive', 'options' => ['service_account' => '/run/secrets/drive.json', 'shared_drive_id' => '0ABCdefGHIjk']],
]);
```

| Option | Default | |
|---|---|---|
| `client_id`, `client_secret`, `refresh_token` | | an OAuth client and an account's refresh token: the access token renewed on its own |
| `service_account` | | instead: a service account's JSON key - its path, the JSON, or the array |
| `subject` | | with a service account: the Workspace user it acts as (domain-wide delegation) |
| `root` | the Drive's root | the folder behind the drive's paths: a path of names, or an ID with `display_paths` false |
| `shared_drive_id`, `shared_folder_id` | | a shared drive, a folder shared with the account |
| `display_paths` | true | paths are names; false: paths are file IDs |
| `permanent_delete` | false | false: a deleted file goes to the bin |
| `public_url` | | Drive serves no public address of its own |

A file "public" is shared with anyone who has the link (reader), "private" is not. With `root` as a
path of names the folder is looked up when the drive is built (the adapter's way); otherwise nothing
is sent until the first operation. The service account's credentials are given to Google's client as
such, so its token is fetched through the HTTP client the factory is given - as application-default
credentials the adapter would fetch it through Google's default one. google/apiclient brings
google/apiclient-services, every Google API's classes: Google's README gives the Composer script
that keeps only Drive's.

## Verified, and not

- Verified (2026-10-24, omnidrive's harness, PHP 8.4), offline: the refresh token exchanged at
  Google's token endpoint on the first operation, not when the drive is built, and Drive's API asked
  with the token it gives; a service account's assertion signed with its key, for its subject and
  Drive's scope; the options required.
- Not verified: Google Drive itself - no account here: `GdriveLiveTest` runs the contract against an
  account with `OMNIDRIVE_GDRIVE_CLIENT_ID`, `_CLIENT_SECRET` and `_REFRESH_TOKEN`, or
  `OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT` (and `_SHARED_DRIVE_ID`), in the folder `OMNIDRIVE_GDRIVE_ROOT`;
  so neither the contract's operations, visibility as sharing, shared drives, nor Google Docs files
  (exported by the adapter) have been seen against Drive.

License: MIT. Unofficial; not affiliated with Google or masbug.
