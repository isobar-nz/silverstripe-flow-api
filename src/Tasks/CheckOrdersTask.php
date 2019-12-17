<?php

namespace Isobar\Flow\Tasks;

use Isobar\Flow\Exception\FlowException;
use SilverStripe\Control\HTTPRequest;
use SwipeStripe\Order\Order;
use SwipeStripe\Order\Status\OrderStatus;

/**
 * Class CheckOrdersTask
 * @package App\Flow\Tasks
 */
class CheckOrdersTask extends BaseFlowTask
{
    /**
     * @var string
     */
    private static $segment = 'CheckOrdersTask';

    /**
     * @var string
     */
    protected $title = 'Check Pending Orders';

    /**
     * @var string
     */
    protected $description = 'Ensures valid orders have been scheduled to Flow';

    /**
     * @return string
     */
    public function getSchedule()
    {
        return "*/30 * * * *"; // checks for orders every 30 mins
    }

    /**
     * Process handler for cron task
     * @throws \Isobar\Flow\Exception\FlowException
     */
    public function process()
    {
        // Get all orders not yet sent to Flow within the valid time period
        $orders = Order::get()
            ->filter([
                'Scheduled' => 0,
                'IsCart'    => 0
            ])->exclude('Status', [
                OrderStatus::CANCELLED,
                OrderStatus::REFUNDED
            ]);

        // run through and schedule
        /** @var Order|\Isobar\Flow\Extensions\OrderExtension $order */
        foreach ($orders as $order) {
            if ($order->UnpaidTotal()->getDecimalValue() <= 0) {
                echo 'Order #' . $order->ID . ' to be scheduled' . "\n";

                $order->scheduleOrder();
            }
        }
    }

    /**
     * @param HTTPRequest $request
     * @throws FlowException
     */
    public function run($request)
    {
        $this->process();
    }
}
