<?php
/**
 * Plugin Name: WP-Property - Property Portal BLM Export
 * Plugin URI: http://biostall.com
 * Description: Add-on for WP-Property plugin that allows a feed to property portals to be automatically generated
 * Author: Steve Marks (BIOSTALL)
 * Author URI: http://biostall.com
 * Version: 1.0.0
 *
 * Copyright 2013 BIOSTALL ( email : info@biostall.com )
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
 
 // TO DO: If a portal is deleted, remove all it's logs and past files

/* Version number */
define( 'WPP_BLM_Export_Version', '1.0.0' );

/** Path for Includes */
define( 'WPP_BLM_Export_Path', plugin_dir_path( __FILE__ ) );

/** How many days to keep database logs */
define( 'WPP_BLM_Export_Keep_Logs_Days', '30' );

/** The EOF (End Of Field) character to be used within the BLM */
define( 'WPP_BLM_Export_EOF_Character', '^' );

/** The EOR (End Of Record) character to be used within the BLM */
define( 'WPP_BLM_Export_EOR_Character', '|' );
 
// Add submenu link to 'Settings' menu
if ( is_admin() ) {
	add_action( 'admin_menu', 'wpp_blm_export_plugin_menu' );
	add_action( 'admin_init', 'wpp_blm_export_register_settings' ); 
}

function wpp_blm_export_plugin_menu() {
	add_options_page( 'BLM Export Settings', 'BLM Export', 'manage_options', 'blm-export-settings', 'wpp_blm_export_admin_page_screen' );
	add_action('admin_notices', 'wpp_blm_export_show_admin_notices');
}

function wpp_blm_export_show_admin_notices()
{
	$error = '';	
	$uploads_dir = wp_upload_dir();
	if( $uploads_dir['error'] === FALSE )
	{
		$uploads_dir = $uploads_dir['basedir'] . '/blm_export/';
		
		if ( ! @file_exists($uploads_dir) )
		{
			if ( ! @mkdir($uploads_dir) )
			{
				$error = 'Unable to create subdirectory in uploads folder for use by WP-Property BLM Export plugin. Please ensure the <a href="http://codex.wordpress.org/Changing_File_Permissions" target="_blank" title="WordPress Codex - Changing File Permissions">correct permissions</a> are set.';
			}
		}
		else
		{
			if ( ! @is_writeable($uploads_dir) )
			{
				$error = 'The uploads folder is not currently writeable and will need to be before the feed can be ran. Please ensure the <a href="http://codex.wordpress.org/Changing_File_Permissions" target="_blank" title="WordPress Codex - Changing File Permissions">correct permissions</a> are set.';
			}
		}
	}
	else
	{
		$error = 'An error occured whilst trying to create the uploads folder. Please ensure the <a href="http://codex.wordpress.org/Changing_File_Permissions" target="_blank" title="WordPress Codex - Changing File Permissions">correct permissions</a> are set. '.$uploads_dir['error'];
	}
	
	if( $error != '' )
	{
		echo '<div id="message" class="error"><p><strong>'.$error.'</strong></p></div>';
	}
}

// Add 'Settings' link in plugin management screen
function wpp_blm_export_settings_link( $actions, $file ) {
	if( false !== strpos( $file, 'wp-property-blm-export' ))
 		$actions['settings'] = '<a href="options-general.php?page=blm-export-settings">Settings</a>';
	return $actions; 
}
add_filter( 'plugin_action_links', 'wpp_blm_export_settings_link', 2, 2 );

/* Display 'Settings' page */

function wpp_blm_export_register_settings()
{
	register_setting('wpp_blm_export_options', 'wpp_blm_export_options', 'wpp_blm_export_admin_page_validate');
}

