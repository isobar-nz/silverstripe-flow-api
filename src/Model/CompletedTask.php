<?php


namespace Isobar\Flow\Model;

use Isobar\Flow\Services\FlowStatus;
use App\Traits\ReadOnlyDataObject;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Class CompletedTask
 *
 * Log of all completed imports
 *
 * @package App\Flow\Model
 * @author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 * @property string $Title
 * @property int $ProductCount
 * @property int $ProductsAdded
 * @property int $ProductsUpdated
 * @property int $ProductsFailed
 * @property int $ProductsDeleted
 * @property string $Status
 * @property string $ImportDetails
 */
class CompletedTask extends DataObject
{
    use ReadOnlyDataObject;

    private static $table_name = 'App_Flow_Model_CompletedTask';

    private static $singular_name = 'Completed Task';

    private static $plural_name = 'Completed Tasks';


    /**
     * @var array
     */
    private static $default_sort = 'Created DESC';

    /**
     * List of database fields. {@link DataObject::$db}
     *
     * @var array
     */
    private static $db = [
        'Title'           => DBVarchar::class,
        'ProductCount'    => DBInt::class,
        'ProductsAdded'   => DBInt::class,
        'ProductsUpdated' => DBInt::class,
        'ProductsFailed'  => DBInt::class,
        'ProductsDeleted' => DBInt::class,
        'Status'          => FlowStatus::ENUM,
        'ImportDetails'   => DBText::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Title'           => 'Title',
        'ProductCount'    => 'Total Products',
        'ProductsAdded'   => 'Products Added',
        'ProductsUpdated' => 'Products Updated',
        'ProductsFailed'  => 'Products Failed',
        'ProductsDeleted' => 'Products Archived',
        'StatusLabel'     => 'Status',
        'Created'         => 'Created'
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Make a readable, scrolling list of errors

        if ($this->ImportDetails) {
            $fields->replaceField('ImportDetails', LiteralField::create('Errors', $this->getReadableErrors()));
        }

        return $fields;
    }

    /**
     * @return DBField
     */
    public function getReadableErrors()
    {
        $errors = $this->ImportDetails;
        $list = '<div style="max-height: 500px; overflow-y: scroll; background-color: #fff; border: 1px solid #e7ebf0;padding:20px;">';

        if ($errors) {

            if ($errorArray = json_decode($errors)) {
                // Build up a list
                $list .= '<ul>';

                foreach ($errorArray as $error) {
                    $list .= "<li>{$error}</li>";
                }

                $list .= '</ul>';

            } else {
                $list .= "<p>{$errors}</p>";
            }

        } else {
            $list .= '<p>No errors.</p>';
        }

        $list .= '</div>';

        return DBField::create_field(DBHTMLText::class, $list);
    }

    /**
     * Status colour in summary list
     *
     * @return DBHTMLText
     */
    public function StatusLabel()
    {
        $html = DBHTMLText::create();

        if (strpos($this->Status, \Isobar\Flow\Services\FlowStatus::COMPLETED) !== false) {
            $html->setValue('<span style="color: #449d44;">' . $this->Status . '</span>');
        } elseif (strpos($this->Status, \Isobar\Flow\Services\FlowStatus::FAILED) !== false) {
            $html->setValue('<span style="color: #ff0000;">' . $this->Status . '</span>');
        } elseif (strpos($this->Status, FlowStatus::CANCELLED) !== false) {
            $html->setValue('<span style="color: #894f48;">' . $this->Status . '</span>');
        } else {
            $html->setValue('<span style="color: #ec971f;">' . $this->Status . '</span>');
        }

        return $html;
    }

    /**
     * @param $errorMessage
     */
    public function addError($errorMessage)
    {
        // Log the error
        $errors = $this->ImportDetails;

        if ($errorArray = json_decode($errors, true)) {
            $errorArray[] = $errorMessage;

            $errors = json_encode($errorArray);
            $this->setField('ImportDetails', $errors);
        } else {
            $this->setField('ImportDetails', json_encode([$errorMessage]));
        }
    }

    /**
     * Sends an email of errors
     */
    public function sendErrorEmail()
    {
        $adminEmail = Email::config()->get('admin_email');

        if ($adminEmail) {
            $email = Email::create();

            $email->setTo($adminEmail);
            $email->setReplyTo($adminEmail);
            $email->setSubject('Error occurred during product import on Villa Maria site');
            $email->setHTMLTemplate('App\Flow\Email\ErrorReportEmail.ss');

            $email->setData([
                'Errors'       => $this->getReadableErrors(),
                'ViewMoreLink' => Controller::join_links(
                    AdminRootController::admin_url(),
                    'flow/imports/App-Flow-Model-CompletedTask'
                )
            ]);

            $email->send();
        }
    }

    /**
     * @return bool
     */
    public function hasFailed()
    {
        return $this->Status == FlowStatus::FAILED || $this->Status == \Isobar\Flow\Services\FlowStatus::CANCELLED;
    }
}
