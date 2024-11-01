jQuery(document).ready(function()
{
	jQuery('.tooltips').tooltip();
	
	//if( document.location.hash != '' && jQuery( document.location.hash ).length > 0 ) {
		jQuery("#wpp_blm_export_settings_tabs").tabs();
	//} else {
	//	jQuery("#wpp_blm_export_settings_tabs").tabs({ cookie: {  name: 'wpp_blm_export_settings_tabs', expires: 30 } });
	//}
	
	jQuery('#active').change(function()
	{
		jQuery('#sending_frequency_row').toggle();
	});
	
	jQuery('#email_report').change(function()
	{
		if (jQuery(this).val() == 1 || jQuery(this).val() == 2)
		{
			jQuery('#email_report_to_row').show();
		}
		else
		{
			jQuery('#email_report_to_row').hide();
		}
	});
	
	jQuery('input[name=\'wpp_blm_export_options[branch_code_use]\']').change(function()
	{
		if (jQuery(this).val() == "single")
		{
			jQuery('.branch-codes-peroffice-table').fadeOut('fast', function()
			{
				jQuery('.branch-codes-single-table').fadeIn('fast');
			});
			jQuery('#branch_codes_peroffice_row').fadeOut('fast');
		}
		if (jQuery(this).val() == "peroffice")
		{
			jQuery('.branch-codes-single-table').fadeOut('fast', function()
			{
				jQuery('.branch-codes-peroffice-table').fadeIn('fast');
			});
			jQuery('#branch_codes_peroffice_row').fadeIn('fast');
		}
	});
	
	jQuery('a.add-portal').click(function()
	{
		var num_portals = jQuery('input[id^=\'portal_name_\']').length;
		
		add_portal_row(num_portals);
		
		return false;
	});
	
	jQuery('body').on('click', 'a.remove-portal', function()
	{
		var confirmBox = confirm("Are you sure you want to remove this portal?\n\nBeware: This is irreversible!! All associated logs and previously generated feeds will be deleted too");
		
		if (confirmBox)
		{
			var portal_id = jQuery(this).attr("id").replace("remove_portal_", "");
			
			jQuery('#portal_details_' + portal_id).fadeOut('fast', function()
			{
				jQuery('#portal_details_' + portal_id).remove();
			});
			
			var num_portals_remaining = jQuery('input[id^=\'portal_name_\']').length;
			
			if (num_portals_remaining == 0)
			{
				jQuery('#portals_none').fadeIn('fast');
			}
		}
		
		return false;
	});
	
	jQuery('#branch_code_office_field').change(function()
	{
		// Loop through all portals and call function below
		jQuery('input[id^=\'portal_name_\']').each(function(i)
		{
			var portal_id = jQuery(this).attr("id").replace("portal_name_", "");
			
			load_branch_code_mapping_fields(portal_id);
		});
	});
	
	jQuery('body').on('change', 'select[id^=\'portal_status_\']', function()
	{
		var portal_id = jQuery(this).attr("id").replace("portal_status_", "");
		
		if (jQuery(this).val() == 0 || jQuery(this).val() == "")
		{
			jQuery('#portal_action_status_' + portal_id).html("Inactive");
			jQuery('#portals_to_run_' + portal_id).attr("disabled", "disabled");
		}
		if (jQuery(this).val() == 1)
		{
			jQuery('#portal_action_status_' + portal_id).html("Currently in test mode");
			jQuery('#portals_to_run_' + portal_id).attr("disabled", false);
		}
		if (jQuery(this).val() == 2)
		{
			jQuery('#portal_action_status_' + portal_id).html("Currently in live mode. The feed will be sent to the portal");
			jQuery('#portals_to_run_' + portal_id).attr("disabled", false);
		}
	});
	
	jQuery('select[name^=\'wpp_blm_export_options[wpp_field]\']').change(function()
	{
		var rm_field = jQuery(this).attr("id").replace("wpp_field_", "");
		
		var continue_on = true;
		
		if (
			(rm_field == 'PUBLISHED_FLAG' && jQuery(this).val() == "") || 
			(rm_field == 'TRANS_TYPE_ID' && (jQuery(this).val() == "1" || jQuery(this).val() == "2")) || 
			(jQuery(this).val() == "concatenate") ||
			(jQuery(this).val() == "postcode_part")
		)
		{
			continue_on = false;
		}
		
		if (continue_on)
		{
			jQuery('#concatenate_fields_' + rm_field).hide();
			jQuery('#postcode_field_' + rm_field).hide();
			
			var this_predefined_values = new Array();
			if (typeof predefined_values[jQuery(this).val()] != 'undefined')
			{
				this_predefined_values = predefined_values[jQuery(this).val()].split(",");
			}
			
			load_wpp_field_map(rm_field, this_predefined_values);
		}
		else
		{
			jQuery('#wpp_field_map_' + rm_field + '_yes_toggle').fadeOut('fast');
			jQuery('#wpp_field_map_' + rm_field + '_yes').fadeOut('fast');
			jQuery('#wpp_field_map_' + rm_field + '_no').fadeOut('fast');
			jQuery('#wpp_field_map_' + rm_field + '_na').fadeOut('fast');
			
			if (jQuery(this).val() == "concatenate")
			{
				jQuery('#concatenate_fields_' + rm_field).show();
				
				var num_existing_selects = jQuery('select[name=\'wpp_blm_export_options[concats][' + rm_field + '][]\']').length;
				if (num_existing_selects == 0)
				{
					// There are no fields already stated. Let's add one for starters
					jQuery('#add_concatenate_field_' + rm_field).trigger('click');
				}
			}
			if (jQuery(this).val() == "postcode_part")
			{
				jQuery('#postcode_field_' + rm_field).show();
			}
		}
	});
	
	jQuery('a[id^=\'add_concatenate_field_\']').click(function()
	{
		var rm_field = jQuery(this).attr("id").replace("add_concatenate_field_", "");
		
		var num_existing_selects = jQuery('select[name=\'wpp_blm_export_options[concats][' + rm_field + '][]\']').length;
		
		var new_select = new jQuery('<select>')
		new_select.attr("name", "wpp_blm_export_options[concats][" + rm_field + "][]");
		new_select.attr("id", "concats_" + rm_field + '_' + (num_existing_selects + 1));
		new_select.css("width", "170px");
		
		var new_option = new jQuery('<option>');
		new_option.text('');
		new_option.val('');
		new_select.append(new_option);
		
		if (typeof concat_dropdown_options_attr[rm_field] != 'undefined')
		{
			for (var i in concat_dropdown_options_attr[rm_field])
			{
				var new_option = new jQuery('<option>');
				new_option.text(concat_dropdown_options_attr[rm_field][i]);
				new_option.val('attr_' + i);
				new_select.append(new_option);
			}
		}
		
		if (typeof concat_dropdown_options_meta[rm_field] != 'undefined')
		{
			for (var i in concat_dropdown_options_meta[rm_field])
			{
				var new_option = new jQuery('<option>');
				new_option.text(concat_dropdown_options_meta[rm_field][i]);
				new_option.val('meta_' + i);
				new_select.append(new_option);
			}
		}
		
		var remove_link = new jQuery('<a>');
		remove_link.attr("href", "#");
		remove_link.attr("id", 'remove_concatenate_field_' + rm_field + '_' + ( num_existing_selects + 1) );
		remove_link.html("-");
		
		new_select.after(" ");
		
		new_select.after(remove_link);
		
		jQuery(this).before(new_select);
		
		jQuery(this).before( new jQuery('<br>') );
		
		return false;
	});
	
	jQuery('body').on('click', 'a[id^=\'remove_concatenate_field_\']', function()
	{
		var rm_field = jQuery(this).attr("id").replace("remove_concatenate_field_", "");
		
		jQuery('#concats_' + rm_field).fadeOut('fast', function()
		{
			jQuery('#concats_' + rm_field).remove();
		});
		jQuery(this).next('br').fadeOut('fast', function()
		{
			jQuery(this).next('br').remove();
		});
		jQuery(this).fadeOut('fast', function()
		{
			jQuery(this).remove();
		});
		return false;			
	});
	
	jQuery('#statistics_feed_portal').change(function()
	{
		var feed_portal_id = jQuery(this).val();
		
		load_statistics_grid(feed_portal_id);
	});
	
	jQuery('body').on('click', '.jqmPropertiesAnchor', function()
	{
		var feed_portal_id = jQuery(this).attr("id").replace("jqmAnchor-", "");
		
		var this_statistics = statistics[feed_portal_id];
		
		var dialogHTML = '';
		for( var i in this_statistics.properties )
		{
			dialogHTML += this_statistics.properties[i].post_title + "<br />";
		}
		
		jQuery('#dialog').html(dialogHTML);
		jQuery('#dialog').jqmShow();
		
		return false;
	});
	
	jQuery('body').on('click', '.jqmLogAnchor', function()
	{
		var feed_portal_id = jQuery(this).attr("id").replace("jqmAnchor-", "");
		
		var this_statistics = statistics[feed_portal_id];
		
		var dialogHTML = '<div style="height:400px; overflow:auto">';
		for( var i in this_statistics.log )
		{
			dialogHTML += this_statistics.log[i].error_date + ' - ' + this_statistics.log[i].message + "<br />";
		}
		dialogHTML += '</div>';
		
		jQuery('#dialog').html(dialogHTML);
		jQuery('#dialog').jqmShow();
		
		return false;
	});
	
	// load portals
	for (var i in portals)
	{
		add_portal_row(i);
	}
	
	load_branch_code_mapping_fields();
	
	load_wpp_field_maps();
	
	load_statistics_grid('');
});

