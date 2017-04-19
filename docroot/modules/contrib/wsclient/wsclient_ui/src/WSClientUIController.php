<?php
namespace Drupal\wsclient_ui;

/**
 * Controller class for customizing the default Entity UI.
 */
class WSClientUIController extends EntityDefaultUIController {

  /**
   * Customizes menu items.
   *
   * @see EntityDefaultUIController::hook_menu()
   */
  function hook_menu() {
    $items = parent::hook_menu();

    // Add additionally need menu items to manage web service operations.
    $id_count = count(explode('/', $this->path)) + 1;
    $items[$this->path . '/manage/%wsclient_service/add/operation'] = array(
      'title' => 'Add operation',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('wsclient_ui_operation', $id_count, NULL, 'add'),
      'access arguments' => array('administer web services'),
      'file' => 'wsclient_ui.inc',
      'file path' => drupal_get_path('module', 'wsclient_ui'),
    );
    $op_count = $id_count + 2;
    $items[$this->path . '/manage/%wsclient_service/operation/%wsclient_ui_operation'] = array(
      'title' => 'Edit operation',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('wsclient_ui_operation', $id_count, $op_count, 'edit'),
      'load arguments' => array($id_count),
      'access arguments' => array('administer web services'),
      'file' => 'wsclient_ui.inc',
      'file path' => drupal_get_path('module', 'wsclient_ui'),
    );
    $items[$this->path . '/manage/%wsclient_service/operation/%wsclient_ui_operation/delete'] = array(
      'title' => 'Delete operation',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('wsclient_ui_operation_delete', $id_count, $op_count),
      'load arguments' => array($id_count),
      'access arguments' => array('administer web services'),
      'file' => 'wsclient_ui.inc',
      'file path' => drupal_get_path('module', 'wsclient_ui'),
    );
    // Menu items to manage data types.
    $items[$this->path . '/manage/%wsclient_service/add/type'] = array(
      'title' => 'Add data type',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('wsclient_ui_type', $id_count, NULL, 'add'),
      'access arguments' => array('administer web services'),
      'file' => 'wsclient_ui.inc',
      'file path' => drupal_get_path('module', 'wsclient_ui'),
    );
    $items[$this->path . '/manage/%wsclient_service/type/%wsclient_ui_type'] = array(
      'title' => 'Edit data type',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('wsclient_ui_type', $id_count, $op_count, 'edit'),
      'load arguments' => array($id_count),
      'access arguments' => array('administer web services'),
      'file' => 'wsclient_ui.inc',
      'file path' => drupal_get_path('module', 'wsclient_ui'),
    );
    $items[$this->path . '/manage/%wsclient_service/type/%wsclient_ui_type/delete'] = array(
      'title' => 'Delete data type',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('wsclient_ui_type_delete', $id_count, $op_count),
      'load arguments' => array($id_count),
      'access arguments' => array('administer web services'),
      'file' => 'wsclient_ui.inc',
      'file path' => drupal_get_path('module', 'wsclient_ui'),
    );

    // Overrides the default description of the top level menu item.
    $items[$this->path]['description'] = 'Manage Web Service Descriptions for Web service client.';
    return $items;
  }
}
