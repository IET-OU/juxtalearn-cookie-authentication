<?php
/*
 * TEST.
 */
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );
date_default_timezone_set( 'GMT' );

require_once '../juxtalearn_cookie_authentication.php';

if ('define' == _get('use')) {
    define( 'JXL_COOKIE_SECRET_KEY', '54321dcba{ Long, random and shared }' );
    define( 'JXL_COOKIE_DOMAIN', cookie_domain());

    function create_cookie_auth() {
        return new JuxtaLearn_Cookie_Authentication();
    }
} else {
    function create_cookie_auth() {
        return new JuxtaLearn_Cookie_Authentication('54{ Long.. }', cookie_domain());
    }
}

test_auth_master_delete_cookies();
$set_result = test_auth_master_set_cookies();
$get_result = test_auth_slave_parse_cookies();

?><title>Test</title><h1>TEST: JuxtaLearn_Cookie_Authentication</h1><pre>
TEST: set cookies
-- <?php
  print_r(array('HTTP_HOST' => $_SERVER['HTTP_HOST'],
          '?host={}'=>_get('host'), '?use={}' => _get('use') ));
  print_r( $set_result ) ?>

TEST: parse cookies
-- <?php print_r( $get_result ) ?>
<?php


// ClipIt.
function test_auth_master_delete_cookies() {
    $delete = _get('delete');

    if ($delete) {
        $auth = create_cookie_auth();
        $b_ok = $auth->delete_cookies();
        die( 'TEST: deleted cookies' );
    }
}

// ClipIt.
function test_auth_master_set_cookies() {
    $expire = _get('expire') ? time() + intval(_get('expire')) : 0;

    try {
        $auth = create_cookie_auth();
    } catch (Exception $ex) {
        // Handle errors, for example, missing shared key.
        die( get_class( $ex ) .' : '. $ex->getMessage() );
    }
    $set_result = null;

    $parse = $auth->parse_cookies();
    //if (!$auth->is_authenticated()) {
        $set_result = $auth->set_required_cookie( 'pebs123', 'student', 999, $expire );
        $b_ok = $auth->set_name_cookie( 'Pablo Llinás Arnaiz', $expire );
        $b_ok = $auth->set_mail_cookie( 'pebs@example.edu', $expire );
        $b_ok = $auth->set_token_cookie( '0491d9433979a6187a9bc03f868aa104', $expire );
    //}
    return $set_result;
}

// Tricky Topic tool, etc.
function test_auth_slave_parse_cookies() {
    $auth = create_cookie_auth();
    return $auth->parse_cookies();
}


// Utilities.
function _get($key, $default = NULL) {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function cookie_domain() {
    $host = _get('host') ? _get('host') : $_SERVER['HTTP_HOST'];
    if (preg_match('@localhost@', $host)) {
      return 'localhost';
    }
    else {
      return preg_replace('@^[^\.]+\.@', '.', $host);
    }
}

#End.
