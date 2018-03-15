<?php

/**
 * Class CatalogPageAdmin
 */

namespace littlegiant\CatalogManager;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\DB;
use SilverStripe\Forms\Form;
use SilverStripe\Control\Controller;

class CatalogPageAdmin extends ModelAdmin
{
  
    /**
     * @config
     * @var string
     */
    private static $menu_icon = 'silverstripe-catalogmanager/images/catalog.png';

    // This allows this class to be hidden, but it's subclasses to be shown
    // In the CMS Main Menu
    public function canView($member = null)
    {
        $class = get_called_class();
        if ($class === self::class) {
            return false;
        }

        return parent::canView($member);
    }

    /**
     * @inheritdoc
     */
    public function getEditForm($id = null, $fields = null)
    {
        $model = singleton($this->modelClass);
        if ($model->has_extension('CatalogPageExtension') || $model->has_extension('CatalogDataObjectExtension')) {
            $list = $this->getList()->setDataQueryParam([
                'Versioned.stage' => 'Stage'
            ]);

            $listField = GridField::create(
                $this->sanitiseClassName($this->modelClass),
                false,
                $list,
                $fieldConfig = GridFieldConfig_RecordEditor::create($this->stat('page_length'))
                    ->removeComponentsByType('GridFieldFilterHeader')
                    ->removeComponentsByType('GridFieldDeleteAction')
                    ->addComponent(new GridfieldPagePublishAction())
            );

            $form = CMSForm::create(
                $this,
                'EditForm',
                new FieldList($listField),
                new FieldList()
            )->setHTMLID('Form_EditForm');

            // Validation
            if (singleton($this->modelClass)->hasMethod('getCMSValidator')) {
                $detailValidator = singleton($this->modelClass)->getCMSValidator();
                $listField->getConfig()->getComponentByType('GridFieldDetailForm')->setValidator($detailValidator);
            }

            if ($gridField = $listField->getConfig()->getComponentByType('GridFieldDetailForm')) {
                $gridField->setItemRequestClass('CatalogPageGridFieldDetailForm_ItemRequest');
            }

            $form->setResponseNegotiator($this->getResponseNegotiator());
            $form->addExtraClass('cms-edit-form cms-panel-padded center');
            $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
            $editFormAction = Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'EditForm');
            $form->setFormAction($editFormAction);
            $form->setAttribute('data-pjax-fragment', 'CurrentForm');

            /** add sorting if we have a field for... */
            if (class_exists('GridFieldSortableRows') && $sortField = $model->getSortFieldname()) {
                $fieldConfig->addComponent(new GridFieldSortableRows($sortField));
            }
        } elseif (method_exists($model, 'getAdminListField')) {
            $form = CMSForm::create(
                $this,
                'EditForm',
                new FieldList($model->getAdminListField()),
                new FieldList(FormAction::create('doSave', 'Save'))
            )->setHTMLID('Form_EditForm');

            $form->setResponseNegotiator($this->getResponseNegotiator());
            $form->addExtraClass('cms-edit-form cms-panel-padded center');
            $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
            $editFormAction = Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'EditForm');
            $form->setFormAction($editFormAction);
            $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        } else {
            $form = parent::getEditForm();
        }

        $this->extend('updateEditForm', $form);

        return $form;
    }

    /**
     * Hook to update sort column on live versions of items after a sort has occured.
     * @param $pages
     */
    public function onAfterGridFieldRowSort($pages)
    {
        $modelClass = $this->modelClass;
        $model = singleton($this->modelClass);
        $sortField = $model->getSortFieldname();

        // if the sort is the default, then we should update SiteTree. If its a custom sort, update the model.
        if ($sortField == 'Sort') {
            $modelClass = 'SiteTree';
        }
        if ($model::config()->get('automatic_live_sort') == true) {
            foreach ($pages as $page) {
                if ($page instanceof $modelClass) {
                    DB::query('UPDATE ' . $modelClass . '_Live SET ' . $model->getSortFieldname() . '=' . $page->{$model->getSortFieldname()} . ' WHERE ID=' . $page->ID);
                }
            }
        }
    }
}