function add_portal_row(portal_id)
{
	var new_portal_details_div = new jQuery('<div>');
	new_portal_details_div.attr("id", "portal_details_" + portal_id);
	new_portal_details_div.css("display", "none");
	
	var portal_html = '		<table class="form-table">';
	portal_html += '			<tbody>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">Portal #' + portal_id + ' Name</th>';
	portal_html += '					<td>';
	portal_html += '						<input type="text" name="wpp_blm_export_options[portals][' + portal_id + '][name]" id="portal_name_' + portal_id + '" value="';
	if (typeof portals[portal_id] != 'undefined') 
	{
		portal_html += portals[portal_id].name;
	}
	portal_html += '">';
	portal_html += '					<a href="#" class="remove-portal" id="remove_portal_' + portal_id + '">-</a></td>';
	portal_html += '				</tr>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">Status</th>';
	portal_html += '					<td>';
	portal_html += '						<select name="wpp_blm_export_options[portals][' + portal_id + '][status]" id="portal_status_' + portal_id + '">';
	portal_html += '							<option value="0"';
	if (typeof portals[portal_id] != 'undefined' && typeof portals[portal_id].status != 'undefined' && parseInt(portals[portal_id].status) == 0) 
	{
		portal_html += ' selected="selected"';
	}
	portal_html += '>Inactive</option>';
	portal_html += '							<option value="1"';
	if (typeof portals[portal_id] != 'undefined' && typeof portals[portal_id].status != 'undefined' && parseInt(portals[portal_id].status) == 1) 
	{
		portal_html += ' selected="selected"';
	}
	portal_html += '>Testing Mode</option>';
	portal_html += '							<option value="2"';
	if (typeof portals[portal_id] != 'undefined' && typeof portals[portal_id].status != 'undefined' && parseInt(portals[portal_id].status) == 2) 
	{
		portal_html += ' selected="selected"';
	}
	portal_html += '>Live Mode</option>';
	portal_html += '						</select>';
	portal_html += '					</td>';
	portal_html += '				</tr>';
	portal_html += '			</tbody>';
	portal_html += '		</table>';
	portal_html += '		<table class="form-table branch-codes-single-table">';
	portal_html += '			<tbody>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">Branch Code</th>';
	portal_html += '					<td>';
	portal_html += '						<input type="text" name="wpp_blm_export_options[portals][' + portal_id + '][branch_code_single]" id="branch_code_single_' + portal_id + '" value="';
	if (typeof portals[portal_id] != 'undefined') 
	{
		portal_html += portals[portal_id].branch_code_single;
	}
	portal_html += '">';
	portal_html += '					</td>';
	portal_html += '				</tr>';
	portal_html += '			</tbody>';
	portal_html += '		</table>';
	portal_html += '		<table class="form-table branch-codes-peroffice-table">';
	portal_html += '			<tbody>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">Branch Codes</th>';
	portal_html += '					<td>';
	portal_html += '						<div id="branch_code_mapping_' + portal_id + '">Please select which fields relates to offices in WP-Property</div>';
	portal_html += '					</td>';
	portal_html += '				</tr>';
	portal_html += '			</tbody>';
	portal_html += '		</table>';
	
	portal_html += '		<table class="form-table">';
	portal_html += '			<tbody>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">FTP Host</th>';
	portal_html += '					<td>';
	portal_html += '						<input type="text" name="wpp_blm_export_options[portals][' + portal_id + '][ftp_host]" id="ftp_host_' + portal_id + '" value="';
	if (typeof portals[portal_id] != 'undefined' && typeof portals[portal_id].ftp_host != 'undefined') 
	{
		portal_html += portals[portal_id].ftp_host;
	}
	portal_html += '">';
	portal_html += '					</td>';
	portal_html += '				</tr>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">FTP Username</th>';
	portal_html += '					<td>';
	portal_html += '						<input type="text" name="wpp_blm_export_options[portals][' + portal_id + '][ftp_user]" id="ftp_user_' + portal_id + '" value="';
	if (typeof portals[portal_id] != 'undefined' && typeof portals[portal_id].ftp_user != 'undefined') 
	{
		portal_html += portals[portal_id].ftp_user;
	}
	portal_html += '">';
	portal_html += '					</td>';
	portal_html += '				</tr>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">FTP Password</th>';
	portal_html += '					<td>';
	portal_html += '						<input type="text" name="wpp_blm_export_options[portals][' + portal_id + '][ftp_pass]" id="ftp_pass_' + portal_id + '" value="';
	if (typeof portals[portal_id] != 'undefined' && typeof portals[portal_id].ftp_pass != 'undefined') 
	{
		portal_html += portals[portal_id].ftp_pass;
	}
	portal_html += '">';
	portal_html += '					</td>';
	portal_html += '				</tr>';
	portal_html += '				<tr>';
	portal_html += '					<th scope="row">FTP Directory</th>';
	portal_html += '					<td>';
	portal_html += '						<input type="text" name="wpp_blm_export_options[portals][' + portal_id + '][ftp_dir]" id="ftp_dir_' + portal_id + '" value="';
	if (typeof portals[portal_id] != 'undefined' && typeof portals[portal_id].ftp_dir != 'undefined') 
	{
		portal_html += portals[portal_id].ftp_dir;
	}
	portal_html += '">';
	portal_html += '					</td>';
	portal_html += '				</tr>';
	portal_html += '			</tbody>';
	portal_html += '		</table>';
	
	portal_html += '		<div style="height:1px; width:300px; margin:22px 0 19px 13px; background-color:#CCC; border-top:1px solid #FFF"></div>';
	
	new_portal_details_div.append(portal_html);
	
	jQuery('#portals').append(new_portal_details_div);
	jQuery('#portal_details_' + portal_id).fadeIn('fast', function()
	{
		jQuery('#portal_name_' + portal_id).focus();
	});
	
	if (jQuery('input[name=\'wpp_blm_export_options[branch_code_use]\']:checked').val() == "single")
	{
		jQuery('#portal_details_' + portal_id + ' .branch-codes-peroffice-table').hide();
		jQuery('#portal_details_' + portal_id + ' .branch-codes-single-table').show();
	}
	if (jQuery('input[name=\'wpp_blm_export_options[branch_code_use]\']:checked').val() == "peroffice")
	{
		jQuery('#portal_details_' + portal_id + ' .branch-codes-peroffice-table').show();
		jQuery('#portal_details_' + portal_id + ' .branch-codes-single-table').hide();
	}
	
	load_branch_code_mapping_fields(portal_id);
	
	jQuery('#portals_none').hide();
}

