<?php


namespace Isobar\Flow\Services\Connector;

/**
 * Interface ConnectorInterface
 * @package App\Flow\Services\Connector
 */
interface Connector
{
    /**
     * @param        $url
     * @param null   $body
     * @param string $encoding
     * @return array
     */
    public function getRequest($url, $body = null, $encoding = 'utf-8'): array;
}
