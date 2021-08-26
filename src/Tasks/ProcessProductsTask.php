<?php

namespace Isobar\Flow\Tasks;

use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Tasks\Services\ProcessProducts;
use Isobar\Flow\Tasks\Services\StockImport;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
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
        return "30 10,14 * * *"; // process after the product import has finished
    }

    public function __construct()
    {
        parent::__construct();

        Environment::increaseMemoryLimitTo(-1);
        Environment::increaseTimeLimitTo(100000);
    }

    /**
     * Process handler for cron task
     * @throws FlowException
     */
    public function process()
    {
        $flowService = new ProcessProducts();

        $flowService->runProcessData();

        // Ensure we run the stock import immediately after
        $flowStockService = new StockImport();

        $flowStockService->runImport();
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
