<?php

use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Connection as MC;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Mautic_Form_Civirules_Condition_MauticContactHasTag extends CRM_CivirulesConditions_Form_Form {

  public function getMauticTags() {
    if (!isset(\Civi::$statics[__FUNCTION__]['tags'])) {
      $api = MC::singleton()->newApi('tags');
      $res = $api->getList();
      \Civi::$statics[__FUNCTION__]['tags'] = [];
      foreach ($res['tags'] as $id => $tag) {
        \Civi::$statics[__FUNCTION__]['tags'][] = ['id' => $tag['tag'], 'text' => $tag['tag']];
      }
    }
    return \Civi::$statics[__FUNCTION__]['tags'];
  }

  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'operator', ts('Contact has: '), ['one' => ts('one of'), 'all' => ts('all of')]);
    $tags = $this->getMauticTags();
    $this->add('select2', 'mautic_tags', ts('Tag(s)'), $tags, true, ['multiple' => TRUE]);
    // add form elements
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = unserialize($this->ruleCondition->condition_params);
    if ($data) {
      $defaultValues += $data;
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    foreach ($this->getRenderableElementNames() as $name) {
      $data[$name] = $this->_submitValues[$name];
    }
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
