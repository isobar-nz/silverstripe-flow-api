<?php


namespace Isobar\Flow\Extensions;


use Exception;
use Isobar\Flow\Traits\HandlesFlowSyncTrait;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;

/**
 * Class FlowLeftAndMainExtension
 * @package Isobar\Flow\Extensions
 *
 * @property LeftAndMain|ModelAdmin $owner
 */
class FlowLeftAndMainExtension extends Extension
{
//    use HandlesFlowSyncTrait;

    /**
     * @param $form
     * @param $message
     * @param $code
     * @return \SilverStripe\Control\HTTPResponse
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function actionComplete($form, $message, $code)
    {
        return $message;
        $request = $this->owner->getRequest();

        /** @var HTTPResponse $response */
        $response = $this->owner->getResponseNegotiator()->respond($request);

        // Pass on message
        $response->addHeader('X-Status', rawurlencode($message));
        $response->setStatusCode($code);

        return $response;
    }

    /**
     * Imports the submitted CSV file based on specifications given in
     * {@link self::model_importers}.
     * Redirects back with a success/failure message.
     *
     * @todo Figure out ajax submission of files via jQuery.form plugin
     *
     * @param array $data
     * @param Form $form
     * @param HTTPRequest $request
     * @return bool|HTTPResponse
     */
    public function sync($data, $form, $request)
    {
        try {
            /** @var HTTPResponse $result */
            $message = $this->doFlowSync($data, $form);
        } catch (Exception $e) {
            $form->sessionMessage($e->getMessage(), 'bad');
        }

        $form->sessionMessage($message, 'good');
        return $this->owner->redirectBack();
    }

    /**
     * Sanitise a model class' name for inclusion in a link
     *
     * @param string $class
     * @return string
     */
    protected function sanitiseClassName($class)
    {
        return str_replace('\\', '-', $class);
    }

}
