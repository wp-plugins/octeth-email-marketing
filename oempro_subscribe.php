<?php
/**
 * @version $Id$
 * @author octeth.com/oempro <support@octeth.com>
 * @see http://octeth.com/oempro/
 * @see Help: http://octeth.com/oempro/help/integration/wordpress/
 * @package Oempro Subscribe WP Plugin
 */

/*
Plugin Name: Octeth Email Marketing
Plugin URI: http://octeth.com/oempro
Description: With this plug-in, blog owner will be able to "link" his Oempro account with WordPress and start accepting email list subscriptions on his blog.
Version: 0.2
Author: Octeth
Author URI: http://octeth.com/oempro
Help URI: http://octeth.com/oempro
License: MIT License
*/

/*
    TODO:
    1. Language localisation files
    2. Custom fields: checkboxes submission
*/

define('OEMPRO_SUBSCRIBE', '0.2');
define('OEMPRO_PLUGIN_NAME', 'Oempro Subscribe');
define('OP_SUBSCRIBE_ACTION', WP_PLUGIN_URL . '/oempro/dispatch.php');

add_action('admin_menu', array('OemproSubscribeAdmin', 'menu'));
add_filter('init', array('OemproSubscribeFront', 'init'));
add_action('wp_ajax_op_test_connection', array('OemproSubscribeAdmin', 'testConnection'));
add_action('wp_ajax_op_get_subscriber_lists', array('OemproSubscribeAdmin', 'getLists'));
add_action('wp_ajax_op_get_subscriber_list_fields', array('OemproSubscribeAdmin', 'getListCustomFields'));
add_action('init', array('OemproSubscribeWidget', 'init'), 1);
load_plugin_textdomain('op_subscribe', '/wp-content/plugins/oempro/langs/');

$OemproPluginOptions = array(
	'op_login' => __('octeth.com/oempro Username'),
	'op_password' => __('octeth.com/oempro Password'),
	'op_account_url' => __('octeth.com/oempro account url'),
	'op_subscription_success' => __('Subscribe success message'),
	'op_subscription_success_pending' => __('Subscribe confirmation pending message'),
	'op_unsubscription_success' => __('Unsubscribe success message'),
	'op_subscription_error_5' => __('"Invalid email address format" error message'),
	'op_subscription_error_6' => __('"Email address already exists in the target list" error message'),
	'op_subscription_error_7' => __('"Invalid email address" error message'),
	'op_unsubscription_error_4' => __('"Invalid email address format" error message'),
	'op_unsubscription_error_5' => __('"Email address doesn\'t exist in the list" error message'),
	'op_unsubscription_error_6' => __('"Invalid email address" error message'),
);

// ============================================================================
class OemproSubscribeWidget extends WP_Widget {

	function OemproSubscribeWidget() {
		$widget_ops = array(
			'classname' => 'op_subscribe_widget',
			'description' => __('Attach and configure to allow users subscribe to newsletters')
		);
		$this->WP_Widget('op_subscribe', OEMPRO_PLUGIN_NAME, $widget_ops);
	}

	function init() {
		register_widget('OemproSubscribeWidget');
	}

	function widget($args, $instance) {
		extract($args);
		$data['title'] = apply_filters(
			'widget_title',
			empty($instance['title']) ? '&nbsp;' : $instance['title'],
			$instance,
			$this->id_base
		);
		$data['target_list'] = apply_filters(
			'widget_target_list',
			intval($instance['target_list']),
			$instance,
			$this->id_base
		);
		$data['allow_unsubscribe'] = apply_filters(
			'widget_allow_unsubscribe',
			empty($instance['allow_unsubscribe']) ? 0 : $instance['allow_unsubscribe'],
			$instance,
			$this->id_base
		);
		$data['custom_fields'] = $instance['custom_fields'];
		echo $before_widget;
		if ($data['title'])
			echo $before_title . $data['title'] . $after_title;
		echo '<div class="op_subscribe_widget_wrap">';
		echo OemproSubscribeFront::draw($data);
		echo '</div>';
		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['allow_unsubscribe'] = $new_instance['allow_unsubscribe'] ? 1 : 0;
		$instance['target_list'] = (int)$new_instance['target_list'];
		$instance['custom_fields'] = $new_instance['custom_fields'];
		return $instance;
	}

