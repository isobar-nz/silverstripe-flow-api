<?php


namespace Isobar\Flow\Tasks\Services;

use App\Ecommerce\Product\WineProduct;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\CompletedTask;
use Isobar\Flow\Model\ScheduledWineProduct;
use Isobar\Flow\Model\ScheduledWineVariation;
use App\Pages\ShopWinesPage;
use Exception;
use SilverStripe\ORM\ValidationException;
use SwipeStripe\Common\Product\ComplexProduct\ComplexProductVariation;
use SwipeStripe\Common\Product\ComplexProduct\ComplexProductVariation_Options;
use SwipeStripe\Common\Product\ComplexProduct\ProductAttribute;
use SwipeStripe\Common\Product\ComplexProduct\ProductAttributeOption;
use SwipeStripe\Order\OrderItem\OrderItem;

/**
 * Class ProcessProducts
 *
 * Runs through all products imported from the API and processes them
 * into the CMS
 *
 * @package   App\Flow
 * @co-author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 */
class ProcessProducts
{
    public $ProductsFailed = 0;

    public $ProductsUpdated = 0;

    public $ProductsAdded = 0;

    public $ProductsDeleted = 0;

    /**
     * Processes all orders from scheduled list
     * @throws FlowException
     */
    public function runProcessData()
    {
        echo "Beginning processing products\n\n";

        // Process scheduled products and import
        try {
            $this->processProducts();
        } catch (ValidationException $e) {
            throw new FlowException($e->getMessage(), $e->getCode());
        }

        echo "\nCompleted processing products\n\n";
    }

    /**
     * @throws ValidationException
     * @throws FlowException
     */
    private function processProducts()
    {
        /** @var CompletedTask $completedTask */
        $completedTask = CompletedTask::get()->filter('Status', FlowStatus::PENDING)->first();

        if (!$completedTask) {
            // Task has been cancelled because of data issues, do not proceed
            echo "\nNo valid tasks found, aborting\n\n";

            return;
        }

        // Process 100 products at one time
        $scheduledProducts = ScheduledWineProduct::get()->filter([
            'Status' => FlowStatus::PENDING
        ])->limit(100);

        if ($scheduledProducts->count()) {
            /** @var ScheduledWineProduct $scheduledProduct */
            foreach ($scheduledProducts as $scheduledProduct) {
                $scheduledProduct->setField('Status', FlowStatus::PROCESSING);
                $scheduledProduct->write();

                echo "Processing product " . $scheduledProduct->ForecastGroup . PHP_EOL;

                $wineProductID = 0;

                // Try to process - catch any exceptions
                try {
                    $wineProductID = $this->processWineProduct($scheduledProduct);

                    $scheduledProduct->setField('Status', FlowStatus::COMPLETED);
                } catch (Exception $e) {
                    $scheduledProduct->setField('Status', FlowStatus::FAILED);

                    $completedTask->addError('Failed importing product: ' . $scheduledProduct->ForecastGroup . ': ' . $e->getMessage());

                    // Keep counter of failed products
                    $this->ProductsFailed++;

                    throw new FlowException($e->getMessage(), $e->getCode());
                } finally {
                    $scheduledProduct->write();
                }

                // Does it have variations?
                $scheduledVariations = $scheduledProduct->ScheduledVariations();

                if ($scheduledVariations) {
                    // Process these as well
                    /** @var ScheduledWineVariation $scheduledVariation */
                    foreach ($scheduledVariations as $scheduledVariation) {
                        echo "Processing variation " . $scheduledVariation->SKU . PHP_EOL;

                        // Try to process - catch any exceptions
                        try {
                            $this->processWineVariation($scheduledVariation, $wineProductID);

                            $scheduledVariation->setField('Status', FlowStatus::COMPLETED);
                        } catch (Exception $e) {
                            $scheduledVariation->setField('Status', FlowStatus::FAILED);

                            $completedTask->addError('Failed importing variation: ' . $scheduledVariation->SKU . ': ' . $e->getMessage());

                            // Keep counter of failed products
                            $this->ProductsFailed++;

                            throw new FlowException($e->getMessage(), $e->getCode());
                        } finally {
                            $scheduledVariation->write();
                        }
                    }
                }
            }

            if ($completedTask) {
                $completedTask->ProductsUpdated = ($completedTask->ProductsUpdated + $this->ProductsUpdated);
                $completedTask->ProductsFailed = ($completedTask->ProductsFailed + $this->ProductsFailed);
                $completedTask->ProductsAdded = ($completedTask->ProductsAdded + $this->ProductsAdded);
                $completedTask->write();
            }
        }

        // Check for errors
        if ($completedTask->hasFailed()) {
            $completedTask->sendErrorEmail();
        }

        // Finish task by removing extra
        $scheduledProducts = ScheduledWineProduct::get()->filter('Status', FlowStatus::PENDING);

        if ($scheduledProducts->count() == 0) {
            $this->runDelete($completedTask);
        }
    }

