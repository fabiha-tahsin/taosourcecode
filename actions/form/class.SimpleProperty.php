<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2008-2010 (original work) Deutsche Institut für Internationale Pädagogische Forschung (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 *
 */

use oat\generis\model\GenerisRdf;
use oat\generis\model\OntologyRdfs;
use oat\oatbox\service\ServiceManager;
use oat\tao\helpers\form\elements\xhtml\Validators;
use oat\tao\model\Lists\Business\Specification\RemoteListClassSpecification;
use oat\tao\model\Lists\Presentation\Web\Factory\DependsOnPropertyFormFieldFactory;
use oat\tao\model\TaoOntology;
use oat\taoBackOffice\model\tree\TreeService;
use oat\tao\helpers\form\ValidationRuleRegistry;
use oat\tao\model\search\index\OntologyIndex;
use Psr\Container\ContainerInterface;

/**
 * Enable you to edit a property
 *
 * @access public
 * @author Bertrand Chevrier, <bertrand.chevrier@tudor.lu>
 * @package tao

 */
class tao_actions_form_SimpleProperty extends tao_actions_form_AbstractProperty
{

    /**
     * Initialize the form elements
     *
     * @access protected
     * @author Bertrand Chevrier, <bertrand.chevrier@tudor.lu>
     */
    protected function initElements()
    {

        $property = $this->getPropertyInstance();

        $index = $this->getIndex();

        $propertyProperties = array_merge(
            tao_helpers_form_GenerisFormFactory::getDefaultProperties(),
            [
                new core_kernel_classes_Property(GenerisRdf::PROPERTY_ALIAS),
                new core_kernel_classes_Property(GenerisRdf::PROPERTY_IS_LG_DEPENDENT),
                new core_kernel_classes_Property(TaoOntology::PROPERTY_GUI_ORDER),
                $this->getProperty(ValidationRuleRegistry::PROPERTY_VALIDATION_RULE)
            ]
        );
        $values = $property->getPropertiesValues($propertyProperties);

        $elementNames = [];
        foreach ($propertyProperties as $propertyProperty) {
            //map properties widgets to form elements
            $element = tao_helpers_form_GenerisFormFactory::elementMap($propertyProperty);

            if (!is_null($element)) {
                //take property values to populate the form
                if (isset($values[$propertyProperty->getUri()])) {
                    if ($element instanceof Validators) {
                        $this->disableValues($property, $element);
                    }

                    $propertyValues = $values[$propertyProperty->getUri()];
                    foreach ($propertyValues as $value) {
                        if (!is_null($value)) {
                            if ($value instanceof core_kernel_classes_Resource) {
                                $element->setValue($value->getUri());
                            }
                            if ($value instanceof core_kernel_classes_Literal) {
                                $element->setValue((string)$value);
                            }
                        }
                    }
                }
                $element->setName("{$index}_{$element->getName()}");
                $element->addClass('property');

                if ($propertyProperty->getUri() == TaoOntology::PROPERTY_GUI_ORDER) {
                    $element->addValidator(tao_helpers_form_FormFactory::getValidator('Integer'));
                }
                if ($propertyProperty->getUri() == OntologyRdfs::RDFS_LABEL) {
                    $element->addValidator(tao_helpers_form_FormFactory::getValidator('NotEmpty'));
                }
                $this->form->addElement($element);
                $elementNames[] = $element->getName();
            }
        }

        //build the type list from the "widget/range to type" map
        $typeElt = tao_helpers_form_FormFactory::getElement("{$index}_type", 'Combobox');
        $typeElt->setDescription(__('Type'));
        $typeElt->addAttribute('class', 'property-type property');
        $typeElt->setEmptyOption(' --- ' . __('select') . ' --- ');
        $options = [];
        $checkRange = false;
        foreach (tao_helpers_form_GenerisFormFactory::getPropertyMap() as $typeKey => $map) {
            $options[$typeKey] = $map['title'];
            $widget = $property->getWidget();
            if ($widget instanceof core_kernel_classes_Resource) {
                if ($widget->getUri() == $map['widget']) {
                    $typeElt->setValue($typeKey);
                    $checkRange = is_null($map['range']);
                }
            }
        }
        $typeElt->setOptions($options);
        $this->form->addElement($typeElt);
        $elementNames[] = $typeElt->getName();

        $range = $property->getRange();

        $rangeSelect = tao_helpers_form_FormFactory::getElement("{$this->getIndex()}_range", 'Combobox');
        $rangeSelect->setDescription(__('List values'));
        $rangeSelect->addAttribute('class', 'property-listvalues property');
        $rangeSelect->setEmptyOption(' --- ' . __('select') . ' --- ');

        if ($checkRange) {
            $rangeSelect->addValidator(tao_helpers_form_FormFactory::getValidator('NotEmpty'));
        }

        $this->form->addElement($rangeSelect);
        $elementNames[] = $rangeSelect->getName();

        //list drop down
        $listElt = $this->getListElement($range);
        $this->form->addElement($listElt);
        $elementNames[] = $listElt->getName();

        //trees dropdown
        $treeElt = $this->getTreeElement($range);
        $this->form->addElement($treeElt);
        $elementNames[] = $treeElt->getName();

        //index part
        $indexes = $property->getPropertyValues(new core_kernel_classes_Property(OntologyIndex::PROPERTY_INDEX));
        foreach ($indexes as $i => $indexUri) {
            $indexProperty = new OntologyIndex($indexUri);
            $indexFormContainer = new tao_actions_form_IndexProperty($indexProperty, $index . $i);
            /** @var tao_helpers_form_Form $indexForm */
            $indexForm = $indexFormContainer->getForm();
            foreach ($indexForm->getElements() as $element) {
                $this->form->addElement($element);
                $elementNames[] = $element->getName();
            }
        }

        //add this element only when the property is defined (type)
        if (!is_null($property->getRange())) {
            $addIndexElt = tao_helpers_form_FormFactory::getElement("index_{$index}_add", 'Free');
            $addIndexElt->setValue(
                "<a href='#' class='btn-info index-adder small index'><span class='icon-add'></span> " . __(
                    'Add index'
                ) . "</a><div class='clearfix'></div>"
            );
            $this->form->addElement($addIndexElt);
            $elementNames[] = $addIndexElt;
        } else {
            $addIndexElt = tao_helpers_form_FormFactory::getElement("index_{$index}_p", 'Free');
            $addIndexElt->setValue(
                "<p class='index' >" . __(
                    'Choose a type for your property first'
                ) . "</p>"
            );
            $this->form->addElement($addIndexElt);
            $elementNames[] = $addIndexElt;
        }

        //add an hidden elt for the property uri
        $encodedUri = tao_helpers_Uri::encode($property->getUri());
        $propUriElt = tao_helpers_form_FormFactory::getElement("{$index}_uri", 'Hidden');
        $propUriElt->addAttribute('class', 'property-uri property');
        $propUriElt->setValue($encodedUri);
        $this->form->addElement($propUriElt);
        $elementNames[] = $propUriElt;

        $element = $this->addDependsOnProperty($index, $property);

        if ($element) {
            $this->form->addElement($element);

            $elementNames[] = $element->getName();
        }

        if (!empty($elementNames)) {
            $groupTitle = $this->getGroupTitle($property);
            $this->form->createGroup("property_{$encodedUri}", $groupTitle, $elementNames);
        }
    }

    /**
     * @param $range
     *
     * @return tao_helpers_form_elements_xhtml_Combobox
     * @throws common_Exception
     */
    protected function getTreeElement($range)
    {

        $dataService = TreeService::singleton();
        /**
         * @var tao_helpers_form_elements_xhtml_Combobox $element
         */
        $element     = tao_helpers_form_FormFactory::getElement("{$this->getIndex()}_range_tree", 'Combobox');
        $element->setDescription(__('Tree values'));
        $element->addAttribute('class', 'property-template tree-template');
        $element->addAttribute('disabled', 'disabled');
        $element->setEmptyOption(' --- ' . __('select') . ' --- ');
        $treeOptions = [];
        foreach ($dataService->getTrees() as $tree) {
            $treeOptions[tao_helpers_Uri::encode($tree->getUri())] = $tree->getLabel();
            if (null !== $range && $range->getUri() === $tree->getUri()) {
                $element->setValue($tree->getUri());
            }
        }
        $element->setOptions($treeOptions);

        return $element;
    }

    private function addDependsOnProperty(
        int $index,
        core_kernel_classes_Property $property
    ): ?tao_helpers_form_FormElement {
        return $this->getDependsOnPropertyFormFieldFactory()->create(
            [
                'index' => $index,
                'property' => $property,
            ]
        );
    }

    /**
     * @param $range
     *
     * @return tao_helpers_form_elements_xhtml_Combobox
     * @throws common_Exception
     */
    protected function getListElement($range)
    {
        $service = tao_models_classes_ListService::singleton();

        /**
         * @var tao_helpers_form_elements_xhtml_Combobox $element
         */
        $element = tao_helpers_form_FormFactory::getElement("{$this->getIndex()}_range_list", 'Combobox');
        $element->setDescription(__('List values'));
        $element->addAttribute('class', 'property-template list-template');
        $element->addAttribute('disabled', 'disabled');
        $element->setEmptyOption(' --- ' . __('select') . ' --- ');
        $listOptions = [];
        $specification = $this->getRemoteListClassSpecification();

        foreach ($service->getLists() as $list) {
            $encodedListUri = tao_helpers_Uri::encode($list->getUri());
            $listOptions[$encodedListUri] = $list->getLabel();

            if (null !== $range && $range->getUri() === $list->getUri()) {
                $element->setValue($list->getUri());
            }

            if ($specification->isSatisfiedBy($list)) {
                $element->addOptionAttribute(
                    $encodedListUri,
                    'data-remote-list',
                    'true'
                );
            }
        }

        $element->setOptions($listOptions);

        return $element;
    }

    private function disableValues(core_kernel_classes_Property $property, Validators $element): void
    {
        $requiredParentValues = ['notEmpty'];
        $disabledValues = [];

        foreach ($property->getDependsOnPropertyCollection() as $parentProperty) {
            $validationRuleProperty = $this->getProperty(ValidationRuleRegistry::PROPERTY_VALIDATION_RULE);
            $validationRules = $parentProperty->getPropertyValues($validationRuleProperty);

            $disabledValues = array_merge(
                $disabledValues,
                array_diff($requiredParentValues, $validationRules)
            );
        }

        $element->setDisabledValues(array_unique($disabledValues));
    }

    private function getDependsOnPropertyFormFieldFactory(): DependsOnPropertyFormFieldFactory
    {
        return $this->getContainer()->get(DependsOnPropertyFormFieldFactory::class);
    }

    private function getRemoteListClassSpecification(): RemoteListClassSpecification
    {
        return $this->getContainer()->get(RemoteListClassSpecification::class);
    }

    private function getContainer(): ContainerInterface
    {
        return ServiceManager::getServiceManager()->getContainer();
    }
}
