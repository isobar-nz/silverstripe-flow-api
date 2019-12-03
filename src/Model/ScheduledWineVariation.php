<?php

namespace Isobar\Flow\Model;

use Isobar\Flow\Services\FlowStatus;
use App\Traits\ReadOnlyDataObject;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class ScheduledWineVariation
 *
 * @package App\Flow\Model
 * @author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 * @property string $SKU
 * @property string $ForecastGroup
 * @property float $PriceModifierAmount
 * @property string $Status
 * @property string $Title
 * @property int $ScheduledProductID
 * @method \Isobar\Flow\Model\ScheduledWineProduct ScheduledProduct()
 */
class ScheduledWineVariation extends DataObject
{
    use ReadOnlyDataObject;

    private static $table_name = 'App_Flow_Model_ScheduledWineVariation';

    private static $singular_name = 'Scheduled Wine Varietal';

    private static $plural_name = 'Scheduled Wine Varietals';

    /**
     * @var array
     */
    private static $default_sort = 'Created DESC';

    private static $db = [
        'SKU'                 => DBVarchar::class,
        'ForecastGroup'       => DBVarchar::class,
        'PriceModifierAmount' => DBDecimal::class,
        'Status'              => FlowStatus::ENUM,
        'Title'               => DBVarchar::class,
        'VariationType'       => DBVarchar::class

    ];

    private static $summary_fields = [
        'Title'               => 'Title',
        'SKU'                 => 'Product Code',
        'ForecastGroup'       => 'Forecast Group',
        'PriceModifierAmount' => 'Price Modifier',
        'StatusLabel'         => 'Status',
        'Created'             => 'Created',
        'VariationType'       => 'Type'
    ];

    private static $has_one = [
        'ScheduledProduct' => ScheduledWineProduct::class
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'ForecastGroup' => [
            'Title' => 'Forecast Group'
        ],
        'SKU'           => [
            'Title' => 'SKU'
        ]
    ];

    /**
     * Helper method
     *
     * @return bool
     */
    public function getIsComplete()
    {
        return $this->Status == FlowStatus::COMPLETED;
    }

    /**
     * Status colour in summary list
     *
     * @return DBHTMLText
     */
    public function StatusLabel()
    {
        $html = DBHTMLText::create();

        if (strpos($this->Status, FlowStatus::COMPLETED) !== false) {
            $html->setValue('<span style="color: #449d44;">' . $this->Status . '</span>');
        } elseif (strpos($this->Status, FlowStatus::FAILED) !== false) {
            $html->setValue('<span style="color: #ff0000;">' . $this->Status . '</span>');
        } else {
            $html->setValue('<span style="color: #ec971f;">' . $this->Status . '</span>');
        }

        return $html;
    }
}