function wpp_blm_export_admin_page_validate( $input ) {
	
	if (!isset($input['compression']))
	{
	   	add_settings_error(
	    	'error',                     	// Setting title
	    	'error_compression',           	// Error ID
	   		'Please select whether the files generated and send should be compressed',
			'error'                         // Type of message
	    );
	}
	if (!isset($input['incremental']))
	{
	   	add_settings_error(
	    	'error',                     	// Setting title
	    	'error_incremental',            // Error ID
	   		'Please select whether the feeds should be incremental',
			'error'                         // Type of message
	    );
	}
	
	if( isset($input['active']) && $input['active'] == "1" )
	{
		if( isset($input['sending_frequency']) && in_array($input['sending_frequency'], array('twicedaily', 'daily', 'bidaily')) )
		{
			// update the scheduled event frequency
			$timestamp = wp_next_scheduled( 'wppblmexportcronhook' );
			wp_unschedule_event($timestamp, 'wppblmexportcronhook' );
			wp_clear_scheduled_hook('wppblmexportcronhook');
			$time = strtotime(date("Y-m-d", strtotime("tomorrow")).' 00:00:00');
			if( $input['sending_frequency'] == 'twicedaily' && date("H") < 12 )
			{
				$time = strtotime(date("Y-m-d").' 12:00:00');
			}
			wp_schedule_event( $time, $input['sending_frequency'], 'wppblmexportcronhook' );
		}
	}
	else
	{
		$timestamp = wp_next_scheduled( 'wppblmexportcronhook' );
		wp_unschedule_event($timestamp, 'wppblmexportcronhook' );
		wp_clear_scheduled_hook('wppblmexportcronhook');
	}
	
	$uploads_dir = wp_upload_dir();
	if( $uploads_dir['error'] === FALSE )
	{
		$uploads_dir = $uploads_dir['basedir'] . '/blm_export/' . $portal_id . '/';
		if( ! @file_exists ($uploads_dir) )
		{
			if ( ! @mkdir($uploads_dir, 0777, true) )
			{
				add_settings_error(
			    	'error',                     	// Setting title
			    	'error_upload_dir_create',      // Error ID
			   		'Failed to create the portal directory. Please ensure the uploads directory is writeable and click \'Update\' to try again',
					'error'                         // Type of message
			    );
			}
		}
	}
	else
	{
		add_settings_error(
	    	'error',                     	// Setting title
	    	'error_upload_dir',             // Error ID
	   		'Failed to create the uploads directory. Please ensure the uploads directory is writeable and click \'Update\' to try again',
			'error'                         // Type of message
	    );
	}
	
	// execute cron if necessary
	if( isset($input['portals_to_run']) && is_array($input['portals_to_run']) && count($input['portals_to_run']) > 0 )
	{
		$cron_url_params = '?custom_cron=wppblmexportcronhook';
		
		foreach ($input['portals_to_run'] as $portal_id)
		{
			$cron_url_params .= '&portals_to_run[]=' . $portal_id;
		}
		
		$cron_url = site_url( $cron_url_params );
		wp_remote_post( $cron_url, array( 'timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters( 'https_local_ssl_verify', true ) ) );
		
		add_settings_error(
	    	'error',                     	// Setting title
	    	'error_ran_portals',            // Error ID
	   		'Executed ' . count($input['portals_to_run']) . ' portals successfully. You can check the progress in the \'Statistics\' tab',
			'updated'                       // Type of message
	    );
	}
    return $input;
}

function wpp_blm_export_admin_page_screen()
{
	global $submenu, $wpdb;
	
	wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
	wp_enqueue_script('jquery-ui-tabs');
	wp_enqueue_script('jquery-ui-tooltip');
    wp_enqueue_style('jquery-ui');
	
	wp_register_style('wpp-blm-export-admin-styles', plugin_dir_url( __FILE__ ) . 'css/wp-property-blm-export-admin.css');
    wp_enqueue_style( 'wpp-blm-export-admin-styles');
	
	wp_register_script('wpp-blm-export-admin-script', plugin_dir_url( __FILE__ ) . 'js/wp-property-blm-export-admin.js');
    wp_enqueue_script( 'wpp-blm-export-admin-script');
	
	wp_register_script('jqModal', plugin_dir_url( __FILE__ ) . 'js/jqModal.js');
    wp_enqueue_script( 'jqModal');
	
	wp_register_style('jqModal', plugin_dir_url( __FILE__ ) . 'css/jqModal.css');
    wp_enqueue_style( 'jqModal');
		
	// access page settings 
 	$page_data = array();
 	foreach( $submenu['options-general.php'] as $i => $menu_item ) {
 		if( $submenu['options-general.php'][$i][2] == 'blm-export-settings' )
 			$page_data = $submenu['options-general.php'][$i];
 	}
?>

<div class="wrap">
	
	<?php screen_icon(); ?>
		
	<h2><?php echo $page_data[3];?></h2>
		
	<?php
		if (is_plugin_active('wp-property/wp-property.php'))
		{
			$wpp_settings = get_option('wpp_settings');
			if( isset($wpp_settings['property_stats']) )
			{
				$wpp_fields = $wpp_settings['property_stats'];
				
				$wpp_meta = $wpp_settings['property_meta'];
				
				$wpp_predefined_values = array();
				if( isset($wpp_settings['predefined_values']) )
				{
					$wpp_predefined_values = $wpp_settings['predefined_values'];
				}

				$wpp_admin_attr_fields = array();
				if( isset($wpp_settings['admin_attr_fields']) )
				{
					$wpp_admin_attr_fields = $wpp_settings['admin_attr_fields'];
				}
		?>
		<form id="wpp_blm_export_options" action="options.php" method="post">
			
			<?php submit_button(); ?>
			
			<?php 
				settings_fields( 'wpp_blm_export_options' );
				//wp_nonce_field('update-options');
				
				$options = get_option('wpp_blm_export_options');
				
				$active = (isset($options['active']) ? $options['active'] : '' );
				
				$compression = (isset($options['compression']) ? $options['compression'] : '' );
				
				$incremental = (isset($options['incremental']) ? $options['incremental'] : '1' );
				
				$email_report = (isset($options['email_report']) ? $options['email_report'] : '' );
				$email_report_to = (isset($options['email_report_to']) ? $options['email_report_to'] : '' );
				
				$sending_frequency = (isset($options['sending_frequency']) ? $options['sending_frequency'] : 'daily' );
				
				$branch_code_use = (isset($options['branch_code_use']) ? $options['branch_code_use'] : 'single' );
				
				$field_concatenations = (isset($options['concats']) ? $options['concats'] : '' );
				
				$field_postcode_parts = (isset($options['postcode']) ? $options['postcode'] : '' );
				
				$archive = (isset($options['archive']) ? $options['archive'] : '1' );
				$archive_duration = (isset($options['archive_duration']) ? $options['archive_duration'] : '7' );
			?>
			
			<script>
				var predefined_values = <?php 
					$predefined_values = array();
					
					foreach( $wpp_predefined_values as $key => $value )
					{
						$key = 'attr_'.$key;
						$predefined_values[$key] = $value;
					}
					foreach( $wpp_admin_attr_fields as $key => $value )
					{
						if( $value == "checkbox" )
						{
							$key = 'attr_'.$key;
							$predefined_values[$key] = ',true';
						}
					}
				 	echo json_encode($predefined_values);
				?>;
				
				<?php
					$wpp_field_map = ( isset($options['wpp_field_map']) ? $options['wpp_field_map'] : array() );
					if( $wpp_field_map !== FALSE && $wpp_field_map != '' && is_array($wpp_field_map) ) {
						
					}else{
						$wpp_field_map = array();
					}
				?>
				var wpp_field_map = <?php echo json_encode($wpp_field_map); ?>;
				
				var concat_dropdown_options_attr = {};
				var concat_dropdown_options_meta = {};
			</script>
			
			<div id="wpp_blm_export_settings_tabs" class="wpp_blm_export_settings_tabs clearfix">
				<ul class="tabs">
			    	<li><a href="#tab_sending_options">Export / Sending Options</a></li>
			    	<li><a href="#tab_portals_branch_codes">Portals / Branch Codes</a></li>
			    	<li><a href="#tab_field_mapping">Field Mapping</a></li>
			    	<li><a href="#tab_media">Media</a></li>
			    	<li><a href="#tab_archiving">Archiving</a></li>
			    	<li><a href="#tab_statistics">Statistics</a></li>
			    	<li><a href="#tab_actions">Action / Tools</a></li>
			  	</ul>
			
				<!-- SENDING OPTIONS -->
			  	<div id="tab_sending_options">
			  		
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Export / Sending Options</h3>
								</td>
							</tr>
						</tbody>
					</table>
			  		
			  		<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									Run Feed Automatically? <a href="#" class="tooltips" title="By choosing to run the feed automatically it will be executed by the WordPress cron handler."></a>
								</th>
								<td>
									<input type="checkbox" name="wpp_blm_export_options[active]" id="active" value="1"<?php if ($active == '1') { echo ' checked="checked"'; } ?>>
								</td>
							</tr>
							<tr id="sending_frequency_row"<?php if ($active != '1') { echo ' style="display:none"'; } ?>>
								<th scope="row">
									Sending Frequency <a href="#" class="tooltips" title="Twice Daily - Midnight and noon each day<br />Daily - Every day at midnight<br />Bi Daily - Every other day at midnight">
								</th>
								<td>
									<select name="wpp_blm_export_options[sending_frequency]">
										<option value="twicedaily"<?php if ($sending_frequency == 'twicedaily') { echo ' selected="selected"'; } ?>>Twice Daily</option>
										<option value="daily"<?php if ($sending_frequency == 'daily' || $sending_frequency == '') { echo ' selected="selected"'; } ?>>Daily</option>
										<option value="bidaily"<?php if ($sending_frequency == 'bidaily') { echo ' selected="selected"'; } ?>>Bi Daily</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									Compression <a href="#" class="tooltips" title="Not compressing the files into a ZIP file will result in each file being uploaded separately, resulting in feeds taking longer."></a>
								</th>
								<td>
									<input type="radio" name="wpp_blm_export_options[compression]" value="1"<?php if ($compression == '1' || $compression == '') { echo ' checked="checked"'; } ?>> Compress BLM and media into ZIP files (recommended)<br />
									<input type="radio" name="wpp_blm_export_options[compression]" value="0"<?php if ($compression == '0') { echo ' checked="checked"'; } ?>> No compression
								</td>
							</tr>
							<tr>
								<th scope="row">
									Send Incremental Feeds <a href="#" class="tooltips" title="By choosing to send incremental feeds, only the media that is new or recently changed will be sent. This results in the feeds running a lot quicker and smaller filesizes.<br /><br />Not sending the feed incrementally will result in every bit of media being sent every time the feed runs.<br /><br />Sometimes this can be useful if, for example, there is a problem with the feed and you want to send everything again."></a>
								</th>
								<td>
									<input type="radio" name="wpp_blm_export_options[incremental]" value="1"<?php if ($incremental == '1' || $incremental == '') { echo ' checked="checked"'; } ?>> Yes (recommended)<br />
									<input type="radio" name="wpp_blm_export_options[incremental]" value="0"<?php if ($incremental == '0') { echo ' checked="checked"'; } ?>> No
								</td>
							</tr>
							<tr>
								<th scope="row">
									Email Reports
								</th>
								<td>
									<select name="wpp_blm_export_options[email_report]" id="email_report">
										<option value=""<?php if ($email_report === FALSE || $email_report == '') { echo ' selected="selected"'; } ?>>Don't Email Reports</option>
										<option value="1"<?php if ($email_report == '1') { echo ' selected="selected"'; } ?>>Only When Errors Occur</option>
										<option value="2"<?php if ($email_report == '2') { echo ' selected="selected"'; } ?>>Everytime A Feed Finishes</option>
									</select>
								</td>
							</tr>
							<tr id="email_report_to_row"<?php if ($email_report != 1 && $email_report != 2) { echo ' style="display:none"'; } ?>>
								<th scope="row">
									Email Reports To
								</th>
								<td>
									<input type="text" name="wpp_blm_export_options[email_report_to]" id="email_report_to" value="<?php if ($email_report_to !== FALSE && $email_report_to != '') { echo $email_report_to; } ?>">
								</td>
							</tr>
						</tbody>
					</table>
			  
			  	</div>
			  	
			  	<!-- PORTALS / BRANCH CODES -->
				<div id="tab_portals_branch_codes">
			  		
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Branch Codes <a href="#" class="tooltips" title="Branch codes are unique codes provided to you by the portal that allows them to know which office / branch a property belongs to.<br /><br />This is particularly useful in the event that you are dealing with a multi-office agency."></a></h3>
								</td>
							</tr>
						</tbody>
					</table>
			  		
			  		<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row">
									Use of Branch Codes:
								</th>
								<td>
									<input type="radio" name="wpp_blm_export_options[branch_code_use]" id="branch_code_use_single" value="single"<?php if ($branch_code_use == 'single' || $branch_code_use == '') { echo ' checked="checked"'; } ?>> 
									<label for="branch_code_use_single">Each portal will have just one branch code</label><br />
									
									<input type="radio" name="wpp_blm_export_options[branch_code_use]" id="branch_code_use_peroffice" value="peroffice"<?php if ($branch_code_use == 'peroffice') { echo ' checked="checked"'; } ?>> 
									<label for="branch_code_use_peroffice">We have multiple branch codes (ie. one per office)</label>
								</td>
							</tr>
					</table>
					<table class="form-table" id="branch_codes_peroffice_row"<?php if ($branch_code_use == 'peroffice') {  }else{ echo ' style="display:none"'; } ?>>
						<tbody>
							<tr>
								<th scope="row">
									Office Field in WP-Property <a href="#" class="tooltips" title="The field you have setup in WP-Property that determines which office / branch a property belongs to."></a>
								</th>
								<td>
									<select name="wpp_blm_export_options[branch_code_office_field]" id="branch_code_office_field">
										<option value=""></option>
										<?php
											$branch_code_office_field = (isset($options['branch_code_office_field']) ? $options['branch_code_office_field'] : '');
											
											foreach ($wpp_fields as $value => $label)
											{
												echo '<option value="attr_'.$value.'"';
												if ($branch_code_office_field == 'attr_'.$value)
												{
													echo ' selected="selected"';
												}
												echo '>'.$label.'</option>';
											}
										?>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Portals</h3>
									<a href="#" class="add-portal button">+ Add New Portal</a>
								</td>
							</tr>
						</tbody>
					</table>
					
					<div id="portals">
						<?php
							$portals = array();
							if( isset($options['portals']) ) { $portals = $options['portals']; }
							
							echo '<script>var portals = '.json_encode($portals).';</script>';
						?>
						<div id="portals_none">
							<table class="form-table">
								<tbody>
									<tr>
										<td>
											No portals have been setup yet.
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<a href="#" class="add-portal button">+ Add New Portal</a>
								</td>
							</tr>
						</tbody>
					</table>
					
			 	</div>
			 	 
			 	<!-- FIELD MAPPING -->
				<div id="tab_field_mapping">
			  		
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Field Mapping <a href="#" class="tooltips" title="By mapping the fields you are telling the plugin how the fields setup in WP-Property relate to the fields in the BLM file that will be sent to the portals.<br /><br />The options will vary depending on the field's requirements so take your time and work down them one at a time.<br /><br />Remember you can always run a test from the 'Actions' tab."></a></h3>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<span class="mandatory">*</span> - Mandatory field in BLM specification
								</td>
							</tr>
						</tbody>
					</table>
			  		
			  		
			  		
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
			  		
							  		<?php
										$field_array = get_field_array();
										
										$wpp_field = ( isset($options['wpp_field'] ) ? $options['wpp_field'] : array());
										if ( $wpp_field !== FALSE && $wpp_field != '' && is_array($wpp_field) )
										{
											
										}
										else
										{
											$wpp_field = array();
										}
									?>
									
									<table class="widefat">
										<thead>
											<tr>
												<th style="width:150px;">Field in BLM</th>
												<th style="width:210px;">Related Field in WP-Property</th>
												<th>Map Values</th>
											</tr>
										</thead>
										<tfoot>
											<tr>
												<th>Field in BLM</th>
												<th>Related Field in WP-Property</th>
												<th>Map Values</th>
											</tr>
										</tfoot>
										<tbody>
										<?php
											foreach( $field_array as $rm_field => $field_props ) {
												
												if( !isset($field_props['generated']) || (isset($field_props['generated']) && !$field_props['generated']) ) {
										?>
											<tr>
												<td><?php echo $rm_field; if ($field_props['mandatory']) { echo ' <span class="mandatory">*</span>'; } ?></td>
												<td>
													<select name="wpp_blm_export_options[wpp_field][<?php echo $rm_field; ?>]" id="wpp_field_<?php echo $rm_field ?>" style="width:190px;">
														<?php
															$property_attribute_options = '';
															
															foreach( $wpp_fields as $value => $label )
															{
																$property_attribute_options .= '<option value="attr_'.$value.'"';
																if( isset($wpp_field[$rm_field]) && $wpp_field[$rm_field] == 'attr_'.$value ) {
																	$property_attribute_options .= ' selected="selected"';
																}
																$property_attribute_options .= '>'.$label.'</option>';
															}
															
															$property_meta_options = '';
															
															foreach( $wpp_meta as $value => $label )
															{
																$property_meta_options .= '<option value="meta_'.$value.'"';
																if( isset($wpp_field[$rm_field]) && $wpp_field[$rm_field] == 'meta_'.$value ) {
																	$property_meta_options .= ' selected="selected"';
																}
																$property_meta_options .= '>'.$label.'</option>';
															}
															
															$options = '';
															
															$concatenate = false;
															$concatenate_selected = false;
															$concatenate_attr = false;
															$concatenate_meta = false;
															
															switch( $rm_field ) {
																case "POSTCODE1" :
																case "POSTCODE2" : {
																	$options .= '<option value=""></option>';
																	$options .= $property_attribute_options;
																	$options .= '<option value="postcode_part"';
																	if( isset($wpp_field[$rm_field]) && $wpp_field[$rm_field] == 'postcode_part' ) {
																		$options .= ' selected="selected"';
																	}
																	$options .= '>Use '.( ( $rm_field == "POSTCODE1" ) ? 'First' : 'Second' ).' Part Of Postcode Field...</option>';
																	break;
																}
																case "FEATURE1" : 
																case "FEATURE2" : 
																case "FEATURE3" : 
																case "FEATURE4" : 
																case "FEATURE5" : 
																case "FEATURE6" : 
																case "FEATURE7" : 
																case "FEATURE8" : 
																case "FEATURE9" : 
																case "FEATURE10" : {
																	$options .= '<option value="1"';
																	if( !isset($wpp_field[$rm_field]) || ( isset($wpp_field[$rm_field]) && ($wpp_field[$rm_field] == '1' || $wpp_field[$rm_field] == '') ) ) {
																		$options .= ' selected="selected"';
																	}
																	$options .= '>Use Features &amp; Community Features</option>';
																	$options .= '<option value="2"';
																	if( isset($wpp_field[$rm_field]) && $wpp_field[$rm_field] == '2' ) {
																		$options .= ' selected="selected"';
																	}
																	$options .= '>Use Features Only</option>';
																	$options .= '<option value="3"';
																	if( isset($wpp_field[$rm_field]) && $wpp_field[$rm_field] == '3' ) {
																		$options .= ' selected="selected"';
																	}
																	$options .= '>Use Community Features Only</option>';
																	break;
																}
																case "SUMMARY" :
																case "DESCRIPTION" : {
																	$options .= '
																		<option value=""></option>
																		<optgroup label="Property Attribute Fields">
																	';
																	$options .= $property_attribute_options;
																	$options .= '
																		</optgroup>
																		<optgroup label="Property Meta Fields">
																	';
																	$options .= $property_meta_options;
																	$options .= '
																		</optgroup>';
																	if( $rm_field == 'DESCRIPTION' ) {
																		$concatenate = true;
																		$concatenate_attr = true;
																		$concatenate_meta = true;
																		
																		$options .= '<option value="concatenate"';
																		if( isset($wpp_field[$rm_field]) && $wpp_field[$rm_field] == 'concatenate' ) {
																			$options .= ' selected="selected"';
																			$concatenate_selected = true;
																		}
																		$options .= '>Concatenate Multiple Fields...</option>';
																		
																	}
																	break;
																}
																case "CREATE_DATE" :
																case "UPDATE_DATE" : {
																	$options .= '
																		<option value="">Use Property '.( ( $rm_field == "CREATE_DATE" ) ? 'Published' : 'Modified' ).' Date</option>
																		<optgroup label="Property Attribute Fields">
																	';
																	$options .= $property_attribute_options;
																	$options .= '</optgroup>';
																	break;
																}
																case "DISPLAY_ADDRESS" : {
																	$concatenate = true;
																	$concatenate_attr = true;
																	
																	$options .= '
																		<optgroup label="Property Attribute Fields">
																	';
																	$options .= $property_attribute_options;
																	$options .= '
																		</optgroup>
																		<option value="concatenate"';
																	if( isset($wpp_field[$rm_field]) && $wpp_field[$rm_field] == 'concatenate' ) {
																		$options .= ' selected="selected"';
																		$concatenate_selected = true;
																	}
																	$options .= '>Concatenate Multiple Fields...</option>';
																	break;
																}	
																case "PUBLISHED_FLAG" : {
																	$options .= '
																		<option value="">Use property published state</option>
																		<optgroup label="Property Attribute Fields">
																	';
																	$options .= $property_attribute_options;
																	$options .= '</optgroup>';
																	break;
																}
																case "TRANS_TYPE_ID" : {
																	$options .= '<option value=""></option>';
																	$options .= '<option value="1">We Only Deal With Resale Properties</option>';
																	$options .= '<option value="2">We Only Deal With Lettings Properties</option>';
																	$options .= '<optgroup label="Property Attribute Fields">';
																	$options .= $property_attribute_options;
																	$options .= '</optgroup>';
																	break;
																}
																default : {
																	$options .= '<option value=""></option>';
																	$options .= $property_attribute_options;
																}
															}
															echo $options;
														?>
													</select>
													<?php
														if( $rm_field == "LET_DATE_AVAILABLE" )
														{
															echo 'Must be in the format dd/mm/yyyy or yyyy-mm-dd';
														}
														if( $rm_field == "POSTCODE1" || $rm_field == "POSTCODE2" ) {
															echo '<div id="postcode_field_'.$rm_field.'" style="padding-top:5px;';
															if( !isset( $wpp_field[$rm_field] ) || ( isset( $wpp_field[$rm_field] ) && $wpp_field[$rm_field] == 'postcode_part' ) ) {
																echo 'display:none;"';
															}
															echo '">';
															
															echo '	<select name="wpp_blm_export_options[postcode]['.$rm_field.']" id="postcode_'.$rm_field.'" style="width:170px;">';
															
															echo '		<option value=""></option>';
															
															foreach( $wpp_fields as $value => $label ) 
															{
																echo '<option value="attr_'.$value.'"';
																if( isset($field_postcode_parts[$rm_field]) && $field_postcode_parts[$rm_field] == 'attr_'.$value )
																{
																	echo ' selected="selected"';
																}
																echo '>'.$label.'</option>';
															}
															
															echo '	</select>';
															
															echo '</div>';
														}
														if( $concatenate ) {
															// This option has the ability to concatenate multiple fields
															echo '<div id="concatenate_fields_'.$rm_field.'" style="padding-top:5px;';
															if( !isset($concatenate_selected) || (isset($concatenate_selected) && $concatenate_selected === FALSE) ) {
																echo 'display:none;"';
															}
															echo '">';
															
															if( isset($field_concatenations[$rm_field]) ) {
																$i = 0;
																foreach( $field_concatenations[$rm_field] as $field_concatenation ) {
																	echo '<select name="wpp_blm_export_options[concats]['.$rm_field.'][]" id="concats_'.$rm_field.'_'.$i.'" style="width:170px;">';
																	
																	$options = '<option value=""></option>';
																	
																	if ( $concatenate_attr ) {
																		if( $concatenate_attr && $concatenate_meta ) $options .= '<optgroup label="Property Attributes">';
																		foreach( $wpp_fields as $value => $label ) {
																			$options .= '<option value="attr_'.$value.'"';
																			if( $field_concatenation == 'attr_'.$value ) {
																				$options .= ' selected="selected"';
																			}
																			$options .= '>'.$label.'</option>';
																		}
																		if( $concatenate_attr && $concatenate_meta ) $options .= '</optgroup>';
																	}
																	
																	if ( $concatenate_meta ) {
																		if( $concatenate_attr && $concatenate_meta ) $options .= '<optgroup label="Property Meta">';
																		foreach( $wpp_meta as $value => $label ) {
																			$options .= '<option value="meta_'.$value.'"';
																			if( $field_concatenation == 'meta_'.$value ) {
																				$options .= ' selected="selected"';
																			}
																			$options .= '>'.$label.'</option>';
																		}
																		if( $concatenate_attr && $concatenate_meta ) $options .= '</optgroup>';
																	}
																	
																	echo $options;
																	
																	echo '</select> <a href="#" id="remove_concatenate_field_'.$rm_field.'_'.$i.'">-</a><br />';
																	
																	++$i;
																}
															}
															
															echo '<a href="#" id="add_concatenate_field_'.$rm_field.'" class="button" style="margin:5px 0">+ Add Field</a>';
															
															echo '</div>
															
															<script>
																concat_dropdown_options_attr.'.$rm_field.' = '.json_encode( ( ( $concatenate_attr ) ? $wpp_fields : array() ) ).';
																concat_dropdown_options_meta.'.$rm_field.' = '.json_encode( ( ( $concatenate_meta ) ? $wpp_meta : array() ) ).';
															</script>';
														}
													?>
												</td>
												<td>
													<?php
														if (isset($field_props['mapping']) && is_array($field_props['mapping']) && count($field_props['mapping']))
														{
															echo '
																<div id="wpp_field_map_'.$rm_field.'_no" style="padding-top:5px">The WP-Property field selected doesn\'t have any predefined values set.</div>
																<div id="wpp_field_map_'.$rm_field.'_yes" style="height:120px; overflow:hidden">
																	<div style="float:left; width:130px; padding-top:5px; font-weight:bold;">BLM Value</div> 
																	<div style="padding-top:5px; font-weight:bold;">WP-Property Value</div>
																	<div class="wpp_clear"></div>';
															foreach ($field_props['mapping'] as $rm_value => $value)
															{
																echo '<label for="wpp_field_map_'.$rm_field.'_'.$rm_value.'" style="float:left; width:130px; padding-top:5px">'.$value['rm_label'].' ('.$rm_value.')</label> <select multiple="multiple" name="wpp_blm_export_options[wpp_field_map]['.$rm_field.']['.$rm_value.'][]" id="wpp_field_map_'.$rm_field.'_'.$rm_value.'"></select><br />';
															}
															echo '</div>
																  <div id="wpp_field_map_'.$rm_field.'_yes_toggle" style="margin:8px 0 5px">
																  	<a href="#" class="button">+ Show More</a>
																  </div>';
														}
														else
														{
															echo '<div id="wpp_field_map_'.$rm_field.'_na"><span style="color:#AAA">N/A</span></div>';
														}
													?>				
												</td>
											</tr>
										<?php
												}
											}
										?>
										</tbody>
									</table>
			  					
			  					</td>
			  				</tr>
			  			</tbody>
			  		</table>
			  		
				</div>
			 	 
			 	<!-- MEDIA -->
				<div id="tab_media">
			  	
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Media Options</h3>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<p><strong>Images</strong></p>
									<p>At present the 'large' version of the images will be sent. The size of these images can be modified in 'Settings &gt; Media'.</p>
									<p>Please note that this may effect other areas of your website so be careful before changing these values.</p>
									<br />
									<p><strong>Floorplans, Brochures, EPC's and Virtual Tours</strong></p>
									<p>Due to there being no standard way of adding these types of media to WP-Property, these are not currently included in the feed.</p>
									<p>This will hopefully be catered for in the near future by way of adding some sort of hook.</p>
									<p>For now we recommend that you add any floorplans or EPC's as additional images on the property record.</p>
								</td>
							</tr>
						</tbody>
					</table>
					
				</div>
			 	 
			 	<!-- ARCHIVING -->
				<div id="tab_archiving">
			  	
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Archiving Options</h3>
								</td>
							</tr>
						</tbody>
					</table>
			  		
			  		<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									Archive Generated Feeds? <a href="#" class="tooltips" title="By archiving feeds you are essentially keeping a temporary backup in the event you wish to query or debug a feed."></a>
								</th>
								<td>
									<input type="radio" name="wpp_blm_export_options[archive]" id="archive_1" value="1"<?php if ((isset($archive) && $archive == '1') || (isset($archive) && $archive == '') || !isset($archive)) { echo ' checked="checked"'; } ?>> <label for="archive_1">Yes</label><br />
									<input type="radio" name="wpp_blm_export_options[archive]" id="archive_0" value="0"<?php if (isset($archive) && $archive == '0') { echo ' checked="checked"'; } ?>> <label for="archive_0">No</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									Keep Feeds For <a href="#" class="tooltips" title="Select how many days you wish to keep archived / previously sent feeds for.<br /><br />Note: The more days you select the more disk space will be used up."></a>
								</th>
								<td>
									<select name="wpp_blm_export_options[archive_duration]">
										<option value="3"<?php if (isset($archive_duration) && $archive_duration == '3') { echo ' selected="selected"'; } ?>>3 days</option>
										<option value="7"<?php if ((isset($archive_duration) && ($archive_duration == '7' || $archive_duration == '')) || !isset($archive_duration)) { echo ' selected="selected"'; } ?>>7 days</option>
										<option value="10"<?php if (isset($archive_duration) && $archive_duration == '10') { echo ' selected="selected"'; } ?>>10 days</option>
										<option value="14"<?php if (isset($archive_duration) && $archive_duration == '14') { echo ' selected="selected"'; } ?>>14 days</option>
										<option value="21"<?php if (isset($archive_duration) && $archive_duration == '21') { echo ' selected="selected"'; } ?>>21 days</option>
										<option value="30"<?php if (isset($archive_duration) && $archive_duration == '30') { echo ' selected="selected"'; } ?>>30 days</option>
										<option value="60"<?php if (isset($archive_duration) && $archive_duration == '60') { echo ' selected="selected"'; } ?>>60 days</option>
										<option value="0"<?php if (isset($archive_duration) && $archive_duration == '0') { echo ' selected="selected"'; } ?>>Forever</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									Archive Folder <a href="#" class="tooltips" title="The directory where you can locate the archived files."></a>
								</th>
								<td>
									<?php 
										$wp_uploads_dir = wp_upload_dir();
										if( $wp_uploads_dir['error'] === FALSE )
										{
											$uploads_dir = $wp_uploads_dir['basedir'] . '/blm_export/';
											$uploads_url = $wp_uploads_dir['baseurl'] . '/blm_export/';
											echo $uploads_dir . '<em>%portal_number%</em>';
										}
										else
										{
											echo '<strong style="color:#900">ERROR:</strong> '.$wp_uploads_dir['error'];
										}
									?>
								</td>
							</tr>
						</tbody>
					</table>
			  
				</div>
				
				<!-- STATISTICS -->
				<div id="tab_statistics">
			  	
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Right Now</h3>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
								<?php
									$result = $wpdb->get_row( " SELECT portal_id, start_date FROM " .$wpdb->prefix . "blm_export_logs_feed_portal WHERE start_date > end_date ORDER BY start_date DESC LIMIT 1 ");
									if( $result != null )
									{
										// A portal is running. Let's get which one it is
										$portal_name = '(unknown)';
										if( count($portals) )
										{
											foreach( $portals as $portal_id => $portal )
											{
												if( $portal_id == $result->portal_id )
												{
													$portal_name = $portal['name'];
													break;
												}
											}
										}
										echo 'The feed is currently running and processing portal: '.$portal_name.'<br /><br />It started at '.date("H:i", strtotime($result->start_date)).' on '.date("l jS F Y", strtotime($result->start_date));
									}
									else
									{
										echo 'No feeds are currently running.';
									}
								?>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Coming Up</h3>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									Next scheduled to run at
								</th>
								<td>
								<?php
									$timestamp = wp_next_scheduled( 'wppblmexportcronhook' );
									if ($timestamp === FALSE)
									{
										echo 'No feeds are scheduled for the future. Either a feed is currently running or feeds are not setup to run automatically under the \'Export / Sending Options\' tab.';
									}
									else
									{
										echo date("H:i", $timestamp).' on '.date("l jS F Y", $timestamp);
									}
								?>
								</td>
							</tr>
						</tbody>
					</table>
			  		
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Past Feeds</h3>
								</td>
							</tr>
						</tbody>
					</table>
			  		<?php
			  			$statistics_query = "SELECT * FROM " .$wpdb->prefix . "blm_export_logs_feed_portal WHERE start_date <= end_date ORDER BY start_date DESC";
			  		?>
			  		<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									Show Statistics For:
								</th>
								<td>
									<select name="statistics_feed_portal" id="statistics_feed_portal">
										<?php
											// Get completed feeds, newest to oldest
											$results = $wpdb->get_results( $statistics_query );
											if( $wpdb->num_rows > 0 )
											{
												echo '<option value=""></option>';
												
												foreach ($results as $result)
												{
													// A portal is running. Let's get which one it is
													$portal_name = '(unknown)';
													if( count($portals) )
													{
														foreach( $portals as $portal_id => $portal )
														{
															if( $portal_id == $result->portal_id )
															{
																$portal_name = $portal['name'];
																break;
															}
														}
													}
													
													echo '<option value="' . $result->id . '">' . $portal_name . ' - ' .date("jS M", strtotime($result->start_date)) . ' at ' . date("H:i", strtotime($result->start_date)) . '</option>';
												}
											}
											else
											{
												echo '<option value="">No feeds completed yet</option>';
											}
										?>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					
					<script>
						<?php
						
							$statistics = array();
							
							$results = $wpdb->get_results( $statistics_query );
							if( $wpdb->num_rows > 0 )
							{
								foreach ($results as $result)
								{
									$statistics[$result->id] = array();
									
									$portal_name = '(unknown)';
									if( count($portals) )
									{
										foreach( $portals as $portal_id => $portal )
										{
											if( $portal_id == $result->portal_id )
											{
												$portal_name = $portal['name'];
												break;
											}
										}
									}
									
									$statistics[$result->id]['status'] = $result->status;
									$statistics[$result->id]['portal_name'] = $portal_name;
									$statistics[$result->id]['start_date'] = date("H:i", strtotime($result->start_date)) . ' on ' . date("l jS F Y", strtotime($result->start_date));
									$statistics[$result->id]['end_date'] = date("H:i", strtotime($result->end_date)) . ' on ' . date("l jS F Y", strtotime($result->end_date));
									
									// Calculate duration
									$duration_seconds = strtotime( $result->end_date ) - strtotime( $result->start_date );
									$duration_minutes = $duration_seconds / 60;
									$duration_hours = $duration_minutes / 60;
									
									$statistics[$result->id]['duration_seconds'] = floor($duration_seconds);
									$statistics[$result->id]['duration_minutes'] = floor($duration_minutes);
									$statistics[$result->id]['duration_hours'] = floor($duration_hours);
									
									// Check ZIP and BLM file exists in order to provide link
									$statistics[$result->id]['blm_file'] = '';
									$statistics[$result->id]['zip_file'] = '';
									if( isset( $uploads_url ) )
									{
										if( file_exists( $uploads_dir . $result->portal_id . '/' . date("YmdHis", strtotime($result->start_date)) . '.blm' ) )
										{
											$statistics[$result->id]['blm_file'] = $uploads_url . $result->portal_id . '/' . date("YmdHis", strtotime($result->start_date)) . '.blm';
										}
										if( file_exists( $uploads_dir . $result->portal_id . '/' . date("YmdHis", strtotime($result->start_date)) . '.zip' ) )
										{
											$statistics[$result->id]['zip_file'] = $uploads_url . $result->portal_id . '/' . date("YmdHis", strtotime($result->start_date)) . '.zip';
										}
									}
									
									// Get properties
									$statistics[$result->id]['properties'] = array();
									$property_results = $wpdb->get_results( " SELECT " .$wpdb->prefix . "blm_export_logs_feed_portal_property.*, post_title FROM " .$wpdb->prefix . "blm_export_logs_feed_portal_property INNER JOIN $wpdb->posts ON $wpdb->posts.ID = " .$wpdb->prefix . "blm_export_logs_feed_portal_property.post_id WHERE feed_portal_id = ".$result->id." ORDER BY send_date ", ARRAY_A );
									if( $wpdb->num_rows > 0 )
									{
										foreach ($property_results as $property_result)
										{
											$statistics[$result->id]['properties'][] = $property_result;
										}
									}
									
									// Get errors
									$statistics[$result->id]['errors'] = 0;
									$statistics[$result->id]['log'] = array();
									$error_results = $wpdb->get_results( " SELECT * FROM " .$wpdb->prefix . "blm_export_logs_feed_error WHERE feed_portal_id = ".$result->id." ORDER BY error_date, id ASC " );
									if( $wpdb->num_rows > 0 )
									{
										foreach ($error_results as $error_result)
										{
											if ($error_result->severity > 0) ++$statistics[$result->id]['errors'];
											$statistics[$result->id]['log'][] = $error_result;
										}
									}
								}
							}
						?>
						var statistics = <?php echo json_encode( $statistics ); ?>;
					</script>
					
					<div id="statistics_table">
						
						<table class="form-table">
							<tbody></tbody>
						</table>
						
					</div>
					
					<div class="jqmWindow" id="dialog">test</div>
			  
				</div>
				
				<!-- ACTIONS -->
				<div id="tab_actions">
			  	
			  		<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Run Feeds</h3>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									
									<p>From here you can manually run feeds for certain portals, or run a test prior to setting a portal live.</p>
									
									<?php
			  							$run_query = "SELECT id FROM " .$wpdb->prefix . "blm_export_logs_feed WHERE start_date > end_date";
										$result = $wpdb->get_row( " SELECT portal_id, start_date FROM " .$wpdb->prefix . "blm_export_logs_feed_portal WHERE start_date > end_date ORDER BY start_date DESC LIMIT 1 ");
										if( $result != null )
										{
											echo '<p>A feed is currently running. Please wait until this feed has finished before running another one.</p><p>You can see feeds currently running in the \'Statistics\' tab.</p>';
										}
										else
										{
									?>
										<p>Please select which portals you wish to run:</p>
										
										<?php
											foreach( $portals as $portal_id => $portal )
											{
												echo '<input type="checkbox"';
												if( $portal['status'] == '0' || $portal['status'] == '' )
												{
													echo ' disabled="disabled"';
												}
												echo ' name="wpp_blm_export_options[portals_to_run][]" id="portals_to_run_'.$portal_id.'" value="'.$portal_id.'"> <label for="portals_to_run_'.$portal_id.'">' . $portal['name'] . ' - ';
												echo '<span id="portal_action_status_' . $portal_id . '">';
												if( $portal['status'] == '0' || $portal['status'] == '' )
												{
													echo 'Inactive';
												}
												if( $portal['status'] == '1' )
												{
													echo 'Currently in test mode';
												}
												if( $portal['status'] == '2' )
												{
													echo 'Currently in live mode. The feed will be sent to the portal';
												}
												echo '</span></label><br />';
											}
										}
									?>
										
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Validator</h3>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<p>You can validate BLM files at the following URL:</p>
									
									<p><a href="http://biostall.com/portal-feed-rmv3-blm-online-validator-tool" target="_blank">http://biostall.com/portal-feed-rmv3-blm-online-validator-tool</a></p>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<h3>Other Resources</h3>
								</td>
							</tr>
						</tbody>
					</table>
					
					<table class="form-table">
						<tbody>
							<tr>
								<td>
									<p><a href="http://www.rightmove.co.uk/ps/pdf/guides/RightmoveDatafeedFormatV3_3_3.pdf" target="_blank">View BLM Specification</a></p>
									
									<p><a href="http://www.rightmove.co.uk/ps/pdf/guides/V3TestFile.blm" target="_blank">View Sample BLM</a></p>
								</td>
							</tr>
						</tbody>
					</table>
			  
				</div>
			</div>
	
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="wpp_blm_export_options" />
			<?php submit_button(); ?>
		</form>
		
<?php
		}
		else
		{
			echo 'WP-Property hasn\'t been configured yet. Before you can being to use this add-on you need to setup the fields in the WP-Property \'Developer\' tab.';
		}
	}
	else
	{
		echo 'The <a href="http://wordpress.org/plugins/wp-property/" target="_blank">WP-Property plugin</a> isn\'t active or hasn\'t been installed yet.';
	}
?>
	
</div>
<?php
}

function get_field_array()
{
	$field_array = array(
		
		'AGENT_REF' => array(
			'generated' => true
		),
		'ADDRESS_1' => array(
			'mandatory' => true
		),
		'ADDRESS_2' => array(
			'mandatory' => true
		),
		'TOWN' => array(
			'mandatory' => true
		),
		'POSTCODE1' => array(
			'mandatory' => true
		),
		'POSTCODE2' => array(
			'mandatory' => true
		),
		'FEATURE1' => array(
			'mandatory' => true
		),
		'FEATURE2' => array(
			'mandatory' => true
		),
		'FEATURE3' => array(
			'mandatory' => true
		),
		'FEATURE4' => array(
			'mandatory' => false
		),
		'FEATURE5' => array(
			'mandatory' => false
		),
		'FEATURE6' => array(
			'mandatory' => false
		),
		'FEATURE7' => array(
			'mandatory' => false
		),
		'FEATURE8' => array(
			'mandatory' => false
		),
		'FEATURE9' => array(
			'mandatory' => false
		),
		'FEATURE10' => array(
			'mandatory' => false
		),
		'SUMMARY' => array(
			'mandatory' => true
		),
		'DESCRIPTION' => array(
			'mandatory' => false
		),
		'BRANCH_ID' => array(
			'generated' => true
		),
		'STATUS_ID' => array(
			'mandatory' => true,
			'mapping' => array(
				"0" => array(
					"rm_label" => "Available"
				),
				"1" => array(
					"rm_label" => "SSTC (Sales only)"
				),
				"2" => array(
					"rm_label" => "SSTCM (Scottish Sales only)"
				),
				"3" => array(
					"rm_label" => "Under Offer (Sales only),"
				),
				"4" => array(
					"rm_label" => "Reserved (Sales only)"
				),
				"5" => array(
					"rm_label" => "Let Agreed (Lettings only)"
				)
			)
		),
		'BEDROOMS' => array(
			'mandatory' => true
		),
		'BATHROOMS' => array(
			'mandatory' => false
		),
		'LIVING_ROOMS' => array(
			'mandatory' => false
		),
		'PRICE' => array(
			'mandatory' => true
		),
		'PRICE_QUALIFIER' => array(
			'mandatory' => false,
			'mapping' => array(
				"0" => array(
					"rm_label" => "Default"
				),
				"1" => array(
					"rm_label" => "POA"
				),
				"2" => array(
					"rm_label" => "Guide Price"
				),
				"3" => array(
					"rm_label" => "Fixed Price"
				),
				"4" => array(
					"rm_label" => "Offers in Excess of"
				),
				"5" => array(
					"rm_label" => "OIRO"
				),
				"6" => array(
					"rm_label" => "Sale by Tender"
				),
				"7" => array(
					"rm_label" => "From"
				),
				"9" => array(
					"rm_label" => "Shared Ownership"
				),
				"10" => array(
					"rm_label" => "Offers Over"
				),
				"11" => array(
					"rm_label" => "Part Buy Part Rent"
				),
				"12" => array(
					"rm_label" => "Shared Equity"
				)
			)
		),
		'PROP_SUB_ID' => array(
			'mandatory' => true,
			'mapping' => array(
				"0" => array(
					"rm_label" => "Not Specified"
				),
				"1" => array(
					"rm_label" => "Terraced"
				),
				"2" => array(
					"rm_label" => "End of Terrace"
				),
				"3" => array(
					"rm_label" => "Semi-Detached"
				),
				"4" => array(
					"rm_label" => "Detached"
				),
				"5" => array(
					"rm_label" => "Mews"
				),
				"6" => array(
					"rm_label" => "Cluster House"
				),
				"7" => array(
					"rm_label" => "Ground Flat"
				),
				"8" => array(
					"rm_label" => "Flat"
				),
				"9" => array(
					"rm_label" => "Studio"
				),
				"10" => array(
					"rm_label" => "Ground Maisonette"
				),
				"11" => array(
					"rm_label" => "Maisonette"
				),
				"12" => array(
					"rm_label" => "Bungalow"
				),
				"13" => array(
					"rm_label" => "Terraced Bungalow"
				),
				"14" => array(
					"rm_label" => "Semi-Detached Bungalow"
				),
				"15" => array(
					"rm_label" => "Detached Bungalow"
				),
				"16" => array(
					"rm_label" => "Mobile Home"
				),
				"20" => array(
					"rm_label" => "Land"
				),
				"21" => array(
					"rm_label" => "Link Detached House"
				),
				"22" => array(
					"rm_label" => "Town House"
				),
				"23" => array(
					"rm_label" => "Cottage"
				),
				"24" => array(
					"rm_label" => "Chalet"
				),
				"27" => array(
					"rm_label" => "Villa"
				),
				"28" => array(
					"rm_label" => "Apartment"
				),
				"29" => array(
					"rm_label" => "Penthouse"
				),
				"30" => array(
					"rm_label" => "Finca"
				),
				"43" => array(
					"rm_label" => "Barn Conversion"
				),
				"44" => array(
					"rm_label" => "Serviced Apartments"
				),
				"45" => array(
					"rm_label" => "Parking"
				),
				"46" => array(
					"rm_label" => "Sheltered Housing"
				),
				"47" => array(
					"rm_label" => "Retirement Property"
				),
				"48" => array(
					"rm_label" => "House Share"
				),
				"49" => array(
					"rm_label" => "Flat Share"
				),
				"50" => array(
					"rm_label" => "Park Home"
				),
				"51" => array(
					"rm_label" => "Garages"
				),
				"52" => array(
					"rm_label" => "Farm House"
				),
				"53" => array(
					"rm_label" => "Equestrian Facility"
				),
				"56" => array(
					"rm_label" => "Duplex"
				),
				"59" => array(
					"rm_label" => "Triplex"
				)
			)
		),
		'CREATE_DATE' => array(
			'mandatory' => false
		),
		'UPDATE_DATE' => array(
			'mandatory' => false
		),
		'DISPLAY_ADDRESS' => array(
			'mandatory' => true
		),
		'PUBLISHED_FLAG' => array(
			'mandatory' => true,
			'mapping' => array(
				"0" => array(
					"rm_label" => "Hidden/invisible"
				),
				"1" => array(
					"rm_label" => "Visible"
				)
			)
		),
		'LET_DATE_AVAILABLE' => array(
			'mandatory' => false
		),
		'LET_BOND' => array(
			'mandatory' => false
		),
		'LET_TYPE_ID' => array(
			'mandatory' => false,
			'mapping' => array(
				"0" => array(
					"rm_label" => "Not Specified"
				),
				"1" => array(
					"rm_label" => "Long Term"
				),
				"2" => array(
					"rm_label" => "Short Term"
				),
				"3" => array(
					"rm_label" => "Student"
				),
				"4" => array(
					"rm_label" => "Commercial"
				)
			)
		),
		'LET_FURN_ID' => array(
			'mandatory' => false,
			'mapping' => array(
				"0" => array(
					"rm_label" => "Furnished"
				),
				"1" => array(
					"rm_label" => "Part Furnished"
				),
				"2" => array(
					"rm_label" => "Unfurnished"
				),
				"3" => array(
					"rm_label" => "Not Specified"
				),
				"4" => array(
					"rm_label" => "Furnished/Un Furnished"
				)
			)
		),
		'LET_RENT_FREQUENCY' => array(
			'mandatory' => false,
			'mapping' => array(
				"0" => array(
					"rm_label" => "Weekly"
				),
				"1" => array(
					"rm_label" => "Monthly"
				),
				"2" => array(
					"rm_label" => "Quarterly"
				),
				"3" => array(
					"rm_label" => "Annual"
				),
				"5" => array(
					"rm_label" => "Per person per week (Student Lettings only))"
				)
			)
		),
		'LET_CONTRACT_IN_MONTHS' => array(
			'mandatory' => false
		),
		'LET_WASHING_MACHINE_FLAG' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_DISHWASHER_FLAG' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_BURGLAR_ALARM_FLAG' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_BILL_INC_WATER' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_BILL_INC_GAS' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_BILL_INC_ELECTRICITY' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_BILL_INC_TV_LICENCE' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_BILL_INC_TV_SUBSCRIPTION' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'LET_BILL_INC_INTERNET' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Included"
				),
				"N" => array(
					"rm_label" => "Not Included or unknown"
				)
			)
		),
		'TENURE_TYPE_ID' => array(
			'mandatory' => false,
			'mapping' => array(
				"1" => array(
					"rm_label" => "Freehold"
				),
				"2" => array(
					"rm_label" => "Leasehold"
				),
				"3" => array(
					"rm_label" => "Feudal"
				),
				"4" => array(
					"rm_label" => "Commonhold"
				),
				"5" => array(
					"rm_label" => "Share of Freehold"
				)
			)
		),
		'TRANS_TYPE_ID' => array(
			'mandatory' => true,
			'mapping' => array(
				"1" => array(
					"rm_label" => "Resale"
				),
				"2" => array(
					"rm_label" => "Lettings"
				),
			)
		),
		'NEW_HOME_FLAG' => array(
			'mandatory' => false,
			'mapping' => array(
				"Y" => array(
					"rm_label" => "Yes"
				),
				"N" => array(
					"rm_label" => "No or unknown"
				),
			)
		)
	);
	
	for( $i = 0; $i <= 40; ++$i )
	{
		$j = $i;
		if( $i < 10 ) { $j = '0' . $i; }
		$field_array['MEDIA_IMAGE_'.$j] = array(
			'generated' => true
		);
	}
	
	$field_array['MEDIA_IMAGE_60'] = array(
		'generated' => true
	);
	$field_array['MEDIA_IMAGE_TEXT_60'] = array(
		'generated' => true
	);
	
	for( $i = 0; $i <= 1; ++$i )
	{
		$j = $i;
		if( $i < 10 ) { $j = '0' . $i; }
		$field_array['MEDIA_FLOOR_PLAN_'.$j] = array(
			'generated' => true
		);
	}

	$field_array['MEDIA_DOCUMENT_00'] = array(
		'generated' => true
	);
	
	$field_array['MEDIA_VIRTUAL_TOUR_00'] = array(
		'generated' => true
	);
	$field_array['MEDIA_VIRTUAL_TOUR_TEXT_00'] = array(
		'generated' => true
	);
	
	return $field_array;
}


