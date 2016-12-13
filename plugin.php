<?php namespace BMC;
/*
Plugin Name: bbPM Compose
Plugin URI: https://samelh.com
Description: bbPress Messages Compose Helper
Author: Samuel Elh
Version: 0.1
Author URI: https://samelh.com
*/

class Loader
{

    /** Class instance **/
    protected static $instance = null;

    /** Constants **/
    public $constants;

    /** Get Class instance **/
    public static function instance()
    {
        return null == self::$instance ? new self : self::$instance;
    }

    public function init()
    {
    	return add_action( "plugins_loaded", array( self::instance(), "load" ) );
	}

	public static function adminNotice()
	{
		global $bbpmc_notice;
    	return $bbpmc_notice ? printf( '<div id="message" class="error fade"><p>%s</p></div>', $bbpmc_notice) : null;
	}

    public static function load()
    {
    	// instance
    	$This = self::instance();
    	// check for bbPress
    	if ( !in_array( 'bbpress/bbpress.php', apply_filters('active_plugins', get_option('active_plugins')) ) ) {
    		global $bbpmc_notice;
    		$bbpmc_notice = '<strong>bbPress Messages Compose error</strong>: Please install and activate bbPress first!';
    		return add_action( 'admin_notices', array( $This, "adminNotice" ) );    		
    	}
    	// check for bbPress Messages
    	else if ( !class_exists('\BBP_messages_message') ) {
    		global $bbpmc_notice;
    		$bbpmc_notice = '<strong>bbPress Messages Compose error</strong>: Please install and activate bbPress Messages first!';
    		return add_action( 'admin_notices', array( $This, "adminNotice" ) );
    	}
    	// all good
    	else {
    		// enqueue scripts
	    	add_action( 'wp_enqueue_scripts', array( $This, 'enqueueScripts' ) );
	    	// ajax
	    	add_action( 'wp_ajax_bbpm-compose', array( $This, 'ajax' ) );
	    	// ajax sender
	    	add_action( 'wp_ajax_bbpm-compose-send', array( $This, 'ajaxSend' ) );
	    	// profile
	    	add_action( 'bbp_template_after_user_profile', array( $This, 'embedProfileFields' ) );
	    	// admin
	    	add_filter( "plugin_action_links_" . plugin_basename(__FILE__), array( $This, "metaUri" ) );
    	}
    }

    public static function enqueueScripts()
    {
    	if ( !is_bbpress() ) return;
    	// base url
    	$base = plugin_dir_url(__FILE__) . '/assets/';
    	// enqueue JS
    	if ( isset($_REQUEST['bbp-compose']) ) {
    		// cSS
    		wp_enqueue_style('bbpmc', "{$base}css/plugin.css");
    		// JS/AJAX
    		wp_enqueue_script('bbpmc', "{$base}js/plugin.js", array('jquery'), '0.1');
    		wp_localize_script('bbpmc', 'BBPMC', array(
    			'ajaxurl' => admin_url('admin-ajax.php'),
    			'nonce' => wp_create_nonce('bmc_nonce')
    		));
    	}
    }

    public static function ajax()
    {
    	if ( apply_filters( 'bbpmc_disabled', false ) ) {wp_die('0');}
        
        if ( !isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'],'bmc_nonce') ) {
            wp_send_json( array( 'success'=>false,'message'=>'authentication required' ) );
        }
        $query = isset( $_REQUEST['search_query'] ) ? sanitize_text_field($_REQUEST['search_query']) : null;
        $exclude = isset( $_REQUEST['exclude'] ) && is_array($_REQUEST['exclude']) ? array_map('intval',$_REQUEST['exclude']) : array();
        $exclude[] = get_current_user_id();
        $args = array( "fields" => array( 'ID', 'display_name' ) );
        
        if ( trim($query) ) {
            $args['search'] = "*{$query}*";
        }
        if ( $exclude ) {
            $args['exclude'] = $exclude;
        }

        $users = get_users($args);

        if ( $users ) { foreach ( $users as $i=>$u ) { $users[$i]->avatar=get_avatar_url($u->ID); } }

        wp_send_json( array('success'=>true,'users'=>$users) );

        // kill request
        wp_die();
    }