	function form($instance) {
		// print_r($instance); // DEBUG
		$instance = wp_parse_args((array)$instance, array(
														 'title' => '',
														 'allow_unsubscribe' => 1
													));
		$title = strip_tags($instance['title']);
		$allowUnsubscribe = (int)$instance['allow_unsubscribe'];
		$targetList = (int)$instance['target_list'];
		$customFields = (!empty($instance['custom_fields']) and is_array($instance['custom_fields'])) ? $instance['custom_fields'] : array();

		printf('
	    <p>
        <label for="%s>">%s</label>
        <input class="widefat" id="%s" name="%s" type="text" value="%s" />
	    </p>',
			   $this->get_field_id('title'),
			   __('Title:'),
			   $this->get_field_id('title'),
			   $this->get_field_name('title'),
			   esc_attr($title)
		);
		printf('
	    <p>
        <label for="%s>">%s</label><br />
        <select class="select widefat" id="%s" name="%s"></select>
	    </p>',
			   $this->get_field_id('target_list'),
			   __('Target subscribers list:'),
			   $this->get_field_id('target_list'),
			   $this->get_field_name('target_list')
		);
		printf("<script type=\"text/javascript\">
      getCustomFieldsByList = function (listId) {
        jQuery.post(
          ajaxurl,
          {
            action: 'op_get_subscriber_list_fields',
            cookie: encodeURIComponent(document.cookie),
            data: {
              listId: listId
            }
          },
          function(result) {
            var instanceNumber = %d;
            if (true == result.success && 0 < result.fields.length) {
              var html = '';
              var optionName = 'widget-" . $this->id_base . "';
              var selectedFieldsJson = " . json_encode($customFields) . ";
              
              jQuery.each(result.fields, function() {
                var id = optionName+'-'+instanceNumber+'-'+this.FieldName.toLowerCase().replace(' ', '_');
                var fieldId = this.CustomFieldID;
                var isSelected = false;
                jQuery.each(selectedFieldsJson, function (k, v) {
                  if (fieldId == k) {
                    if (v.enabled) {
                      isSelected = true;
                    }
                  }
                });
                
                html += '<input id=\"'+id+'\" type=\"checkbox\" name=\"'+optionName+'['+instanceNumber+'][custom_fields]['+fieldId+'][enabled]\" value=\"true\"'+((true == isSelected) ? 'checked=\"checked\"': '')+' />';
                html += '<label for=\"'+id+'\">'+this.FieldName+'</label><br />';
                
                jQuery.each(this, function(k, v) {
                  html += '<input type=\"hidden\" name=\"'+optionName+'['+instanceNumber+'][custom_fields]['+fieldId+']['+k+']\" value=\"'+v+'\" />';
                });
              });
              
              jQuery('#customFieldsContainer'+instanceNumber).html(html);

            } else {
              jQuery('#customFieldsContainer'+instanceNumber).html('<p>" . __('No custom fields available.') . "</p>');
            }
          }, 'json'
        );
      }

      jQuery.post(
        ajaxurl,
        {
          action: 'op_get_subscriber_lists',
          cookie: encodeURIComponent(document.cookie),
          data: {}
        },
        function(result) {
          if (result.success) {
            var html = '';
            var selected = '" . $targetList . "';
            for(var i = 0, n = result.lists.length; i < n; i++) {
              var list = result.lists[i];
              html += '<option value=\"'+list.ListID+'\"'+((list.ListID == selected) ? 'selected=\"selected\"': '')+'>'+list.Name+'</option>';
            }
            selectElId = '%s';
          
            // bind change event to fetch custom fields
            jQuery('#'+selectElId).html(html).change( function () {
              getCustomFieldsByList(jQuery(this).val());
            });
          
            // fetch custom fields for preloaded list
            getCustomFieldsByList(jQuery('#'+selectElId).val());
          } else {
            var msg = '';
            if (result.msg) {
              msg = result.msg;
            } else {
              msg = '" . __('There are no any subscriber lists available. Please create one at your octeth.com/oempro account') . "';
            }
            alert(msg);
            return false;    
          }
        }, 'json'
      );
      </script>",
			   $this->number,
			   $this->get_field_id('target_list')
		);
		printf('
	    <p>
	        <input class="checkbox" type="checkbox" id="%s" name="%s" ' . checked($allowUnsubscribe, true, false) . ' />
	        <label for="%s>">%s</label>
	    </p>',
			   $this->get_field_id('allow_unsubscribe'),
			   $this->get_field_name('allow_unsubscribe'),
			   $this->get_field_id('allow_unsubscribe'),
			   __('Allow unsubscribe')
		);
		printf('<h4>%s</h4>', __('Custom fields:'));
		printf('<p>%s</p>', __('Select custom fields which will be shown on frontend.'));
		printf('<div id="customFieldsContainer%s"></div>', $this->number);
	}
}

