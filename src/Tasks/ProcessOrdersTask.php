<?php

namespace Isobar\Flow\Tasks;

use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Tasks\Services\ProcessOrders;

/**
 * Class ProcessOrdersTask
 * @package App\Flow\Tasks
 */
class ProcessOrdersTask extends BaseFlowTask
{
    /**
     * @var string
     */
    private static $segment = 'ProcessOrdersTask';

    /**
     * @var string
     */
    protected $title = 'Process Pending Orders';

    /**
     * @var string
     */
    protected $description = 'Process all scheduled orders and send to Flow';

    /**
     * @return string
     */
    public function getSchedule()
    {
        return "*/10 * * * *"; // process scheduled records every 20 mins
    }

    /**
     * Process handler for cron task
     * @throws FlowException
     */
    public function process()
    {
        $flowService = new ProcessOrders();

        try {
            $flowService->runProcessData();
        } catch (Exception $e) {
            throw new FlowException($e->getMessage(), $e->getCode());
        }
    }
}
