<?php

namespace Isobar\Flow\Forms\GridField;

use Exception;
use Isobar\Flow\Traits\HandlesFlowSyncTrait;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\ORM\ValidationResult;
use Symbiote\GridFieldExtensions\GridFieldExtensions;

/**
 * Adds an "Export list" button to the bottom of a {@link GridField}.
 */
class GridFieldSyncFlowButton implements GridField_HTMLProvider, GridField_ActionProvider
{
    use Injectable;
    use HandlesFlowSyncTrait;

    /**
     * Fragment to write the button to
     */
    protected $targetFragment;

    /**
     * Determines what kind of sync to perform
     */
    protected $type;

    /**
     * @param string $targetFragment The HTML fragment to write the button into
     * @param string $type Determines what to sync
     */
    public function __construct($targetFragment = "buttons-before-left", $type = 'full')
    {
        $this->targetFragment = $targetFragment;
        $this->type = $type;
    }


    /**
     * Place the export button in a <p> tag below the field
     *
     * @param GridField $gridField
     *
     * @return array
     */
    public function getHTMLFragments($gridField)
    {
        GridFieldExtensions::include_requirements();

        // Build action button
        $button = new GridField_FormAction(
            $gridField,
            'sync',
            _t('Isobar\\Flow.SYNC_BUTTON', 'Sync from Flow'),
            'sync',
            null
        );

        $button
            ->addExtraClass('btn btn-secondary font-icon-sync btn--icon-large action_sync')
            ->setForm($gridField->getForm());

        return [
            $this->targetFragment => $button->Field(),
        ];
    }

    /**
     * Return a list of the actions handled by this action provider.
     *
     * Used to identify the action later on through the $actionName parameter
     * in {@link handleAction}.
     *
     * There is no namespacing on these actions, so you need to ensure that
     * they don't conflict with other components.
     *
     * @param GridField
     * @return array with action identifier strings.
     */
    public function getActions($gridField)
    {
        return ['sync'];
    }

    /**
     * Handle an action on the given {@link GridField}.
     *
     * Calls ALL components for every action handled, so the component needs
     * to ensure it only accepts actions it is actually supposed to handle.
     *
     * @param GridField $gridField
     * @param string $actionName Action identifier, see {@link getActions()}.
     * @param array $arguments Arguments relevant for this
     * @param array $data All form data
     * @throws \Isobar\Flow\Exception\FlowException
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'sync') {
            $this->doFlowSync($data, $gridField->getForm(), $gridField);
        }
    }

    /**
     * @param Form $form
     * @param string $message
     * @param int $code
     * @param GridField $gridField
     * @return mixed
     */
    public function actionComplete($form, $message, $code, $gridField)
    {
        Controller::curr()->getResponse()->addHeader('X-Status', rawurlencode($message));

        Controller::curr()->getResponse()->setStatusCode($code);

        return $gridField->FieldHolder();
    }
}