function load_wpp_field_maps()
{
	jQuery('select[name^=\'wpp_blm_export_options[wpp_field]\']').each(function(i)
	{
		var rm_field = jQuery(this).attr("id").replace("wpp_field_", "");
		
		var continue_on = true;
		
		if (
			(rm_field == 'PUBLISHED_FLAG' && jQuery(this).val() == "") || 
			(rm_field == 'TRANS_TYPE_ID' && (jQuery(this).val() == "1" || jQuery(this).val() == "2")) || 
			(jQuery(this).val() == "concatenate") ||
			(jQuery(this).val() == "postcode_part")
		)
		{
			continue_on = false;
		}
		
		if (continue_on)
		{
			jQuery('#concatenate_fields_' + rm_field).hide();
			jQuery('#postcode_field_' + rm_field).hide();
			
			var this_predefined_values = new Array();
			if (typeof predefined_values[jQuery(this).val()] != 'undefined')
			{
				this_predefined_values = predefined_values[jQuery(this).val()].split(",");
			}
			
			load_wpp_field_map(rm_field, this_predefined_values);
		}
		else
		{
			jQuery('#wpp_field_map_' + rm_field + '_yes_toggle').fadeOut('fast');
			jQuery('#wpp_field_map_' + rm_field + '_yes').fadeOut('fast');
			jQuery('#wpp_field_map_' + rm_field + '_no').fadeOut('fast');
			jQuery('#wpp_field_map_' + rm_field + '_na').fadeOut('fast');
			
			if (jQuery(this).val() == "concatenate")
			{
				jQuery('#concatenate_fields_' + rm_field).show();
			}
			if (jQuery(this).val() == "postcode_part")
			{
				jQuery('#postcode_field_' + rm_field).show();
			}
		}
	});
}

