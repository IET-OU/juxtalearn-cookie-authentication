<?php
/**
 * Simple HTTP domain cookie creation and authentication.
 *
 * @license LGPLv2.1+
 * @copyright 2014 The Open University.
 * @author Nick Freear, 25 April 2014.
 * @author Pablo Llinás Arnaiz
 * @link   https://gist.github.com/nfreear/9b3431b75a843e839f3c
 * @link   http://tools.ietf.org/html/rfc6265
 */

/*
Questions/ TODOs:
  - What is the maximum allowed length of the user-login (username)?
  - What characters can the user-login contain? (Example, spaces, punctuation?)
  - 'user_role' is now optional (2014-05-16). This needs review.
*/


class JuxtaLearn_Cookie_Authentication {

    // Names for optional `defined()` constants.
    const DF_KEY    = 'JXL_COOKIE_SECRET_KEY';
    const DF_DOMAIN = 'JXL_COOKIE_DOMAIN';

    const KEY_MIN_SIZE = 10;  //Ideally: 32+

    // Our cookie names.
    const CK_TOKEN = 'clipit_token';
    const CK_USER  = 'clipit_user';
    const CK_NAME  = 'clipit_name';
    const CK_MAIL  = 'clipit_mail';

    // TODO: role is now optional (2014-05-16).
    const COOKIE_FORMAT = '%hash.%time.login=%login.role=%role.id=%id';
    const COOKIE_REGEX  =
        '/^(?P<H>\w+)\.(?P<T>\d+)\.login=(?P<L>\w+)\.role=(?P<R>\w*)\.id=(?P<i>\d*)$/';

    private $shared_secret_key;
    protected $cookie_domain;
    protected $parse_r;


    /**
     * The shared secret key and cookie domain can either be set here as parameters
     * to the constructor, or as PHP `define()` constants - see `test.php`
     *
     * @throws InvalidArgumentException for a missing or too short secret key.
     * @useby auth-master
     * @useby auth-slave 
     */
    public function __construct( $secret_key = NULL, $cookie_domain = NULL ) {
        $this->shared_secret_key = $secret_key ? $secret_key : (
                defined(self::DF_KEY) ? constant(self::DF_KEY) : NULL );
        $this->cookie_domain = $cookie_domain ? $cookie_domain : (
                defined(self::DF_DOMAIN) ? constant(self::DF_DOMAIN) : NULL );

        if (!$this->cookie_domain) {
            $this->cookie_domain = '.juxtalearn.org';
        }
        if ('localhost' == $this->cookie_domain) {
            $this->cookie_domain = NULL;
        }
        if (strlen( $this->shared_secret_key ) < self::KEY_MIN_SIZE) {
            throw new InvalidArgumentException(
                __CLASS__ . ' - {shared_secret_key} is missing or too short.' );
        }
    }

    /**
     * Called by ClipIt (authentication master), to set an authentication cookie.
     *
     * @param string $user_login The user's login ID, sometimes called a "username".
     * @param string $user_role  An identifying string for the user's role.
     * @param int    $user_id    An optional numeric ID, probably from ClipIt's database,
     * @param int    $expire     The time the cookie expires.
     * @return array Debug information, including input parameters.
     * @example  $result = $auth->set_required_cookie( 'pebs123', 'teacher', 999 );
     *           var_dump( $result[ 'user_role' ] );
     * @useby auth-master
     */
    public function set_required_cookie( $user_login, $user_role = 'student', $user_id = NULL, $expire = 0 ) {
        $timestamp = time();
        $payload = $this->user_payload( $user_login, $user_role, $timestamp, $user_id );
        $user_cookie = $this->make_cookie_hash( $timestamp, $payload ) . $payload;

        return array(
            'user_cookie_ok' => setcookie(
                self::CK_USER, $user_cookie, $expire, '/', $this->cookie_domain ),
            'user_cookie_value' => $user_cookie,
            'user_cookie_size' => strlen( $user_cookie ),
            'cookie_domain' => $this->cookie_domain,
            'cookie_path' => '/',
            'user_login' => $user_login,
            'user_id' => $user_id,
            'user_role' => $user_role,
            'expire' => $expire,
            'time' => $timestamp,
            'time_formatted' => $this->cookie_date( $timestamp ),
        );
    }

    /**
     * @param string $api_token ClipIt can set a token to be used in the ClipIt REST API.
     * @param int    $expire    The time the cookie expires.
     * @example  $b_ok = $auth->set_token_cookie( '0491d9433979a6187a9bc03f868aa104' );
     * @useby auth-master
     */
    public function set_token_cookie( $api_token, $expire = 0 ) {
        return setcookie(
            self::CK_TOKEN, $api_token, $expire, '/', $this->cookie_domain );
    }

    /**
     * @param string $display_name Optionally, ClipIt sets the user's actual full name.
     * @param int    $expire       The time the cookie expires.
     * @example  $b_ok = $auth->set_name_cookie( "Pablo Llinás Arnaiz" );
     * @useby auth-master
     */
    public function set_name_cookie( $display_name, $expire = 0 ) {
        return setcookie(
             self::CK_NAME, $display_name, $expire, '/', $this->cookie_domain );
    }

