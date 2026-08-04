<?php

declare(strict_types=1);

namespace SiretManagement\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SiretManagement\Service\IntraCommunityVatChecker;
use SiretManagement\Service\VatExistenceChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Translation\Translator;

class VatExistenceCheckerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Translator::getInstance() is normally set up by TheliaHttpKernel; outside of a full
        // kernel boot (plain unit test) it must be instantiated manually so trans() calls in
        // VatExistenceChecker don't throw. No catalogue is loaded, so trans() falls back to
        // returning the source string, which is enough for these tests.
        try {
            Translator::getInstance();
        } catch (\RuntimeException) {
            new Translator(new RequestStack());
        }
    }

    private function makeChecker(array $httpGetResponses, array $httpPostResponses, LoggerInterface $logger): VatExistenceChecker
    {
        return new class($logger, $httpGetResponses, $httpPostResponses) extends VatExistenceChecker {
            private array $getResponses;
            private array $postResponses;

            public function __construct(LoggerInterface $logger, array $getResponses, array $postResponses)
            {
                parent::__construct($logger, new IntraCommunityVatChecker());
                $this->getResponses = $getResponses;
                $this->postResponses = $postResponses;
            }

            protected function httpGet(string $url): array
            {
                return array_shift($this->getResponses) ?? ['httpCode' => 0, 'body' => null, 'error' => true];
            }

            protected function httpPost(string $url, array $jsonBody): array
            {
                return array_shift($this->postResponses) ?? ['httpCode' => 0, 'body' => null, 'error' => true];
            }
        };
    }

    public function testCheckDoesNothingForValidAndFoundVatNumber(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        // Real response observed live for FR40303265045 (existing company)
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => json_encode([
                'countryCode' => 'FR',
                'vatNumber' => '40303265045',
                'valid' => true,
                'name' => 'SA SODIMAS',
                'address' => "11 RUE AMPERE\n26600 PONT DE L ISERE",
            ]),
            'error' => false,
        ]], $logger);

        $this->assertNull($checker->check('FR40303265045'));
    }

    public function testCheckIsNonBlockingButReturnsNoticeWhenNumberNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        // Real response observed live for FR99999999999 (syntactically valid, non-existing)
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => json_encode([
                'countryCode' => 'FR',
                'vatNumber' => '99999999999',
                'valid' => false,
            ]),
            'error' => false,
        ]], $logger);

        $notice = $checker->check('FR99999999999');

        $this->assertNotNull($notice);
        $this->assertIsString($notice);
    }

    public function testCheckIsNonBlockingOnInvalidInputErrorWrapper(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        // Real response observed live for an invalid country code (ZZ): no "valid" field at all.
        // Simulated here with a well-formed FR number so it passes our own format check and
        // reaches the HTTP call, to test that this alternate response schema is parsed without crashing.
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => json_encode([
                'actionSucceed' => false,
                'errorWrappers' => [['error' => 'INVALID_INPUT']],
            ]),
            'error' => false,
        ]], $logger);

        $checker->check('FR12123456789');
    }

    public function testCheckThrowsWhenViesReportsATransientRateLimitError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        // Real response observed live under load: same {actionSucceed:false} schema as
        // INVALID_INPUT, but MS_MAX_CONCURRENT_REQ means "service busy", not "bad input".
        // This must be blocking, not logged-and-ignored.
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => json_encode([
                'actionSucceed' => false,
                'errorWrappers' => [['error' => 'MS_MAX_CONCURRENT_REQ']],
            ]),
            'error' => false,
        ]], $logger);

        $this->expectException(\RuntimeException::class);
        $checker->check('FR40303265045');
    }

    public function testCheckThrowsWhenViesAnswers200WithUnparsableBody(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        // VIES answering 200 with an empty/non-JSON body (maintenance page, proxy, WAF...)
        // must be treated as an outage, not silently read as "no VAT number found".
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => 'not json',
            'error' => false,
        ]], $logger);

        $this->expectException(\RuntimeException::class);
        $checker->check('FR40303265045');
    }

    public function testTestVatNumberReturnsFailureWhenViesAnswers200WithUnparsableBody(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => '',
            'error' => false,
        ]], $logger);

        $result = $checker->testVatNumber('FR40303265045');

        $this->assertFalse($result['success']);
    }

    public function testCheckThrowsOnNetworkError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [[
            'httpCode' => 0,
            'body' => null,
            'error' => true,
        ]], $logger);

        $this->expectException(\RuntimeException::class);
        $checker->check('FR40303265045');
    }

    public function testCheckThrowsOnServiceUnavailable5xx(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [[
            'httpCode' => 503,
            'body' => null,
            'error' => false,
        ]], $logger);

        $this->expectException(\RuntimeException::class);
        $checker->check('FR40303265045');
    }

    public function testCheckSkipsUnrecognizedCountryCodesSilentlyWithoutAnyHttpCall(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        // No canned response provided at all: if the code called httpPost/httpGet here,
        // the fallback error response would make check() throw, failing this test.
        $checker = $this->makeChecker([], [], $logger);

        $checker->check('NOTAVALIDVATNUMBER');
    }

    public function testCheckSkipsWrongLengthNumberSilentlyWithoutAnyHttpCall(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        // Bug found manually via the BO: FR51306138901104584 has 17 digits instead of the
        // 11 a French VAT number requires. The old loose "2 letters + digits" check let this
        // reach VIES, which answered "not found" (no format error), misread as a functional
        // configuration test. It must now be rejected locally before any HTTP call is made.
        $checker = $this->makeChecker([], [], $logger);

        $checker->check('FR51306138901104584');
    }

    public function testTestVatNumberRejectsWrongLengthNumberInsteadOfReportingSuccess(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [], $logger);

        $result = $checker->testVatNumber('FR51306138901104584');

        $this->assertFalse($result['success']);
    }

    public function testCheckApiAvailabilityReflectsCheckStatusResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // Real response observed live on /check-status
        $checker = $this->makeChecker([[
            'httpCode' => 200,
            'body' => json_encode([
                'vow' => ['available' => true],
                'countries' => [
                    ['countryCode' => 'FR', 'availability' => 'Available'],
                ],
            ]),
            'error' => false,
        ]], [], $logger);

        $result = $checker->checkApiAvailability();

        $this->assertTrue($result['available']);
    }

    public function testCheckApiAvailabilityReportsUnavailableWhenVowIsDown(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $checker = $this->makeChecker([[
            'httpCode' => 200,
            'body' => json_encode(['vow' => ['available' => false], 'countries' => []]),
            'error' => false,
        ]], [], $logger);

        $result = $checker->checkApiAvailability();

        $this->assertFalse($result['available']);
    }

    public function testTestVatNumberReturnsFailureOnTransientRateLimitError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => json_encode([
                'actionSucceed' => false,
                'errorWrappers' => [['error' => 'MS_MAX_CONCURRENT_REQ']],
            ]),
            'error' => false,
        ]], $logger);

        $result = $checker->testVatNumber('FR40303265045');

        $this->assertFalse($result['success']);
    }

    public function testTestVatNumberReturnsSuccessWhenViesRespondsEvenIfNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => json_encode([
                'countryCode' => 'FR',
                'vatNumber' => '99999999999',
                'valid' => false,
            ]),
            'error' => false,
        ]], $logger);

        $result = $checker->testVatNumber('FR99999999999');

        // success = the call worked technically, even though no record was found;
        // found = false so the BO shows this as orange, not green, distinct from a real match
        $this->assertTrue($result['success']);
        $this->assertFalse($result['found']);
    }

    public function testTestVatNumberReturnsFoundTrueWhenViesFindsAMatch(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [[
            'httpCode' => 200,
            'body' => json_encode([
                'countryCode' => 'FR',
                'vatNumber' => '40303265045',
                'valid' => true,
                'name' => 'SA SODIMAS',
            ]),
            'error' => false,
        ]], $logger);

        $result = $checker->testVatNumber('FR40303265045');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['found']);
    }

    public function testTestVatNumberReturnsFailureOnServiceError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [[
            'httpCode' => 503,
            'body' => null,
            'error' => false,
        ]], $logger);

        $result = $checker->testVatNumber('FR40303265045');

        $this->assertFalse($result['success']);
    }

    public function testTestVatNumberReturnsFailureForUnrecognizedFormat(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $checker = $this->makeChecker([], [], $logger);

        $result = $checker->testVatNumber('NOTAVALIDVATNUMBER');

        $this->assertFalse($result['success']);
    }
}
