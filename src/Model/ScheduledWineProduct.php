<?php

namespace Isobar\Flow\Model;

use Isobar\Flow\Services\FlowStatus;
use App\Traits\ReadOnlyDataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class ScheduledWineProduct
 *
 * @package App\Flow\Model
 * @author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 * @property string $ForecastGroup
 * @property string $Description
 * @property float $BasePrice
 * @property string $Status
 * @method DataList|\Isobar\Flow\Model\ScheduledWineVariation[] ScheduledVariations()
 */
class ScheduledWineProduct extends DataObject
{
    use ReadOnlyDataObject;

    private static $table_name = 'App_Flow_Model_ScheduledWineProduct';

    private static $singular_name = 'Scheduled Wine Product';

    private static $plural_name = 'Scheduled Wine Products';

    /**
     * @var array
     */
    private static $default_sort = 'Created DESC';

    private static $db = [
        'ForecastGroup' => DBVarchar::class,
        'Description' => DBVarchar::class,
        'BasePrice' => DBDecimal::class,
        'Status' => FlowStatus::ENUM
    ];

    private static $summary_fields = [
        'ForecastGroup' => 'Forecast Group',
        'Description' => 'Description',
        'BasePrice' => 'Base Price',
        'StatusLabel' => 'Status',
        'Created' => 'Created'
    ];

    private static $has_many = [
        'ScheduledVariations' => ScheduledWineVariation::class
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'ForecastGroup' => [
            'Title' => 'Forecast Group'
        ],
        'Description' => [
            'Title' => 'Description'
        ]
    ];

    public function getTitle()
    {
        return $this->ForecastGroup;
    }

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
