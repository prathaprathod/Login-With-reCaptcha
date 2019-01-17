<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'Direct access not allowed!' );
}

function lwr_delete($array) {
	foreach ($array as $one) {
		delete_option("lwr_{$one}");
	}	
}

lwr_delete(array("site_key", "secret_key", "login_check_disable"));
