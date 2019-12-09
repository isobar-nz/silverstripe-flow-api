<?php

namespace Isobar\Flow\Tasks;

use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Tasks\Services\ProcessProducts;
use Isobar\Flow\Tasks\Services\StockImport;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ValidationException;

/**
 * Class ProcessProductsTask
 * @package App\Flow\Tasks
 */
class ProcessProductsTask extends BuildTask implements CronTask
{
    /**
     * @var string
     */
    private static $segment = 'ProcessProductsTask';

    /**
     * @var string
     */
    protected $title = 'Process Pending Products';

    /**
     * @var string
     */
    protected $description = 'Process all scheduled orders and send to Flow';

    /**
     * @return string
     */
    public function getSchedule()
    {
        return "30 1 * * *"; // process after the product import has finished
    }

    /**
     * Process handler for cron task
     * @throws FlowException
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

        $flowService = new ProcessProducts();

        $flowService->runProcessData();

        // Ensure we run the stock import immediately after
        $flowStockService = new StockImport();

        $flowStockService->runImport();

    }
}
