<?php


namespace Isobar\Flow\Services\Product;


use Isobar\Flow\Services\Connector\Connector;
use Isobar\Flow\Services\EnvironmentSettings;
use Isobar\Flow\Services\Product\ProductServiceInterface;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

class ProductAPIService implements ProductServiceInterface
{
    use Injectable;

    /**
     * @var \Isobar\Flow\Services\Connector\Connector
     */
    protected $connector;

    /**
     * @var ProductServiceInterface
     */
    protected $service;

    /**
     * @return array List of products
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function products()
    {
        return $this->getProducts();
    }

    /**
     * @return array
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    protected function getProducts()
    {
        $url = Environment::getEnv(EnvironmentSettings::PRODUCTS_URL);
        $response = $this->getConnector()->getRequest($url);

        $products = [];

        if (!empty($response['productList'])) {
            foreach ($response['productList']['product'] as $product) {
                $products[] = [
                    'forecastGroup'      => $product['forecastGroup'],
                    'vintage'            => $product['vintage'],
                    'productCode'        => $product['productCode'],
                    'productDescription' => $product['productDescription'],
                    'packingSize'        => $product['packingSize']
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
     * @param \Isobar\Flow\Services\Connector\Connector $connector
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
