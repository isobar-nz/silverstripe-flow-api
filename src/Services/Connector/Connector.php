<?php


namespace Isobar\Flow\Services\Connector;

/**
 * Interface ConnectorInterface
 * @package App\Flow\Services\Connector
 */
interface Connector
{
    /**
     * @param string $url
     * @param null $body
     * @return array
     */
    public function getRequest($url, $body = null): array;
}
