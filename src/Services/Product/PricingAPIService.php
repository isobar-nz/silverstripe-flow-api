<?php


namespace Isobar\Flow\Services\Product;


use Isobar\Flow\Services\Connector\Connector;
use Isobar\Flow\Services\EnvironmentSettings;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

class PricingAPIService implements ProductServiceInterface
{
    use Injectable;

    /**
     * @var Connector
     */
    protected $connector;

    /**
     * @var ProductServiceInterface
     */
    protected $service;

    /**
     * @return array List of products
     */
    public function products()
    {
        return $this->getPricing();
    }

    /**
     * @return array
     */
    protected function getPricing()
    {
        $url = Environment::getEnv(EnvironmentSettings::PRICING_URL);
        $response = $this->getConnector()->getRequest($url);

        $products = [];

        if (!empty($response['customerPriceList'])) {
            foreach ($response['customerPriceList']['customerPrice'] as $product) {
                $products[] = [
                    'market'                  => $product['market'],
                    'webDebtorCode'           => $product['webDebtorCode'],
                    'forecastGroup'           => $product['forecastGroup'],
                    'productCode'             => $product['productCode'],
                    'priceGroup'              => $product['priceGroup'],
                    'retailPriceCase'         => $product['retailPriceCase'],
                    'currentSellingPriceUnit' => $product['currentSellingPriceUnit']
                ];
            }
        }

        return $products;
    }

    /**
     * @return Connector
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * @param Connector $connector
     * @return $this
     */
    public function setConnector(Connector $connector)
    {
        $this->connector = $connector;
        return $this;
    }


    /**
     * @return ProductServiceInterface
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param ProductServiceInterface $service
     * @return $this
     */
    public function setService(ProductServiceInterface $service)
    {
        $this->service = $service;
        return $this;
    }
}
