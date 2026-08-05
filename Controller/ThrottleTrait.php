<?php

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

use SiretManagement\SiretManagement;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;

/**
 * Lightweight throttle for endpoints that trigger a real call to VIES. Combines a
 * per-session check (fast path for a normal browser) with a per-IP check backed by
 * the shared cache pool, since a client that never sends the session cookie would
 * otherwise never be throttled.
 */
trait ThrottleTrait
{
    private function isThrottled(string $sessionKey, int $minIntervalSeconds = 3): bool
    {
        $now = time();

        $session = $this->getSession();
        $lastSessionCall = $session->get($sessionKey);
        $sessionThrottled = null !== $lastSessionCall && ($now - $lastSessionCall) < $minIntervalSeconds;
        $session->set($sessionKey, $now);

        return $sessionThrottled || $this->isIpThrottled($sessionKey, $minIntervalSeconds, $now);
    }

    private function isIpThrottled(string $sessionKey, int $minIntervalSeconds, int $now): bool
    {
        $ip = $this->getRequest()->getClientIp() ?? 'unknown';
        $item = $this->throttleCache->getItem('siretmanagement_throttle_'.md5($sessionKey.'|'.$ip));

        $lastIpCall = $item->isHit() ? $item->get() : null;
        $throttled = null !== $lastIpCall && ($now - $lastIpCall) < $minIntervalSeconds;

        $item->set($now);
        $item->expiresAfter($minIntervalSeconds);
        $this->throttleCache->save($item);

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
