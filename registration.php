<?php 
/*
Plugin Name: Registration
Plugin URI: http://svenni.dragly.com
Description: Create your own registration form for anything and save the people to a database.
Author: Svenn-Arne Dragly
Author URI: http://svenni.dragly.com
Version: 0.3
*/

/*  Copyright 2010  Svenn-Arne Dragly  (email : s@dragly.com)
    Some parts of code might be from Kieran O'Shea's Calendar Plugin.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

global $wpdb;

$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain( 'registration','wp-content/plugins/'.$plugin_dir, $plugin_dir);

// Enable the ability for the registration to be loaded from pages
add_shortcode("registration", "registration_insert");
add_shortcode('registration_list','registration_list_insert');
add_shortcode('registration_counter','registration_counter_insert');

// define table names
define('WP_REGISTRATION', $wpdb->prefix . 'registration');

function registration_insert($atts)
{
    extract(shortcode_atts(array(
		'category' => '1',
	), $atts));
    global $wpdb;
    $captcha_instance = new ReallySimpleCaptcha();
    $issaved = false;
    $output = "";

    if(isset($_POST['regsubmit'])) {
        if(!$captcha_instance->check($wpdb->escape($_POST['prefix']), $wpdb->escape($_POST['captcha']))) {
            $output .= "Teksten i bildet stemte ikke overens med teksten du skrev inn.";
        } else if(!preg_match("/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}/i",$_POST['epost'])) {
            $output .= "E-postadressen du har oppgitt ser dessverre ikke ut til å være gyldig.";
        } else {
            $sql = $wpdb->query("INSERT INTO " . WP_REGISTRATION . " (navn, tittel, fylke, epost, informasjon, godkjent, category) VALUES ('" . 
                $wpdb->escape($_POST['navn']) . "', '" .
                $wpdb->escape($_POST['tittel']) . "', '" . 
                $wpdb->escape($_POST['fylke']) . "', '" . 
                $wpdb->escape($_POST['epost']) . "', '" . 
                $wpdb->escape($_POST['informasjon']) . "', '" .
                "1', '" . $category . "')");
            $wpdb->get_results($sql);
            $output .= "Takk for din registrering. Ditt navn vil dukke opp i listen over tilsluttede snarlig.";
            $issaved = true;
            $content = "";
        }
        $captcha_instance->remove($wpdb->escape($_POST['prefix']));
    }
    if(!$issaved) {
        $word = $captcha_instance->generate_random_word();
        $prefix = mt_rand();
        $image = $captcha_instance->generate_image($prefix, $word);
        $output .= "<p><form method='post' name='regform' action='" . $_SERVER['REQUEST_URI'] . "'>
        <input type=hidden name=prefix value='".$prefix."' />
        Fullt navn:<br />
        <input type=text name=navn value='" . (isset($_POST['navn']) ? $_POST['navn'] : "") . "' /><br />
        Tittel (valgfritt):<br />
        <input type=text name=tittel value='" . (isset($_POST['tittel']) ? $_POST['tittel'] : "") . "'/><br />
        Fylke (valgfritt):<br />
        <input type=text name=fylke value='" . (isset($_POST['fylke']) ? $_POST['fylke'] : "") . "'/><br />
        E-post (vil ikke bli publisert):<br />
        <input type=text name=epost value='" . (isset($_POST['epost']) ? $_POST['epost'] : "") . "'/><br />
        Kort informasjon om deg selv (valgfritt):<br />
        <textarea name=informasjon>" . (isset($_POST['informasjon']) ? $_POST['informasjon'] : "") . "</textarea><br />
        Skriv inn teksten fra bildet under:<br />
        <img src='" . get_option('home') . "/wp-content/plugins/really-simple-captcha/tmp/" . $image . "' /><br />
        <input type=text name=captcha /><br />
        <br />
        <input type=submit name=regsubmit value=OK />
      </form></p>";
    }
    return $output;
}

function registration_list_insert($atts)
{
    extract(shortcode_atts(array(
		'category' => '1',
	), $atts));
    return registration_list($category);
}
function registration_counter_insert($atts) {
    extract(shortcode_atts(array(
		'category' => '1',
	), $atts));
    return registration_counter($category);
}
function registration_install () {
    global $wpdb;

    $table_name = WP_REGISTRATION;
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE " . $table_name . " (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                navn VARCHAR(255) DEFAULT '0' NOT NULL,
                tittel VARCHAR(255) NOT NULL,
                fylke VARCHAR(255) NOT NULL,
                epost VARCHAR(255) NOT NULL,
                informasjon text NOT NULL,
                godkjent tinyint(1) NOT NULL,
                prioritet mediumint(9) NOT NULL
                UNIQUE KEY id (id)
                );";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

    }
}

function registration_list($category) {
    global $wpdb;
    $return = '';
    $priclass = array("regpri0","regpri1","regpri2");
    $curpri = 0;
    $first = true;
    $registrations = $wpdb->get_results("SELECT * FROM " . WP_REGISTRATION . " WHERE godkjent = 1 AND synlig = 1 AND category = " . $category . " ORDER BY prioritet DESC");
    $return .= "<p>";
    foreach($registrations as $reg) {
        if($curpri != $reg->prioritet || $first) {
            $curpri = $reg->prioritet;
            if(!$first) {
                $return .= "</span>";
            }
            $return .= "<span class='" . $priclass[$reg->prioritet] . "'>";
        }
        $return .= stripslashes($reg->navn) . "<br />\n";
        $return .= "<span class='regtitle'>";
        if(($reg->tittel == "" || $reg->tittel == " ") && $reg->fylke == "") {
            $return .= "---";
        } else {
            $return .= stripslashes($reg->tittel) . (($reg->fylke != "") ? (" (" .stripslashes($reg->fylke) . ")") : "");
        }
        $return .= "</span><br />\n";
        $first = false;
    }
    $return .= "</span>";
    $return .= "</p>";
    return $return;
}

function get_registration_list($category=1) {
    print registration_list($category);
}

function registration_counter($category=1) {
    global $wpdb;
    $counter = $wpdb->get_var("SELECT COUNT(*) FROM " . WP_REGISTRATION . " WHERE godkjent = 1 AND category = " . $category . " ORDER BY prioritet DESC");
    return $counter;
}
function get_registration_counter($category=1) {
    print registration_counter($category);
}

register_activation_hook(__FILE__,'registration_install');

?>