    /**
     * @param string $email Optionally, ClipIt sets the user's email address.
     * @param int    $expire       The time the cookie expires.
     * @example  $b_ok = $auth->set_mail_cookie( "pbs@example.org" );
     * @useby auth-master
     */
    public function set_mail_cookie( $email, $expire = 0 ) {
        return setcookie(
             self::CK_MAIL, $email, $expire, '/', $this->cookie_domain );
    }
    /**
     * Called by ClipIt to delete all associated cookies - security.
     * @useby auth-master
     */
    public function delete_cookies() {
        if (isset( $_COOKIE[self::CK_USER] )) {
            unset( $_COOKIE[self::CK_USER] );
            unset( $_COOKIE[self::CK_NAME] );
            unset( $_COOKIE[self::CK_MAIL] );
            unset( $_COOKIE[self::CK_TOKEN] );
        }
        $expire = time() - 3600;
        setcookie( self::CK_NAME, '', $expire, '/', $this->cookie_domain );
        setcookie( self::CK_MAIL, '', $expire, '/', $this->cookie_domain );
        setcookie( self::CK_TOKEN, '', $expire, '/', $this->cookie_domain );
        return setcookie( self::CK_USER, '', $expire, '/', $this->cookie_domain );
    }

    /**
     * Called by Tricky Topic tool etc. (authentication slave), to get authentication data.
     *
     * @return array  Flag indicating if authentication succeeded, user data, debug info.
     * @example  $result = $auth->parse_cookies();
     *           var_dump( $auth->get_user_login() );
     * @useby auth-slave
     */
    public function parse_cookies() {
        $this->parse_r = array( 'is_authenticated' => false );
 
        // Try a basic check.
        $user_cookie = $this->get_cookie(self::CK_USER);
        if (!$user_cookie) {
            $this->parse_r['msg'] = 'Warning, missing authentication cookie.';
            return $this->parse_r;
        }

        // Try to extract data.
        if (!preg_match( self::COOKIE_REGEX, $user_cookie, $m )) {
            $this->parse_r['msg'] = 'Error, unexpected user-cookie format.';
            $this->parse_r['cookie'] = $user_cookie;
            $this->parse_r['regex'] = self::COOKIE_REGEX;
            $this->parse_r['regex_matches'] = $m;
            return $this->parse_r;
        }

        $this->parse_r = $r = array(
            'is_authenticated' => false,  // Still false!
            'token_cookie_value' => $this->get_cookie(self::CK_TOKEN),
            'user_cookie_value' => $m[0],
            'hash' => $m[ 'H' ],        //$m[1]
            'user_login' => $m[ 'L' ],  //$m[3] (We've swapped positions.)
            'user_role'=>isset($m[ 'R' ]) ? $m[ 'R' ] : NULL, //$m[4]
            'user_id' => isset($m[ 'i' ]) ? $m[ 'i' ] : NULL, //$m[5]
            'user_mail' => $this->get_cookie(self::CK_MAIL),
            'display_name' => $this->get_cookie(self::CK_NAME),
            'api_token' => $this->get_cookie(self::CK_TOKEN),
            'time' => $m[ 'T' ],   //$m[2]
            'time_formatted' => $this->cookie_date( $m[ 'T' ] ), //$m[2]
        );

        // Try to validate.
        $payload = $this->user_payload(
            $r['user_login'], $r['user_role'], $r['time'], $r['user_id'] );
        $try_hash = $this->make_cookie_hash( $r['time'], $payload );

        if ($try_hash != $r['hash']) {
            // ERROR.
            $this->parse_r = array(
                'is_authenticated' => false, 'msg' => 'Error, invalid cookie.',
                'regex_matches' => $m, 'regex' => self::COOKIE_REGEX );
            return $this->parse_r;
        }

        $this->parse_r['msg'] = 'Success';
        $this->parse_r['is_authenticated'] = true;
        return $this->parse_r;
    }

    public function authenticate() {
        return $this->parse_cookies();
    }

    // ==========================================
    // Public utilities.

    /**
     * @return bool Is the user authenticated?
     * @example  $result = $auth->parse_cookie();
     *           if ($auth->is_authenticated)  // Do something...
     * @useby auth-slave
     */
    public function is_authenticated() {
        return $this->get_property( 'is_authenticated' );
    }

    public function get_user_login() {
        return $this->get_property( 'user_login' );
    }

    public function get_api_token() {
        return $this->get_property( 'api_token' );
    }

    public function get_property( $key ) {
        return isset($this->parse_r[ $key ]) ? $this->parse_r[ $key ] : NULL;
    }

    public function get_cookie( $key ) {
        return isset($_COOKIE[ $key ]) ? $_COOKIE[ $key ] : NULL;
    }

    // ==========================================
    // Utilities.

    protected function make_cookie_hash( $timestamp, $payload ) {
        return md5( $this->shared_secret_key . $timestamp . $payload );
    }

    protected function user_payload( $login, $role, $timestamp, $user_id = NULL ) {
        return strtr( self::COOKIE_FORMAT, array( '%hash' => '', '%login' => $login,
            '%role' => $role, '%id' => $user_id, '%time' => $timestamp ));
    }

    protected function cookie_date( $timestamp ) {
        return date( 'l, j F Y H:i:s', $timestamp );
    }
}


#End.