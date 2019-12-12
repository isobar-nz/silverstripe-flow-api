<?php


namespace Isobar\Flow\Extensions;


use Isobar\Flow\Traits\HandlesFlowSyncTrait;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ValidationResult;

class FlowGridFieldExtension extends Extension
{
    use HandlesFlowSyncTrait;

    public function updateFormActions(FieldList $actions)
    {
        $this->updateFlowActions($actions, $this->owner->getRecord());
    }

    /**
     * @param Form $form
     * @param $message
     * @param $code
     * @return mixed
     */
    public function actionComplete($form, $message, $code)
    {
        $type = $code == 200 ? 'good' : 'bad';
        $form->sessionMessage($message, $type, ValidationResult::CAST_HTML);

        $link = $form->getController()->Link();

        // TODO: This redirect doesn't work
        return $this->owner->getController()->redirect($link);
    }
}
