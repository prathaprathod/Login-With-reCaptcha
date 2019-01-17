<?php
/*
* Plugin Name: Login With Recaptcha
* Description: Simply protect your WordPress against spam comments and brute-force attacks, thanks to Google reCAPTCHA!
* Version: 2.9
* Author: Prathap Rathod
* Author URI: https://www.novami.cz
* License: GPL3
* Text Domain: login-with-recaptcha
*/

if (!defined('ABSPATH')) {
	die( 'Direct access not allowed!' );
}

function lwr_add_plugin_action_links($links) {
	return array_merge(array("tools" => "<a href=\"tools.php?page=lwr-options\">".__("Settings", "login-with-recaptcha")."</a>"), $links);
}
add_filter("plugin_action_links_".plugin_basename(__FILE__), "lwr_add_plugin_action_links");

function lwr_activation($plugin) {
	if ($plugin == plugin_basename(__FILE__) && (!get_option("lwr_site_key") || !get_option("lwr_secret_key"))) {
		exit(wp_redirect(admin_url("tools.php?page=lwr-options")));
	}
}
add_action("activated_plugin", "lwr_activation");

function lwr_options_page() {
	echo "<div class=\"wrap\">
	<h1>".__("Login With reCAPTCHA Options", "login-with-recaptcha")."</h1>
	<form method=\"post\" action=\"options.php\">";
	settings_fields("lwr_header_section");
	do_settings_sections("lwr-options");
	submit_button();
	echo "</form>
	</div>";
}

function lwr_menu() {
	add_submenu_page("tools.php", "Login With reCAPTCHA", "Login With reCAPTCHA", "manage_options", "lwr-options", "lwr_options_page");
}
add_action("admin_menu", "lwr_menu");

function lwr_display_content() {
	echo "<p>".__("You have to <a href=\"https://www.google.com/recaptcha/admin\" rel=\"external\">register your domain</a> first, get required keys (reCAPTCHA V2) from Google and save them below.", "login-with-recaptcha")."</p>";
}

function lwr_display_site_key_element() {
	$lwr_site_key = filter_var(get_option("lwr_site_key"), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	echo "<input type=\"text\" name=\"lwr_site_key\" class=\"regular-text\" id=\"lwr_site_key\" value=\"{$lwr_site_key}\" />";
}

function lwr_display_secret_key_element() {
	$lwr_secret_key = filter_var(get_option("lwr_secret_key"), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	echo "<input type=\"text\" name=\"lwr_secret_key\" class=\"regular-text\" id=\"lwr_secret_key\" value=\"{$lwr_secret_key}\" />";
}

function lwr_display_login_check_disable() {
	echo "<input type=\"checkbox\" name=\"lwr_login_check_disable\" id=\"lwr_login_check_disable\" value=\"1\" ".checked(1, get_option("lwr_login_check_disable"), false)." />";
}

function lwr_display_options() {
	add_settings_section("lwr_header_section", __("What first?", "login-with-recaptcha"), "lwr_display_content", "lwr-options");

	add_settings_field("lwr_site_key", __("Site Key", "login-with-recaptcha"), "lwr_display_site_key_element", "lwr-options", "lwr_header_section");
	add_settings_field("lwr_secret_key", __("Secret Key", "login-with-recaptcha"), "lwr_display_secret_key_element", "lwr-options", "lwr_header_section");
	add_settings_field("lwr_login_check_disable", __("Disable reCAPTCHA for login", "login-with-recaptcha"), "lwr_display_login_check_disable", "lwr-options", "lwr_header_section");

	register_setting("lwr_header_section", "lwr_site_key");
	register_setting("lwr_header_section", "lwr_secret_key");
	register_setting("lwr_header_section", "lwr_login_check_disable");
}
add_action("admin_init", "lwr_display_options");

function frontend_lwr_script() {
	$lwr_site_key = filter_var(get_option("lwr_site_key"), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	$lwr_display_list = array("comment_form_after_fields", "register_form", "lost_password", "lostpassword_form", "retrieve_password", "resetpass_form", "woocommerce_register_form", "woocommerce_lostpassword_form", "woocommerce_after_order_notes", "bp_after_signup_profile_fields");
	
	if (!get_option("lwr_login_check_disable")) {
		array_push($lwr_display_list, "login_form", "woocommerce_login_form");
	}
	
	foreach($lwr_display_list as $lwr_display) {
		add_action($lwr_display, "lwr_display");
	}
	
	wp_register_script("lwr_recaptcha_main", plugin_dir_url(__FILE__)."main.js?v=2.9");
	wp_enqueue_script("lwr_recaptcha_main");
	wp_localize_script("lwr_recaptcha_main", "lwr_recaptcha", array("site_key" => $lwr_site_key));
		
	wp_register_script("lwr_recaptcha", "https://www.google.com/recaptcha/api.js?hl=".get_locale()."&onload=lwr&render=explicit");
	wp_enqueue_script("lwr_recaptcha");
		
	wp_enqueue_style("style", plugin_dir_url(__FILE__)."style.css?v=2.9");
}

function lwr_display() {
	echo "<div class=\"lwr-recaptcha\"></div>";
}

function lwr_verify($input) {
	if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["g-recaptcha-response"])) {
		$lwr_secret_key = filter_var(get_option("lwr_secret_key"), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$recaptcha_response = filter_input(INPUT_POST, "g-recaptcha-response", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$response = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret={$lwr_secret_key}&response={$recaptcha_response}");
		$response = json_decode($response["body"], 1);
		
		if ($response["success"]) {
			return $input;
		} elseif (is_array($input)) { // Array = Comment else Object
			wp_die("<p><strong>".__("ERROR:", "login-with-recaptcha")."</strong> ".__("Google reCAPTCHA verification failed.", "login-with-recaptcha")."</p>", "reCAPTCHA", array("response" => 403, "back_link" => 1));
		} else {
			return new WP_Error("reCAPTCHA", "<strong>".__("ERROR:", "login-with-recaptcha")."</strong> ".__("Google reCAPTCHA verification failed.", "login-with-recaptcha"));
		}
	} else {
		wp_die("<p><strong>".__("ERROR:", "login-with-recaptcha")."</strong> ".__("Google reCAPTCHA verification failed.", "login-with-recaptcha")." ".__("Do you have JavaScript enabled?", "login-with-recaptcha")."</p>", "reCAPTCHA", array("response" => 403, "back_link" => 1));
	}
}

function lwr_check() {
	if (get_option("lwr_site_key") && get_option("lwr_secret_key") && !is_user_logged_in() && !function_exists("wpcf7_contact_form_shortcode")) {
		add_action("login_enqueue_scripts", "frontend_lwr_script");
		add_action("wp_enqueue_scripts", "frontend_lwr_script");
		
		$lwr_verify_list = array("preprocess_comment", "registration_errors", "lostpassword_post", "resetpass_post", "woocommerce_register_post");
		
		if (!get_option("lwr_login_check_disable")) {
			array_push($lwr_verify_list, "wp_authenticate_user", "bp_signup_validate");
		}
		
		foreach($lwr_verify_list as $lwr_verify) {
			add_action($lwr_verify, "lwr_verify");
		}
	}
}

add_action("init", "lwr_check");