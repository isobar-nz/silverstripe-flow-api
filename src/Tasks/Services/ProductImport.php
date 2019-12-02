<?php


namespace Isobar\Flow\Tasks\Services;

use Isobar\Flow\Services\FlowAPIConnector;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\CompletedTask;
use Isobar\Flow\Model\ScheduledWineProduct;
use Isobar\Flow\Model\ScheduledWineVariation;
use Isobar\Flow\Services\Product\ProductAPIService;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;

/**
 * Class ProductImport
 * @package App\Flow
 * @co-author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 */
class ProductImport
{

    /**
     * Imports data from Flow XML feed
     *
     * @return \Isobar\Flow\Model\CompletedTask
     * @throws \SilverStripe\ORM\ValidationException
     *
     */
    public function runImport()
    {
        if (Director::is_cli()) {
            echo "Started Product Import\n\n";
        }

        // log task on CompletedTasks as started
        $task = CompletedTask::create();
        $count = CompletedTask::get()->count();

        $task->Title = 'Import Product Task ' . ($count + 1);
        $task->Status = FlowStatus::PENDING;

        $task->write();

        // import PIMS data to temp table
        $this->importData($task);

        if (Director::is_cli()) {
            echo "\nCompleted Product Import\n\n";
        }

        return $task;
    }

    /**
     * @param \Isobar\Flow\Model\CompletedTask $task
     * @throws ValidationException
     */
    private function importData(CompletedTask $task)
    {
        // Get product data
//        $result = ApiController::singleton()->getProducts();

        try {
            $api = ProductAPIService::singleton();

            $connector = singleton(FlowAPIConnector::class);
            $api->setConnector($connector);

            $result = $api->products();
        } catch (HTTPResponse_Exception $e) {
            $task->setField('Status', FlowStatus::CANCELLED);

            $task->addError($e->getMessage());

            $task->write();

            return;
        }

        // Empty result triggers failure
        if (empty($result)) {
            $task->setField('Status', FlowStatus::CANCELLED);

            $task->addError('Data from Flow is empty.');

            $task->write();

            return;
        }

        // Get count
        $task->setField('ProductCount', count($result));

        // truncate scheduledProduct and scheduledProductData table before importing data
        DB::query('TRUNCATE TABLE "App_Flow_Model_ScheduledWineProduct"');
        DB::query('TRUNCATE TABLE "App_Flow_Model_ScheduledWineVariation"');

        // loop through product data and build
        foreach ($result as $product) {
            try {
                $this->importProductData($product, $task);
            } catch (ValidationException $e) {
                $task->Status = FlowStatus::FAILED;

                $task->addError($e->getMessage());

                $task->write();
            }
        }

        if ($task->Status != FlowStatus::FAILED) {
            $task->Status = FlowStatus::PENDING;
        }
        $task->write();
    }

    /**
     * @param array $product
     * @param \Isobar\Flow\Model\CompletedTask $task
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function importProductData(array $product, CompletedTask $task)
    {
        // Find or make forecast group
        if (Director::is_cli()) {
            echo "Importing product " . $product['productCode'] . "\n";
        }

        // Find or make forecast group
        /** @var ScheduledWineProduct $scheduledProduct */
        $scheduledProduct = ScheduledWineProduct::get()->filter([
            'ForecastGroup' => $product['forecastGroup']
        ])->first();

        if (!$scheduledProduct) {
            $title = preg_replace('!\s+!', ' ', $product['productDescription']);

            $scheduledProduct = ScheduledWineProduct::create([
                'ForecastGroup' => $product['forecastGroup'],
                'Description'   => $title
            ]);

            $scheduledProduct->write();
        }

        // Check to see if this is a non-vintage option
        if ($product['vintage'] != 'NVIN') {

            // Import variations
            $scheduledVariation = ScheduledWineVariation::create([
                'Title'         => $product['vintage'],
                'SKU'           => $product['productCode'],
                'ForecastGroup' => $product['forecastGroup']
            ]);

            $scheduledVariation->write();

            $scheduledProduct->ScheduledVariations()->add($scheduledVariation);
        }

        $scheduledProduct->write();
    }
}
