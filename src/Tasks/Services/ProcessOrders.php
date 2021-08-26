<?php


namespace Isobar\Flow\Tasks\Services;

use App\Pages\MediaPages\WineClubEvent;
use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Model\ScheduledOrder;
use Isobar\Flow\Services\FlowAPIConnector;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Services\Product\OrderAPIService;
use SwipeStripe\Order\Order;
use SwipeStripe\Order\Status\OrderStatus;
use SwipeStripe\Order\Status\OrderStatusUpdate;

/**
 * Class ProcessOrders
 *
 * Runs through scheduled orders and sends them to FLOW
 *
 * @package App\Flow
 */
class ProcessOrders
{
    public $OrdersFailed;

    public $OrdersSent;

    /**
     * Processes all orders from scheduled list
     * @throws FlowException
     */
    public function runProcessData()
    {
        echo "Beginning processing orders\n\n";

        // Send orders to Flow
        try {
            $this->processOrders();
        } catch (Exception $e) {
            throw new FlowException($e->getMessage(), $e->getCode(), $e);
        }

        echo "\nCompleted processing orders\n\n";
    }

    /**
     * @throws FlowException
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function processOrders()
    {
        // Process 5 orders at one time
        $scheduledOrders = ScheduledOrder::get()->filter([
            'Status' => FlowStatus::PENDING
        ])->limit(5);

        if ($scheduledOrders->count()) {
            /** @var ScheduledOrder $scheduledOrder */
            foreach ($scheduledOrders as $scheduledOrder) {

                // Get the Order
                /** @var Order $order */
                $order = $scheduledOrder->Order();

                // If the order status has changed to refunded/cancelled etc, do not send to flow.
                if ($order->Status != OrderStatus::PENDING) {
                    $scheduledOrder->setField('Status', FlowStatus::CANCELLED);
                    $scheduledOrder->write();

                    continue;
                }

                $scheduledOrder->setField('Status', FlowStatus::PROCESSING);

                $xmlData = $scheduledOrder->getXmlData();

                // If no data, fail
                if ($xmlData === false) {
                    $scheduledOrder->setField('Status', FlowStatus::FAILED);
                    $scheduledOrder->write();

                    $this->OrdersFailed++;

                    continue;
                }

                // TODO update for Flow integration with Events
                // if there are only events in the cart, then do not post to flow
                $items = $order->OrderItems();
                $eventsCount = 0;
                foreach ($items as $item) {
                    if ($item->PurchasableClass === WineClubEvent::class) {
                        $eventsCount++;
                    }
                }

                $result = [];

                try {
                    $api = OrderAPIService::singleton();

                    $connector = singleton(FlowAPIConnector::class);
                    $api->setConnector($connector);

                    $result = $api->order($xmlData, 'UTF-16');
                } catch (FlowException $e) {
                    $scheduledOrder->setField('Status', FlowStatus::FAILED);
                    $scheduledOrder->setField('Logs', json_encode($result));

                    $this->OrdersFailed++;
                    throw $e;
                }

                // Ensure we save the result
                $scheduledOrder->setField('Logs', json_encode($result));

                if (isset($result['message'])) {
                    // The expected result is simply a message, make a log note

                    $statusUpdateData = [
                        'NotifyCustomer'  => 0,
                        'CustomerVisible' => 0,
                        'Message'         => $result['message'],
                        'Status'          => OrderStatus::COMPLETED
                    ];

                    $scheduledOrder->setField('Status', FlowStatus::COMPLETED);
                    $this->OrdersSent++;
                } else {
                    // Some sort of error which we'll want to log
                    $statusUpdateData = [
                        'NotifyCustomer'  => 0,
                        'CustomerVisible' => 0,
                        'Message'         => json_encode($result),
                        'Status'          => OrderStatus::PENDING
                    ];

                    // reset the order status to pending to allow it to reprocess at a later time
                    $scheduledOrder->setField('Status', FlowStatus::PENDING);

                    // Check for duplicate orders
                    if (isset($result['FlowLogItem'])) {
                        if (isset($result['FlowLogItem']['Exception'])) {
                            if ($result['FlowLogItem']['Exception'] == 409) {
                                $statusUpdateData = [
                                    'NotifyCustomer'  => 0,
                                    'CustomerVisible' => 0,
                                    'Message'         => $result['FlowLogItem']['Description'],
                                    'Status'          => OrderStatus::COMPLETED
                                ];

                                $scheduledOrder->setField('Status', FlowStatus::COMPLETED);
                            }
                        }
                    }
                }

                $scheduledOrder->write();

                // Get the order we are processing to write notes
                $order = $scheduledOrder->Order();

                if ($order && $order->exists()) {
                    // Create status update
                    $update = OrderStatusUpdate::create($statusUpdateData);

                    $update->write();

                    $order->OrderStatusUpdates()->add($update);

                    // Mark as sent to flow
                    $order->setField('SentToFlow', 1);

                    // Ensure order status itself is correct
                    $order->setField('Status', $scheduledOrder->Status);

                    $order->write();
                }
            }
        }
    }
}