    /**
     * Converts a scheduled product into a real WineProduct
     *
     * @param ScheduledWineProduct $scheduledProduct
     * @return int
     * @throws ValidationException
     */
    private function processWineProduct(ScheduledWineProduct $scheduledProduct): int
    {
        // Get the catalog page
        $shopWines = ShopWinesPage::get()->first();

        // First find the wine product by forecast group
        $wineProduct = WineProduct::get()
            ->filter('ForecastGroup', $scheduledProduct->ForecastGroup)
            ->first();

        if ($wineProduct && $wineProduct->exists()) {
            // Updated product
            $this->ProductsUpdated++;

            // Update pack size
            $wineProduct->update([
                'PackSize' => $scheduledProduct->PackSize
            ]);
        } else {
            // Create a new WineProduct in draft mode
            $wineProduct = WineProduct::create();

            $wineProduct->update([
                'Title'         => $scheduledProduct->Description,
                'ForecastGroup' => $scheduledProduct->ForecastGroup,
                'PackSize'      => $scheduledProduct->PackSize ?: 6
            ]);

            // If we have a parent
            if ($shopWines && $shopWines->exists()) {
                $wineProduct->setField('ParentID', $shopWines->ID);
            }

            // New product
            $this->ProductsAdded++;
        }

        // Process the price
        if ($scheduledProduct->BasePrice > 0) {
            // @todo fix + unit test
            $moneyFormattedPrice = str_replace('.', '', $scheduledProduct->BasePrice);

            $wineProduct->setField('BasePriceAmount', $moneyFormattedPrice);
            $wineProduct->setField('BasePriceCurrency', 'NZD');
        }

        // Process the pack price
        if ($scheduledProduct->PackPrice > 0) {
            // @todo fix + unit test
            $moneyFormattedPrice = str_replace('.', '', $scheduledProduct->PackPrice);

            $wineProduct->setField('PackPriceAmount', $moneyFormattedPrice);
            $wineProduct->setField('PackPriceCurrency', 'NZD');
        }

        // Enough info to write
        // @todo only run if isChanged()
        $wineProduct->write();

        // If the wine product has been published in the past, it should be safe to re-publish
        if ($wineProduct->isPublished()) {
            $wineProduct->publishRecursive();
        }


        // Processed ID is returned
        return $wineProduct->ID;
    }

    /**
     * Processes variations
     *
     * @param ScheduledWineVariation $scheduledWineVariation
     * @param int $wineProductID
     * @throws FlowException
     */
    private function processWineVariation(ScheduledWineVariation $scheduledWineVariation, int $wineProductID)
    {
        // We should only process the variation if we have a valid wine product ID
        if ($wineProductID > 0) {
            // Process the variation - first find the wine product
            /** @var WineProduct $wineProduct */
            $wineProduct = WineProduct::get()->byID($wineProductID);

            if ($wineProduct && $wineProduct->exists()) {
                // Get the "Vintage" attribute
                $vintageAttribute = ProductAttribute::get()->filter([
                    'Title'     => 'Vintage',
                    'ProductID' => $wineProductID
                ])->first();

                // Do we have a Product Attribute Option with the right title?
//                $productAttributeOption = ProductAttributeOption::get()->filter([
//                    'Title'              => $scheduledWineVariation->Title,
//                    'ProductAttributeID' => $vintageAttribute->ID
//                ])->first();


                // Find the variation
                /** @var ComplexProductVariation $wineProductVariation */
                $wineProductVariation = $wineProduct->ProductVariations()->filter([
                    'SKU' => $scheduledWineVariation->SKU
                ])->first();

                if (!$wineProductVariation) {
                    $wineProductVariation = $this->createWineVariation($scheduledWineVariation, $wineProductID);
                    $wineProduct->ProductVariations()->add($wineProductVariation);
                    $wineProductVariation->write();
                }
            } else {
                throw new FlowException('Wine product not found');
            }
        } else {
            throw new FlowException('Missing product ID');
        }
    }

