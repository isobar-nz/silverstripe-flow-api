<?php

namespace Isobar\Flow\Config;

use SilverStripe\Core\Config\Configurable;

class FlowConfig
{
    use Configurable;

    /**
     * @var string Flow username
     */
    private static $api_username;

    /**
     * @var string Flow pass
     */
    private static $api_password;

    /**
     * @var string Hostname
     *
     * Can be IP + port, including http
     */
    private static $api_hostname;

    /**
     * @var string API ver
     *
     * Generally 'api' but leaves room for API versions
     */
    private static $endpoint = 'api';

    /**
     * @var int
     *
     * Number that we consider and item "out of stock"
     */
    private static $soh_threshold = 6;

    /**
     * @var string
     *
     * Optional order prefix
     */
    private static $order_prefix;

    /**
     * @var string
     *
     * Optional order prefix
     */
    private static $web_debtor_code;
}
