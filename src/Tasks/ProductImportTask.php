<?php

namespace Isobar\Flow\Tasks;

use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Tasks\Services\PricingImport;
use Isobar\Flow\Tasks\Services\ProductImport;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;

/**
 * Class ProductImportTask
 *
 * Responsible for scheduling the import of products
 *
 * @package App\Flow\Tasks
 * @co-author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 */
class ProductImportTask extends BuildTask implements CronTask
{
    /**
     * @var string
     */
    private static $segment = 'ProductImportTask';

    /**
     * @var string
     */
    protected $title = 'Import Product Data';

    /**
     * @var string
     */
    protected $description = 'Import all products from flow into temp table';

    public function __construct()
    {
        parent::__construct();

        Environment::increaseMemoryLimitTo(-1);
        Environment::increaseTimeLimitTo(100000);
    }

    /**
     * @return string
     */
    public function getSchedule()
    {
        return "0 10,14 * * *"; // Import twice daily at 10am and 2pm
    }

    /**
     * When this script is supposed to run the CronTaskController will execute
     * process().
     *
     * @return void
     * @throws FlowException
     */
    public function process()
    {
        // Product import
        $flowService = new ProductImport();

        $task = $flowService->runImport();

        // Pricing import
        $flowPricingService = new PricingImport($task);

        $flowPricingService->runImport();
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
