<?php

namespace Isobar\Flow\Tasks\Services;

use App\Ecommerce\Product\WineProduct;
use BadMethodCallException;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\ChangeSet;

trait ChangeSetPublishTrait
{
    /**
     * @var ChangeSet
     */
    protected $changeSet = null;

    /**
     * Publish / update a record
     *
     * @param DataObject $record
     * @throws ValidationException
     */
    protected function publish(DataObject $record)
    {
        if (empty($this->changeSet)) {
            throw new BadMethodCallException("runProcessData not called");
        }
        if ($record->isChanged(null, DataObject::CHANGE_VALUE)) {
            $record->write();
        }

        // If item is an unpublished wine product, do not publish it
        // This requires records to be manually published
        if ($record instanceof WineProduct && !$record->isPublished()) {
            return;
        }

        // Ensure changeset is saved when adding the first item
        if (!$this->changeSet->isInDB()) {
            $this->changeSet->write();
        }
        $this->changeSet->addObject($record);
    }

    protected function initChangeSet()
    {
        $this->changeSet = ChangeSet::create();
    }

    protected function finishChangeSet()
    {
        // If we have saved at least one record, publish changeset
        if ($this->changeSet->isInDB()) {
            $this->changeSet->publish();
        }
        $this->changeSet = null;
    }
}
