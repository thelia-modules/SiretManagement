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

namespace SiretManagement\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Thelia\Domain\Legal\Enum\VatVerificationStatus;
use Thelia\Domain\Legal\Service\VatNumberVerifierInterface;
use Thelia\Domain\Legal\VatVerificationResult;

#[AsAlias(VatNumberVerifierInterface::class)]
final readonly class VatNumberVerifier implements VatNumberVerifierInterface
{
    private const int REFUSAL_KEPT_FOR_SECONDS = 600;

    private const array PREFIXES_BY_COUNTRY = ['GR' => ['EL', 'GR']];

    public function __construct(
        private VatExistenceChecker $vatExistenceChecker,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function verify(string $vatNumber, string $countryIsoAlpha2): VatVerificationResult
    {
        $normalized = strtoupper(preg_replace('/\s+/u', '', $vatNumber) ?? '');
        $country = strtoupper($countryIsoAlpha2);

        $prefix = null;

        foreach (self::PREFIXES_BY_COUNTRY[$country] ?? [$country] as $candidate) {
            if (str_starts_with($normalized, $candidate)) {
                $prefix = $candidate;
                break;
            }
        }

        if (null === $prefix) {
            return VatVerificationResult::refused(new \DateTimeImmutable());
        }

        if ('GR' === $prefix) {
            $normalized = 'EL'.substr($normalized, 2);
        }

        $refusal = $this->cache->getItem('siret_management.vat_refused.'.hash('xxh128', $normalized));

        if ($refusal->isHit()) {
            return VatVerificationResult::refused(new \DateTimeImmutable());
        }

        $result = $this->answerOfVies($normalized);

        if (VatVerificationStatus::REFUSED === $result->status) {
            $this->cache->save($refusal->set(true)->expiresAfter(self::REFUSAL_KEPT_FOR_SECONDS));
        }

        return $result;
    }

    private function answerOfVies(string $vatNumber): VatVerificationResult
    {
        $outcome = $this->vatExistenceChecker->checkExistence($vatNumber);

        if (!$outcome['ok']) {
            return $outcome['transient']
                ? VatVerificationResult::undetermined()
                : VatVerificationResult::refused(new \DateTimeImmutable());
        }

        return $outcome['valid']
            ? VatVerificationResult::verified(new \DateTimeImmutable(), $outcome['name'])
            : VatVerificationResult::refused(new \DateTimeImmutable());
    }
}
