<?php


namespace Isobar\Flow\Services\Product;


use Isobar\Flow\Services\Connector\Connector;
use Isobar\Flow\Services\EnvironmentSettings;
use Isobar\Flow\Services\Order\OrderServiceInterface;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

class OrderAPIService implements OrderServiceInterface
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
     * @param null $body
     * @return array
     */
    public function order($body = null)
    {
        return $this->postOrder($body);
    }

    /**
     * @param string $body
     * @return array
     */
    protected function postOrder($body)
    {
        $url = Environment::getEnv(EnvironmentSettings::ORDERS_URL);
        $response = $this->getConnector()->getRequest($url, $body);

        return $response;
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