// ============================================================================
class OemproSubscribeAdmin {

	static function menu() {
		add_options_page(
			'Oempro Subscribe Plugin Options',
			OEMPRO_PLUGIN_NAME,
			'manage_options',
			'oempro-subscribe',
			array('OemproSubscribeAdmin', 'options')
		);
	}

	static function options() {
		global $OemproPluginOptions;
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}

		echo '<div class="wrap">';
		echo '<div id="icon-options-general" class="icon32"><br></div><h2>' . OEMPRO_PLUGIN_NAME . '</h2>';
		printf('<div id="resultsContainer"><p>%s</p></div>', __('After saving of these options go to theme widgets to enable and configure your subscribe form'));
		echo '<form method="post" action="options.php" id="opSettingsForm">';
		wp_nonce_field('update-options');
		echo '<input type="hidden" name="action" value="update" />';
		echo '<table class="form-table"><tbody>';
		foreach ($OemproPluginOptions as $optionCode => $title) {
			printf('<tr valign="top">
        <th scope="row">%s</th>
        <td><input type="text" name="%s" id="%s" value="%s" class="regular-text code" /></td>
      </tr>', $title, $optionCode, $optionCode, get_option($optionCode));
		}

		echo '</tbody></table>';
		printf('<input type="hidden" name="page_options" value="%s" />', implode(',', array_keys($OemproPluginOptions)));
		printf('<div id="submitContainer"><p class="submit"><input type="button" class="button-primary" value="%s" id="submitBtn" /></p></div>', __('Save changes'));
		echo '</form>';
		echo '</div>';
		echo "<script type=\"text/javascript\">
      jQuery('#submitBtn').click( function () {
        jQuery.post(
          ajaxurl,
          {
            action: 'op_test_connection',
            cookie: encodeURIComponent(document.cookie),
            data: {
              op_api_key: jQuery('#op_api_key').val(),
              op_account_url: jQuery('#op_account_url').val(),
              op_login: jQuery('#op_login').val(),
              op_password: jQuery('#op_password').val()
            }
          },
          function(result) {
            if (result.success) {
              jQuery('#resultsContainer').html('<div class=\"message updated\"><p>" . __('Congratulations! Connection credentials are valid.') . "</p></div>');
              jQuery('#opSettingsForm').submit()
            } else {
              jQuery('#resultsContainer').html('<div class=\"error\"><p>" . __('Connection credentials are not valid. Please review and correct.') . "</p></div>');
              return false;    
            }
          }, 'json'
        );
      });
    </script>";
	}

	static function getLists() {
		$result = array(
			'success' => false,
			'lists' => null
		);
		$OemproSubscribeDispatcher = new OemproSubscribeDispatcher();
		$OemproSubscribeDispatcher->init($_POST['data']);
		$lists = $OemproSubscribeDispatcher->getSubscriberLists();
		if (count($lists['Lists'])) {
			$result = array(
				'success' => true,
				'lists' => $lists['Lists']
			);
		}
		die(json_encode($result));
	}

	static function getListCustomFields() {
		$result = array(
			'success' => false,
			'fields' => null
		);
		if (!$listId = (int)$_POST['data']['listId']) die(json_encode($result));
		$OemproSubscribeDispatcher = new OemproSubscribeDispatcher();
		$OemproSubscribeDispatcher->init($_POST['data']);
		$fields = $OemproSubscribeDispatcher->getSubscriberListFields($listId);

		if (count($fields)) {
			$result = array(
				'success' => true,
				'fields' => $fields
			);
		}
		die(json_encode($result));
	}