    /**
     * @param ScheduledWineVariation $scheduledWineVariation
     * @param int $wineProductID
     * @return ComplexProductVariation
     * @throws Exception
     */
    private function createWineVariation(ScheduledWineVariation $scheduledWineVariation, int $wineProductID)
    {
        // Get the corresponding variation type
        $vintageAttribute = ProductAttribute::get()->filter([
            'Title'     => $scheduledWineVariation->VariationType,
            'ProductID' => $wineProductID
        ])->first();

        if ($vintageAttribute && $vintageAttribute->exists()) {
            $vintageAttributeID = $vintageAttribute->ID;
        } else {
            $vintageAttribute = ProductAttribute::create();

            $vintageAttribute->update([
                'Title'     => $scheduledWineVariation->VariationType,
                'ProductID' => $wineProductID
            ]);

            // @todo if isChanged && isPublished()

            $vintageAttributeID = $vintageAttribute->write();
            $vintageAttribute->publishRecursive();
        }

        // Do we have a Product Attribute Option with the right title?
        $productAttributeOption = ProductAttributeOption::get()->filter([
            'Title'              => $scheduledWineVariation->Title,
            'ProductAttributeID' => $vintageAttributeID
        ])->first();


        // Format the price modifier amount
        $modifier = $scheduledWineVariation->PriceModifierAmount;

        if ($modifier > 0) {
            // @todo fix
            $modifier = str_replace('.', '', $modifier);
        }

        if ($productAttributeOption && $productAttributeOption->exists()) {
            // Ensure price is updated
            $productAttributeOption->setField('SKU', $scheduledWineVariation->SKU);
            $productAttributeOption->setField('PriceModifierAmount', $modifier);

            // @todo move out of the loop, put inside the if() statement along with publish()
            $productAttributeOption->write();

            $productAttributeOptionID = $productAttributeOption->ID;
        } else {
            // Create it
            $productAttributeOption = ProductAttributeOption::create();

            $productAttributeOption->update([
                'ClassName'             => ProductAttributeOption::class,
                'Title'                 => $scheduledWineVariation->Title,
                'ProductAttributeID'    => $vintageAttribute->ID,
                'PriceModifierAmount'   => $modifier,
                'PriceModifierCurrency' => 'NZD' // TODO: Use dynamic currency
            ]);

            // @todo same as above
            $productAttributeOptionID = $productAttributeOption->write();
        }

        // @todo only publish if changed
        $productAttributeOption->publishSingle();

        // Get the options
        /** @var ComplexProductVariation_Options $variationOptions */
        $variationOptions = ComplexProductVariation_Options::get()
            ->filter('ProductAttributeOptionID', $productAttributeOptionID)
            ->first();

        // if it exists, we need to check the variation
        if ($variationOptions && $variationOptions->exists()) {
            $wineProductVariation = $variationOptions->ComplexProductVariation();

            // Check it has a SKU
            /** @noinspection PhpUndefinedFieldInspection */
            if (!$wineProductVariation->SKU) {
                $wineProductVariation->setField('SKU', $scheduledWineVariation->SKU);

                // @todo only write / publish if changed
                $wineProductVariation->write();
                $wineProductVariation->publishSingle();
            }
        } else {
            // We're going to make a new one
            $wineProductVariation = ComplexProductVariation::create();

            $wineProductVariation->update([
                'SKU'       => $scheduledWineVariation->SKU,
                'ProductID' => $wineProductID
            ]);

            $wineProductVariationID = $wineProductVariation->write();
            $wineProductVariation->publishSingle();

            // Now connect all the options to the attribute and variation
            $productVariationOptions = ComplexProductVariation_Options::create();

            $productVariationOptions->update([
                'ComplexProductVariationID' => $wineProductVariationID,
                'ProductAttributeOptionID'  => $productAttributeOptionID
            ]);

            // Write
            $productVariationOptions->write();
        }


        $this->ProductsAdded++;

        return $wineProductVariation;
    }