add_filter( 'cron_schedules', 'wpp_blm_export_add_schedule' );
function wpp_blm_export_add_schedule( $schedules ) {
	// Adds bi-daily weekly to the existing schedules.
	$schedules['bidaily'] = array(
		'interval' => 172800,
		'display' => __( 'Every Other Day' )
	);
	return $schedules;
}

add_action('init', 'run_customcron');
function run_customcron() 
{
    if( isset($_GET['custom_cron']) )
    {
    	if( isset($_GET['portals_to_run']) && is_array($_GET['portals_to_run']) && count($_GET['portals_to_run']) > 0 )
		{
       		do_action($_GET['custom_cron'], $_GET['portals_to_run']);
		}
    }
}

function wpp_blm_export_execute_feed($portals = array()) 
{
	remove_action('pre_get_posts', array('WPP_F', 'pre_get_posts'));
	require( WPP_BLM_Export_Path . 'cron.php' );
}
add_action('wppblmexportcronhook', 'wpp_blm_export_execute_feed');

function wpp_blm_export_archive($portals = array()) 
{
	require( WPP_BLM_Export_Path . 'archive.php' );
}
add_action('wppblmexportarchive', 'wpp_blm_export_archive');

/*
 * Logs an error to $wpdb->prefix . blm_export_logs_feed_error table
 * 
 * @param $feed_portal_id (int) - The id from $wpdb->prefix . blm_export_logs_feed_portal
 * @param $severity (int) - 0 = Debug / Information Message, 1 = Critical. This error caused the feed to not be processed and therefore not sent, 2 = This error prevented a property from being included in the feed, 3 = A minor error occured but the property was still included in the feed
 * @param $message (int) - Human-readable message 
 * @param $post_id (int) - The WordPress post_id
 */
