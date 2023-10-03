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

namespace SiretManagement\Service;

use SimonDevelop\Sirene;
use SiretManagement\SiretManagement;
use Thelia\Core\Translation\Translator;

class SiretAPIManagement
{
    /**
     * @throws \Exception
     */
    private function getSirenClient(): Sirene
    {
        $valuePublicConsumer = SiretManagement::getConfigValue(SiretManagement::PUBLIC_CONSUMER, null);
        $valuePrivateConsumer = SiretManagement::getConfigValue(SiretManagement::PRIVATE_CONSUMER, null);

        if (empty($valuePublicConsumer) || empty($valuePrivateConsumer)) {
            throw new \InvalidArgumentException("Siren API credentials are missing, please check SiretManagement module configuration");
        }

        @mkdir( __DIR__.'/../Config/jwt_directory');

        return new Sirene([
            'secret' => base64_encode("$valuePublicConsumer:$valuePrivateConsumer"),
            'jwt_path' => __DIR__.'/../Config/jwt_directory',
        ]);
    }

    /**
     * @throws \Exception
     */
    public function getDenomination($codeSirenOrSiret)
    {
        $data = $this->getData($codeSirenOrSiret);

        return $data['uniteLegale']['periodesUniteLegale'][0]['denominationUniteLegale'] ?? '';
    }

    /**
     * @throws \Exception
     */
    public function getData($code): array
    {
        $codeLength = \strlen($code);
        if (!\in_array($codeLength, [9, 14])) {
            throw new \Exception(Translator::getInstance()->trans('Wrong length for siret or siren number, 14 or 9 digits expected'));
        }
        $codeType = \strlen($code) === 14 ? 'siret' : 'siren';

        $data = $this->getSirenClient()->$codeType($code);

        if ($data === null || $data['header']['statut'] === 400) {
            throw new \Exception(Translator::getInstance()->trans('Invalid %codeType number', ['%codeType' => $codeType], SiretManagement::DOMAIN_NAME));
        }

        if ($data['header']['statut'] !== 200) {
            throw new \Exception(
                $data['header']['message'] ?? Translator::getInstance()->trans('Undefined SIRENE API error', [], SiretManagement::DOMAIN_NAME)
                . ' ('. $data['header']['statut'] .')');
        }

        return $data;
    }

    /**
     * @throws \Exception
     */
    public function checkSiret($codeSiret): array
    {
        if (\strlen($codeSiret) !== 14) {
            throw new \Exception(Translator::getInstance()->trans('Wrong length for siret number, 14 digits expected'));
        }

        return $this->getData($codeSiret);
    }

    /**
     * @throws \Exception
     */
    public function checkSiren($codeSiren): array
    {
        if (\strlen($codeSiren) !== 9) {
            throw new \Exception(Translator::getInstance()->trans('Wrong length for siren number 9 digits expected'));
        }

        return $this->getData($codeSiren);
    }
}
