<?php

namespace Omnidrive\Gdrive\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Omnidrive\Exception\InvalidConfigException;
use Omnidrive\Gdrive\GdriveDriveFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * The factory's wiring, offline: which credentials reach Google's token
 * endpoint, and the Drive API asked with the token it gives. The contract
 * against Google Drive itself is GdriveLiveTest's (an account of one's own).
 */
final class GdriveDriveTest extends TestCase
{
    /** @var list<RequestInterface> */
    private array $sent = [];

    private function http(): Client
    {
        return new Client(['handler' => HandlerStack::create(function (RequestInterface $request) {
            $this->sent[] = $request;
            if ('oauth2.googleapis.com' === $request->getUri()->getHost()) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"ya29.offline","expires_in":3599,"token_type":"Bearer"}'));
            }

            if (str_starts_with($request->getUri()->getPath(), '/batch/')) {
                // Drive's batch answer: one part per request, each an HTTP response of its own.
                preg_match_all('~Content-ID: <?([^>\r\n]+)>?~', (string) $request->getBody(), $ids);
                $body = '';
                foreach ($ids[1] as $id) {
                    $body .= "--batch_offline\r\nContent-Type: application/http\r\nContent-ID: response-$id\r\n\r\nHTTP/1.1 200 OK\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{\"files\":[]}\r\n";
                }

                return Create::promiseFor(new Response(200, ['Content-Type' => 'multipart/mixed; boundary=batch_offline'], $body.'--batch_offline--'));
            }

            return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], '{"files":[]}'));
        })]);
    }

    public function testARefreshTokenIsExchangedOnTheFirstOperationAndItsTokenAsksDrive(): void
    {
        $drive = (new GdriveDriveFactory($this->http()))->create(['client_id' => 'id.apps.googleusercontent.com', 'client_secret' => 'secret', 'refresh_token' => '1//refresh'], 'gdrive');

        self::assertSame('gdrive', $drive->name());
        self::assertSame(['local' => false, 'publicUrls' => false, 'temporaryUrls' => false], $drive->capabilities()->toArray());
        self::assertCount(0, $this->sent, 'nothing is sent when the drive is built');

        self::assertFalse($drive->exists('nothing-here.txt'));
        [$token, $api] = $this->firstTwo();
        parse_str((string) $token->getBody(), $form);
        self::assertSame(['https://oauth2.googleapis.com/token', 'refresh_token', '1//refresh', 'id.apps.googleusercontent.com'], [(string) $token->getUri(), $form['grant_type'] ?? null, $form['refresh_token'] ?? null, $form['client_id'] ?? null]);
        self::assertSame('www.googleapis.com', $api->getUri()->getHost());
        self::assertMatchesRegularExpression('~^/(batch/)?drive/v3~', $api->getUri()->getPath());
        self::assertSame('Bearer ya29.offline', $api->getHeaderLine('Authorization'));
    }

    public function testAServiceAccountSignsItsOwnAssertionForTheUserItActsAs(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        $json = (string) json_encode(['type' => 'service_account', 'project_id' => 'p', 'private_key_id' => 'k1', 'private_key' => $pem, 'client_email' => 'drive@p.iam.gserviceaccount.com', 'client_id' => '1', 'token_uri' => 'https://oauth2.googleapis.com/token']);

        $drive = (new GdriveDriveFactory($this->http()))->create(['service_account' => $json, 'subject' => 'site@example.org']);
        self::assertFalse($drive->exists('nothing-here.txt'));

        [$token, $api] = $this->firstTwo();
        parse_str((string) $token->getBody(), $form);
        self::assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type'] ?? null);
        $assertion = $form['assertion'] ?? null;
        self::assertIsString($assertion);
        [$header, $payload, $signature] = explode('.', $assertion) + ['', '', ''];
        /** @var array<string, string> $claims */
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        self::assertSame(['drive@p.iam.gserviceaccount.com', 'site@example.org', 'https://www.googleapis.com/auth/drive'], [$claims['iss'] ?? null, $claims['sub'] ?? null, $claims['scope'] ?? null]);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($details['key']);
        self::assertSame(1, openssl_verify("$header.$payload", (string) base64_decode(strtr($signature, '-_', '+/')), $details['key'], \OPENSSL_ALGO_SHA256), 'signed with the account\'s key');
        self::assertSame('Bearer ya29.offline', $api->getHeaderLine('Authorization'));
    }

    /** @return array{RequestInterface, RequestInterface} the token's request, then the Drive API's */
    private function firstTwo(): array
    {
        self::assertGreaterThanOrEqual(2, \count($this->sent));

        return [$this->sent[0], $this->sent[1]];
    }

    public function testCredentialsAreRequired(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('refresh_token (with client_id and client_secret) or service_account');
        (new GdriveDriveFactory())->create([]);
    }

    public function testARefreshTokenNeedsItsClient(): void
    {
        $this->expectException(InvalidConfigException::class);
        (new GdriveDriveFactory())->create(['refresh_token' => 'x']);
    }
}
