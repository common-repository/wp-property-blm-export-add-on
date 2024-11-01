<?php
/**
 * Executed cron - Compile BLM file and send to portals
*/

global $wpdb;

$wpdb->flush(); // Flush the cache. Don't want to risk any mix ups

ini_set( "display_errors", 1 );
error_reporting( E_ALL );
set_time_limit( 0 );

$wpp_blm_export_options = get_option("wpp_blm_export_options");

if (!isset($portals) || (isset($portals) && !is_array($portals))) { $portals = array(); }
$portals_to_execute = $portals;

// Delete logs older that WPP_BLM_Export_Keep_Logs_Days days
$wpdb->query( "DELETE FROM ".$wpdb->prefix . "blm_export_logs_feed WHERE start_date < DATE_SUB(NOW(), INTERVAL ".WPP_BLM_Export_Keep_Logs_Days." DAY)" );
$wpdb->query( "DELETE FROM ".$wpdb->prefix . "blm_export_logs_feed_portal WHERE start_date < DATE_SUB(NOW(), INTERVAL ".WPP_BLM_Export_Keep_Logs_Days." DAY)" );
$wpdb->query( "DELETE FROM ".$wpdb->prefix . "blm_export_logs_feed_portal_property WHERE send_date < DATE_SUB(NOW(), INTERVAL ".WPP_BLM_Export_Keep_Logs_Days." DAY)" );
$wpdb->query( "DELETE FROM ".$wpdb->prefix . "blm_export_logs_feed_error WHERE error_date < DATE_SUB(NOW(), INTERVAL ".WPP_BLM_Export_Keep_Logs_Days." DAY)" );