	static function testConnection() {
		$result = array(
			'success' => false
		);
		$OemproSubscribeDispatcher = new OemproSubscribeDispatcher();
		$OemproSubscribeDispatcher->init($_POST['data']);
		if ($OemproSubscribeDispatcher->getConnection())
			$result['success'] = true;

		die(json_encode($result));
	}
}

// ============================================================================
class OemproSubscribeFront {

	static function init() {
		wp_enqueue_script('jquery');
	}

	static function draw($data) {
		// print_r($data); // DEBUG
		$widgetSessionKey = rand(10000, 30000);
		$html = '
      <form role="opSubscribeForm" method="post" id="opSubscribeForm' . $widgetSessionKey . '" action="' . OP_SUBSCRIBE_ACTION . '">
        <input type="hidden" name="target_list" value="' . intval($data['target_list']) . '" />
        <div id="opResultContainer"></div>
    	  <div>
  	      <p>
	          <label for="opSubscriptionField' . $widgetSessionKey . '">Email:</label><br />
	          <input type="text" value="" name="email" id="opSubscriptionField' . $widgetSessionKey . '">
  	      </p>';
		if (isset($data['custom_fields']) and count($data['custom_fields'])) {
			foreach ($data['custom_fields'] as $custom_field) {
				if (isset($custom_field['enabled']) and true == $custom_field['enabled']) {
					$id = 'opCustomField' . $custom_field['CustomFieldID'] . $widgetSessionKey;
					$name = 'custom_fields[CustomField' . $custom_field['CustomFieldID'] . ']';

					if ('Hidden' != $custom_field['FieldType'])
						$html .= '<p><label for="' . $id . '">' . $custom_field['FieldName'] . '</label>';

					switch ($custom_field['FieldType']) {
						case 'Single line':
							$html .= '<br /><input type="text" value="' . htmlentities($custom_field['FieldDefaultValue']) . '" name="' . $name . '" id="' . $id . '" />';
							break;
						case 'Paragraph text':
							$html .= '<br /><textarea name="' . $name . '" id="' . $id . '">' . htmlentities($custom_field['FieldDefaultValue']) . '</textarea>';
							break;
						case 'Multiple choice':
							if ($options = explode(',', $custom_field['FieldOptions'])) {
								$html .= '<ul>';
								foreach ($options as $option) {
									$checked = false;
									$option = str_replace(array('[[', ']]'), '', $option);
									if (false !== strpos($option, '*')) {
										$checked = true;
										$option = str_replace('*', '', $option);
									}
									$html .= '<li><input type="radio" name="' . $name . '" value="' . $option . '"' . (($checked) ? ' checked="checked"' : '') . ' /><label>' . $option . '</label></li>';
								}
								$html .= '</ul>';
							}
							break;
						case 'Drop down':
							if ($options = explode(',', $custom_field['FieldOptions'])) {
								$html .= '<br /><select name="' . $name . '" id="' . $id . '">';
								foreach ($options as $option) {
									$checked = false;
									$option = str_replace(array('[[', ']]'), '', $option);
									if (false !== strpos($option, '*')) {
										$checked = true;
										$option = str_replace('*', '', $option);
									}
									$html .= '<option value="' . $option . '"' . (($checked) ? ' selected="selected"' : '') . '>' . $option . '</option>';
								}
								$html .= '</select>';
							}
							break;
						case 'Checkboxes':
							if ($options = explode(',', $custom_field['FieldOptions'])) {
								$html .= '<ul>';
								foreach ($options as $option) {
									$checked = false;
									$option = str_replace(array('[[', ']]'), '', $option);
									if (false !== strpos($option, '*')) {
										$checked = true;
										$option = str_replace('*', '', $option);
									}
									$html .= '<li><input type="checkbox" name="' . $name . '[]" value="' . $option . '"' . (($checked) ? ' checked="checked"' : '') . ' /><label>' . $option . '</label></li>';
								}
								$html .= '</ul>';
							}
							break;
						case 'Hidden':
							$html .= '<input type="hidden" value="' . htmlentities($custom_field['FieldDefaultValue']) . '" name="' . $name . '" id="' . $id . '" />';
							break;
					}
					$html .= '</p>';
				}
			}
		}

		if ($data['allow_unsubscribe']) {
			$html .= '<p>
        <input type="radio" id="opActionFieldSubscribe' . $widgetSessionKey . '" name="action" value="subscribe" checked="checked" />
        <label for="opActionFieldSubscribe' . $widgetSessionKey . '">' . __('Subscribe') . '</label>
        <br />
        <input type="radio" id="opActionFieldUnsubscribe' . $widgetSessionKey . '" name="action" value="unsubscribe" />
        <label for="opActionFieldUnsubscribe' . $widgetSessionKey . '">' . __('Unsubscribe') . '</label>
      </p>';
		}
		$html .= '<div class="customFieldsContainer">';
		$html .= '</div>';
		$html .= '<p>
  	        <input type="submit" id="opSubmitSubscription' . $widgetSessionKey . '" value="' . __('Submit') . '">
    	    </p>
      	</div>
      </form>';
		$js = "
      jQuery(document).ready(function(){
        if (jQuery('#opSubscribeForm$widgetSessionKey input[name=action]').length) {
          jQuery('#opSubscribeForm$widgetSessionKey input[name=action]').change(function (radio) {
            action = jQuery('#opSubscribeForm$widgetSessionKey input[name=action]:checked').val();
            if ('subscribe' == action) {
              jQuery('#opSubscribeForm$widgetSessionKey .customFieldsContainer').show();
            } else {
              jQuery('#opSubscribeForm$widgetSessionKey .customFieldsContainer').hide();
            }
          });
        }
        
        jQuery('#opSubscribeForm$widgetSessionKey').submit( function (evt) {
            
          var action = 'subscribe';
          if (jQuery('#opSubscribeForm$widgetSessionKey input[name=action]').length) {
            action = jQuery('#opSubscribeForm$widgetSessionKey input[name=action]:checked').val();
          }
          
          jQuery.ajax({
            url: jQuery('#opSubscribeForm$widgetSessionKey').attr('action'),
            dataType: 'json',
            type: 'POST',
            data: jQuery('#opSubscribeForm$widgetSessionKey').serialize(),
            success: function (data, textStatus, XMLHttpRequest) {
                if (true == data.success) {
                  var msg = data.msg;
                  jQuery('#opResultContainer').html('<div><p style=\"font-weight: bold; color: green;\">'+msg+'</p></div>');

                  // reset form
                  jQuery(':input','#opSubscribeForm$widgetSessionKey')
                    .not(':radio, :button, :submit, :reset, :hidden, :checkbox')
                    .val('');
                } else {
                  var msg = data.msg; // we have different errors
                  jQuery('#opResultContainer').html('<div><p style=\"font-weight: bold; color: red;\">'+msg+'</p></div>');
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
              alert('Error:' + textStatus + ' ' + errorThrown);
            }
          });
          return false;
        });
      });";
		return sprintf("%s\n<script type=\"text/javascript\">%s</script>", $html, $js);
	}
}

// ============================================================================
class OemproSubscribeDispatcher {

