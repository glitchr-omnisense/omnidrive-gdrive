<?php

namespace Omnidrive\Gdrive;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Client;
use Google\Service\Drive as DriveService;
use GuzzleHttp\ClientInterface;
use Masbug\Flysystem\GoogleDriveAdapter;
use Omnidrive\Config;
use Omnidrive\Drive;
use Omnidrive\Exception\InvalidConfigException;
use Omnidrive\Flysystem\FlysystemDriveFactory;

/**
 * A folder of Google Drive - My Drive, a shared drive or a folder shared with
 * the account - through masbug/flysystem-google-drive-ext (google/apiclient),
 * paths by name as the Drive's web page shows them:
 *
 *   (new GdriveDriveFactory())->create(['client_id' => '...', 'client_secret' => '...', 'refresh_token' => '...', 'root' => 'Site/uploads']);
 *   (new GdriveDriveFactory())->create(['service_account' => '/run/secrets/drive.json', 'shared_drive_id' => '0ABC...']);
 *
 * Options: an OAuth client's client_id, client_secret and refresh_token (an
 * account's own Drive: the access token renewed on its own), or
 * service_account (the JSON key of a service account, a path or the JSON
 * itself) with subject (a Workspace user it acts as, by domain-wide
 * delegation); root (the folder behind the drive's paths, as a path of names -
 * or its ID with display_paths false); shared_drive_id (a shared drive),
 * shared_folder_id (a folder shared with the account); display_paths (true:
 * paths are names, as Drive shows them; false: paths are file IDs);
 * permanent_delete (false: a deleted file goes to the bin); public_url (Drive
 * serves no address of its own).
 *
 * A file "public" is shared with anyone who has the link (reader), "private"
 * not. With root as a path of names, the folder is looked up when the drive is
 * built (the adapter's way); otherwise nothing is sent until the first
 * operation.
 */
final class GdriveDriveFactory extends FlysystemDriveFactory
{
    public function __construct(private readonly ?ClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnidrive.factory_name' => 'gdrive',
            'omnidrive.factory_title' => 'A folder of Google Drive',
            'display_paths' => true,
            'permanent_delete' => false,
        ]);
    }

    protected function build(Config $c, string $name): Drive
    {
        $settings = ['application_name' => 'omnidrive'];
        $key = '' === $c->get('service_account') ? null : $c->get('service_account');
        if (null !== $key) {
            // The account's credentials given as such, not as "application default" ones: the adapter
            // would fetch those itself, through Google's default HTTP client rather than this one.
            $settings['credentials'] = new ServiceAccountCredentials(DriveService::DRIVE, self::serviceAccount($key), $c->string('subject'));
        }
        $client = new Client($settings);
        if (null !== $this->http) {
            $client->setHttpClient($this->http);
        }
        $client->setScopes([DriveService::DRIVE]);
        if (null === $key && null !== $refresh = $c->string('refresh_token')) {
            if (null === $c->string('client_id') || null === $c->string('client_secret')) {
                throw new InvalidConfigException('The "gdrive" drive\'s refresh_token needs client_id and client_secret.');
            }
            $client->setClientId((string) $c->string('client_id'));
            $client->setClientSecret((string) $c->string('client_secret'));
            // An expired access token with its refresh token: Google's client renews it on the first request.
            $client->setAccessToken(['access_token' => '', 'refresh_token' => $refresh, 'created' => 0, 'expires_in' => 0]);
        } elseif (null === $key) {
            throw new InvalidConfigException('The "gdrive" drive needs refresh_token (with client_id and client_secret) or service_account.');
        }

        $options = [
            'useDisplayPaths' => $c->bool('display_paths', true),
            'usePermanentDelete' => $c->bool('permanent_delete'),
        ];
        if (null !== $sharedDrive = $c->string('shared_drive_id')) {
            $options['teamDriveId'] = $sharedDrive;
        }
        if (null !== $sharedFolder = $c->string('shared_folder_id')) {
            $options['sharedFolderId'] = $sharedFolder;
        }

        return $this->drive($name, new GoogleDriveAdapter(new DriveService($client), $c->string('root'), $options), $c);
    }

    /** @return array<string, mixed> the JSON key: given as such, as its JSON, or as the path of its file */
    private static function serviceAccount(mixed $key): array
    {
        if (\is_string($key) && !str_starts_with(ltrim($key), '{')) {
            $json = @file_get_contents($key);
            if (false === $json) {
                throw new InvalidConfigException(\sprintf('The "gdrive" drive\'s service_account file cannot be read: %s.', $key));
            }
            $key = $json;
        }
        $key = \is_string($key) ? json_decode($key, true) : $key;
        if (!\is_array($key) || !isset($key['client_email'], $key['private_key'])) {
            throw new InvalidConfigException('The "gdrive" drive\'s service_account is not a service account\'s JSON key (client_email, private_key).');
        }

        /** @var array<string, mixed> $key */
        return $key;
    }
}
