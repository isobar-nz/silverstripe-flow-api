<?php


namespace Isobar\Flow\Services;


use App\Config\RequiresConfig;
use Isobar\Flow\Services\Connector\BasicAuthConnector;
use Isobar\Flow\Services\Connector\Connector;
use Isobar\Flow\Services\EnvironmentSettings;
use SilverStripe\Core\Injector\Injectable;

class FlowAPIConnector implements Connector
{
    use Injectable;
    use RequiresConfig;
    use BasicAuthConnector;

    /**
     * @return string
     */
    public function getBasicAuth()
    {
        $username = $this->getEnv(EnvironmentSettings::CLIENT_USERNAME);
        $password = $this->getEnv(EnvironmentSettings::CLIENT_PASSWORD);

        $basicAuth = base64_encode($username . ":" . $password);

        return $basicAuth;
    }
}
