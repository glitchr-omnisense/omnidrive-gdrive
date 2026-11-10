<?php

namespace Omnidrive\Gdrive\Tests;

use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\Scope;
use Omnisense\Contracts\Policy\Unit;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/** Its usage policy, as data: Google Drive's published quotas, in quota units, per project and per user. */
final class PolicyTest extends TestCase
{
    public function testThePackageDeclaresItsPolicy(): void
    {
        $policy = UsagePolicy::fromFile(__DIR__.'/../policy.json');

        self::assertSame('gdrive', $policy->provider, 'named after the factory');
        $limits = (array) $policy->rateLimits;
        self::assertCount(2, $limits);
        self::assertSame([1000000, 325000], [$limits[0]->max, $limits[1]->max]);
        self::assertSame([Unit::Units, Unit::Units], [$limits[0]->unit, $limits[1]->unit]);
        self::assertSame([Scope::Application, Scope::User], [$limits[0]->scope, $limits[1]->scope]);
        self::assertSame(200, $limits[0]->cost['read'], 'a download');
        self::assertSame(50, $limits[0]->cost['write'], 'an edit method');
        self::assertSame(Permission::Forbidden, $policy->rights->redistribution, 'user data is never transferred nor sold');
        self::assertTrue($policy->requirements->key);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertMatchesRegularExpression('~"omnisense": \{\s*"policy": "policy\.json"~', (string) file_get_contents(__DIR__.'/../composer.json'), 'composer.json names it');
    }
}
