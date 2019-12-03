<?php


namespace Isobar\Flow\Extensions;


use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

/**
 * Class FlowVariationExtension
 * @package Isobar\Flow\Extensions
 */
class FlowVariationExtension extends DataExtension
{
    private static $db = [
        'SKU' => 'Text'
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'OptionsSummary'  => 'Options',
        'SKU'             => 'SKU',
        'Price.Value'     => 'Price',
        'IsComplete.Nice' => 'Complete',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->insertAfter('OutOfStock', TextField::create('SKU', 'ProductCode'));
    }
}