function wpp_blm_export_log_error($feed_portal_id, $severity, $message, $post_id = '') 
{
	global $wpdb;
	
	$wpdb->insert( 
		$wpdb->prefix . "blm_export_logs_feed_error", 
		array(
			'feed_portal_id' => $feed_portal_id,
			'post_id' => $post_id,
			'severity' => $severity,
			'message' => $message,
			'error_date' => date("Y-m-d H:i:s")
		)
	);
	
	if( $severity == 1 )
	{
		// This error is severe meaning no properties will be sent.
		// Delete all corresponding entries in $wpdb->prefix . blm_export_logs_feed_portal_property
		// so incremental feeds work as expected the next time the feed runs.
		
		$wpdb->delete( 
			$wpdb->prefix . "blm_export_logs_feed_portal_property", 
			array( 'feed_portal_id' => $feed_portal_id )
		);
	}
}

// Activation / Deactivation / Deletion
register_activation_hook( __FILE__, 'wpp_blm_export_activation' );
register_deactivation_hook( __FILE__, 'wpp_blm_export_deactivation' );

function wpp_blm_export_activation()
{
	// actions to perform once on plugin activation go here
	
	$current_version = get_option( 'wpp_blm_export_version') ;
	
	if ($current_version === FALSE || $current_version == '')
	{
		// this plugin has never been activated before
		$options = array();
		update_option( 'wpp_blm_export_options', $options );
		
		wpp_blm_export_create_tables();
	}
	else
	{
		if ( $current_version != WPP_BLM_Export_Version )
		{
			// Beware, it was running a different version previously.
			// Let's just double check everything is set ok and update if applicable
			wpp_blm_export_create_tables();
		}
		else
		{
			// We're just reactivating the same version. All is well...
		}
	}
	
	// always update version
	update_option( 'wpp_blm_export_version', WPP_BLM_Export_Version );
	
	// Activate cron
	$cron_frequency = 'daily';
	
	$cron_frequency_option = get_option( 'wpp_blm_export_options' );
	if( isset($cron_frequency_option['sending_frequency']) && in_array($cron_frequency_option['sending_frequency'], array('twicedaily', 'daily', 'bidaily')) ) {
		$cron_frequency = $cron_frequency_option['sending_frequency'];
	}
	
	// Clear crons - just incase there is one set
	$timestamp = wp_next_scheduled( 'wppblmexportcronhook' );
	wp_unschedule_event($timestamp, 'wppblmexportcronhook' );
	wp_clear_scheduled_hook('wppblmexportcronhook');
	
	$timestamp = wp_next_scheduled( 'wppblmexportarchive' );
	wp_unschedule_event($timestamp, 'wppblmexportarchive' );
	wp_clear_scheduled_hook('wppblmexportarchive');
	
	// Schedule cron to execute feed
	wp_schedule_event( strtotime(date("Y-m-d").' 00:00:00'), $cron_frequency, 'wppblmexportcronhook');
	
	// Schedule cron to perform archive
	wp_schedule_event( strtotime(date("Y-m-d").' 00:00:00'), 'daily', 'wppblmexportarchive');
	
	//register uninstaller
    register_uninstall_hook( __FILE__, 'wpp_blm_export_uninstall' );
}

