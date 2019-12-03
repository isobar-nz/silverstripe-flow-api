<?php


namespace Isobar\Flow\Extensions;


use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SwipeStripe\Common\Product\ComplexProduct\ComplexProduct;
use SwipeStripe\Common\Product\SimpleProduct;

/**
 * Class FlowProductExtension
 * @package src\Extensions
 *
 * @property ComplexProduct|SimpleProduct $owner
 */
class FlowProductExtension extends DataExtension
{
    private static $db = [
        'ForecastGroup' => 'Varchar(255)'
    ];

    private static $indexes = [
        'forecastgroup' => [
            'type'    => 'unique',
            'columns' => ['ForecastGroup']
        ]
    ];

    /**
     * @param array $fields
     */
    public function updateSearchableFields(&$fields)
    {
        $fields['ForecastGroup'] = [
            'Title' => 'Forecast Group'
        ];
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->insertAfter('Description',
            TextField::create('ForecastGroup', 'Forecast Group')
        );
    }
}
