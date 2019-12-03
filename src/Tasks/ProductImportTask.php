<?php

namespace Isobar\Flow\Tasks;

use Exception;
use Isobar\Flow\Tasks\Services\PricingImport;
use Isobar\Flow\Tasks\Services\ProductImport;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ValidationException;

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

    /**
     * @return string
     */
    public function getSchedule()
    {
        return "15 1 * * *"; // Import every night
    }

    /**
     * Import handler for cron task
     */
    public function process()
    {
        $this->run(new NullHTTPRequest());
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', 100000);

        // Product import
        $flowService = new ProductImport();

        try {
            $task = $flowService->runImport();
        } catch (Exception $e) {
            echo $e->getMessage();

            return;
        }

        // Pricing import
        $flowPricingService = new PricingImport($task);

        try {
            $flowPricingService->runImport();
        } catch (ValidationException $e) {
            echo $e->getMessage();

            return;
        }
    }
}
