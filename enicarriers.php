<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class EniCarriers extends CarrierModule
{
    public $id_carrier;

    public function __construct()
    {
        $this->name = 'enicarriers';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Jonathan Danse';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Carriers');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        $hooks = [
            'displayCarrierExtraContent',
            'actionCarrierUpdate',
        ];

        return
            parent::install()
            && $this->registerHook($hooks)
            && $this->addCarriers();
    }

    public function uninstall()
    {
        return
            parent::uninstall()
            && $this->removeCarriers();
    }

    public function addCarriers()
    {
        $carrierIds = [];

        $carrier = new Carrier();
        // Nom du transporteur, non traductible
        $carrier->name = 'Transporteur utilisant les tranches';
        // Délai de livraison associé au transporteur, traductible
        foreach (Language::getLanguages(true) as $language) {
            $carrier->delay[(int) $language['id_lang']] = 'Delai du transporteur';
        }

        // Type de facturation :
        // - 0: Livraison gratuite (= Carrier::SHIPPING_METHOD_FREE)
        // - 1: En fonction du poids total (= Carrier::SHIPPING_METHOD_WEIGHT)
        // - 2: En fonction du prix total (= Carrier::SHIPPING_METHOD_PRICE)
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_WEIGHT;

        // Comportement hors tranches :
        // - 0: Prendre la tranche la plus grande
        // - 1: Désactiver le transporteur
        $carrier->range_behavior = 0;

        // Propriétés exclusives aux transporteurs ajoutés par un module
        // - is_module : définit le transporteur comme ajouté par un module
        // - shipping_external : permet de ne pas recourir à des méthodes spécifiques au module pour le calcul du prix de livraison
        // - external_module_name : défini le nom du module ajoutant le transporteur
        // - need_range : permet de définir si les plages sont utilisées
        $carrier->is_module = 1;
        $carrier->shipping_external = true;
        $carrier->external_module_name = $this->name;
        $carrier->need_range = 1;

        $carrier->add();

        // Association des groupes de clients au transporteur ajouté
        $customersGroups = Group::getGroups(Context::getContext()->language->id);
        $carrier->setGroups(array_column($customersGroups, 'id_group'));

        // Création des tranches
        // Instance de RangeWeight() ou de RangePrice()
        // La méthode getRangeObject() se base sur la propriété shipping_method
        $rangeObject = $carrier->getRangeObject();
        $rangeObject->id_carrier = $carrier->id;
        $rangeObject->delimiter1 = '0';
        $rangeObject->delimiter2 = '10000';
        $rangeObject->add();

        // Association des tranches aux zones
        $zones = Zone::getZones(true);
        foreach ($zones as $zone) {
            // Toutes les tranches seront associées à la zone en un seule étape
            $carrier->addZone((int) $zone['id_zone']);
        }

        $carrierIds[] = (int) $carrier->id;

        $carrier = new Carrier();
        // Nom du transporteur, non traductible
        $carrier->name = 'Transporteur sans tranches';
        foreach (Language::getLanguages(true) as $language) {
            $carrier->delay[(int) $language['id_lang']] = 'Delai du transporteur';
        }
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_PRICE;
        $carrier->range_behavior = 0;
        $carrier->is_module = 1;
        $carrier->shipping_external = true;
        $carrier->external_module_name = $this->name;
        $carrier->need_range = 0;

        $carrier->add();

        $rangeObject = $carrier->getRangeObject(Carrier::SHIPPING_METHOD_PRICE);
        $rangeObject->id_carrier = $carrier->id;
        $rangeObject->delimiter1 = '0';
        $rangeObject->delimiter2 = '10000';
        $rangeObject->add();
        
        $zones = Zone::getZones(true);
        foreach ($zones as $zone) {
            $carrier->addZone((int) $zone['id_zone']);
        }

        $customersGroups = Group::getGroups(Context::getContext()->language->id);
        $carrier->setGroups(array_column($customersGroups, 'id_group'));

        $carrierIds[] = (int) $carrier->id;

        Configuration::updateGlobalValue('ENI_CARRIERS_IDS', json_encode($carrierIds));

        return true;
    }

    protected function removeCarriers()
    {
        $carrierIds = json_decode(Configuration::get('ENI_CARRIERS_IDS', null, 0, 0, '{}'), true);

        if (is_array($carrierIds)) {
            foreach ($carrierIds as $carrierId) {
                $carrier = new Carrier((int) $carrierId);
                $carrier->delete();
            }
        }

        Configuration::deleteByName('ENI_CARRIERS_IDS');

        return true;
    }

    public function hookActionCarrierUpdate(array $params)
    {
        $oldCarrierId = (int) $params['id_carrier'];
        $newCarrierId = (int) $params['carrier']->id;

        $carrierIds = json_decode(Configuration::get('ENI_CARRIERS_IDS', null, 0, 0, '{}'), true);

        $newCarrierIds = [];
        if (is_array($carrierIds)) {
            foreach ($carrierIds as $carrierId) {
                if ($oldCarrierId === (int) $carrierId) {
                    $newCarrierIds[] = $newCarrierId;
                } else {
                    $newCarrierIds[] = (int) $carrierId;
                }
            }
        }
        Configuration::updateGlobalValue('ENI_CARRIERS_IDS', json_encode($newCarrierIds));
    }

    public function displayInfoByCart($params)
    {
        return $this->displayConfirmation('displayInfoByCart');
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        return $this->displayInformation('hookDisplayCarrierExtraContent');
    }

    public function getPackageShippingCost($cart, $shippingCost, $products)
    {
        return 10 + (float) $shippingCost;
    }

    public function getOrderShippingCost($cart, $shippingCost)
    {
        return 50;
    }

    public function getOrderShippingCostExternal($cart)
    {
        return 42;
    }
}
