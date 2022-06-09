<?php

namespace SiretManagement\Service;

use Exception;
use SiretManagement\SiretManagement;
use SimonDevelop\Sirene;
use Thelia\Core\Translation\Translator;

class SiretAPIManagement
{
    /**
     * @throws Exception
     */
    private function getSirenClient(): Sirene
    {
        $valuePublicConsumer = SiretManagement::getConfigValue('public_consumer', null);
        $valuePrivateConsumer = SiretManagement::getConfigValue('private_consumer', null);
        return new Sirene([
            "secret" => base64_encode("$valuePublicConsumer:$valuePrivateConsumer"),
            "jwt_path" => __DIR__ . '/../jwt_directory'
        ]);
    }

    /**
     * @throws Exception
     */
    public function getDenomination($codeSirenOrSiret)
    {
        $data = $this->getData($codeSirenOrSiret);
        return $data["uniteLegale"]["periodesUniteLegale"][0]["denominationUniteLegale"] ?? '';
    }

    /**
     * @throws Exception
     */
    public function getData($code)
    {
        $codeLength = strlen($code);
        if (!in_array($codeLength, [9, 14])){
            throw new Exception(Translator::getInstance()->trans("Bad length for siret or siren number"));
        }
        $codeType = strlen($code) === 14 ? 'siret' : 'siren';

        $data = $this->getSirenClient()->$codeType($code);

        if ($data === null || $data["header"]["statut"] === 400) {
            throw new Exception(Translator::getInstance()->trans("Invalid %codeType number",['%codeType' => $codeType], SiretManagement::DOMAIN_NAME));
        }

        if ($data["header"]["statut"] !== 200) {
            throw new Exception(Translator::getInstance()->trans("Connection problem with siren API "));
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function checkSiret($codeSiret)
    {
        if (strlen($codeSiret) !== 14) {
            throw new Exception(Translator::getInstance()->trans("Bad length for siret number"));
        }
        $this->getData($codeSiret);
    }

    /**
     * @throws Exception
     */
    public function checkSiren($codeSiren)
    {
        if (strlen($codeSiren) !== 9) {
            throw new Exception(Translator::getInstance()->trans("Bad length for siren number"));
        }
        $this->getData($codeSiren);
    }

}