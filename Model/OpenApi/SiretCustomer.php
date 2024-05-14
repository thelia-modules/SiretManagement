<?php

namespace SiretManagement\Model\OpenApi;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Model\Api\BaseApiModel;

#[Schema(
    description: "Company data for customer",
)]
class SiretCustomer extends BaseApiModel
{
    #[Property(type: "string")]
    protected string $codeSiret;

    #[Property(type: "string")]
    protected string $codeTvaIntra;

    #[Property(type: "string")]
    protected ?string $denominationUniteLegale;

    public function getCodeSiret(): string
    {
        return $this->codeSiret;
    }

    public function setCodeSiret(string $codeSiret): SiretCustomer
    {
        $this->codeSiret = $codeSiret;
        return $this;
    }

    public function getCodeTvaIntra(): string
    {
        return $this->codeTvaIntra;
    }

    public function setCodeTvaIntra(string $codeTvaIntra): SiretCustomer
    {
        $this->codeTvaIntra = $codeTvaIntra;
        return $this;
    }

    public function getDenominationUniteLegale(): ?string
    {
        return $this->denominationUniteLegale;
    }

    public function setDenominationUniteLegale(?string $denominationUniteLegale): SiretCustomer
    {
        $this->denominationUniteLegale = $denominationUniteLegale;
        return $this;
    }
}