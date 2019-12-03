<?php

namespace Isobar\Flow\Forms\GridField;

use Isobar\Flow\Model\CompletedTask;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationResult;

/**
 * Class CompletedTask_ItemRequest
 * @package App\Flow\Admin
 *
 * @property CompletedTask $record
 */
class CompletedTask_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    private static $allowed_actions = [
        'edit',
        'view',
        'ItemEditForm'
    ];

    /**
     * @return HTTPResponse|Form
     */
    public function ItemEditForm()
    {
        $editForm = parent::ItemEditForm();

        if ($this->record->hasFailed()) {
            $editForm->Actions()->push(FormAction::create('doSendEmail', 'Send email of errors to admin')
                ->setUseButtonTag(true)
                ->addExtraClass('btn-primary')
                ->setReadonly(false));
        }

        return $editForm;
    }

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse|DBHTMLText
     */
    public function doSendEmail($data, $form)
    {
        // Save from form data
        $this->record->sendErrorEmail();

        $message = 'Sent error log email.';

        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);

        // Redirect after save
        return $this->redirectAfterSave(false);
    }
}
