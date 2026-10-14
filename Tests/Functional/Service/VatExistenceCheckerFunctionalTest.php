<?php

declare(strict_types=1);

namespace SiretManagement\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SiretManagement\Service\IntraCommunityVatChecker;
use SiretManagement\Service\VatExistenceChecker;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Translation\Translator;

/**
 * Live check: makes REAL HTTP calls to the VIES API (ec.europa.eu). SiretManagement\Tests\Unit\Service\VatExistenceCheckerTest
 * covers the same answers (valid, unknown, unreachable, rate-limited) with a simulated client.
 *
 * Opt-in, in the `vies-live` group: it only runs with SIRET_MANAGEMENT_LIVE_VIES=1 in the environment. VIES answers
 * repeated calls with a rate limit (MS_MAX_CONCURRENT_REQ), so a default run must never depend on it.
 */
#[Group('vies-live')]
class VatExistenceCheckerFunctionalTest extends TestCase
{
    private VatExistenceChecker $checker;

    public static function setUpBeforeClass(): void
    {
        try {
            Translator::getInstance();
        } catch (\RuntimeException) {
            new Translator(new RequestStack());
        }
    }

    protected function setUp(): void
    {
        if ('1' !== getenv('SIRET_MANAGEMENT_LIVE_VIES')) {
            $this->markTestSkipped('Live VIES check, run with SIRET_MANAGEMENT_LIVE_VIES=1.');
        }

        $this->checker = new VatExistenceChecker(new NullLogger(), new IntraCommunityVatChecker(), HttpClient::create());

        $availability = $this->checker->checkApiAvailability();
        if (!$availability['available']) {
            $this->markTestSkipped('VIES is currently unreachable: '.$availability['message']);
        }
    }

    public function testCheckApiAvailabilityAgainstLiveVies(): void
    {
        $result = $this->checker->checkApiAvailability();

        $this->assertTrue($result['available']);
    }

    public function testCheckDoesNotThrowForARealExistingFrenchVatNumber(): void
    {
        // SA SODIMAS, verified live during development
        $this->checker->check('FR40303265045');
        $this->addToAssertionCount(1);
    }

    public function testCheckDoesNotThrowForASyntacticallyValidButNonExistingVatNumber(): void
    {
        $this->checker->check('FR99999999999');
        $this->addToAssertionCount(1);
    }

    public function testTestVatNumberAgainstLiveViesForAnExistingNumber(): void
    {
        $result = $this->checker->testVatNumber('FR40303265045');

        $this->assertTrue($result['success']);
    }

    public function testTestVatNumberAgainstLiveViesForANonExistingNumber(): void
    {
        $result = $this->checker->testVatNumber('FR99999999999');

        $this->assertTrue($result['success']);
    }

    public function testTestVatNumberAgainstLiveViesForAnInvalidFormat(): void
    {
        $result = $this->checker->testVatNumber('NOTAVALIDVATNUMBER');

        $this->assertFalse($result['success']);
    }

    public function testCheckExistenceAgainstLiveViesForAnExistingNumber(): void
    {
        $outcome = $this->checker->checkExistence('FR40303265045');

        $this->assertTrue($outcome['ok']);
        $this->assertTrue($outcome['valid']);
        $this->assertSame('SA SODIMAS', $outcome['name']);
    }

    public function testCheckExistenceAgainstLiveViesForANonExistingNumber(): void
    {
        $outcome = $this->checker->checkExistence('FR99999999999');

        $this->assertTrue($outcome['ok']);
        $this->assertFalse($outcome['valid']);
    }

    public function testCheckExistenceAgainstLiveViesForAnInvalidFormat(): void
    {
        $outcome = $this->checker->checkExistence('NOTAVALIDVATNUMBER');

        $this->assertFalse($outcome['ok']);
        $this->assertTrue($outcome['transient']);
    }
}
