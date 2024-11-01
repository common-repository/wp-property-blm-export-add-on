<?php
/**
 * Executed archive cron - Cleans up old files
*/

ini_set( "display_errors", 1 );
error_reporting( E_ALL );
set_time_limit( 0 );

$wpp_blm_export_options = get_option("wpp_blm_export_options");

$portals = ( isset($wpp_blm_export_options['portals']) ? $wpp_blm_export_options['portals'] : array() );
$archive = ( isset($wpp_blm_export_options['archive']) ? $wpp_blm_export_options['archive'] : '1' );
$archive_duration = ( isset($wpp_blm_export_options['archive_duration']) ? $wpp_blm_export_options['archive_duration'] : '7' );

if( count($portals) && $archive == '1' && $archive_duration != 0 && $archive_duration != "" )
{
	foreach( $portals as $portal_id => $portal )
	{
		$uploads_dir = wp_upload_dir();
		
		if( $uploads_dir['error'] === FALSE )
		{
			$path = $uploads_dir['basedir'] . '/blm_export/' . $portal_id . '/';
			
			$days = $archive_duration; 
			  
			// Open the directory  
			if( $handle = @opendir($path) )  
			{  
			    // Loop through the directory  
			    while( false !== ($file = @readdir($handle)) )  
			    {  
			        // Check the file we're doing is actually a file  
			        if( @is_file($path.$file) )  
			        {  
			            // Check if the file is older than X days old  
			            if( filemtime($path.$file) < ( time() - ( $days * 24 * 60 * 60 ) ) )  
			            {  
			                // Do the deletion  
			                @unlink($path.$file);  
			            }  
			        }  
			    }  
			}		
		}
	}
}
