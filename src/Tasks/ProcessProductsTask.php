<?php

namespace Isobar\Flow\Tasks;

use Isobar\Flow\Tasks\Services\ProcessProducts;
use Isobar\Flow\Tasks\Services\StockImport;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;

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

        $flowService = new ProcessProducts();

        try {
            $flowService->runProcessData();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        // Ensure we run the stock import immediately after
        $flowStockService = new StockImport();

        try {
            $flowStockService->runImport();
        } catch (\Exception $e) {
            echo $e->getMessage();

            return;
        }
    }
}
