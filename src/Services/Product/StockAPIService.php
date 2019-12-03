<?php


namespace Isobar\Flow\Services\Product;


use Isobar\Flow\Services\Connector\Connector;
use Isobar\Flow\Services\EnvironmentSettings;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

class StockAPIService implements ProductServiceInterface
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
        return $this->getStock();
    }

    /**
     * @return array
     */
    protected function getStock()
    {
        $url = Environment::getEnv(EnvironmentSettings::STOCK_URL);
        $response = $this->getConnector()->getRequest($url);

        $products = [];

        if (!empty($response['stockOnHandList'])) {
            foreach ($response['stockOnHandList']['stockOnHand'] as $product) {
                $products[] = [
                    'webDebtorCode' => $product['webDebtorCode'],
                    'productCode'   => $product['productCode'],
                    'stock'         => $product['stock']
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
