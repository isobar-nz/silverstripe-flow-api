<?php

namespace Isobar\Flow\Admin;

use Exception;
use Isobar\Flow\Services\EnvironmentSettings;
use Isobar\Flow\Tasks\ProcessProductsTask;
use Isobar\Flow\Tasks\ProductImportTask;
use Isobar\Flow\Tasks\StockImportTask;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use LittleGiant\SinglePageAdmin\SinglePageAdmin;
use Psr\Http\Message\ResponseInterface;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SimpleXMLElement;

/**
 * Class FlowStatusAdmin
 *
 * @package App\ModelAdmin
 */
class StatusAdmin extends LeftAndMain implements PermissionProvider
{
    const CMS_ACCESS = 'CMS_ACCESS_' . self::class;

    /**
     * @var string
     */
    private static $menu_title = 'Flow API Status';

    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-silverstripe';

    /**
     * @var string
     */
    private static $url_segment = 'flow-status';

    private static $allowed_actions = [
        'doFlowSync',
        'EditForm'
    ];

    /**
     * Initialize requirements for this view
     */
    public function init()
    {
        parent::init();

        Requirements::customCSS('.cms-content-view pre {
            background: #ffffff !important;
            border: 1px solid #ced5e1;
        }
        .cms-content-header-tabs.cms-tabset-nav-primary {
            display:none;
        }
        ');

        Requirements::javascript("isobar-nz/silverstripe-flow:client/js/FlowSyncAction.js");
    }

    /**
     * @param null $id
     * @param null $fields
     * @return $this|null|Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $form->Fields()->add(new TabSet('Root'));

        $form->Fields()->addFieldsToTab('Root.Products', [
            HeaderField::create('ProductHeader', 'Product Status'),
            LiteralField::create('ProductData', $this->renderWith('Isobar\Flow\ModelAdmin\Includes\StatusAdmin_status', [
                'StatusData' => $this->getFlowStatus(EnvironmentSettings::PRODUCTS_URL)
            ]))
        ]);

        $form->Fields()->addFieldsToTab('Root.Stock', [
            HeaderField::create('ProductHeader', 'Stock Status'),
            LiteralField::create('StockData', $this->renderWith('Isobar\Flow\ModelAdmin\Includes\StatusAdmin_status', [
                'StatusData' => $this->getFlowStatus(EnvironmentSettings::STOCK_URL)
            ]))
        ]);


        $form->Fields()->addFieldsToTab('Root.Pricing', [
            HeaderField::create('ProductHeader', 'Pricing Status'),
            LiteralField::create('PricingData', $this->renderWith('Isobar\Flow\ModelAdmin\Includes\StatusAdmin_status', [
                'StatusData' => $this->getFlowStatus(EnvironmentSettings::PRICING_URL)
            ]))
        ]);

        $form->Actions()->push(
            FormAction::create('doFlowSync', 'Sync from Flow')
                ->addExtraClass('btn btn-primary font-icon-sync')
                ->setUseButtonTag(true)
        );

        return $form;
    }

    /**
     * @param $data
     * @param $form
     * @return HTTPResponse
     * @throws HTTPResponse_Exception
     */
    public function doFlowSync($data, $form)
    {
        ob_start();
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', 100000);

        $request = $this->getRequest();
        $response = $this->getResponseNegotiator()->respond($request);

        $code = 200;
        $message = 'Flow synced.';

        // Product
        $productTask = new ProductImportTask();

        try {
            $productTask->process();
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
        }

        // Process
        $productTask = new ProcessProductsTask();

        try {
            $productTask->process();
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
        }

        // Stock
        $stockTask = new StockImportTask();

        try {
            $stockTask->process();
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
        }

        // Pass on message
        $response->addHeader('X-Status', rawurlencode($message));
        $response->setStatusCode($code);

        // Suppress echo
        ob_end_clean();
        return $response;
    }

    /**
     * @param null $request
     * @return null|Form|SinglePageAdmin
     */
    public function EditForm($request = null)
    {
        return $this->getEditForm();
    }

    /**
     * @param $endpoint
     * @return ArrayData
     */
    public function getFlowStatus($endpoint)
    {
        try {
            $url = Environment::getEnv($endpoint);

            $response = $this->rawRequest($url);
            $contents = $response->getBody()->getContents();
            $xml = new SimpleXMLElement($contents);

            $pretty = $this->formatXmlString($contents);

            if (!empty($xml)) {
                return ArrayData::create([
                    'Message' => 'Retrieved ' . count($xml) . ' records',
                    'Status'  => 'good',
                    'Data'    => $pretty
                ]);
            } else {
                return ArrayData::create([
                    'Message' => 'Empty data',
                    'Status'  => 'error'
                ]);
            }
        } catch (Exception $e) {
            return ArrayData::create([
                'Message' => $e->getMessage(),
                'Status'  => 'error'
            ]);
        }
    }

    /**
     * @param string $url
     * @return ResponseInterface
     */
    protected function rawRequest($url)
    {
        $username = Environment::getEnv(EnvironmentSettings::CLIENT_USERNAME);
        $password = Environment::getEnv(EnvironmentSettings::CLIENT_PASSWORD);

        $basicAuth = base64_encode($username . ":" . $password);

        $client = $this->getClient($basicAuth);
        $options = [];

        return $client->get($url, $options);
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
     * @return array
     */
    public function providePermissions()
    {
        return [
            self::CMS_ACCESS => [
                'name'     => 'View flow status admin',
                'category' => _t(Permission::class . '.CMS_ACCESS_CATEGORY', 'CMS Access'),
            ],
        ];
    }

    function formatXmlString($xml)
    {
        // add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
        $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);

        // now indent the tags
        $token = strtok($xml, "\n");
        $result = ''; // holds formatted version as it is built
        $pad = 0; // initial indent
        $indent = 0; // initial indent
        $matches = []; // returns from preg_matches()

        // scan each line and adjust indent based on opening/closing tags
        while ($token !== false) :

            // test for the various tag states

            // 1. open and closing tags on same line - no change
            if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) {
                $indent = 0;
                // 2. closing tag - outdent now
            } elseif (preg_match('/^<\/\w/', $token, $matches)) {
                $pad--;
                // 3. opening tag - don't pad this one, only subsequent tags
            } elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) {
                $indent = 1;
                // 4. no indentation needed
            } else {
                $indent = 0;
            }

            // pad the line with the required number of leading spaces
            $line = str_pad($token, strlen($token) + $pad, "\t", STR_PAD_LEFT);
            $result .= $line . "\n"; // add to the cumulative result, with linefeed
            $token = strtok("\n"); // get the next token
            $pad += $indent; // update the pad size for subsequent lines
        endwhile;

        return $result;
    }
}
