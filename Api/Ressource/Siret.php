<?php

namespace SiretManagement\Api\Ressource;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use SiretManagement\Model\Map\SiretCustomerTableMap;
use SiretManagement\Model\SiretCustomer;
use SiretManagement\Model\SiretCustomerQuery;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Thelia\Api\Resource\Cart;
use Thelia\Api\Resource\Customer;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;

class Siret implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    #[Groups([Customer::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([Customer::GROUP_READ_SINGLE])]
    public int $customerId;
    #[Groups([Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $codeSiret;

    #[Groups([Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $codeTvaIntra;

    #[Groups([Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $denominationUniteLegale;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Siret
    {
        $this->id = $id;
        return $this;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function setCustomerId(int $customerId): Siret
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function getCodeSiret(): string
    {
        return $this->codeSiret;
    }

    public function setCodeSiret(string $codeSiret): Siret
    {
        $this->codeSiret = $codeSiret;
        return $this;
    }

    public function getCodeTvaIntra(): string
    {
        return $this->codeTvaIntra;
    }

    public function setCodeTvaIntra(string $codeTvaIntra): Siret
    {
        $this->codeTvaIntra = $codeTvaIntra;
        return $this;
    }

    public function getDenominationUniteLegale(): ?string
    {
        return $this->denominationUniteLegale;
    }

    public function setDenominationUniteLegale(?string $denominationUniteLegale): Siret
    {
        $this->denominationUniteLegale = $denominationUniteLegale;
        return $this;
    }


    public static function getResourceParent(): string
    {
        return Customer::class;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new SiretCustomerTableMap();
    }
    public static function extendQuery(ModelCriteria $query, Operation $operation = null, array $context = []): void
    {
        $tableMap = static::getPropelRelatedTableMap();
        $query->useSiretCustomerQuery()->endUse();

        foreach ($tableMap->getColumns() as $column) {
            $query->withColumn(SiretCustomerTableMap::COL_CODE_SIRET, 'Siret_code');
            $query->withColumn(SiretCustomerTableMap::COL_CODE_TVA_INTRA, 'Siret_code_tva_intra');
            $query->withColumn(SiretCustomerTableMap::COL_DENOMINATION_UNITE_LEGALE, 'Siret_denomination_unite_legale');
        }
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $this->codeSiret = $activeRecord->getVirtualColumn('Siret_code');
        $this->codeTvaIntra = $activeRecord->getVirtualColumn('Siret_code_tva_intra');
        $this->denominationUniteLegale = $activeRecord->getVirtualColumn('Siret_denomination_unite_legale');

        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $this->codeSiret = $data['codeSiret'];
        $this->codeTvaIntra = $data['codeTvaIntra'];
        $this->denominationUniteLegale = $data['denominationUniteLegale'];
        return $this;
    }

    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        $model = new SiretCustomer();
        if (isset($activeRecord->getSiretCustomers()->getData()[0])){
            $id = $activeRecord->getSiretCustomers()->getData()[0]->getId();
            $model = SiretCustomerQuery::create()->filterById($id)->findOne();
        }

        $model->setCustomerId($activeRecord->getId());
        $model->setCodeSiret($this->getCodeSiret());
        $model->setCodeTvaIntra($this->getCodeTvaIntra());
        $model->setDenominationUniteLegale($this->getDenominationUniteLegale());

        $model->save();
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        foreach ($activeRecord->getSiretCustomers() as $siret){
            SiretCustomerQuery::create()->findOneById($siret->getId())->delete();
        }
    }
}
