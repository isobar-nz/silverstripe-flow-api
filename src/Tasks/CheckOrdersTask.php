<?php

namespace Isobar\Flow\Tasks;

use Isobar\Flow\Order\OrderExtension;
use Isobar\Flow\Tasks\Services\ProcessOrders;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;
use SwipeStripe\Order\Order;

/**
 * Class CheckOrdersTask
 * @package App\Flow\Tasks
 */
class CheckOrdersTask extends BuildTask implements CronTask
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
     */
    public function process()
    {
        $this->run(new NullHTTPRequest());
    }

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        ini_set('memory_limit', -1);

        // Get all orders not yet sent to Flow within the valid time period
        $orders = Order::get()
            ->filter([
                'Scheduled'           => 0,
                'IsCart'              => 0,
                'Created:GreaterThan' => '2019-05-24 00:00:00'
            ]);

        // run through and schedule
        /** @var Order|OrderExtension $order */
        foreach ($orders as $order) {
            if ($order->UnpaidTotal()->getDecimalValue() <= 0) {
                echo 'Order #' . $order->ID . ' to be scheduled' . "\n";

                $order->scheduleOrder();
            }
        }
    }
}
