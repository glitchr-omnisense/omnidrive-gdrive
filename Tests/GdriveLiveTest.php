<?php

namespace Omnidrive\Gdrive\Tests;

use Omnidrive\Drive;
use Omnidrive\Gdrive\GdriveDriveFactory;
use Omnidrive\Testing\DriveContractTests;
use PHPUnit\Framework\TestCase;

/**
 * The contract against Google Drive itself, in a folder of an account of one's
 * own: OMNIDRIVE_GDRIVE_CLIENT_ID, OMNIDRIVE_GDRIVE_CLIENT_SECRET and
 * OMNIDRIVE_GDRIVE_REFRESH_TOKEN, or OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT (the
 * JSON key's path) with OMNIDRIVE_GDRIVE_SHARED_DRIVE_ID; the folder
 * OMNIDRIVE_GDRIVE_ROOT (omnidrive-live, a folder that exists). Skipped otherwise.
 */
final class GdriveLiveTest extends TestCase
{
    use DriveContractTests;

    protected function drive(): Drive
    {
        $env = static fn (string $name): ?string => false === getenv($name) || '' === getenv($name) ? null : (string) getenv($name);
        if (null === $env('OMNIDRIVE_GDRIVE_REFRESH_TOKEN') && null === $env('OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT')) {
            self::markTestSkipped('No Google Drive credentials given (OMNIDRIVE_GDRIVE_REFRESH_TOKEN or OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT).');
        }

        return (new GdriveDriveFactory())->create([
            'client_id' => $env('OMNIDRIVE_GDRIVE_CLIENT_ID'),
            'client_secret' => $env('OMNIDRIVE_GDRIVE_CLIENT_SECRET'),
            'refresh_token' => $env('OMNIDRIVE_GDRIVE_REFRESH_TOKEN'),
            'service_account' => $env('OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT'),
            'shared_drive_id' => $env('OMNIDRIVE_GDRIVE_SHARED_DRIVE_ID'),
            'root' => $env('OMNIDRIVE_GDRIVE_ROOT') ?? 'omnidrive-live',
        ], 'gdrive');
    }
}
