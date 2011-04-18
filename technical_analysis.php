<?php
/**
 * Trading Central Technical Analysis API
 *
 * This class provides an easy way to generate a user-specific URL to Trading Central's Technical Analysis.
 * The URL is temporary and will automatically expire.
 *
 * @author Leonid Mamchenkov <leonid@mamchenkov.net>
 */
class TechnicalAnalysis {

	/**
	 * URL Prefix
	 */
	const DEFAULT_URL = "http://###PARTNER###.tradingcentral.com/login.asp?token=###TOKEN###";

	private $partnerId;
	private $partnerKey;
	private $partnerUrl;

	/**
	 * Locales supported by Trading Central
	 * @var $locales
	 */
	private $locales = array(
						'de_DE',
						'en_GB',
						'es_ES',
						'fr_FR',
						'it_IT',
						'ja_JP',
						'nl_NL',
						'ru_RU',
						'zh_CN', // Trading Central people insist that Chinese is 'ch', not 'cn'. Don't believe them!
					);	

	/**
	 * Map of application languages and supported locales
	 *
	 * Language key is what your application will be sending to 
	 * methods like getUrl().  Locale is one of the supported
	 * locales of Trading Central.
	 *
	 * Example 1:
	 *
	 * $langMap = array(
	 * 		'en' => 'en_GB',
	 * 		'ru' => 'ru_RU',
	 * 		...
	 * );
	 *
	 * Example 2:
	 *
	 * $langMap = array(
	 * 		'english' => 'en_GB',
	 * 		'russian' => 'ru_RU',
	 * );
	 *
	 * If you don't set your own map, the default one will be used. In
	 * this case, the language for each supported locale will be the 
	 * first part of the locale itself ('ru' part of 'ru_RU').
	 *
	 * Use setLanguageMap() method to set your own mapping.
	 */
	private $langMap = array();

	/**
	 * Constructor
	 *
	 * @param string $partnerId Trading Central partner ID (e.g. fxpro, fxcc, etc)
	 * @param string $partnerKey Trading Central partner encryption key (like ABCdEfJhIkLmNop1qrS2Tu==)
	 * @param string $partnerUrl Trading Central URL pattern (use ###PARTNER### and ###TOKEN### placeholders)
	 */
	public function __construct($partnerId = null, $partnerKey = null, $partnerUrl = self::DEFAULT_URL) {
		$this->partnerId = $partnerId;
		$this->partnerKey = $partnerKey;
		$this->partnerUrl = $partnerUrl;

		// Set default language map
		$defaultMap = $this->getDefaultMap();
		$this->setLanguageMap($defaultMap);
	}

	/**
	 * Get default langauge<->locale map
	 *
	 * There is a number of ways how this can be generated. The simplest
	 * though is just by extracting languages from supported locales.
	 *
	 * @return array
	 */
	protected function getDefaultMap() {
		$result = array();

		$supportedLocales = $this->getSupportedLocales();
		foreach ($supportedLocales as $supportedLocale) {
			list($langMajor, $langMinor) = explode('_', $supportedLocale);
			$result[$langMajor] = $supportedLocale;
		}

		return $result;
	}

	/**
	 * Get the list of locales supported by Trading Central
	 *
	 * @return array
	 */
	public function getSupportedLocales() {
		return $this->locales;
	}

	/**
	 * Map languages to locales
	 *
	 * @param array $map Key-Value list of language and locales
	 * @return void
	 */
	public function setLanguageMap($map) {
		if (empty($map)) {
			throw new Exception("Empty language maps are not allowed");
		}
		if (!is_array($map)) {
			throw new Exception("Language map must be an array");
		}

		$supportedLocales = $this->getSupportedLocales();
		$mappedLocales = array_values($map);
		foreach ($mappedLocales as $mappedLocale) {
			if (!in_array($mappedLocale, $supportedLocales)) {
				throw new Exception("Unsupported locale [$mappedLocale] detected in map");
			}
		}

		$this->langMap = $map;
	}

	/**
	 * Get URL for specified user and languages
	 *
	 * This is the main method of the class.  It expects the user ID and the preferred language
	 * (will fallback to English if unsupported language given) and returns the full URL.
	 *
	 * @param string $user_id User ID for who the URL is being constructed
	 * @param string $language Preferred language
	 * @return string URL
	 */
	public function getURL($user_id, $language) {
		$result = null;

		$locale = $this->getLocale($language);
		$token = $this->getToken($user_id, $locale);
		$encrypted_token = $this->encryptToken($token);
		$result = $this->buildUrl($encrypted_token);

		return $result;
	}

	/**
	 * Encrypt token
	 *
	 * Encypt token using Blowfish algorithm.
	 * NOTE: Make sure to use Crypt_Blowfish-1.1.0RC2, because Crypt_Blowfish-1.0.1 doesn't not work properly.
	 *
	 * @param string $token Token to encrypt
	 * @return string Encrypted token
	 */
	private function encryptToken($token) {
		if (empty($token)) {
			throw new Exception("Empty token detected");
		}
		if (empty($this->partnerKey)) {
			throw new Exception("Partner key is not specified");
		}

		require_once 'Crypt/Blowfish.php';

		$bf = new Crypt_Blowfish(base64_decode($this->partnerKey));
		$result = urlencode(base64_encode($bf->encrypt($token)));
		return $result;
	}

	/**
	 * Construct token string
	 *
	 * As per Trading Central specification, token is a comma separated list of
	 * parameters, such partner ID, user ID, locale, and current time.
	 *
	 * @param string $user_id User ID for who the token is constructed
	 * @param string $locale Locale for user's preferred language
	 * @return string Token
	 */
	private function getToken($user_id, $locale) {
		if (empty($this->partnerId)) {
			throw new Exception("Partner ID is not specified");
		}

		$result = implode(',', array($this->partnerId, $user_id, $locale, time()));
		return $result;
	}

	/**
	 * Convert language to locale
	 *
	 * @param string $language Language to convert
	 * @return string Locale
	 */
	private function getLocale($language) {
		$result = '';

		if (empty($this->langMap[$language])) {
			throw new Exception("Language [$language] is not supported");
		}
		$result = $this->langMap[$language];

		return $result;
	}

	/**
	 * Fill URL pattern with actual values
	 *
	 * @param string $token Encrypted token
	 * @return string
	 */
	private function buildUrl($token) {
		$result = $this->partnerUrl;
		$result = preg_replace("/###PARTNER###/", $this->partnerId, $result);
		$result = preg_replace("/###TOKEN###/", $token, $result);
		return $result;
	}
}

?>
