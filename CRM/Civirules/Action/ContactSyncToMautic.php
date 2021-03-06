<?php
/**
 *
 * CiviRules action to create contact from Mautic Webhook.
 */
use CRM_Mautic_Utils as U;
use CRM_Mautic_Connection as MC;

class CRM_Civirules_Action_ContactSyncToMautic extends CRM_Civirules_Action {

  protected $ruleAction = array();

  protected $action = array();

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    if (U::$skipUpdatesToMautic) {
      U::checkDebug('Skipping update to Mautic because contact has just been synched.');
      return;
    }
    // The civi api gives more data compared to $triggerData::getEntityData().
    $contact_id = $triggerData->getContactId();
    $fields = CRM_Mautic_Contact_FieldMapping::getMapping();
    unset($fields['civicrm_contact_id']);
    $commsPrefs = ['is_opt_out', 'do_not_email', 'do_not_phone', 'do_not_sms', 'do_not_mail'];
    $fields = array_merge(array_keys($fields), $commsPrefs);
    $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contact_id, 'return' => $fields]);
    if ($contact) {
      $mauticContact = CRM_Mautic_Contact_FieldMapping::convertToMauticContact($contact, TRUE);
      $mauticContactId = CRM_Mautic_Contact_ContactMatch::getMauticFromCiviContact($contact);

      if ($mauticContact) {
        $api = MC::singleton()->newApi('contacts');
        if ($mauticContactId) {
          U::checkDebug("Updating mautic contact.", $mauticContact);
          $response = $api->edit($mauticContactId, $mauticContact);
        }
        else {
          U::checkDebug("Creating mautic contact.", $mauticContact);
          $response = $api->create($mauticContact);
        }
        if (!$mauticContactId && !empty($response['contact']['id'])) {
          $mauticContactId = $response['contact']['id'];
        }
        // Save mautic id to custom field if it is not stored already.
        U::saveMauticIDCustomField($contact, $mauticContactId);
        // Sync segments from Civi Groups.
        // For this to be effective with smart groups the rule should have a delay
        // greater than smartgroup cache timeout.
        U::syncContactSegmentsFromGroups($contact_id, $mauticContactId);
      }
    }
  }

  /**
   *
   * {@inheritDoc}
   * @see CRM_Civirules_Action::getExtraDataInputUrl()
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return NULL;
  }
}
