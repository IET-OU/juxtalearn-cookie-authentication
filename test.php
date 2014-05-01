<?php
/*
 * TEST.
 */
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );
date_default_timezone_set( 'GMT' );

require_once './juxtalearn_cookie_authentication.php';


define( 'JXL_COOKIE_SECRET_KEY', '54321dcba{ Long, random and shared }' );
define( 'JXL_COOKIE_DOMAIN', 'localhost' );


test_auth_master_delete_cookies();
$set_result = test_auth_master_set_cookies();
$get_result = test_auth_slave_parse_cookies();


?><title>Test</title><h1>TEST: JuxtaLearn_Cookie_Authentication</h1><pre>
TEST: set cookies
-- <?php print_r( $set_result ) ?>

TEST: parse cookies
-- <?php print_r( $get_result ) ?>
<?php


// ClipIt.
function test_auth_master_delete_cookies() {
    $delete = isset( $_GET['delete'] );

    if ($delete) {
        $auth = new JuxtaLearn_Cookie_Authentication();
        $b_ok = $auth->delete_cookies();
        die( 'TEST: deleted cookies' );
    }
}

// ClipIt.
function test_auth_master_set_cookies() {
    $expire = isset($_GET['expire']) ? time() + intval($_GET['expire']) : 0;

    try {
        $auth = new JuxtaLearn_Cookie_Authentication();
    } catch (Exception $ex) {
        die( get_class( $ex ) .' : '. $ex->getMessage() );
    }
    $set_result = null;

    $parse = $auth->parse_cookies();
    //if (!$auth->is_authenticated()) {
        $set_result = $auth->set_required_cookie( 'pebs123', 'teacher', 999, $expire );
        $b_ok = $auth->set_name_cookie( 'Pablo Llinás Arnaiz', $expire );
        $b_ok = $auth->set_token_cookie( '0491d9433979a6187a9bc03f868aa104', $expire );
    //}
    return $set_result;
}

// Tricky Topic tool, etc.
function test_auth_slave_parse_cookies() {
    $auth = new JuxtaLearn_Cookie_Authentication();
    return $auth->parse_cookies();
}


#End.