function load_branch_code_mapping_fields(portal_id)
{
	jQuery('#branch_code_mapping_' + portal_id).html(""); // empty the mapping div
		
	var newHTML = '';
	
	if (jQuery('#branch_code_office_field').val() != '')
	{
		if (typeof predefined_values[jQuery('#branch_code_office_field').val()] != 'undefined')
		{
			if (predefined_values[jQuery('#branch_code_office_field').val()] != "")
			{
				var values = predefined_values[jQuery('#branch_code_office_field').val()].split(",");
				
				for (var i in values)
				{
					newHTML += '<label style="float:left; padding-top:5px; min-width:120px;">' + values[i] + ':</label> <input type="text" name="wpp_blm_export_options[portals][' + portal_id + '][branch_code_peroffice][' + values[i] + ']" id="" value="';
					
					if (typeof portals[portal_id] != 'undefined') 
					{
						if (typeof portals[portal_id].branch_code_peroffice != 'undefined')
						{
							for (j in portals[portal_id].branch_code_peroffice)
							{
								if (j == values[i])
								{
									newHTML += portals[portal_id].branch_code_peroffice[j];
								}
							}
						}
					}
					
					newHTML += '"><br />';
				}
			}
			else
			{
				newHTML += 'No predetermined values exist for the field selected';
			}
		}
		else
		{
			newHTML += 'No predetermined values found for the field selected';
		}
	}
	else
	{
		newHTML += 'Please select which fields relates to offices in WP-Property';
	}
	
	jQuery('#branch_code_mapping_' + portal_id).html(newHTML);
}

