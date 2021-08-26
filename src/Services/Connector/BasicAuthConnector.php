<?php


namespace Isobar\Flow\Services\Connector;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\ORM\ValidationException;
use SimpleXMLElement;

trait BasicAuthConnector
{
    /**
     * @return string
     * @throws ValidationException
     */
    abstract public function getBasicAuth();

    /**
     * @param string $url
     * @param null   $body
     * @param string $encoding
     * @return array
     * @throws HTTPResponse_Exception
     * @throws ValidationException
     */
    public function getRequest($url, $body = null, $encoding = 'utf-8'): array
    {
        $basicAuth = $this->getBasicAuth();

        try {
            // Attempt a valid connection
            return $this->request($url, $basicAuth, $body, $encoding);
        } catch (ClientException $e) {
            $response = new HTTPResponse();
            $response->setBody($e->getResponse()->getBody()->getContents());
            $response->setStatusCode($e->getResponse()->getStatusCode());
            foreach ($e->getResponse()->getHeaders() as $name => $value) {
                $response->addHeader($name, implode(';', $value));
            }
            throw new HTTPResponse_Exception($response);
        }
    }

    /**
     * Make a request
     *
     * @param string       $url
     * @param string       $basicAuth
     * @param array|string $body
     *
     * @param string       $encoding
     * @return array
     */
    protected function request($url, $basicAuth = null, $body = null, $encoding = 'utf-8'): array
    {
        $response = $this->rawRequest($url, $basicAuth, $body, $encoding);
        $contents = $response->getBody()->getContents();

        $xml = new SimpleXMLElement($contents);

        $processedData = self::convertXmlToArray($xml);

        return $processedData;
    }

    /**
     * @param string $basicAuth
     * @return Client
     */
    protected function getClient($basicAuth = null)
    {
        $headers = [
            'Accept'       => 'application/xml; charset=utf-8',
            'Content-Type' => 'application/xml; charset=utf-8',
        ];

        if ($basicAuth) {
            $headers['Authorization'] = 'Basic ' . $basicAuth;
        }

        $client = new Client([
            RequestOptions::HEADERS => $headers
        ]);

        return $client;
    }

    /**
     * @param string      $url
     * @param string|null $basicAuth
     * @param string|null $body
     * @param string      $encoding
     * @return ResponseInterface
     */
    protected function rawRequest($url, $basicAuth = null, $body = null, $encoding = 'utf-8')
    {
        $client = $this->getClient($basicAuth);
        $options = [];


        // If no body, just do a get
        if (!$body) {
            return $client->get($url, $options);
        }

        // If body given, HTTP post
        $options[RequestOptions::HEADERS] = [
            'Content-Type' => "application/xml; charset={$encoding}",
        ];

        if (is_array($body)) {
            $options[RequestOptions::FORM_PARAMS] = $body;
        } else {
            $options[RequestOptions::BODY] = $body;
        }

        return $client->post($url, $options);
    }


    /**
     * Responsible for formatting the XML returned
     *
     * @param SimpleXMLElement $xml
     * @return array
     */
    public static function convertXmlToArray(SimpleXMLElement $xml): array
    {
        $parser = function (SimpleXMLElement $xml, array $collection = []) use (&$parser) {
            $nodes = $xml->children();
            $attributes = $xml->attributes();

            if (0 !== count($attributes)) {
                foreach ($attributes as $attrName => $attrValue) {
                    $collection['attributes'][] = strval($attrValue);
                }
            }

            if (0 === $nodes->count()) {
                $collection = strval($xml);
                return $collection;
            }

            foreach ($nodes as $nodeName => $nodeValue) {
                if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                    $collection[$nodeName] = $parser($nodeValue);
                    continue;
                }

                $collection[$nodeName][] = $parser($nodeValue);
            }

            return $collection;
        };

        return [
            $xml->getName() => $parser($xml)
        ];
    }
}
