<?php


namespace Isobar\Flow\Tasks\Services;

use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Exception\ValidationException;
use Isobar\Flow\Services\FlowAPIConnector;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\ScheduledOrder;
use Isobar\Flow\Services\Product\OrderAPIService;
use App\Pages\MediaPages\WineClubEvent;
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
        } catch (\Exception $e) {
            throw new FlowException($e->getMessage(), $e->getMessage());
        }

        echo "\nCompleted processing orders\n\n";
    }

    /**
     * @throws \SilverStripe\ORM\ValidationException
     * @throws FlowException
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
                $scheduledOrder->setField('Status', FlowStatus::PROCESSING);

                $xmlData = $scheduledOrder->XmlData;

                // If no data, fail
                if (empty($xmlData)) {
                    $scheduledOrder->setField('Status', FlowStatus::FAILED);
                    $scheduledOrder->write();

                    $this->OrdersFailed++;

                    continue;
                }

                // TODO update for Flow integration with Events
                // if there are only events in the cart, then do not post to flow
                /** @noinspection PhpUndefinedMethodInspection */
                $items = $scheduledOrder->Order()->OrderItems();
                $eventsCount = 0;
                foreach ($items as $item) {
                    if ($item->PurchasableClass === WineClubEvent::class) {
                        $eventsCount++;
                    }
                }

                /** @noinspection PhpUndefinedMethodInspection */
                if ($eventsCount === $items->count()) {
                    // Some sort of error which we'll want to log
                    $statusUpdateData = [
                        'NotifyCustomer'  => 0,
                        'CustomerVisible' => 0,
                        'Message'         => 'Events Only',
                        'Status'          => OrderStatus::COMPLETED
                    ];

                    $scheduledOrder->setField('Status', FlowStatus::COMPLETED);

                    $this->OrdersSent++;
                } else {
                    $result = [];

                    try {
                        $api = OrderAPIService::singleton();

                        $connector = singleton(FlowAPIConnector::class);
                        $api->setConnector($connector);

                        $result = $api->order($xmlData);
                    } catch (FlowException $e) {
                        $scheduledOrder->setField('Status', FlowStatus::FAILED);

                        $this->OrdersFailed++;
                        throw $e;
                    }

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