function load_wpp_field_map(rm_field, this_predefined_values)
{
	// get all the mapping select's related to this field type
	jQuery('select[name^=\'wpp_blm_export_options[wpp_field_map][' + rm_field + ']\']').each(function(i)
	{
		jQuery(this).html("");
		
		var rm_value = jQuery(this).attr("id").replace("wpp_field_map_" + rm_field + "_" , "");
		
		var new_option = new jQuery('<option>');
		new_option.html("(blank)");
		new_option.val("");
		new_option.attr("selected", false);
		
		// Loop through previously mapped values
		if (typeof wpp_field_map[rm_field] != 'undefined')
		{
			wpp_field_map_temp = wpp_field_map[rm_field];
			
			if (typeof wpp_field_map_temp[rm_value] != 'undefined')
			{
				wpp_field_map_temp = wpp_field_map_temp[rm_value];
				for (var j in wpp_field_map_temp)
				{
					if (wpp_field_map_temp[j] == "")
					{
						new_option.attr("selected", "selected");
					}
				}
			}
		}
	
		jQuery(this).append(new_option);
		
		// loop through predefined values for the chosen field 
		var num_done = 0;
		for (var i in this_predefined_values)
		{
			if (this_predefined_values[i] != "")
			{
				// Loop through the prefined values and add them as options
				var new_option = new jQuery('<option>');
				new_option.html(this_predefined_values[i]);
				new_option.val(this_predefined_values[i]);
				new_option.attr("selected", false);
				
				// Loop through previously mapped values
				if (typeof wpp_field_map[rm_field] != 'undefined')
				{
					wpp_field_map_temp = wpp_field_map[rm_field];
					
					if (typeof wpp_field_map_temp[rm_value] != 'undefined')
					{
						wpp_field_map_temp = wpp_field_map_temp[rm_value];
						for (var j in wpp_field_map_temp)
						{
							if (wpp_field_map_temp[j] == this_predefined_values[i])
							{
								new_option.attr("selected", "selected");
							}
						}
					}
				}
				
				jQuery(this).append(new_option);
				
				num_done = num_done + 1;
			}
		}
	
		if (num_done == 0)
		{
			jQuery(this).html("");
			
			jQuery('#wpp_field_map_' + rm_field + '_yes_toggle').fadeOut('fast');
			jQuery('#wpp_field_map_' + rm_field + '_yes').fadeOut('fast', function() 
			{ 
				jQuery('#wpp_field_map_' + rm_field + '_no').fadeIn('fast');
			});
		}
		else
		{
			jQuery('#wpp_field_map_' + rm_field + '_no').fadeOut('fast', function() 
			{ 
				jQuery('#wpp_field_map_' + rm_field + '_yes').fadeIn('fast');
				jQuery('#wpp_field_map_' + rm_field + '_yes_toggle').fadeIn('fast');
			});
		}
	});
	
	jQuery('#wpp_field_map_' + rm_field + '_yes_toggle a').click(function()
	{
		if (jQuery('#wpp_field_map_' + rm_field + '_yes').height() == 120)
		{
			jQuery('#wpp_field_map_' + rm_field + '_yes').animate({
				height: jQuery('#wpp_field_map_' + rm_field + '_yes')[0].scrollHeight
			}, 'fast', function()
			{
				jQuery('#wpp_field_map_' + rm_field + '_yes_toggle a').html("- Show Less");
			});
		}
		else
		{
			jQuery('#wpp_field_map_' + rm_field + '_yes').animate({
				height: 120
			}, 'fast', function()
			{
				jQuery('#wpp_field_map_' + rm_field + '_yes_toggle a').html("+ Show More");
			});
		}
		return false;
	});
}

