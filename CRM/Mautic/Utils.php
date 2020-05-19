<?php

use CRM_Mautic_Connection as MC;

class CRM_Mautic_Utils {
  
  protected static $segmentData = [];
  
 
  /**
   * Gets Mautic Segments in [id] => label format.
   * @return string[]
   */
  public static function getMauticSegmentOptions() {
    $options = [];
    foreach (self::getMauticSegments() as $segment) {
      $options[$segment['id']] = $segment['name'];
    }
    return $options;
  }
  
  /**
   * Fetches segment data from the api.
   */
  public static function getMauticSegments() {
    if (!self::$segmentData) {
      $segmentsApi = MC::singleton()->newApi('segments');
      if ($segmentsApi) {
        $segments = $segmentsApi->getList();
        dpm($segments);
        if (!empty($segments['lists'])) {
          self::$segmentData = $segments['lists'];
        }
      }
    }
    return self::$segmentData;
  }
  
  public static function getContactsInSegment($segmentID, $batchSize = 300) {
    $contactAPI = MC::singleton()->newApi('contacts');
    // @todo retrieve in batches.
 $search = 'segment:' . $segmentID;
    $start = 0;
    $limit = 0;
    $orderBy = 'id';
    $orderDir = 'DESC';
    // Not sure what published / unpublished contact means.
    $publishedOnly = TRUE;
    $minimal = TRUE;
    $contact = $contactApi->getList(
        $search,
        $start,
        $limit,
        $orderBy, 
        $orderDir,
        $publishedOnly,
        $minimal
    );
    if (!empty($contact['contacts'])) {
      return  $contact['contacts'];
    }
  
  }
 
  /**
   * Convenience function to get details on a Mautic Segment.
   * @param int $segmentId
   * @param string $property
   *  Name of property to return. If not set, will return all properties in an associative array.  
   * 
   * @return mixed|array
   */
  public static function getMauticSegment($segmentId, $property = NULL) {
    $segment = CRM_Utils_Array::value($segmentId, self::getMauticSegments(), []);
    if ($property) {
      return CRM_Utils_Array::value($property, $segment, '');
    }
    return $segment;
  }
  
  
  /**
   * Look up an array of CiviCRM groups linked to Mautic segments.
   *
   * @param $groupIDs mixed array of CiviCRM group Ids to fetch data for; or empty to return ALL mapped groups.
   * @param $mauticSegmentId mixed Fetch details for a particular segment only, or null.
   * @return array keyed by CiviCRM group id whose values are arrays of details
   */
  public static function getGroupsToSync($groupIDs = [], $mauticSegmentId = NULL) {

    $params = $groups = $temp = [];
    $groupIDs = array_filter(array_map('intval',$groupIDs));

    if (!empty($groupIDs)) {
      $groupIDs = implode(',', $groupIDs);
      $whereClause = "entity_id IN ($groupIDs)";
    } else {
      $whereClause = "1 = 1";
    }
    $whereClause .= " AND mautic_segment_id IS NOT NULL AND mautic_segment_id <> ''";

    if ($mauticSegmentId) {
      // just want results for a particular MC list.
      $whereClause .= " AND mautic_segment_id = %1 ";
      $params[1] = array($mauticSegmentId, 'String');
    }

    $query  = "
      SELECT  
        entity_id, 
        mautic_segment_id, 
        cg.title as civigroup_title,
        cg.saved_search_id,
        cg.children
      FROM   civicrm_value_mautic_settings m
      INNER JOIN civicrm_group cg ON m.entity_id = cg.id
      WHERE $whereClause";
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $segment = self::getMauticSegment($dao->mautic_segment_id);
      $groups[$dao->entity_id] = [
          // Mautic Segment 
          'segment_id' => $dao->mautic_segment_id,
          'segment_name' => CRM_Utils_Array::value('name', $segment),
          // Details from CiviCRM
          'civigroup_title' => $dao->civigroup_title,
          'civigroup_uses_cache' => (bool) (($dao->saved_search_id > 0) || (bool) $dao->children),
       ];
    }
    CRM_Mautic_Utils::checkDebug( __CLASS__ . __FUNCTION__ . '$groups', $groups);
    return $groups;
  }
  
  /**
   * Log a message and optionally a variable, if debugging is enabled.
   */
  public static function checkDebug($description, $variable='VARIABLE_NOT_PROVIDED') {
    $debugging = CRM_Mautic_Setting::get('mautic_enable_debugging');
    
    if ($debugging == 1) {
      if ($variable === 'VARIABLE_NOT_PROVIDED') {
        // Simple log message.
        CRM_Core_Error::debug_log_message($description, FALSE, 'mautic');
      }
      else {
        // Log a variable.
        CRM_Core_Error::debug_log_message(
            $description . "\n" . var_export($variable,1)
            , FALSE, 'mautic');
      }
    }
  }
  
  
}