// Check WP-Property Plugin is active as we'll need this
if( in_array( 'wp-property/wp-property.php', (array) get_option( 'active_plugins', array() ) ) )
{
	if( $wpp_blm_export_options !== FALSE && $wpp_blm_export_options != "" )
	{
		// Plugin exists
		
		if( 
			( isset($wpp_blm_export_options['active']) && $wpp_blm_export_options['active'] == '1' )
			||
			count($portals_to_execute)
		)
		{
			// Portal is active, or we're running it manually for a particular portal
		
			// Make sure there's not already a feed running
			// We also check for feeds that may have fallen over (ie. ones that haven't ran in more than 12 hours)
			$feed_running = false;
			
			$result = $wpdb->get_results( "
				SELECT 
					id
				FROM 
					" .$wpdb->prefix . "blm_export_logs_feed
				WHERE 
					start_date <= end_date OR
					(
						start_date > end_date AND
						DATE_ADD(start_date, INTERVAL 12 HOUR) <= NOW()
					)
			");
			if ( $result )
			{
				
			}
			else
			{
				$feed_running = true;
				
				// Let's actually check a feed has ran before
				$result = $wpdb->get_results( " SELECT id FROM " .$wpdb->prefix . "blm_export_logs_feed ");
				if ($wpdb->num_rows == 0)
				{
					// Nope. This is the first one
					$feed_running = false;
				}
			}
			
			if ( !$feed_running )
			{
				// There are no portals currently running
				
				// Lets write a log entry for this feed
				$wpdb->insert( 
					$wpdb->prefix . "blm_export_logs_feed", 
					array( 
						'start_date' => date("Y-m-d H:i:s")
					)
				);
				$feed_id = $wpdb->insert_id;
				
				$compressed = true;
				if( isset($wpp_blm_export_options['compression']) && $wpp_blm_export_options['compression'] == '0' )
				{
					$compressed = false;
				}
				
				// Get portals
				$portals = ( isset($wpp_blm_export_options['portals']) ? $wpp_blm_export_options['portals'] : array() );
				
				$incremental = (isset($wpp_blm_export_options['incremental']) ? $wpp_blm_export_options['incremental'] : '1' );
				
				$email_report = (isset($wpp_blm_export_options['email_report']) ? $wpp_blm_export_options['email_report'] : '' );
				$email_report_to = (isset($wpp_blm_export_options['email_report_to']) ? $wpp_blm_export_options['email_report_to'] : '' );
				
				$field_array = get_field_array();
				
				$field_map = ( isset($wpp_blm_export_options['wpp_field']) ? $wpp_blm_export_options['wpp_field'] : array() );
				
				$field_mapped_values = ( isset($wpp_blm_export_options['wpp_field_map']) ? $wpp_blm_export_options['wpp_field_map'] : array() );
				
				$branch_code_use = ( isset($wpp_blm_export_options['branch_code_use']) ? $wpp_blm_export_options['branch_code_use'] : '');
				
				$field_concatenations = ( isset($wpp_blm_export_options['concats']) ? $wpp_blm_export_options['concats'] : '' );
					
				$field_postcode_parts = ( isset($wpp_blm_export_options['postcode']) ? $wpp_blm_export_options['postcode'] : '' );
				
				$archive = ( isset($wpp_blm_export_options['archive']) ? $wpp_blm_export_options['archive'] : '1' );
				
				$feed_portal_ids = array();
				
				// Loop through portals 
				foreach ($portals as $portal_id => $portal)
				{
					// Check the portal is active
					if (isset($portal['status']) && ($portal['status'] == 1 || $portal['status'] == 2))
					{
						// Yup, the feed is active.
						
						// Check we're ok to send this portal
						if (count($portals_to_execute) == 0 || in_array($portal_id, $portals_to_execute))
						{
							// Yup, all good, We need to run this portal. Let's continue...
							
							$start_time = time();
							// Lets write a log entry for this portal
							$wpdb->insert( 
								$wpdb->prefix . "blm_export_logs_feed_portal", 
								array(
									'feed_id' => $feed_id,
									'portal_id' => $portal_id,
									'status' => $portal['status'],
									'start_date' => date("Y-m-d H:i:s", $start_time)
								)
							);
							$feed_portal_id = $wpdb->insert_id;
							
							$feed_portal_ids[] = $feed_portal_id;
							
							// Log
							wpp_blm_export_log_error($feed_portal_id, 0, "Starting");
							
							// Check we have FTP details if the feed is active
							if (
								$portal['status'] == 1 ||
								(
									$portal['status'] == 2 && 
									isset($portal['ftp_host']) && trim($portal['ftp_host']) != "" && 
									isset($portal['ftp_user']) && trim($portal['ftp_user']) != "" && 
									isset($portal['ftp_pass']) && trim($portal['ftp_pass']) != ""
								)
							)
							{
								// Get the last start time from the database for this portal.
								// We'll use this when it comes to doing incremental feeds (if turned on in settings)
								$last_start_time = 0;
								if( $incremental )
								{
									$result = $wpdb->get_row( " SELECT start_date FROM " .$wpdb->prefix . "blm_export_logs_feed_portal WHERE status = 2 ORDER BY start_date DESC ");
									if ($result != null)
									{
										$last_start_time = strtotime($result->start_date);
									}
								}
								
								$wp_uploads_dir = wp_upload_dir();
								if( $wp_uploads_dir['error'] === FALSE )
								{
									$uploads_dir = $wp_uploads_dir['basedir'] . '/blm_export/' . $portal_id . '/';
									
									// Hopefully we should never get into this scenario as it should have been dealt with by the settings screen.
									// Nevertheless, let's just make sure the directory exists and at least attempt to recreate it if it doesn't
									if( ! @file_exists ($uploads_dir) )
									{
										// Log
										wpp_blm_export_log_error($feed_portal_id, 0, "The directory " . $uploads_dir . " doesn't exist. Attempting to create it.");
								
										if( ! @mkdir($uploads_dir, 0777, true))
										{
											// Log
											wpp_blm_export_log_error($feed_portal_id, 1, "Failed to create directory " . $uploads_dir . ".");
											continue;
										}
									}
									
									$blm_filename = date("YmdHis", $start_time).'.blm';
									$files_to_ftp = array();
									if ($compressed)
									{
										$zip_filename = date("YmdHis", $start_time).'.zip';
										$files_to_zip = array();
									}
									
									// Log
									wpp_blm_export_log_error($feed_portal_id, 0, "Getting properties to include in the feed");
									
									// Get the property ids to be sent using the functionality from WP-Property to ensure same results
									// Might need to confugre $args if only 10 properties for sample getting set, or ordering being applied
									//$properties = WPP_F::get_properties( $args = "", $total = false );
									$properties = WPP_F::get_searchable_properties();
									
									// Log
									wpp_blm_export_log_error($feed_portal_id, 0, "Found " . count($properties) . " properties");
									
									// Log
									wpp_blm_export_log_error($feed_portal_id, 0, "Generating BLM header");
									
									// Generate the BLM header
									$blm_header = "";
									$blm_header .= "#HEADER#" . PHP_EOL;
									$blm_header .= "Version : 3" . PHP_EOL;
									$blm_header .= "EOF : '" . WPP_BLM_Export_EOF_Character."'" . PHP_EOL;
									$blm_header .= "EOR : '" . WPP_BLM_Export_EOR_Character."'" . PHP_EOL . PHP_EOL;
									
									$blm_header .= "Property Count : ".count($properties) . PHP_EOL;
									$blm_header .= "Generated Date : ".date("j-M-Y")." ".date("H:i")."" . PHP_EOL . PHP_EOL;
									
									// Finished with the BLM header
									
									// Log
									wpp_blm_export_log_error($feed_portal_id, 0, "Generating BLM definition");
									
									// Now let's generate the definition
									$blm_definition = "#DEFINITION#" . PHP_EOL;
									
									if( count($field_array) ) {
										
										// Loop through BLM fields
										foreach($field_array as $rm_field => $field_data) {
											$blm_definition .= $rm_field . WPP_BLM_Export_EOF_Character;
										} // end foreach
										$blm_definition .= WPP_BLM_Export_EOR_Character . PHP_EOL . PHP_EOL;
										
										// Finished the definition
										
										// Log
										wpp_blm_export_log_error($feed_portal_id, 0, "Generating BLM data");
										
										// Now let's generate the data
										$blm_data = "#DATA#" . PHP_EOL;
										
										// Check we found properties
										if( count($properties) )
										{
											// We have properties to add to the file
											
											// Loop through properties and add a row to the BLM
											foreach( $properties as $property_id )
											{
												$property = get_post($property_id);
												
												$property_meta = get_post_meta($property_id);
												
												$property_images = get_children( array('post_parent' => $property_id, 'post_type' => 'attachment', 'post_mime_type' =>'image') );
												
												// Work out whether we need to send media
												// Obviously always send the media if incremental feeds are disabled
												$send_media = false;
												if( $incremental == '1' )
												{
													// Send media if the property has been modified or created, or if the feed has never been ran before in live mode
													if ( $last_start_time = 0 || strtotime($property->post_modified) > $last_start_time || strtotime($property->post_date) > $last_start_time )
													{
														$send_media = true;
													}
												}
												else
												{
													$send_media = true;
												}
												
												$features_done = 0;
												
												// Keep track of what media is being sent for logging purposes
												$images_referenced = 0;
												$images_sent = 0;
												
												$epcs_referenced = 0;
												$epcs_sent = 0;
												
												$floorplans_referenced = 0;
												$floorplans_sent = 0;
												
												$brochures_referenced = 0;
												$brochures_sent = 0;
												
												$virtual_tours_referenced = 0;
												$virtual_tours_sent = 0;
												
												// get the branch code here as we'll use it in a few places in the row
												$branch_code = '';
												if( $branch_code_use == 'single' )
												{
													// We have one branch code for the entire feed
													$branch_code = $portal['branch_code_single'];
												}
												if( $branch_code_use == 'peroffice' )
												{
													// We have a different branch code for each office. 
													// Let's get the office that this property belongs to, then the corresponding branch code
													if( isset($wpp_blm_export_options['branch_code_office_field']) && trim($wpp_blm_export_options['branch_code_office_field']) != "" )
													{
														$office_field = explode("_", $wpp_blm_export_options['branch_code_office_field'], 2);
														
														if( count($office_field) == 2 )
														{
															// Get the second bit.
															// The first bit will contain 'attr_' or 'meta_' depending on the type of field.
															$office_field = $office_field[1]; 
															
															// We now know which field holds the office
															if( isset($property_meta[$office_field][0]) && trim($property_meta[$office_field][0]) != "" )
															{
																if( isset($portal['branch_code_peroffice']) && is_array($portal['branch_code_peroffice']) )
																{
																	if( isset($portal['branch_code_peroffice'][$property_meta[$office_field][0]]) && trim($portal['branch_code_peroffice'][$property_meta[$office_field][0]]) != "" )
																	{
																		$branch_code = $portal['branch_code_peroffice'][$property_meta[$office_field][0]];
																	}
																}
															}
														}
														
														unset($office_field);
													}
												}
												
												// Loop through BLM fields
												foreach( $field_array as $rm_field => $field_data ) 
												{
													$field_value = '';
													if( !isset( $field_data['generated'] ) || ( isset( $field_data['generated'] ) && !$field_data['generated'] ) ) 
													{
														// this is a field that the user has selected a map for
														if( isset($field_map[$rm_field]) )
														{
															// Mapping does exist for this field. Now we just need to work out what mapping
															
															$perform_field_lookup = true;
															
															switch( $rm_field )
															{
																case "POSTCODE1" :
																case "POSTCODE2" : {
																	
																	if( $field_map[$rm_field] == "postcode_part" )
																	{
																		// Get the field that the user has said is the postcode
																		if( isset($wpp_blm_export_options['postcode'][$rm_field]) && trim($wpp_blm_export_options['postcode'][$rm_field]) != "" )
																		{
																			$postcode_meta_field = explode("_", $wpp_blm_export_options['postcode'][$rm_field], 2);
														
																			if( count($postcode_meta_field) == 2 )
																			{
																				// Get the second bit.
																				// The first bit will contain 'attr_' or 'meta_' depending on the type of field.
																				$postcode_meta_field = $postcode_meta_field[1]; 
																					
																				if( isset($property_meta[$postcode_meta_field][0]) && trim($property_meta[$postcode_meta_field][0]) != "" )
																				{
																					$explode_postcode = explode( " ", preg_replace( '/\s+/', ' ', trim( $property_meta[$postcode_meta_field][0]) ) );
																					if( $rm_field == "POSTCODE1" && isset($explode_postcode[0]))
																					{
																						$field_value = $explode_postcode[0];
																					}
																					if( $rm_field == "POSTCODE2" && isset($explode_postcode[1]))
																					{
																						$field_value = $explode_postcode[1];
																					}
																				}
																				
																				unset($postcode_meta_field);
																				unset($explode_postcode);
																			}
																		}
																		
																		$perform_field_lookup = false;
																	}
																	
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
																	
																	$property_features = false;
																	$community_features = false;
																	$feature_terms = array();
																	
																	switch( $field_map[$rm_field] )
																	{
																		case "1" : { // Use Features & Community Features
																			$property_features = true;
																			$community_features = true;
																			break;
																		}
																		case "2" : { // Use Features Only
																			$property_features = true;
																			break;
																		}
																		case "3" : { // Use Community Features Only
																			$community_features = true;
																			break;
																		}
																	}
																	
																	if( $property_features )
																	{
																		$property_features = get_the_terms($property_id, "property_feature");
																		
																		if( $property_features && ! is_wp_error( $property_features ) )
																		{
																			foreach( $property_features as $feature )
																			{
																				$feature_terms[] = $feature;
																			}
																		}
																		else
																		{
																			$property_features = false;
																		}
																	}
																	if( $community_features )
																	{
																		$community_features = get_the_terms($property_id, "community_feature");
																		
																		if( $community_features && ! is_wp_error( $community_features ) )
																		{
																			foreach( $community_features as $feature )
																			{
																				$feature_terms[] = $feature;
																			}
																		}
																		else
																		{
																			$community_features = false;
																		}
																	}
																	
																	if( count($feature_terms) )
																	{
																		$i = 0;
																		foreach ($feature_terms as $feature)
																		{
																			if( $i == $features_done )
																			{
																				$field_value = $feature->name;
																				
																				++$features_done;
																				break;
																			}
																			++$i;
																		}
																	}
																	
																	unset($property_features);
																	unset($community_features);
																	unset($feature_terms);
																	
																	$perform_field_lookup = false;
																	
																	break;
																}
																case "DISPLAY_ADDRESS" :
																case "DESCRIPTION" : {
																	
																	if( $field_map[$rm_field] == "concatenate" )
																	{
																		if( isset($wpp_blm_export_options['concats'][$rm_field]) && is_array($wpp_blm_export_options['concats'][$rm_field]) )
																		{
																			$final_fields_to_concatenate = array();
																			
																			$field_separator = ( ( $rm_field == "DISPLAY_ADDRESS" ) ? ', ' : '<br /><br />');
																			
																			foreach( $wpp_blm_export_options['concats'][$rm_field] as $concat_field )
																			{
																				$concat_meta_field = explode("_", $concat_field, 2);
														
																				if( count($concat_meta_field) == 2 )
																				{
																					// Get the second bit.
																					// The first bit will contain 'attr_' or 'meta_' depending on the type of field.
																					$concat_meta_field = $concat_meta_field[1];
																					
																					if( isset($property_meta[$concat_meta_field][0]) && trim($property_meta[$concat_meta_field][0]) != "" )
																					{
																						$final_fields_to_concatenate[] = $property_meta[$concat_meta_field][0];
																					}
																				}
																				
																				unset($concat_meta_field);
																			}
																			
																			if( count($final_fields_to_concatenate) )
																			{
																				$field_value = implode($field_separator, $final_fields_to_concatenate);
																			}
																			
																			unset($final_fields_to_concatenate);
																			unset($field_separator);
																		}
																		
																		$perform_field_lookup = false;
																	}
																	
																	break;
																}
																case "CREATE_DATE" :
																case "UPDATE_DATE" : {
																	
																	if( $field_map[$rm_field] == "" )
																	{
																		// Mapping has been left blank. Let's use the property created / modified date
																		$field_value = ( ( $field_map[$rm_field] == "CREATE_DATE" ) ? $property->post_date : $property->post_modified );
																		
																		$perform_field_lookup = false;
																	}
																	
																	break;
																}
																case "PUBLISHED_FLAG" : {
																	
																	if( $field_map[$rm_field] == "" )
																	{
																		// Mapping has been left blank. Let's use the property published flag (ie. it will always be '1')
																		$field_value = '1';
																		
																		$perform_field_lookup = false;
																	}
																	
																	break;
																}
																case "LET_DATE_AVAILABLE" : {
																	
																	if( $field_map[$rm_field] != "" && strlen(trim($field_map[$rm_field])) == 10 )
																	{
																		// Mapping has been left blank. Let's use the property published flag (ie. it will always be '1')
																		$field_value = date("Y-m-d H:i:s", strtotime(trim($field_map[$rm_field])));
																		
																		$perform_field_lookup = false;
																	}
																	
																	break;
																}
																case "TRANS_TYPE_ID" : {
																	
																	if( $field_map[$rm_field] == "1" || $field_map[$rm_field] == "2" )
																	{
																		// Only deal with resale or lettings properties
																		// Therefore just always send it as '1' (Resale) or '2' (Lettings)
																		$field_value = $field_map[$rm_field];
																		
																		$perform_field_lookup = false;
																	}
																	
																	break;
																}
															}
															if( $perform_field_lookup )
															{
																// This field hasn't been dealt with yet and is a standard field lookup
																
																// Standard field. Get from property meta
																	
																if( isset($field_map[$rm_field]) && trim($field_map[$rm_field]) != "" )
																{
																	$property_meta_field = explode("_", $field_map[$rm_field], 2);
													
																	if( count($property_meta_field) == 2 )
																	{
																		// Get the second bit.
																		// The first bit will contain 'attr_' or 'meta_' depending on the type of field.
																		$property_meta_field = $property_meta_field[1];
																		
																		if( isset($property_meta[$property_meta_field][0]) && trim($property_meta[$property_meta_field][0]) != "" )
																		{
																			if( isset($field_data['mapping']) && is_array($field_data['mapping']) && count($field_data['mapping']) )
																			{
																				// This is a field that contains mapped values. Bit more complicated but let's give it a go...
																				
																				if( isset($field_mapped_values[$rm_field]) && is_array($field_mapped_values[$rm_field]) )
																				{
																					// Ok, good. A mapping has been set.
																					
																					$temp_field_value = $property_meta[$property_meta_field][0];
																					
																					// Now we've got the value from property_meta, see how this relates to the BLM value
																					foreach( $field_data['mapping'] as $rm_value => $mapping )
																					{
																						if( isset($field_mapped_values[$rm_field][$rm_value]) && in_array($temp_field_value, $field_mapped_values[$rm_field][$rm_value]) )
																						{
																							$field_value = $rm_value;
																						}
																					}
																					
																					unset($temp_field_value);
																				}
																				
																			}
																			else
																			{
																				$field_value = $property_meta[$property_meta_field][0];
																			}
																		}
																		
																	}
		
																	unset($property_meta_field);
																}
															}
														}
													}
													else
													{
														// this is a field that we need to generate ourselves
														switch ($rm_field)
														{
															case "AGENT_REF": {
																$field_value = $branch_code.'_'.$property_id;
																break;
															}
															case "BRANCH_ID": {
																$field_value = $branch_code;
																break;
															}
															default: {
																
															}
														}
														
														// Images
														if( substr($rm_field, 0, 12) == "MEDIA_IMAGE_" && strlen($rm_field) == 14 )
														{
															$this_image = str_replace("MEDIA_IMAGE_", "", $rm_field);
															
															if( count($property_images) > 0 )
															{
																$i = 0;
																foreach( $property_images as $attachment_id => $attachment ) 
																{
															    	if( $i == $this_image )
															    	{
															    		$image = wp_get_attachment_image_src( $attachment_id, 'large' );
																		
																		if ($image !== FALSE)
																		{
																			// We found an image
																			$image_src = $image[0];
																			
																			$ext = explode(".", $image_src);
																			$ext = $ext[(count($ext) - 1)];
																			
																			$new_name = $branch_code . '_' . $property_id . '_IMG_' . $this_image . '.' . $ext;
																			
																			if( $send_media )
																			{
																				$image_path = str_replace($wp_uploads_dir['baseurl'], $wp_uploads_dir['basedir'], $image_src);
																				
																				if( $compressed )
																				{
																					$files_to_zip[] = array(
																						"src" => $image_path,
																						"dest" => $new_name
																					);
																				}
																				else
																				{
																					$files_to_ftp[] = array(
																						"src" => $image_path,
																						"dest" => $new_name
																					);
																				}
																				
																				++$images_sent;
																			}
																			
																			$field_value = $new_name;
																			
																			++$images_referenced;
																			
																			unset($image_src);
																			unset($ext);
																			unset($new_name);
																		}
																		break;
																	}
																	++$i;
															   	}
															   }
														}
													}	
													
													// Replace out EOR and EOF characters from the field value as this will cause the BLM to be invalid
													$field_value = str_replace(array(WPP_BLM_Export_EOF_Character, WPP_BLM_Export_EOR_Character), " ", $field_value);
													
													// Add the field to the BLM content
													$blm_data .= $field_value . WPP_BLM_Export_EOF_Character;
													
												} // end foreach
												
												// Write this entry to the database
												// This will be particularly important when it comes to doing incremental feeds so we know when the property was last sent
												$wpdb->insert( 
													$wpdb->prefix . "blm_export_logs_feed_portal_property", 
													array(
														'feed_portal_id' => $feed_portal_id,
														'post_id' => $property_id,
														'images_referenced' => $images_referenced,
														'images_sent' => $images_sent,
														'epcs_referenced' => $epcs_referenced,
														'epcs_sent' => $epcs_sent,
														'floorplans_referenced' => $floorplans_referenced,
														'floorplans_sent' => $floorplans_sent,
														'brochures_referenced' => $brochures_referenced,
														'brochures_sent' => $brochures_sent,
														'virtual_tours_referenced' => $virtual_tours_referenced,
														'virtual_tours_sent' => $virtual_tours_sent,
														'send_date' => date("Y-m-d H:i:s")
													)
												);
												
												$blm_data .= WPP_BLM_Export_EOR_Character . PHP_EOL;
												
											} // end foreach
										}
										
										$blm_data .= "#END#";
										
										$blm_contents = $blm_header . $blm_definition . $blm_data;
										
										unset($blm_header);
										unset($blm_definition);
										unset($blm_data);
										
										//Write BLM contents to file
										$blm_handle = fopen($uploads_dir . $blm_filename, "w+");
										fwrite($blm_handle, $blm_contents);
										fclose($blm_handle);
										
										unset($blm_contents);
										
										// Do we need to zip?
										if( $compressed )
										{
											$files_to_zip[] = array(
												"src" => $uploads_dir . $blm_filename,
												"dest" => $blm_filename
											);
										}
										else
										{
											$files_to_ftp[] = array(
												"src" => $uploads_dir . $blm_filename,
												"dest" => $blm_filename
											);
										}
										
										if( $compressed && count($files_to_zip) )
										{
											// Log
											wpp_blm_export_log_error($feed_portal_id, 0, "Putting everything into a ZIP file");
											
											// Let's put everything into a zip
											$zip = new ZipArchive;
											
											$res = $zip->open( $uploads_dir . $zip_filename, ZipArchive::CREATE );
											if( $res === TRUE )
											{
												// Loop through all the files and add them to the zip
											    foreach ($files_to_zip as $file_to_zip)
												{
													$zip->addFile($file_to_zip['src'], $file_to_zip['dest']);
												}
												
												$zip->close(); // Close the zip
												
												// Add the successfully created zip to the list of files to end via FTP
												$files_to_ftp[] = array(
													"src" => $uploads_dir . $zip_filename,
													"dest" => $zip_filename
												);
											}
											else
											{
												// Failed to open/create zip
												
											    // Log error
											    wpp_blm_export_log_error($feed_portal_id, 1, "Failed to open / create ZIP file with name " . $uploads_dir . $zip_filename . ": " . $res);
											}
										}
										
										// All done and ready to FTP the files up. First let's check we're not in testing mode
										if( $portal['status'] == 2 )
										{
											// Ok. We are live and good to go. Let's get punching files up to the server...
											
											if( count($files_to_ftp) )
											{
												// Log
												wpp_blm_export_log_error($feed_portal_id, 0, "Connecting to FTP server");
											
												// Connect to FTP
												
												$ftp_connect = @ftp_connect( trim( $portal['ftp_host'] ) );
												
												if( ! $ftp_connect ) 
												{
													// Log error
											    	wpp_blm_export_log_error($feed_portal_id, 1, "Failed to connect to FTP server using hostname '" . $portal['ftp_host'] . "'. Please ensure this information is correct.");
												}
												else
												{
													if( ! @ftp_login( $ftp_connect, trim( $portal['ftp_user'] ), trim( $portal['ftp_pass'] ) ) ) 
													{
														// Log error
											    		wpp_blm_export_log_error($feed_portal_id, 1, "Failed to login to FTP server using user '" . $portal['ftp_user'] . "'. Please ensure the username and password are correct.");
													}
													else
													{
														// Change directory we need to
														$continue_ftp = true;
														if( isset($portal['ftp_dir']) && trim( $portal['ftp_dir'] ) != "" )
														{
															if( @ftp_chdir( $ftp_connect, trim( $portal['ftp_dir'] ) ) ) 
															{
																// Changed DTP directory successfully
															}
															else
															{
																// Log error
											    				wpp_blm_export_log_error($feed_portal_id, 1, "Failed to change FTP directory to '" . $portal['ftp_dir'] . "'. Please ensure this directory exists.");
																
																$continue_ftp = false;
															}
														}
														
														if( $continue_ftp )
														{
															@ftp_pasv( $ftp_connect, true );
															
															// Log
															wpp_blm_export_log_error($feed_portal_id, 0, "Uploading " . count($files_to_ftp) . " files to FTP server");
															
															// We're now connected via FTP successfully. Let's upload all the files
															foreach( $files_to_ftp as $file_to_ftp )
															{
																if( ! @ftp_put( $ftp_connect, $file_to_ftp['dest'], $file_to_ftp['src'], ( (isset($file_to_ftp['mode'])) ? $file_to_ftp['mode'] : FTP_BINARY) ) )
																{
																	// Whoops. FTP failed.
																	
																	// Log error
																	$severity = 3;
																	if( strpos(strtolower($file_to_ftp['src']), ".blm") !== FALSE || strpos(strtolower($file_to_ftp['src']), ".zip") )
																	{
																		$severity = 1;
																	}
											    					wpp_blm_export_log_error($feed_portal_id, $severity, "Failed to upload file '" . $portal['ftp_dir'] . "' via FTP.");
																	unset($severity);
																}
															} //End foreach file to FTP
														}
													}
													
													ftp_close($ftp_connect);
												}

												unset($files_to_ftp);
												if ($compressed)
												{
													unset($files_to_zip);
												}
											}
										}

										if( $archive == '' )
										{
											// It's been requested we don't archive any files. Ok then.. let's delete the files we created
											unlink($uploads_dir . $blm_filename);
											if ($compressed) unlink($uploads_dir . $zip_filename);
										}
									}
								}
								else
								{
									// Error when calling wp_upload_dir(). Probably an error creating it (ie. permissions)
									
									// Log error
									wpp_blm_export_log_error($feed_portal_id, 1, "An error occured whilst trying to generate the uploads directory: " . $wp_uploads_dir['error'] . " Please ensure the correct permissions are set");
								}
							}
							else
							{
								// Log error
								wpp_blm_export_log_error($feed_portal_id, 1, "Portal is active but no FTP details have been supplied");
									
							}
							
							// Log
							wpp_blm_export_log_error($feed_portal_id, 0, "Finished");
							
							// Lets write a log entry for this feed
							$wpdb->update( 
								$wpdb->prefix . "blm_export_logs_feed_portal", 
								array( 
									'end_date' => date("Y-m-d H:i:s")
								),
								array( 'id' => $feed_portal_id )
							);
						}
					}
					else
					{
						// Portal is inactive
						
					}
					
					if( count($portals) > 1 ) sleep(10); // And breath...
					
				} // end foreach portal
				
				// Lets write a log entry for this feed
				$wpdb->update( 
					$wpdb->prefix . "blm_export_logs_feed", 
					array( 
						'end_date' => date("Y-m-d H:i:s")
					),
					array( 'id' => $feed_id )
				);
				
				// Send email report
				if( ( $email_report == '1' || $email_report == '2' ) && trim($email_report_to) != "" && count($feed_portal_ids) )
				{
					$send_report = false;
					
					if( $email_report == '2' )
					{
						// Everytime a feed finishes
						$send_report = true;
					}
					if( $email_report == '1' )
					{
						// only when errors occur
						
						// Check if any serious errors existed
						$result = $wpdb->get_row( "SELECT start_date FROM " .$wpdb->prefix . "blm_export_logs_feed_error WHERE id IN (" . implode(",", $feed_portal_ids) . ") AND severity <> 0");
						if ($result != null)
						{
							// Yup, we found errors
							$send_report = true;
						}
					}
					
					if( $send_report )
					{
						$email_body = "";
						foreach( $feed_portal_ids as $feed_portal_id )
						{
							// Get the portal name
							$report_portal_name = '(unknown)';
							
							$result = $wpdb->get_row( "SELECT portal_id FROM " .$wpdb->prefix . "blm_export_logs_feed_portal WHERE id = ".$feed_portal_id );
							if ($result != null)
							{
								foreach( $portals as $portal_id => $portal )
								{
									if( $result->portal_id == $portal_id )
									{
										$report_portal_name = $portal['name'];
										break;
									}
								}
							}
							
							$email_body .= $report_portal_name;
							$email_body .= PHP_EOL;
							$email_body .= "----------";
							$email_body .= PHP_EOL . PHP_EOL;
							
							// Collate all the errors
							$results = $wpdb->get_results( "SELECT * FROM " .$wpdb->prefix . "blm_export_logs_feed_error WHERE feed_portal_id = ".$feed_portal_id." ORDER BY error_date ASC, id ASC" );
							if( $wpdb->num_rows > 0 )
							{
								foreach ($results as $result)
								{
									$email_body .= $result->error_date . " - " . $result->message;
									$email_body .= PHP_EOL;
								}
							}
							
							$email_body .= PHP_EOL . PHP_EOL . PHP_EOL;
							
							unset($report_portal_name);
						}
						
						if( $email_body != "" )
						{
							wp_mail( $email_report_to, "Wordpress BLM Export Report", $email_body );
						}
					}
					
					unset($send_report);
					unset($email_report);
					unset($email_report_to);
					unset($feed_portal_ids);
				}
			}
			else
			{
				// There is already a feed running. Don't do anything for now
				
			}
		
		}
		else
		{
			// Feed isn't set to run automatically
			
		}
	}
	else
	{
		// No options exist
		
	}
}
else
{
	// WP-Property plugin isn't active
	
}
