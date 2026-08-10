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

namespace SiretManagement\Controller;

use Psr\Cache\CacheItemPoolInterface;
use SiretManagement\SiretManagement;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;

/**
 * Lightweight throttle for endpoints that trigger a real call to VIES. Combines a
 * per-session check (fast path for a normal browser) with a per-IP check backed by
 * the shared cache pool, since a client that never sends the session cookie would
 * otherwise never be throttled.
 *
 * Consumers must provide $this->throttleCache via getThrottleCache() -- declared as
 * abstract below rather than assumed as an implicit property, so a class using this
 * trait without wiring it fails at compile time instead of a PHPStan "undefined
 * property" surprise.
 */
trait ThrottleTrait
{
    abstract private function getThrottleCache(): CacheItemPoolInterface;

    private function isThrottled(string $sessionKey, int $minIntervalSeconds = 3): bool
    {
        $now = time();

        // getSession() throws SessionNotFoundException outside of a request with a
        // session (e.g. a stateless API call): fall back to the IP-only check rather
        // than crashing the endpoint.
        $sessionThrottled = false;
        $request = $this->getRequest();
        if ($request->hasSession()) {
            $session = $request->getSession();
            $lastSessionCall = $session->get($sessionKey);
            $sessionThrottled = null !== $lastSessionCall && ($now - $lastSessionCall) < $minIntervalSeconds;
            $session->set($sessionKey, $now);
        }

        // Both checks are evaluated unconditionally (no ||-short-circuit): the IP
        // counter must be refreshed on every call, even when the session already
        // throttled the request, otherwise a session-scoped block leaves the IP
        // counter stale and the very next request past that block sails through.
        $ipThrottled = $this->isIpThrottled($sessionKey, $minIntervalSeconds, $now);

        return $sessionThrottled || $ipThrottled;
    }

    private function isIpThrottled(string $sessionKey, int $minIntervalSeconds, int $now): bool
    {
        $ip = $this->getRequest()->getClientIp() ?? 'unknown';
        $cache = $this->getThrottleCache();
        $item = $cache->getItem('siretmanagement_throttle_'.md5($sessionKey.'|'.$ip));

        $lastIpCall = $item->isHit() ? $item->get() : null;
        $throttled = null !== $lastIpCall && ($now - $lastIpCall) < $minIntervalSeconds;

        $item->set($now);
        $item->expiresAfter($minIntervalSeconds);
        $cache->save($item);

        return $throttled;
    }

    private function throttledJsonResponse(): Response
    {
        return $this->jsonResponse(json_encode(
            [
                'success' => false,
                'found' => false,
                'available' => false,
                'message' => Translator::getInstance()?->trans(
                    'Please wait a few seconds before testing again.',
                    [],
                    SiretManagement::DOMAIN_NAME
                ),
            ],
            JSON_THROW_ON_ERROR
        ), 429);
    }
}
