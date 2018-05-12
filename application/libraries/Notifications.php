<?php

if (!defined('BASEPATH'))
  exit('No direct script access allowed');

/**
 *
 * Notification library for Code Igniter.
 *
 * In this Package we set to Notification of any post and
 * send to particular user or many user in a time.
 *
 * @package        Notification
 * @author         Parth Sutariya (http://pathusutariya.blogspot.com/)
 * @version        1.0.0
 */
class Notifications {

  /**
   * Table Name for Notification
   * @var string 
   */
  private $notificationTable;

  /**
   * Notification Sender ID
   * @var int 
   */
  private $userId = 0;

  /**
   * Codigniter Instence
   * @var object instance 
   */
  private $ci;

  /**
   * Table Name for Notify to user
   * @var string 
   */
  private $notificationUserTable;

  /**
   * Type of Notification ex- Post, Profile
   * @var string 
   */
  private $type = '';

  /**
   * Unique Type ID Helping to Add more personal Notification
   * @var int 
   */
  private $type_id = 0;

  /**
   * Additional Information
   * @var String 
   */
  private $comment = '';

  /**
   * Action specifier ex- add, update
   * @var string 
   */
  private $token = '';

  /**
   * Who to send Notification
   * @var Int | Array
   */
  private $target      = 0;
  private $only_unread = 0;

  /**
   * Notification ID
   * @var int 
   */
  private $id = 0;

  /**
   * per page limit
   * @var int 
   */
  private $perpage_limit = 0;

  /**
   * Page number to show notification
   * @var int 
   */
  private $page_offset = 0;
  private $error;

  public function __construct() {
    $this->ci = &get_instance();
    $this->ci->load->database();
    $this->config();
  }

  public function config($new_options = []) {

    $this->ci->config->load('notifications', TRUE);
    $options = $this->ci->config->item('notifications');
    $options = array_merge($options, $new_options);


    $this->notificationTable     = $options['notification_table'];
    $this->notificationUserTable = $options['notification_read_tracking_table'];
    $this->perpage_limit         = $options['per_page'];
    $this->target                = $options['default_target'];
    $this->userId                = $options['default_sender'];
    $this->comment               = $options['default_comment'];
    $this->type                  = $options['default_type'];
    $this->token                 = $options['default_token'];
  }

  /**
   * Set UserId
   * @param int $userid
   * @return $this
   */
  public function user($userid) {
    $this->userId = $userid;
    return $this;
  }

  /**
   * Set which type of Notification
   * @param string $type
   * @return $this
   */
  public function type($type, $id = 0) {
    $this->type    = $type;
    if ($id)
      $this->type_id = $id;
    return $this;
  }

  public function flush() {
    $this->type    = '';
    $this->type_id = 0;
    $this->token   = '';
    $this->comment = '';
    $this->id      = 0;
    return $this;
  }

  /**
   * Set comment
   * @param string $comment
   * @return $this
   */
  public function comment($comment) {
    $this->comment = $comment;
    return $this;
  }

  /**
   * Set token
   * @param string $token
   * @return $this
   */
  public function token($token) {
    $this->token = $token;
    return $this;
  }

  /**
   * Set Notification Id
   * @param int $id
   * @return $this
   */
  public function id($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Set Notified user id or ids
   * @param int|array $notified_ids
   * @return $this
   */
  public function notify($notified_ids = 0) {
    if ($notified_ids)
      $this->target = $notified_ids;
    if ($this->id) {
      $data = array();
      if (is_array(($this->target))) {
        foreach ($this->target as $value) {
          $data[] = array(
            "notification_id" => $this->id,
            "user_id"         => $value,
          );
        }
        $this->ci->db->insert_batch($this->notificationUserTable, $data);
      }
      elseif (is_int($this->target)) {
        $data = array(
          "notification_id" => $this->id,
          "user_id"         => $this->target,
        );
        $this->ci->db->insert($this->notificationUserTable, $data);
      }
    }
  }

  /**
   * Set User is already viewed
   * @param int|array $notified_ids
   * @return $this
   */
  public function unread() {
    $this->only_unread = 1;
    return $this;
  }

  /**
   * Get Notification data
   */
  public function get() {
    $this->ci->db->from($this->notificationUserTable);
    $this->ci->db->join($this->notificationTable, "{$this->notificationUserTable}.notification_id = {$this->notificationTable}.id");
    $this->_querybuilder();
    $query = $this->ci->db->get();
    return $this->_dbcleanresult($query);
  }

  /**
   * Add Notification to Database
   */
  public function add() {
    if ($this->userId)
      $data['user_id'] = $this->userId;
    if ($this->type)
      $data['type']    = $this->type;
    if ($this->type_id)
      $data['type_id'] = $this->type_id;
    if ($this->token)
      $data['token']   = $this->token;
    if ($this->comment)
      $data['comment'] = $this->comment;

    $this->ci->db->insert($this->notificationTable, $data);
    $this->id = $this->ci->db->insert_id();
    return $this;
  }

  /**
   * Update mark as read
   */
  public function mark_as_read() {
    if ($this->userId)
      $this->ci->db->where('user_id', $this->userId);
    if ($this->id)
      $this->ci->db->where('notification_id', $this->id);

    $this->ci->db->update($this->notificationUserTable, array("read_at" => date("Y:m:d H:i:s", time())));
  }

  /**
   * Update mark as un-read
   */
  public function mark_as_unread() {
    if ($this->userId)
      $this->ci->db->where('user_id', $this->userId);
    if ($this->id)
      $this->ci->db->where('notification_id', $this->id);

    $this->ci->db->update($this->notificationUserTable, array("read_at" => NULL));
  }

  /**
   * Set Pagination
   * @param int $offset
   * @param int $limit
   * @return $this
   */
  public function page($offset, $limit = 0) {
    if ($limit)
      $this->perpage_limit = $limit;
    $this->page_offset   = $offset-1;
    return $this;
  }

  public function error() {
    return $this->error;
  }

  private function _dbcleanresult($result) {
    if ($result->num_rows() > 1)
      return $result->result();
    if ($result->num_rows() == 1)
      return $result->row();
    else
      return false;
  }

  private function _querybuilder() {
    if ($this->type)
      $this->ci->db->where("{$this->notificationTable}.type", $this->type);
    if ($this->type_id)
      $this->ci->db->where("{$this->notificationTable}.type_id", $this->type_id);
    if ($this->token)
      $this->ci->db->where("{$this->notificationTable}.token", $this->token);
    if ($this->comment)
      $this->ci->db->where("{$this->notificationTable}.comment", $this->comment);
    if ($this->userId) {
      $this->ci->db->where("{$this->notificationUserTable}.user_id", $this->userId);
    }
    if ($this->only_unread)
      $this->ci->db->where("{$this->notificationUserTable}.read_at", NULL);
    if ($this->id) {
      $this->ci->db->where("{$this->notificationUserTable}.notification_id", $this->id);
      $this->ci->db->where("{$this->notificationUserTable}.notification_id", $this->id);
    }

    if ($this->perpage_limit)
      $this->ci->db->limit( $this->perpage_limit, ($this->perpage_limit * $this->page_offset));
  }

}

?>