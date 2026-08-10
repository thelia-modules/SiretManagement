<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SiretManagement\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use SiretManagement\Controller\ThrottleTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;

/**
 * Minimal stand-in for a controller using ThrottleTrait. Real consumers get getRequest()
 * from Thelia\Controller\BaseController (backed by an injected RequestStack) and
 * jsonResponse() from the same class; this host reproduces just that contract so the
 * trait can be tested without booting the full Thelia DI container.
 */
final class ThrottleTraitHost
{
    use ThrottleTrait;

    public function __construct(
        private readonly Request $request,
        private readonly CacheItemPoolInterface $throttleCache,
    ) {
    }

    public function callIsThrottled(string $sessionKey, int $minIntervalSeconds = 3): bool
    {
        return $this->isThrottled($sessionKey, $minIntervalSeconds);
    }

    private function getRequest(): Request
    {
        return $this->request;
    }

    private function getThrottleCache(): CacheItemPoolInterface
    {
        return $this->throttleCache;
    }
}

class ThrottleTraitTest extends TestCase
{
    private function requestWithSession(array $server = []): Request
    {
        $request = Request::create('/register/checkVatNumber', server: $server);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    public function testFirstCallIsNeverThrottled(): void
    {
        $host = new ThrottleTraitHost($this->requestWithSession(), new ArrayAdapter());

        self::assertFalse($host->callIsThrottled('test.key'));
    }

    public function testSecondCallWithinWindowIsThrottled(): void
    {
        $host = new ThrottleTraitHost($this->requestWithSession(), new ArrayAdapter());

        self::assertFalse($host->callIsThrottled('test.key', 3));
        self::assertTrue($host->callIsThrottled('test.key', 3));
    }

    public function testSessionLessRequestFallsBackToIpCheck(): void
    {
        // No session attached at all: hasSession() is false, mirroring a stateless client
        // that never sends the session cookie. This is the case guarded by the commit
        // "guard session-less requests and fix IP throttle short-circuit" -- without it,
        // a session-less client would never be throttled.
        $request = Request::create('/register/checkVatNumber', server: ['REMOTE_ADDR' => '203.0.113.1']);
        $host = new ThrottleTraitHost($request, new ArrayAdapter());

        self::assertFalse($host->callIsThrottled('test.key', 3));
        self::assertTrue($host->callIsThrottled('test.key', 3));
    }

    public function testDifferentIpsAreThrottledIndependently(): void
    {
        $cache = new ArrayAdapter();
        $hostA = new ThrottleTraitHost(
            Request::create('/register/checkVatNumber', server: ['REMOTE_ADDR' => '203.0.113.10']),
            $cache
        );
        $hostB = new ThrottleTraitHost(
            Request::create('/register/checkVatNumber', server: ['REMOTE_ADDR' => '203.0.113.20']),
            $cache
        );

        self::assertFalse($hostA->callIsThrottled('test.key', 3));
        self::assertFalse($hostB->callIsThrottled('test.key', 3));
    }

    public function testIpCounterIsRefreshedEvenWhenSessionAlreadyThrottles(): void
    {
        // Regression test for the "IP throttle short-circuit" fix: both checks must run
        // unconditionally, so a session-scoped block must never leave the IP counter stale.
        // Seed a stale IP stamp (well outside the window) alongside a fresh session stamp
        // (inside the window), so only the session check throttles this call -- then assert
        // the IP stamp got refreshed anyway.
        $cache = new ArrayAdapter();
        $request = $this->requestWithSession();
        $host = new ThrottleTraitHost($request, $cache);

        $ip = $request->getClientIp() ?? 'unknown';
        $cacheKey = 'siretmanagement_throttle_'.md5('test.key|'.$ip);

        $staleStamp = time() - 100;
        $item = $cache->getItem($cacheKey);
        $item->set($staleStamp);
        $cache->save($item);
        $request->getSession()->set('test.key', time());

        self::assertTrue($host->callIsThrottled('test.key', 3));

        $refreshedStamp = $cache->getItem($cacheKey)->get();

        self::assertGreaterThan($staleStamp, $refreshedStamp);
    }
}
