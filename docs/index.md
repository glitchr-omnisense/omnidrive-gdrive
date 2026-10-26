# omnidrive/gdrive

A folder of Google Drive - My Drive, a shared drive, or a folder shared with the account - as an
[omnisense/omnidrive](https://github.com/glitchr-omnisense/omnidrive) drive, through
masbug/flysystem-google-drive-ext (`GoogleDriveAdapter` over google/apiclient's Drive v3 service):
paths by name, as Drive's web page shows them. The token is asked at Google's OAuth endpoint
(`https://oauth2.googleapis.com/token`), the files at the Drive API (`https://www.googleapis.com/drive/v3/...`).

## Installation

```sh
composer require omnisense/omnidrive omnidrive/gdrive
```

Requires PHP 8.2, `omnisense/omnidrive` (1.x), `masbug/flysystem-google-drive-ext` (^2.5),
`google/apiclient` (^2.15), `google/auth` (^1.37) and `guzzlehttp/guzzle` (^7.0).
google/apiclient brings google/apiclient-services, every Google API's classes: Google's README
gives the Composer script that keeps only Drive's.

## Credentials

Either an OAuth client of a Google Cloud project with the Drive API enabled (`client_id`,
`client_secret`) and an account's **refresh token** given to that client (scope
`https://www.googleapis.com/auth/drive`); or a **service account**'s JSON key (its path, its JSON
or the decoded array), which sees only what is shared with it - a shared drive, a shared folder -
or, with `subject`, acts as a Workspace user by domain-wide delegation.

## Options

`new GdriveDriveFactory(?GuzzleHttp\ClientInterface $http = null)` (Google's client's own when
`null`); factory name `gdrive`; no option is declared required, but one of the two credentials is.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `client_id`, `client_secret`, `refresh_token` | string | none | these, or `service_account` | |
| `service_account` | string or array | none | this, or the three above | the JSON key: a path, the JSON, or the array (`client_email`, `private_key`) |
| `subject` | string | none | no | with a service account: the Workspace user it acts as |
| `root` | string | the Drive's root | no | a path of names, or a folder ID with `display_paths: false` |
| `shared_drive_id` | string | none | no | a shared drive (the adapter's `teamDriveId`) |
| `shared_folder_id` | string | none | no | a folder shared with the account |
| `display_paths` | bool | `true` | no | paths are names; `false`: file IDs |
| `permanent_delete` | bool | `false` | no | `false`: a deleted file goes to the bin |
| `public_url` | string or list | none | no | the prefix files are served from, or several (sharded) |
| `visibility` | `public`/`private` | the adapter's | no | a write's visibility when it does not say |
| `directory_visibility` | `public`/`private` | the adapter's | no | the same for directories |
| `retain_visibility` | bool | `true` | no | whether a copy keeps its source's visibility |

## Capabilities

| Capability | |
|---|---|
| `local` | no |
| `publicUrls` | with `public_url` (Drive serves no public address of its own) |
| `temporaryUrls` | no |
| visibility per file | yes: `public` is shared with anyone who has the link (reader), `private` not |
| checksum | the adapter's |

## Behaviour

The scope is Drive's full scope. With a refresh token, the client holds an expired access token
and its refresh token: Google's client renews it on the first request. A service account's
credentials are given to Google's client as such (`ServiceAccountCredentials`), so its token is
fetched through the HTTP client the factory is given. With `root` as a path of names the folder is
looked up when the drive is built (the adapter's way); otherwise nothing is sent until the first
operation. Google Docs files are exported by the adapter when read.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnidrive\Gdrive\GdriveDriveFactory;
use Omnidrive\Registry;

$drives = new Registry([new GdriveDriveFactory()], [
    'drive' => ['factory' => 'gdrive', 'options' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'refresh_token' => getenv('GOOGLE_REFRESH_TOKEN'),
        'root' => 'Site/uploads',                         // a path of names: looked up when the drive is built
    ]],
    'team' => ['factory' => 'gdrive', 'options' => ['service_account' => '/run/secrets/drive.json', 'shared_drive_id' => '0ABCdefGHIjk']],
]);

$drives->get('drive')->write('reports/2026-10.csv', "month,visits\n2026-10,1200\n", ['visibility' => 'public']);
echo json_encode($drives->get('drive')->capabilities()->toArray()), "\n";   // {"local":false,"publicUrls":false,"temporaryUrls":false}
foreach ($drives->get('team')->list('', deep: true) as $entry) {
    echo $entry->path, "\n";
}
```

(not run: no Google account in the harness; the capabilities line is what the drive answers, probed
in the harness on 2026-10-25 without reaching Google)

## Errors

Google's refusals (an invalid grant, a missing permission, a quota) reach the drive through the
adapter as `DriveException`s; a missing file read is a `FileNotFoundException`. A `refresh_token`
without `client_id` and `client_secret`, no credential at all, or a `service_account` that cannot
be read or is not a service account's key is an `InvalidConfigException`.

## Verified, and not

Verified (2026-10-24, omnidrive's harness, PHP 8.4), offline: the refresh token exchanged at
Google's token endpoint on the first operation, not when the drive is built, and Drive's API asked
with the token it gives; a service account's assertion signed with its key, for its subject and
Drive's scope; the options required. Not verified: Google Drive itself - no account here
(`GdriveLiveTest` runs the contract with `OMNIDRIVE_GDRIVE_CLIENT_ID`, `_CLIENT_SECRET` and
`_REFRESH_TOKEN`, or `OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT` and `_SHARED_DRIVE_ID`, in the folder
`OMNIDRIVE_GDRIVE_ROOT`): neither the contract's operations, visibility as sharing, shared drives
nor Google Docs files have been seen against Drive.

## Responsibility

An independent, unofficial client, not affiliated with Google or masbug. Whoever runs it is responsible for the data it stores and the
access it gives (a public or temporary address serves the file to whoever holds it); see the core's
Responsibility section ([omnisense/omnidrive](https://github.com/glitchr-omnisense/omnidrive#responsibility)).
