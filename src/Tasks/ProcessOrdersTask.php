<?php

namespace Isobar\Flow\Tasks;

use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Tasks\Services\ProcessOrders;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;

/**
 * Class ProcessOrdersTask
 * @package App\Flow\Tasks
 */
class ProcessOrdersTask extends BuildTask implements CronTask
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
     */
    public function process()
    {
        $this->run(new NullHTTPRequest());
    }

    /**
     * @param HTTPRequest $request
     * @throws FlowException
     */
    public function run($request)
    {
        ini_set('memory_limit', -1);

        $flowService = new ProcessOrders();

        try {
            $flowService->runProcessData();
        } catch (Exception $e) {
            throw new FlowException($e->getMessage(), $e->getCode());
        }
    }
}
