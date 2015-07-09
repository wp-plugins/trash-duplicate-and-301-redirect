<?php
/*
Plugin Name: Trash Duplicates And 301 Redirection
Plugin URI: http://solwininfotech.com/
Description: Find and delete duplicates posts,custom posts and pages specifying which one to keep (newest, oldest or manual selection) and 301 redirection to the post you are keeping.
Version: 1.0.0
Date: 08 July 2015
Author: Solwin Infotech
Author URI: http://solwininfotech.com/
 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define('TDRD_PLUGIN_URL',  plugins_url(dirname(__FILE__)));
define('TDRD_PLUGIN_DIR',  plugin_dir_path( __FILE__ ));

function TDRD_init() {

	// set the current plugin version
    
	$trash_duplicates_version = '1.0.0';

	$trash_duplicates_options = get_option( 'trash_duplicates_options' );

        // if it's not the latest version.
	if ( version_compare( $trash_duplicates_version, $trash_duplicates_options[ 'version' ], '>' ) ) {
            $trash_duplicates_options[ 'version' ] = $trash_duplicates_version;
            update_option( 'trash_duplicates_options', $trash_duplicates_options );
	}

	//  Load admin scripts
        
	if ( is_admin() ){
            require_once( 'trash-duplicates-admin.php' );
            require_once( 'redirect_admin.php' );
        }
        else
        {
            require_once( 'redirect_client.php' );
        }

}
add_action( 'init', 'TDRD_init', 0 );
?>