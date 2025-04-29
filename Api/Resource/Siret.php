<?php

namespace SiretManagement\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use SiretManagement\Controller\SiretSearchController;
use SiretManagement\Model\Map\SiretCustomerTableMap;
use SiretManagement\Model\SiretCustomer;
use SiretManagement\Model\SiretCustomerQuery;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Resource\Customer;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/register/searchSiret',
            controller: SiretSearchController::class,
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'siret',
                        'in' => 'query',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => 'Numéro SIRET à rechercher'
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Informations sur l\'entreprise',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'siret' => '12345678900010',
                                    'nom' => 'Nom de la société',
                                    'adresse' => 'Adresse',
                                    'ville' => 'Paris'
                                ]
                            ]
                        ]
                    ],
                    '400' => ['description' => 'SIRET manquant'],
                    '500' => ['description' => 'Erreur interne']
                ]
            ],
            read: false,
        )
    ],
    paginationEnabled: false,
)]
class Siret implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    public ?int $id = null;

    public int $customerId;
    #[Groups([Customer::GROUP_ADMIN_READ, Customer::GROUP_ADMIN_WRITE,Customer::GROUP_FRONT_WRITE,Customer::GROUP_FRONT_READ_SINGLE])]
    public ?string $codeSiret;

    #[Groups([Customer::GROUP_ADMIN_READ, Customer::GROUP_ADMIN_WRITE,Customer::GROUP_FRONT_WRITE,Customer::GROUP_FRONT_READ_SINGLE])]
    public ?string $codeTvaIntra;

    #[Groups([Customer::GROUP_ADMIN_READ, Customer::GROUP_ADMIN_WRITE,Customer::GROUP_FRONT_WRITE,Customer::GROUP_FRONT_READ_SINGLE])]
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
        if (SiretCustomerQuery::create()->filterByCustomerId($query->get('customer.id'))->findOne() === null){
            return;
        }
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
        if (SiretCustomerQuery::create()->filterByCustomerId($activeRecord->getId())->findOne() === null){
            return $this;
        }
        $this->codeSiret = $activeRecord->hasVirtualColumn('Siret_code') ? $activeRecord->getVirtualColumn('Siret_code') : null;
        $this->codeTvaIntra = $activeRecord->hasVirtualColumn('Siret_code_tva_intra') ? $activeRecord->getVirtualColumn('Siret_code_tva_intra') : null;
        $this->denominationUniteLegale = $activeRecord->hasVirtualColumn('Siret_denomination_unite_legale') ? $activeRecord->getVirtualColumn('Siret_denomination_unite_legale') : null;

        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $this->codeSiret = $data['codeSiret'];
        $this->codeTvaIntra = $data['codeTvaIntra'];
        $this->denominationUniteLegale = $data['denominationUniteLegale'] ?? null;
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