    public static function ajaxSend()
    {
    	if ( apply_filters( 'bbpmc_disabled', false ) ) {wp_die('0');}

    	if ( !isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'],'bmc_nonce') ) {
            return wp_send_json( array( 'success'=>false,'message'=>'authentication required' ) );
        }

        $user_ids = isset($_REQUEST['users']) && is_array($_REQUEST['users']) ? array_map('intval', $_REQUEST['users']) : array();
        $role = isset($_REQUEST['role']) ? sanitize_text_field($_REQUEST['role']) : null;
        $all = isset($_REQUEST['all']);
        $message = isset($_REQUEST['message']) ? $_REQUEST['message'] : null;

        if ( !$message ) {
        	return wp_send_json( array( 'success'=>false,'message'=>'Please type out a message!' ) );
        }

		$args = array( 'fields' => array( 'ID' ) );

		if ( $user_ids && is_array($user_ids) ) {
			$args['include'] = $user_ids;
		} else if ( $role ) {
			$args['role'] = $role;
		} else if ( isset( $all ) && $all ) {
			// nothing
		} else {
            return wp_send_json( array( 'success'=>false,'message'=>'No recipients specified for this message.' ) );
		}

		$users = get_users($args);
		$recipients = array();
		global $current_user;
		if ( $users ) {
			foreach ( $users as $user ) {
				if ( $user->ID == $current_user->ID )
					continue;
				if ( !empty($user->ID) ) {
					$recipients[] = (int) $user->ID;
				}
			}
		} else {
            return wp_send_json( array( 'success'=>false,'message'=>'No recipients specified for this message.' ) );			
		}

		$original_input = $message;
		$message = preg_replace('#(<br */?>\s*)+#i', "\n", $message);
		$message = str_replace( array( "<", ">" ), array( "&_lt;", "&_gt;" ), $message );
		$message = esc_attr( strip_tags( $message, '' ) );
		$message = apply_filters( 'bbpm_format_message_input', $message, $original_input );

		if ( !$message ) {
        	return wp_send_json( array( 'success'=>false,'message'=>'Please type out a message!' ) );			
		}

		if ( !$recipients ) {
            return wp_send_json( array( 'success'=>false,'message'=>'No recipients specified for this message.' ) );						
		}

		if ( !$current_user->ID ) {			
            return wp_send_json( array( 'success'=>false,'message'=>'authentication required' ) );
		}

		$counter = 0;

		foreach ( $recipients as $user_id ) {
			$sent = \BBP_messages_message::sender( $user_id, $message, $current_user->ID );
			if ( $sent ) {
				$counter++;
			}
		}

		if ( $counter ) {
            return wp_send_json(array('success'=>true,'message'=>sprintf(
            	'Message successfully sent to %s',
            	sprintf( _n( '%s user', '%s users', $counter ), $counter )
            )));
		} else {
            return wp_send_json( array( 'success'=>false,'message'=>'Error occured: Message was not sent.' ) );
		}
    }

    public static function embedProfileFields()
    {
    	if ( apply_filters( 'bbpmc_disabled', false ) ) return;

    	$user_id = bbp_get_displayed_user_id();

    	if ( !isset( $_REQUEST['bbp-compose'] ) ) {
    		printf(
    			'<p><button onclick="window.location.replace(\'?bbp-compose=1\'); return false;">Compose New Message</button></p>'
    		);
    		return;
    	}

    	global $current_user;

    	$args = array(
    		'request_uri' => remove_query_arg( array('bbp-compose'), $_SERVER['REQUEST_URI']),
    		'autosave' => null,
    		'focus' => 'body',
    		'criteria' => null,//'role',
    		'roles' => wp_roles()->role_names,
    		'count_users' => count_users() 
    	);
    	// remove empty roles
        foreach ( $args['roles'] as $i=>$n ) {
        	if( !isset($args['count_users']['avail_roles'][$i]) ) {
        		unset( $args['roles'][$i] );
        	} else if ( 1 === (int) $args['count_users']['avail_roles'][$i] ) {
        		if ( in_array($i, $current_user->roles) ) {
        			unset( $args['roles'][$i] );
        		}
        	}
       	}

    	self::sendForm($args);

    }

    public static function sendForm($args)
    {
    	?>
    	<form method="post" action="<?php echo esc_url($args['request_uri']); ?>" id="bbpm-compose">
    	
	    	<p>
	    		<textarea name="bbpmc_text" rows="5" cols="60" placeholder="Type a message.."<?php echo 'body' === $args['focus'] ? ' autofocus="autofocus"' : ''; ?>><?php echo esc_attr($args['autosave']); ?></textarea>
	    	</p>
	    	
	    	<p>
		    	<label for="rc_criteria">Recipients</label>
		    	<select name="rc_criteria" id="rc_criteria"<?php echo 'body' === $args['focus'] ? ' autofocus="autofocus"' : ''; ?>>
			    	<option value="0" disabled <?php selected(!$args['criteria']); ?>>Select Criteria</option>
			    	<option value="search"<?php selected($args['criteria'],'search'); ?>>Search and select</option>
			    	<option value="role"<?php selected($args['criteria'],'role'); ?>>Select by role</option>
			    	<option value="all"<?php selected($args['criteria'],'all'); ?>>Select all users</option>
		       	</select>
	    	</p>

	    	<div id="cr-search" style="display:none;">
	    		<div class="cr-picked">
	    			<i>Picked users</i><br/>
	    		</div>

	    		<p><input type="text" placeholder="Search users (press ENTER to search)" size="50" /></p>

	    		<div class="cr-results"></div>
	    	</div>

	    	<div id="cr-roles" style="display:none;">
	    		<p>
		    		<?php foreach ( $args['roles'] as $role => $name ) : ?>
		    			<label><input type="radio" name="role" value="<?php echo esc_attr( $role ); ?>">
		    			<?php echo esc_attr( $name ); ?> <i>(<?php echo $args['count_users']['avail_roles'][$role]; ?> users)</i></label><br/>
		    		<?php endforeach; ?>
	    		</p>
	    	</div>

	    	<div id="cr-all" style="display:none;">
	    		<p>All <?php echo -1+$args['count_users']['total_users'] ; ?> users will be selected as recipients.</p>
	    	</div>

	    	<p>
	    		<input type="hidden" name="bbp-compose" />
	    		<?php wp_nonce_field('bmc_nonce','bmc_nonce'); ?>
	    		<input type="submit" name="bbpmc_send" value="Send" disabled="disabled" />
	    	</p>
    	
    	</form>
    	<?php
    }

    public static function metaUri( $links )
    {
    	return array(
    		'<a href="https://github.com/elhardoum/bbpm-compose/">' . __('Documentation') . '</a>',
    	) + $links;
    }
}

$BMC = new Loader;
$BMC->init();