	private $_response;
	private $_email;
	private $_options;
	private $_SessionID;
	private $_IpAddress;
	private $_successMessages;
	private $_subscriptionErrors;
	private $_unsubscriptionErrors;
	private $_targetListID;
	private $_customFields;

	public function OemproSubscribeDispatcher() {
		$this->_response = array(
			'msg' => '',
			'success' => false
		);
		$this->_SessionID = null;
		$this->_IpAddress = $this->_getIpAddress();
		$this->_successMessages = array(
			'subscription_success' => __('Successfully subscribed'),
			'subscription_success_pending' => __('Please check your inbox to confirm subscription'),
			'unsubscription_success' => __('Successfully unsubscribed')
		);
		$this->_subscriptionErrors = array(
			'5' => __('Invalid email address format'),
			'6' => __('Email address already exists in the target list'),
			'7' => __('Invalid email address')
		);
		$this->_unsubscriptionErrors = array(
			'4' => __('Invalid email address format'),
			'5' => __('Email address doesn\'t exist in the list'),
			'6' => __('Invalid email address')
		);
		$this->_customFields = array();
	}

	private function _getIpAddress() {
		if (isset($_SERVER)) {
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
				$ip_addr = $_SERVER["HTTP_X_FORWARDED_FOR"];
			}
			elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
				$ip_addr = $_SERVER["HTTP_CLIENT_IP"];
			}
			else {
				$ip_addr = $_SERVER["REMOTE_ADDR"];
			}
		}
		else {
			if (getenv('HTTP_X_FORWARDED_FOR')) {
				$ip_addr = getenv('HTTP_X_FORWARDED_FOR');
			}
			elseif (getenv('HTTP_CLIENT_IP')) {
				$ip_addr = getenv('HTTP_CLIENT_IP');
			}
			else {
				$ip_addr = getenv('REMOTE_ADDR');
			}
		}
		return $ip_addr;
	}

	public function setEmail($email) {
		if (!$this->_email = $this->_validateEmail($email)) {
			$this->_setResponse($this->_subscriptionErrors[5]);
			$this->_sendResponse();
		}
		return $this->_email;
	}

	public function setTargetList($listId) {
		return $this->_targetListID = $listId;
	}

	public function setCustomFields($customFields = array()) {
		return $this->_customFields = $customFields;
	}

	public function subscribe() {
		if (!$this->_email) {
			$this->_setResponse($this->_subscriptionErrors[7]);
			$this->_sendResponse();
		}

		$this->init();
		try {
			$params = array(
				'ListID'		=> $this->_targetListID,
				'EmailAddress'	=> $this->_email,
				'IPAddress'		=> $this->_IpAddress
			);
			if (count($this->_customFields)) {
				foreach ($this->_customFields as $key => $val) {
					$params[$key] = $val;
				}
			}

			//print_r($this->_getCommandUrl('Subscriber.Subscribe', $params));die; // DEBUG
			$response = $this->_getResponse($this->_getCommandUrl('Subscriber.Subscribe', $params));

			if ($response['Success']) {
				if ('Subscribed' == $response['Subscriber']['SubscriptionStatus'])
					$this->_setResponse($this->_successMessages['subscription_success'], true);
				elseif ('Confirmation Pending' == $response['Subscriber']['SubscriptionStatus'])
					$this->_setResponse($this->_successMessages['subscription_success_pending'], true);
			} else {
				if (count($response['ErrorCode'])) {
					$this->_setResponse($errorCode); // show code by default
					// we need to show up only first problem which we know
					foreach ($response['ErrorCode'] as $errorCode) {
						if (isset($this->_subscriptionErrors[$errorCode]))
							$this->_setResponse($this->_subscriptionErrors[$errorCode]);
					}
				}
			}
		} catch (Exception $e) {
			$this->_setResponse($e);
		}
		$this->_sendResponse();
	}

	public function unsubscribe() {
		if (!$this->_email) {
			$this->_setResponse($this->_subscriptionErrors[7]);
			$this->_sendResponse();
		}

		$this->init();
		try {
			$response = $this->_getResponse($this->_getCommandUrl('Subscriber.Unsubscribe',
																  array(
																	   'ListID' => $this->_targetListID,
																	   'EmailAddress' => $this->_email,
																	   'IPAddress' => $this->_IpAddress
																  )
											));
			if ($response['Success']) {
				$this->_setResponse($this->_successMessages['unsubscription_success'], true);
			} else {
				if (count($response['ErrorCode'])) {
					$this->_setResponse($errorCode); // show code by default
					// we need to show up only first problem which we know
					foreach ($response['ErrorCode'] as $errorCode) {
						if (isset($this->_subscriptionErrors[$errorCode]))
							$this->_setResponse($this->_unsubscriptionErrors[$errorCode]);
					}
				}
			}
		} catch (Exception $e) {
			$this->_setResponse($e);
		}
		$this->_sendResponse();
	}

	private function _getResponse($url) {
		return json_decode(file_get_contents($url), true);
	}

	private function _getCommandUrl($command, $params = array()) {

		if ($command == 'Subscriber.Subscribe' || $command == 'Subscriber.Unsubscribe') {

			$url = $this->_options['api_url'] . sprintf(
				'Command=%s&ResponseFormat=JSON',
				$command
			);

		} else {

			if (!$this->_SessionID) {
				$url = $this->_options['api_url'] . sprintf(
					'Command=User.Login&Username=%s&Password=%s&ResponseFormat=JSON',
					$this->_options['login'],
					$this->_options['password']
				);
				$response = $this->_getResponse($url);
				if (true == $response['Success'])
					$this->_SessionID = $response['SessionID'];
				else {
					$this->_setResponse(__(serialize($url) . 'Oempro credentials are incorrect'));
					$this->_sendResponse();
				}
			}

			$url = $this->_options['api_url'] . sprintf(
				'Command=%s&SessionID=%s&ResponseFormat=JSON',
				$command,
				$this->_SessionID
			);

		}

		if (count($params)) {
			foreach ($params as $paramKey => $val) {
				if (!empty($val))
					if (!is_array($val)) {
						$url .= sprintf('&%s=%s', $paramKey, htmlentities(urlencode($val)));
					} else {
						foreach ($val as $valEl) {
							$url .= sprintf('&%s=%s', $paramKey . '[]', htmlentities(urlencode($valEl)));
						}

					}
			}
		}
		return $url;
	}

	private function _validateEmail($email) {
		return is_email(trim($email));
	}

	private function _setResponse($msg, $success = false) {
		$this->_response['msg'] = $msg;
		$this->_response['success'] = $success;
	}

	private function _sendResponse() {
		die(json_encode($this->_response));
	}

	// populate and map saved options, including overwrite for standard error messages according plugin settings
	public function init($testData = array()) {
		if (!count($testData)) {
			global $OemproPluginOptions;
			$options = array_keys($OemproPluginOptions);
			foreach ($options as $optionCode) {
				if (false !== strpos($optionCode, 'unsubscription_error')) {
					if ($val = trim(get_option($optionCode))) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_unsubscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'subscription_error')) {
					if ($val = trim(get_option($optionCode))) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_subscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'success')) {
					if ($val = trim(get_option($optionCode))) {
						$key = substr($optionCode, 3);
						$this->_successMessages[$key] = $val;
					}
				} else {
					$key = substr($optionCode, 3);
					$this->_options[$key] = get_option($optionCode);
				}
			}
		} else {
			foreach ($testData as $optionCode => $testVal) {
				if (false !== strpos($optionCode, 'unsubscription_error')) {
					if ($val = trim($testVal)) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_unsubscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'subscription_error')) {
					if ($val = trim($testVal)) {
						$nmbr = (int)substr($optionCode, -1, 1);
						$this->_subscriptionErrors[$nmbr] = $val;
					}
				} elseif (false !== strpos($optionCode, 'success')) {
					if ($val = trim($testVal)) {
						$key = substr($optionCode, 3);
						$this->_successMessages[$key] = $val;
					}
				} else {
					$key = substr($optionCode, 3);
					$this->_options[$key] = $testVal;
				}
			}
		}

		$this->_options['api_url'] = rtrim($this->_options['account_url'], '/') . '/api.php?';
		return $this->_options;
	}

	public function getSubscriberLists() {
		return $this->_getResponse($this->_getCommandUrl('Lists.Get', array('OrderField' => 'Name', 'OrderType' => 'ASC')));
	}

	public function getConnection() {
		$url = $this->_options['api_url'] . sprintf(
			'Command=User.Login&Username=%s&Password=%s&ResponseFormat=JSON',
			$this->_options['login'],
			$this->_options['password']
		);
		$response = $this->_getResponse($url);
		return (true === $response['Success']) ? true : false;
	}

	public function getSubscriberListFields($listId) {
		$result = array();

		$this->init();
		$this->setTargetList($listId);
		try {
			$params = array(
				'SubscriberListID'	=> $this->_targetListID,
				'OrderField'		=> 'CustomFieldID',
				'OrderType'			=> 'ASC',
			);
			$response = $this->_getResponse($this->_getCommandUrl('CustomFields.Get', $params));
			if ($response['Success']) {
				$result = $response['CustomFields'];
			}
		} catch (Exception $e) {
		}
		return $result;
	}
}
// ============================================================================