<?php
/*
 * Copyright (c) 2018, Tribal Limited
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Zenario, Tribal Limited nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL TRIBAL LTD BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace ze;

class cookie {



	//Formerly "setCookieOnCookieDomain()"
	public static function set($name, $value, $expire = COOKIE_TIMEOUT) {
	
		if ($expire > 1) {
			$expire += time();
		}
	
		setcookie($name, $value, $expire, SUBDIRECTORY, COOKIE_DOMAIN, \ze\link::isHttps(), true);
		$_COOKIE[$name] = $value;
	}

	//Formerly "clearCookie()"
	public static function clear($name) {
		\ze\cookie::set($name, '', 1);
	
		//Attempt to fix a bug where any cookies that were set with the wrong domain and/or path
		//will stay still stay on the visitor's browser.
		if (function_exists('httpHostWithoutPort')) {
			setcookie($name, '', 1, '/', '.'. \ze\link::hostWithoutPort());
		}
		setcookie($name, '', 1, '/');
		setcookie($name, '', 1);
	
		unset($_COOKIE[$name]);
	}

	//Formerly "setCookieConsent()"
	public static function setConsent() {
		\ze\cookie::set('cookies_accepted', 1);
		unset($_SESSION['cookies_rejected']);
	}

	//Formerly "setCookieNoConsent()"
	public static function setNoConsent() {
		if (isset($_COOKIE['cookies_accepted'])) {
			\ze\cookie::clear('cookies_accepted');
		}
		$_SESSION['cookies_rejected'] = true;
	}
	
	

	//Formerly "requireCookieConsent()"
	public static function requireConsent() {
		\ze::$cookieConsent = 'require';
	}





	const canSetFromTwig = true;
	//Formerly "canSetCookie()"
	public static function canSet() {
		return \ze::setting('cookie_require_consent') != 'explicit' || !empty($_COOKIE['cookies_accepted']) || \ze\priv::check();
	}

	//Formerly "hideCookieConsent()"
	public static function hideConsent() {
		if (\ze::$cookieConsent != 'require') {
			\ze::$cookieConsent = 'hide';
		}
	}



	//Formerly "showCookieConsentBox()"
	public static function showConsentBox() {
		require \ze::funIncPath(__FILE__, __FUNCTION__);
	}
	
	

	//Formerly "zenarioSessionName()"
	public static function sessionName() {
		return 'PHPSESSID'.
			(COOKIE_DOMAIN? ('-'. preg_replace('@\W@', '_', COOKIE_DOMAIN)) : '').
			(SUBDIRECTORY && SUBDIRECTORY != '/'? ('-'. preg_replace('@\W@', '_', str_replace('/', '', SUBDIRECTORY))) : '');
	}

	//Formerly "startSession()"
	public static function startSession() {
		if (!isset($_SESSION)) {
			session_name(\ze\cookie::sessionName());
		
			if (COOKIE_DOMAIN) {
				session_set_cookie_params(SESSION_TIMEOUT, SUBDIRECTORY, COOKIE_DOMAIN);
			} else {
				session_set_cookie_params(SESSION_TIMEOUT, SUBDIRECTORY);
			}
			session_start();
		
			//Fix for a bug with the $lifetime option in session_set_cookie_params()
			//as mentioned on http://php.net/manual/en/function.session-set-cookie-params.php
			\ze\cookie::set(session_name(), session_id(), SESSION_TIMEOUT);
		}
	}
}