function wpp_blm_export_create_tables()
{
	global $wpdb;
	
	// Create tables for storing statistics
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	// Create table to record individual feeds being ran
   	$table_name = $wpdb->prefix . "blm_export_logs_feed";
      
   	$sql = "CREATE TABLE $table_name (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				start_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				end_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			  	PRIMARY KEY  (id)
    		);";
	
	$table_name = $wpdb->prefix . "blm_export_logs_feed_portal";
	
	$sql .= "CREATE TABLE $table_name (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				feed_id bigint(20) UNSIGNED NOT NULL,
				portal_id bigint(20) UNSIGNED NOT NULL,
				status tinyint(1) UNSIGNED NOT NULL,
				start_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				end_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			  	PRIMARY KEY  (id)
    		);";
	
	$table_name = $wpdb->prefix . "blm_export_logs_feed_portal_property";
	
	$sql .= "CREATE TABLE $table_name (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				feed_portal_id bigint(20) UNSIGNED NOT NULL,
				post_id bigint(20) UNSIGNED NOT NULL,
				images_referenced tinyint(2) UNSIGNED NOT NULL,
				images_sent tinyint(2) UNSIGNED NOT NULL,
				epcs_referenced tinyint(2) UNSIGNED NOT NULL,
				epcs_sent tinyint(2) UNSIGNED NOT NULL,
				floorplans_referenced tinyint(2) UNSIGNED NOT NULL,
				floorplans_sent tinyint(2) UNSIGNED NOT NULL,
				brochures_referenced tinyint(2) UNSIGNED NOT NULL,
				brochures_sent tinyint(2) UNSIGNED NOT NULL,
				virtual_tours_referenced tinyint(2) UNSIGNED NOT NULL,
				virtual_tours_sent tinyint(2) UNSIGNED NOT NULL,
				send_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			  	PRIMARY KEY  (id)
    		);";
	
	$table_name = $wpdb->prefix . "blm_export_logs_feed_error";
	
	$sql .= "CREATE TABLE $table_name (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				feed_portal_id bigint(20) UNSIGNED NOT NULL,
				post_id bigint(20) UNSIGNED NOT NULL,
				severity tinyint(1) UNSIGNED NOT NULL,
				message varchar(255) NOT NULL,
				error_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			  	PRIMARY KEY  (id)
    		);";
	
	dbDelta( $sql );
	
	$last_error = $wpdb->last_error;
	if( $last_error != FALSE && $last_error != "" )
	{
		// An error occured during activating tables. Output it...
		echo 'The following error occured whilst attempting to activate the plugin. Please report it as a bug and we will fix it asap: '.$wpdb->last_error;
		die();
	}
}

