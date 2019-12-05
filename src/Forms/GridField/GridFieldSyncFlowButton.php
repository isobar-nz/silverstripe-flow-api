<?php

namespace Isobar\Flow\Forms\GridField;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * Adds an "Export list" button to the bottom of a {@link GridField}.
 */
class GridFieldSyncFlowButton implements GridField_HTMLProvider
{
    use Injectable;

    /**
     * Fragment to write the button to
     */
    protected $targetFragment;

    /**
     * Determines what kind of sync to perform
     */
    protected $type;

    /**
     * @var Form $flowForm
     */
    protected $flowForm;

    /**
     * @var string
     */
    protected $modalTitle = null;

    /**
     * URL for iframe
     *
     * @var string
     */
    protected $flowIframe = null;


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
        $modalID = $gridField->ID() . '_FlowModal';

        // Check for form message prior to rendering form (which clears session messages)
        $form = $this->getFlowForm();
        $hasMessage = $form && $form->getMessage();

        // Render modal
        $template = SSViewer::get_templates_by_class(static::class, '_Modal');
        $viewer = new ArrayData([
            'FlowModalTitle' => $this->getModalTitle(),
            'FlowModalID'    => $modalID,
            'FlowIframe'     => $this->getFlowIframe(),
            'FlowForm'       => $this->getFlowForm(),
        ]);
        $modal = $viewer->renderWith($template)->forTemplate();

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
            ->setForm($gridField->getForm())
            ->setAttribute('data-toggle', 'modal')
            ->setAttribute('aria-controls', $modalID)
            ->setAttribute('data-target', "#{$modalID}")
            ->setAttribute('data-modal', $modal);

        // If form has a message, trigger it to automatically open
        if ($hasMessage) {
            $button->setAttribute('data-state', 'open');
        }

        return [
            $this->targetFragment => $button->Field()
        ];
    }

    /**
     * export is an action button
     *
     * @param GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return [];
    }

    /**
     * @return string
     */
    public function getModalTitle()
    {
        return $this->modalTitle;
    }

    /**
     * @param string $modalTitle
     * @return $this
     */
    public function setModalTitle($modalTitle)
    {
        $this->modalTitle = $modalTitle;
        return $this;
    }

    /**
     * @return Form
     */
    public function getFlowForm()
    {
        return $this->flowForm;
    }

    /**
     * @param Form $flowForm
     * @return $this
     */
    public function setFlowForm($flowForm)
    {
        $this->flowForm = $flowForm;
        return $this;
    }

    /**
     * @return string
     */
    public function getFlowIframe()
    {
        return $this->flowIframe;
    }

    /**
     * @param string $flowIframe
     * @return $this
     */
    public function setFlowIframe($flowIframe)
    {
        $this->flowIframe = $flowIframe;
        return $this;
    }
}