    /**
     * @param CompletedTask $completedTask
     * @throws ValidationException
     * @throws FlowException
     */
    private function runDelete(CompletedTask $completedTask)
    {
        // if all scheduled products have been processed and completed task isn't
        // flagged as complete run delete old records
        try {
            // Archive old products
            $this->deleteWineVariations();
            $this->deleteOldProducts();

            $completedTask->Status = FlowStatus::COMPLETED;
            $completedTask->ProductsDeleted = $this->ProductsDeleted;
            $completedTask->write();
        } catch (Exception $exception) {
            $completedTask->Status = FlowStatus::FAILED;
            $completedTask->addError($exception->getMessage());
            $completedTask->write();

            throw new FlowException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @return bool
     * Deletes all the products that don't exist in the imported data table (ScheduledProduct)
     *
     * @todo - Check if this method is
     *        - 1 safe to keep / fix
     *        - 2 needed
     */
    private function deleteOldProducts()
    {
        // get distinct product sub systems from imported scheduled product data
        $scheduledProductIDs = ScheduledWineProduct::get()
            ->column('ForecastGroup');

        // loop through current subsystems if not in import data then archive
        $productsToDelete = WineProduct::get()->filter('ForecastGroup:not', $scheduledProductIDs);

        if ($productsToDelete->count() > 0) {
            /** @var WineProduct $product */
            foreach ($productsToDelete as $product) {
                // Un-publish
//                $product->doUnpublish();
            }

            $this->ProductsDeleted += $productsToDelete->count();
        }

        // Publish products to clean up
        $productsToPublish = WineProduct::get()->filter('ForecastGroup', $scheduledProductIDs);

        /** @var WineProduct $product */
        foreach ($productsToPublish as $product) {
            // @todo maybe comment this out for now, until we figure out if it's necessary
            if ($product->isPublished()) {
                $product->publishRecursive();
            }
        }

        return true;
    }


    /**
     * @return bool
     * Deletes all the products that don't exist in the imported data table (ScheduledProduct)
     */
    private function deleteWineVariations()
    {
        // get distinct product sub systems from imported scheduled product data
        $scheduledVariationSKUs = ScheduledWineVariation::get()
            ->column('SKU');

        // loop through current subsystems if not in import data then archive
        $variations = ComplexProductVariation::get()->filter('SKU:not', $scheduledVariationSKUs);

        $variationCount = $variations->count();

        /** @var ComplexProductVariation $variation */
        foreach ($variations as $variation) {
            // Remove the Attribute option with this ID
            $attributeOptions = ComplexProductVariation_Options::get()
                ->filter('ComplexProductVariationID', $variation->ID);

            /** @var ComplexProductVariation_Options $attributeOption */
            foreach ($attributeOptions as $attributeOption) {
                // Get the attribute
                $option = $attributeOption->ProductAttributeOption();

                // Remove option attached to this attribute
                if ($option && $option->exists()) {
                    $option->doUnpublish();
                    $option->delete();
                }

                // Remove attribute
                $attributeOption->delete();
            }

            // Now remove any that are in the cart
            $cartItems = OrderItem::get()->filter([
                'PurchasableClass' => ComplexProductVariation::class,
                'PurchasableID'    => $variation->ID,
                'Order.IsCart'     => 1
            ]);

            /** @var OrderItem $cartItem */
            foreach ($cartItems as $cartItem) {
                $cartItem->delete();
            }

            // Remove this one
            $variation->doUnpublish();
            $variation->delete();
        }

        $this->ProductsDeleted += $variationCount;
        return true;
    }
}
