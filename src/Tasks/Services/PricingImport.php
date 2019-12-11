<?php


namespace Isobar\Flow\Tasks\Services;

use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Services\FlowAPIConnector;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\CompletedTask;
use Isobar\Flow\Model\ScheduledWineProduct;
use Isobar\Flow\Model\ScheduledWineVariation;
use Isobar\Flow\Services\Product\PricingAPIService;
use SilverStripe\Control\Director;

/**
 * Class PricingImport
 * @package App\Flow
 */
class PricingImport
{
    /**
     * @var CompletedTask $task
     */
    public $CompletedTask;

    /**
     * PricingImport constructor.
     * @param CompletedTask $task
     */
    public function __construct(CompletedTask $task)
    {
        $this->CompletedTask = $task;
    }

    /**
     * Imports data from Flow XML feed
     * @throws FlowException
     */
    public function runImport()
    {
        if (Director::is_cli()) {
            echo "Started Pricing Import\n\n";
        }

        // log task on CompletedTasks as started
        $task = $this->CompletedTask;

        // import PIMS data to temp table
        try {
            $this->importData($task);
        } catch (Exception $e) {
            throw new FlowException($e->getMessage(), $e->getCode());
        }

        if (Director::is_cli()) {
            echo "\nCompleted Pricing Import\n\n";
        }
    }

    /**
     * @param CompletedTask $task
     * @throws \SilverStripe\ORM\ValidationException
     * @throws FlowException
     */
    private function importData(CompletedTask $task)
    {
        $api = PricingAPIService::singleton();

        $connector = singleton(FlowAPIConnector::class);
        $api->setConnector($connector);

        $result = $api->products();

        // Empty result triggers failure
        if (empty($result)) {
            $task->setField('Status', FlowStatus::CANCELLED);
            $task->setField('ImportDetails', 'Data from Flow is empty');

            $task->write();
            return;
        }


        // loop through product data and build
        foreach ($result as $pricing) {
            try {
                $this->importPricingData($pricing, $task);
            } catch (Exception $e) {
                $task->Status = FlowStatus::FAILED;
                $task->write();
                throw new FlowException($e->getMessage(), $e->getCode());
            }
        }

        $task->Status = FlowStatus::PENDING;
        $task->write();
    }

    /**
     * @param array $pricing
     * @param CompletedTask $task
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function importPricingData(array $pricing, CompletedTask $task)
    {
        // Find or make forecast group

        if (Director::is_cli()) {
            echo "Importing pricing data for " . $pricing['productCode'] . "\n";
        }

        // Find or make forecast group
        /** @var ScheduledWineProduct $scheduledProduct */
        $scheduledProduct = ScheduledWineProduct::get()->filter([
            'ForecastGroup' => $pricing['forecastGroup']
        ])->first();

        if ($scheduledProduct && $scheduledProduct->exists()) {
            // Default base to calculate variation modifiers
            $productBasePrice = $scheduledProduct->getField('BasePrice');

            // Does it have pricing? If not, set the base
            if ($productBasePrice < 1) {
                // Set it to whatever this one is
                $productBasePrice = $pricing['currentSellingPriceUnit'];

                $scheduledProduct->setField('BasePrice', $productBasePrice);

                $scheduledProduct->write();
            }

            // Get the variation
            $scheduledVariation = ScheduledWineVariation::get()->filter([
                'SKU' => $pricing['productCode']
            ])->first();

            if (!$scheduledVariation) {
                $scheduledVariation = ScheduledWineVariation::create([
                    'SKU' => $pricing['productCode']
                ]);

                $scheduledVariation->write();

                $scheduledProduct->ScheduledVariations()->add($scheduledVariation);

                $scheduledProduct->write();
            }

            // Set up the pricing for the variation - if the same as the base price, should be 0
            if ($pricing['currentSellingPriceUnit'] == $productBasePrice) {
                $scheduledVariation->setField('PriceModifierAmount', 0);
            } else {
                // Calculate the difference
                $diff = floatval($pricing['currentSellingPriceUnit']) - $productBasePrice;

                // Calculate the price modifier - must be saved without decimal place
                $moneyFormattedPrice = str_replace('.', '', $diff);

                $scheduledVariation->setField('PriceModifierAmount', $moneyFormattedPrice);
            }

            $scheduledVariation->write();
        }
    }
}
