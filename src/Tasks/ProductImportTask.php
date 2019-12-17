<?php

namespace Isobar\Flow\Tasks;

use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Tasks\Services\PricingImport;
use Isobar\Flow\Tasks\Services\ProductImport;
use SilverStripe\Control\HTTPRequest;

/**
 * Class ProductImportTask
 *
 * Responsible for scheduling the import of products
 *
 * @package App\Flow\Tasks
 * @co-author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 */
class ProductImportTask extends BaseFlowTask
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

    /**
     * @return string
     */
    public function getSchedule()
    {
        return "15 1 * * *"; // Import every night
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