function load_statistics_grid(feed_portal_id)
{
	// Empty the grid
	jQuery('#statistics_table tbody').html("");
	
	var new_html = '';
	if ( feed_portal_id != '' && typeof statistics[feed_portal_id] != 'undefined' )
	{
		var this_statistics = statistics[feed_portal_id];
		
		new_html += '<tr>';
		new_html += '	<th scope="row">Portal Status</th>';
		new_html += '	<td>' + ( ( this_statistics.status == 1 ) ? 'Test Mode' : 'Live Mode' ) + '</td>';
		new_html += '</tr>';
		
		new_html += '<tr>';
		new_html += '	<th scope="row">Start Time</th>';
		new_html += '	<td>' + this_statistics.start_date + '</td>';
		new_html += '</tr>';
		
		new_html += '<tr>';
		new_html += '	<th scope="row">End Time</th>';
		new_html += '	<td>' + this_statistics.end_date + '</td>';
		new_html += '</tr>';
		
		new_html += '<tr>';
		new_html += '	<th scope="row">Duration</th>';
		new_html += '	<td>' + this_statistics.duration_hours + ' hours ' + this_statistics.duration_minutes + ' minutes ' + this_statistics.duration_seconds + ' seconds</td>';
		new_html += '</tr>';
		
		new_html += '<tr>';
		new_html += '	<th scope="row">Properties Included</th>';
		new_html += '	<td>' + this_statistics.properties.length + '</td>';
		new_html += '</tr>';
		
		new_html += '<tr>';
		new_html += '	<th scope="row">Errors</th>';
		new_html += '	<td>' + this_statistics.errors + '</td>';
		new_html += '</tr>';
		
		new_html += '<tr>';
		new_html += '	<th scope="row">Actions</th>';
		new_html += '	<td>';
		if ( this_statistics.properties.length > 0 ) 
		{
			new_html += '	<a href="#" class="button jqmPropertiesAnchor" id="jqmAnchor-' + feed_portal_id + '">View Properties</a>';
		}
		if ( this_statistics.log.length > 0 ) 
		{
			new_html += '	<a href="#" class="button jqmLogAnchor" id="jqmAnchor-' + feed_portal_id + '">View Log</a>';
		}
		if ( this_statistics.blm_file != "" ) 
		{
			new_html += '	<a href="' + this_statistics.blm_file + '" class="button" target="_blank">Download Sent BLM File</a>';
		}
		if ( this_statistics.zip_file != "" ) 
		{
			new_html += '	<a href="' + this_statistics.zip_file + '" class="button" target="_blank">Download Sent ZIP File</a>';
		}
		new_html += '	</td>';
		new_html += '</tr>';
		
		jQuery('#dialog').jqm();
		//jQuery('#dialog').jqmShow();
	}
	
	if ( new_html == '' )
	{
		new_html += '<tr>';
		new_html += '	<td>Please select which feed you wish to view stats for</td>';
		new_html += '</tr>';
	}
	
	jQuery('#statistics_table tbody').append(new_html);
}
