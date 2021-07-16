<?php
declare(strict_types=1);

namespace Isobar\Flow\Extensions;

use Exception;
use SilverStripe\ORM\DB;
use SwipeStripe\Order\Order;
use SilverStripe\Core\Extension;
use Isobar\Flow\Config\FlowConfig;
use SilverStripe\Control\Controller;

/**
 * Class PaymentGatewayControllerExtension
 *
 * @package App\Ecommerce
 */
class PurchaseServiceExtension extends Extension
{
    /**
     * @param array $data
     */
    public function onBeforeCompletePurchase(array &$data): void
    {
        if ($this->owner->getPayment()->Gateway === 'PaymentExpress_PxFusion') {
            $sessionId = Controller::has_curr() ? Controller::curr()->getRequest()->getVar('sessionid') : null;
            if (empty($sessionId)) {
                throw new \RuntimeException('Cannot get PXFusion "sessionid" query parameter from controller.');
            }

            $data['sessionId'] = $sessionId;
        }
    }

    /**
     * @param array $gatewayData
     *
     * Replace the session ID with the cart ID (order number)
     */
    public function onBeforePurchase(array &$gatewayData): void
    {
        $controller = Controller::curr();

        if (method_exists($controller, 'getActiveCart')) {
            /** @var Order $cart */
            $cart = Controller::curr()->getActiveCart();

            if ($cart && $cart->exists()) {

                // Generate and store the Flow reference
                $suffix = FlowConfig::config()->get('order_suffix');

                $max = DB::query('SELECT MAX(FlowReference + 0) FROM "SwipeStripe_Order"')->value();

                if ($max > 0) {
                    $max++;
                    $transactionId = $max . $suffix;
                } else {
                    // If that fails, use the cart ID
                    $transactionId = $cart->ID . $suffix;
                }

                // Save to the order
                try {
                    $cart->setField('FlowReference', $transactionId);
                    $cart->write();
                } catch (Exception $e) {
                    $transactionId = $cart->ID;
                }

                // Set the order id as the reference
                $gatewayData['transactionId'] = $transactionId;
            }
        }
    }
}
