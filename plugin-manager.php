<?php
/*
Plugin Name: Multisite Plugin Manager (mcguffin)
Plugin URI: https://github.com/mcguffin/multisite-plugin-manager
Description: This is a fork of <a href="https://github.com/uglyrobot/multisite-plugin-manager">https://github.com/uglyrobot/multisite-plugin-manager</a> by Aaron Edwards. The essential plugin for every multisite install! Manage plugin access permissions across your entire multisite network.
Version: 3.2.0
Author: Aaron Edwards, Jörn Lund
Author URI: http://uglyrobot.com
Network: true

Copyright 2009-2014 UglyRobot Web Development (http://uglyrobot.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class PluginManager {
	
	private static $instance = null;
	
	/**
	 *	Get instance
	 */
	public static function getInstance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 *	prevent cloning
	 */
	private function __clone() { }
	
	/**
	 *	Add actions
	 */
	private function __construct() {
		//declare hooks
		add_action( 'network_admin_menu', array( &$this, 'add_menu' ) );
		add_action( 'wpmu_new_blog', array( &$this, 'new_blog' ), 50 ); //auto activation hook
		add_filter( 'all_plugins', array( &$this, 'remove_plugins' ) );
		add_filter( 'plugin_action_links', array( &$this, 'action_links' ), 10, 4 );

		add_action( 'plugins_loaded', array( &$this, 'localization' ) );

		//individual blog options
		add_action( 'wpmueditblogaction', array( &$this, 'blog_options_form' ) );
		add_action( 'wpmu_update_blog_options', array( &$this, 'blog_options_form_process' ) );

		add_filter( 'plugin_row_meta' , array( &$this, 'remove_plugin_meta' ), 10, 2 );
		add_action( 'admin_init', array( &$this, 'remove_plugin_update_row' ) );
	}

	/**
	 *	Load translations
	 *
	 *	@action plugins_loaded
	 */
	function localization() {
		load_plugin_textdomain('pm', false, '/multisite-plugin-manager/languages/');
	}

	/**
	 *	Add Network admin menu item
	 *
	 *	@action network_admin_menu
	 */
	function add_menu() {
		add_submenu_page( 'plugins.php', __('Plugin Management', 'pm'), __('Plugin Management', 'pm'), 'manage_network_options', 'plugin-management', array( &$this, 'admin_page' ) );
	}

	/**
	 *	Display Network admin page
	 */
	function admin_page() {

		if (!current_user_can('manage_network_options'))
			die('Nice Try!');

		$this->process_form();
		?>
		<div class='wrap'>
			<div class="icon32" id="icon-plugins"><br></div>
			<h2><?php _e('Manage Plugins', 'pm'); ?></h2>

			<?php if (isset($_REQUEST['saved'])) { ?>
				<div id="message" class="updated fade"><p><?php _e('Settings Saved', 'pm'); ?></p></div>
			<?php }

			?>
			<h3><?php _e('Help', 'pm'); ?></h3>
			<p>
				<strong><?php _e('Auto Activation', 'pm'); ?></strong><br/>
				<?php _e('When auto activation is on for a plugin, newly created blogs will have that plugin activated automatically. This does not affect existing blogs.', 'pm'); ?>
			</p>
			<p>
				<strong><?php _e('User Control', 'pm'); ?></strong><br/>
				<?php _e('When user control is enabled for a plugin, all users will be able to activate/deactivate the plugin through the <cite>Plugins</cite> menu. When you turn it off, users that have the plugin activated are grandfathered in, and will continue to have access until they deactivate it.', 'pm'); ?>
			</p>
			<p>
				<strong><?php _e('Mass Activation/Deactivation', 'pm'); ?></strong><br/>

				<?php _e('Mass activate and Mass deactivate buttons activate/deactivates the specified plugin for all blogs. This is different than the "Network Activate" option on the network plugins page, as users can later disable it and this only affects existing blogs. It also ignores the User Control option.', 'pm'); ?>
			</p>

			<form action="plugins.php?page=plugin-management&saved=1" method="post">
				<table class="widefat">
					<thead>
						<tr>
							<th><?php _e('Name', 'pm'); ?></th>
							<th><?php _e('Version', 'pm'); ?></th>
							<th><?php _e('Author', 'pm'); ?></th>
							<th title="<?php _e('Users may activate/deactivate', 'pm'); ?>"><?php _e('User Control', 'pm'); ?></th>
							<th><?php _e('Mass Activate', 'pm'); ?></th>
							<th><?php _e('Mass Deactivate', 'pm'); ?></th>
						</tr>
					</thead>
				<?php

				$plugins = get_plugins();
				$auto_activate = (array)get_site_option('pm_auto_activate_list');
				$user_control = (array)get_site_option('pm_user_control_list');
				foreach ( $plugins as $file => $p ) {

					//skip network plugins or network activated plugins
					if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) )
						continue;
					?>
					<tr>
						<td><?php echo $p['Name']?></td>
						<td><?php echo $p['Version']?></td>
						<td><?php echo $p['Author']?></td>
						<td>
						<?php
							echo '<select name="control['.$file.']" />'."\n";
							$u_checked = in_array($file, $user_control);
							$auto_checked = in_array($file, $auto_activate);

							if ($u_checked) {
								$n_opt = '';
								$s_opt = '';
								$a_opt = ' selected="yes"';
								$auto_opt = '';
							} else if ($auto_checked) {
								$n_opt = '';
								$s_opt = '';
								$a_opt = '';
								$auto_opt = ' selected="yes"';
							}else {
								$n_opt = ' selected="yes"';
								$s_opt = '';
								$a_opt = '';
								$auto_opt = '';
							}
							$opts = '<option value="none"'.$n_opt.'>' . __('Deny', 'pm') . '</option>'."\n";
							$opts .= '<option value="all"'.$a_opt.'>' . __('Allow', 'pm') . '</option>'."\n";
							$opts .= '<option value="auto"'.$auto_opt.'>' . __('Allow and Auto-Activate', 'pm') . '</option>'."\n";

							echo $opts.'</select>';
						?>
						</td>
						<td><?php echo "<a href='plugins.php?page=plugin-management&mass_activate=$file'>" . __('Activate All', 'pm') . "</a>" ?></td>
						<td><?php echo "<a href='plugins.php?page=plugin-management&mass_deactivate=$file'>" . __('Deactivate All', 'pm') . "</a>" ?></td>
					</tr>
					<?php
				}
				?>
				</table>
				<p class="submit">
					<input name="Submit" class="button-primary" value="<?php _e('Update Options', 'pm') ?>" type="submit">
				</p>
			</form>
		</div>
		<?php
	} //end admin_page()
	
	
	/**
	 *	Return all plugins that can be controlled by current blog admin
	 */
	function get_controllable_plugins() {
		$auto_activate		= (array) get_site_option('pm_auto_activate_list');
		$user_control		= (array) get_site_option('pm_user_control_list');
		$override_plugins	= (array) get_option('pm_plugin_override_list');

		// check if $override_plugins is not a numeric array
		if ( 0 === array_sum( array_keys( $override_plugins ) ) ) {
			$override_allow = array_keys( array_filter( $override_plugins, array($this,'_filter_value_1') ) );
			$override_deny  = array_keys( array_filter( $override_plugins, array($this,'_filter_value_0') ) );
		} else {
			// old style: $override_plugins contains controllabla plugins
			$override_allow = $override_plugins;
			$override_deny = array();
		}
		
		
		// merge allowed plugins
		$controllable_plugins = array_unique( array_merge( $auto_activate, $user_control, $override_allow ) );
		
		// subtract denied plugins
		$controllable_plugins = array_diff($controllable_plugins, $override_deny );

		// here we go.
		return $controllable_plugins;
	}
	/**
	 *	array_filter() filter function
	 */
	private function _filter_value_0( $value ) {
		return $value === '0';
	}
	/**
	 *	array_filter() filter function
	 */
	private function _filter_value_1( $value ) {
		return $value === '1';
	}

	//
	/**
	 *	removes the meta information for normal admins
	 *
	 *	@filter	plugin_row_meta
	 */
	function remove_plugin_meta($plugin_meta, $plugin_file) {
		if ( is_network_admin() || is_super_admin() ) {
			return $plugin_meta;
		} else {
			remove_all_actions("after_plugin_row_$plugin_file");
			return array();
		}
	}

	/**
	 *	@action	admin_init
	 */
	function remove_plugin_update_row() {
		if ( !is_network_admin() && !is_super_admin() ) {
			remove_all_actions('after_plugin_row');
		}
	}

	/**
	 *	Process Network admin page form
	 *
	 *	@usedby admin_page
	 */
	function process_form() {

		if (isset($_GET['mass_activate'])) {
			$plugin = $_GET['mass_activate'];
			$this->mass_activate($plugin);
		}
		if (isset($_GET['mass_deactivate'])) {
			$plugin = $_GET['mass_deactivate'];
			$this->mass_deactivate($plugin);
		}

		if (isset($_POST['control'])) {
			//create blank arrays
			$user_control = array();
			$auto_activate = array();
			foreach ($_POST['control'] as $plugin => $value) {
				if ($value == 'none') {
				  //do nothing
				} else if ($value == 'all') {
					$user_control[] = $plugin;
				} else if ($value == 'auto') {
					$auto_activate[] = $plugin;
			  }
			}
			update_site_option('pm_user_control_list', array_unique($user_control));
			update_site_option('pm_auto_activate_list', array_unique($auto_activate));

			//can't save blank value via update_site_option
			if (!$user_control)
				update_site_option('pm_user_control_list', 'EMPTY');
			if (!$auto_activate)
				update_site_option('pm_auto_activate_list', 'EMPTY');
		}
	}

	/**
	 *	options added to wpmu-blogs.php edit page. Overrides sitewide control settings for an individual blog.
	 *
	 *	@action wpmueditblogaction
	 */
	function blog_options_form($blog_id) {

		switch_to_blog($blog_id);

		$plugins			= get_plugins();
		$auto_activate		= (array) get_site_option( 'pm_auto_activate_list' );
		$user_control		= (array) get_site_option( 'pm_user_control_list' );
		$override_plugins	= (array) get_option( 'pm_plugin_override_list' );
		
		?>
		</table>
		<h3><?php _e('Plugin Override Options', 'pm') ?></h3>
		<p style="padding:5px 10px 0 10px;margin:0;">
			<?php _e('Choose “Allow” if you want to allow plugin (de-)activation on this specific site. Choose “Deny” to always deny activation for blog users.', 'pm') ?>
		</p>
		<table class="widefat" style="margin:10px;width:95%;">
			<thead>
				<tr>
					<th><?php _e('Name', 'pm'); ?></th>
					<th><?php _e('Author', 'pm'); ?></th>
					<th><?php _e('Version', 'pm'); ?></th>
					<th><?php _e('User-Control (Network Default)', 'pm'); ?></th>
					<th title="<?php _e('Blog users may activate/deactivate', 'pm') ?>"><?php _e('User Control', 'pm') ?></th>
					<th><?php _e('Activation', 'pm'); ?></th>
				</tr>
			</thead>
			<?php

  

		$control_options = array(
			''	=> __('— Network default —', 'pm'),
			'1'	=> __('Allow', 'pm'),
			'0'	=> __('Deny', 'pm'),
		);
  
		foreach ( $plugins as $file => $p ) {

		//skip network plugins or network activated plugins
			if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) )
				continue;
			?>
			<tr>
				<td><?php echo $p['Name']?></td>
				<td><?php echo $p['Author']?></td>
				<td><?php echo $p['Version']?></td>
				<td><?php 
					if ( in_array( $file, $auto_activate ) ) {
						?><span style="color:#093"><?php
						?><span class="dashicons dashicons-yes"></span><?php
						_e('Allow and Auto-Activate', 'pm');
						?></span><?php
					} else if ( in_array( $file, $user_control ) ) {
						?><span style="color:#093"><?php
						?><span class="dashicons dashicons-yes"></span><?php
						_e('Allow', 'pm');
						?></span><?php
					} else {
						?><span style="color:#aaa"><?php
						?><span class="dashicons dashicons-no-alt"></span><?php
						_e('Deny', 'pm');
						?></span><?php
					}
				?></td>
				<td>
				<?php

					printf('<select name="plugins[%s]">',$file);
					foreach ( $control_options as $value => $label ) {
						$plugin_status = in_array($file, $override_plugins) ? '1' : ( isset($override_plugins[$file]) ? $override_plugins[$file] : '');
						printf('<option value="%s" %s>%s</option>', 
								$value, 
								selected($value, $plugin_status ), 
								$label
							);
					  }
					  echo '</select>'
				?>
				</td>
				<td><?php 
					if ( is_plugin_active( $file) ) {
						?><button class="button" type="submit" name="deactivate-plugin" value="<?php esc_attr_e($file); ?>"><?php
							_e('Deactivate plugin');
						?></button><?php
					} else {
						?><button class="button-primary" type="submit" name="activate-plugin" value="<?php esc_attr_e($file); ?>"><?php
							_e('Activate plugin');
						?></button><?php
					}
				?></td>
			</tr>
			<?php
		}
		echo '</table>';
		restore_current_blog();
	}

	//
	/**
	 *	process options from wpmu-blogs.php edit page. Overrides sitewide control settings for an individual blog.
	 *
	 *	@action wpmu_update_blog_options
	 */
	function blog_options_form_process() {
		if ( isset( $_POST['deactivate-plugin'] ) ) {
			deactivate_plugins($_POST['deactivate-plugin']);
		} else if ( isset( $_POST['activate-plugin'] ) ) {
			activate_plugin($_POST['activate-plugin'], null, false, true );
		}
		$override_plugins = array();
		if (is_array($_POST['plugins'])) {
			foreach ( $_POST['plugins'] as $plugin => $value ) {
				$override_plugins[$plugin] = $value;
			}
			update_option( "pm_plugin_override_list", $override_plugins );
		} else {
			update_option( "pm_plugin_override_list", array() );
		}
	}

	/**
	 *	activate on new blog
	 *
	 *	@action wpmu_new_blog
	 */
	function new_blog( $blog_id ) {
		require_once( ABSPATH.'wp-admin/includes/plugin.php' );

		$auto_activate = (array) get_site_option('pm_auto_activate_list');
		if ( count( $auto_activate ) ) {
			switch_to_blog($blog_id);
			activate_plugins($auto_activate, '', false); //silently activate any plugins
			restore_current_blog();
		}
	}

	/**
	 *	Mass activate
	 *
	 *	@usedby process_form
	 */
	function mass_activate($plugin) {
		global $wpdb;
		
		if (wp_is_large_network()) {
			?><div class="error"><p><?php _e('Failed to mass activate: Your multisite network is too large for this function.', 'pm'); ?></p></div><?php
			return false;
		}
		
		set_time_limit(120);

		$blogs = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = {$wpdb->siteid} AND spam = 0");
		if ($blogs)	{
			foreach($blogs as $blog_id)	{
				switch_to_blog($blog_id);
				activate_plugin($plugin); //silently activate the plugin
				restore_current_blog();
			}
			?><div id="message" class="updated fade"><p><span style="color:#FF3300;"><?php echo esc_html($plugin); ?></span><?php _e(' has been MASS ACTIVATED.', 'pm'); ?></p></div><?php
		} else {
			?><div class="error"><p><?php _e('Failed to mass activate: error selecting blogs', 'pm'); ?></p></div><?php
		}
	}

	/**
	 *	Mass deactivate
	 *
	 *	@usedby process_form
	 */
	function mass_deactivate($plugin) {
		global $wpdb;

		if (wp_is_large_network()) {
			?><div class="error"><p><?php _e('Failed to mass activate: Your multisite network is too large for this function.', 'pm'); ?></p></div><?php
			return false;
		}
		
		set_time_limit(120);

		$blogs = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = {$wpdb->siteid} AND spam = 0");
		if ($blogs)	{
			foreach ($blogs as $blog_id)	{
				switch_to_blog($blog_id);
				deactivate_plugins($plugin, true); //silently deactivate the plugin
				restore_current_blog();
			}
			?><div id="message" class="updated fade"><p><span style="color:#FF3300;"><?php echo esc_html($plugin); ?></span><?php _e(' has been MASS DEACTIVATED.', 'pm'); ?></p></div><?php
		} else {
			?><div class="error"><p><?php _e('Failed to mass deactivate: error selecting blogs', 'pm'); ?></p></div><?php
		}
	}

	/**
	 *	remove plugins with no user control
	 *
	 *	@filter all_plugins
	 */
	function remove_plugins($all_plugins) {

		if (is_super_admin()) //don't filter siteadmin
			return $all_plugins;

		$controllable_plugins = $this->get_controllable_plugins();

		foreach ( (array)$all_plugins as $plugin_file => $plugin_data) {
			if ( ! in_array($plugin_file, $controllable_plugins) ) {
				unset($all_plugins[$plugin_file]); //remove plugin
			}
		}
		return $all_plugins;
	}

	/**
	 *	plugin activate links
	 *
	 *	@filter plugin_action_links
	 */
	function action_links($action_links, $plugin_file, $plugin_data, $context) {
		global $psts, $blog_id;
		
		if (is_network_admin() || is_super_admin()) //don't filter siteadmin
			return $action_links;

		$auto_activate = (array)get_site_option('pm_auto_activate_list');
		$user_control = (array)get_site_option('pm_user_control_list');
		$override_plugins = (array)get_option('pm_plugin_override_list');

		if ($context != 'active') {
			if (in_array($plugin_file, $user_control) || in_array($plugin_file, $auto_activate) || in_array($plugin_file, $override_plugins)) {
				return $action_links;
			}
		}
		return $action_links;
	}

	/**
	 *	use jquery to remove associated checkboxes to prevent mass activation (usability, not security)
	 *
	 *	@action after_plugin_row_$plugin_file
	 */
	function remove_checks($plugin_file) {
		echo '<script type="text/javascript">jQuery("input:checkbox[value=\''.esc_js($plugin_file).'\']).remove();</script>';
	}

}

$pm = PluginManager::getInstance();