function wpp_blm_export_update_db_check()
{
    if( get_site_option( 'wpp_blm_export_version' ) != WPP_BLM_Export_Version ) 
    {
        wpp_blm_export_activation();
    }
}
add_action( 'plugins_loaded', 'wpp_blm_export_update_db_check' );

function wpp_blm_export_deactivation()
{    
	// actions to perform once on plugin deactivation go here
	
	// Clear cron
	$timestamp = wp_next_scheduled( 'wppblmexportcronhook' );
	wp_unschedule_event($timestamp, 'wppblmexportcronhook' );
	wp_clear_scheduled_hook('wppblmexportcronhook');
	
	// Clear cron
	$timestamp = wp_next_scheduled( 'wppblmexportarchive' );
	wp_unschedule_event($timestamp, 'wppblmexportarchive' );
	wp_clear_scheduled_hook('wppblmexportarchive');
}

function wpp_blm_export_uninstall()
{
	global $wpdb;
	
    //actions to perform once on plugin uninstall go here
    
    // delete options
    delete_option('wpp_blm_export_options');
    delete_option('wpp_blm_export_version');
	
	// drop tables
	$table_name = $wpdb->prefix . "blm_export_logs_feed";
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	
	$table_name = $wpdb->prefix . "blm_export_logs_feed_portal";
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	
	$table_name = $wpdb->prefix . "blm_export_logs_feed_portal_property";
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	
	$table_name = $wpdb->prefix . "blm_export_logs_feed_error";
	$sql = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query($sql);
	
    // Clear cron
    $timestamp = wp_next_scheduled( 'wppblmexportcronhook' );
	wp_unschedule_event($timestamp, 'wppblmexportcronhook' );
	wp_clear_scheduled_hook('wppblmexportcronhook');
	
	// Clear cron
	$timestamp = wp_next_scheduled( 'wppblmexportarchive' );
	wp_unschedule_event($timestamp, 'wppblmexportarchive' );
	wp_clear_scheduled_hook('wppblmexportarchive');
	
    // execute hook to delete archived files
	do_action('wppblmexportarchive');
}