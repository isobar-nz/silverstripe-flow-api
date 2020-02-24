<?php

namespace Isobar\Flow\Model;

use Isobar\Flow\Services\FlowStatus;
use App\Traits\ReadOnlyDataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SwipeStripe\Order\Order;

/**
 * Class ScheduledOrder
 *
 * @package App\Flow\Model
 * @author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 * @property string $Status
 * @property boolean $Active
 * @property string $XmlData
 * @property int $OrderID
 * @method Order Order()
 */
class ScheduledOrder extends DataObject
{
    use ReadOnlyDataObject;

    private static $table_name = 'App_Flow_Model_ScheduledOrder';

    private static $singular_name = 'Scheduled Order';

    private static $plural_name = 'Scheduled Orders';

    /**
     * @var array
     */
    private static $default_sort = 'Created DESC';

    private static $db = [
        'Status'  => FlowStatus::ENUM,
        'Active'  => DBBoolean::class,
        'XmlData' => DBText::class,
        'Logs'    => DBText::class
    ];

    private static $has_one = [
        'Order' => Order::class
    ];

    private static $summary_fields = [
        'Order.ID'            => 'Order ID',
        'Order.FlowReference' => 'Order Reference',
        'Active.Nice'         => 'Active',
        'StatusLabel'         => 'Status',
        'Created'             => 'Created'
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'OrderID' => [
            'Title' => 'Order Number'
        ]
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Make some fields read only
        $fields->replaceField(
            'Active',
            $fields->dataFieldByName('Active')->performReadonlyTransformation()
        );
        $fields->replaceField(
            'XmlData',
            $fields->dataFieldByName('XmlData')->performReadonlyTransformation()
        );
        $fields->replaceField(
            'Logs',
            $fields->dataFieldByName('Logs')->performReadonlyTransformation()
        );
        $fields->replaceField(
            'OrderID',
            $fields->dataFieldByName('OrderID')->performReadonlyTransformation()
        );


        return $fields;
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

    /**
     * @param Member|null $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        return Permission::check('ADMIN', 'any', $member);
    }
}
