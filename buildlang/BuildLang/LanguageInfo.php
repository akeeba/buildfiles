<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildLang;

/**
 * Class to retrieve information about the language represented by a specific language code
 */
class LanguageInfo
{
	/**
	 * The language code, e.g. en-GB
	 *
	 * @var  string
	 */
	private $code;

	/**
	 * The human readable language name, e.g. "English (United Kingdom)"
	 *
	 * @var  string
	 */
	private $name;

	/**
	 * The country component of the language as a lowercase ISO 3166-1 alpha-2 code, e.g. "gb"
	 *
	 * @var  string
	 */
	private $country;

	/**
	 * Language code to language name map, loaded from the JSON file on request
	 *
	 * @var   array
	 */
	private static $nameMap = [];

	/**
	 * Get the human readable name of a language code
	 *
	 * @param   string $languageCode
	 *
	 * @return  string
	 */
	public static function getLanguageName(string $languageCode): string
	{
		if (empty(static::$nameMap))
		{
			self::loadLanguageNameMap();
		}

		if (isset(static::$nameMap[$languageCode]))
		{
			return static::$nameMap[$languageCode];
		}

		throw new \OutOfBoundsException("Language code $languageCode is not a valid language");
	}

	/**
	 * Loads the language code to language name map from the JSON file.
	 *
	 * The JSON file itself is a simple export of the query
	 * SELECT code, name FROM lang_languages;
	 * run against the Weblate database.
	 *
	 * @return  void
	 */
	protected static function loadLanguageNameMap()
	{
		$inFile          = realpath(__DIR__ . '/../langmap.json');
		$json            = file_get_contents($inFile);
		$raw             = json_decode($json, true);
		static::$nameMap = [];

		foreach ($raw as $entry)
		{
			static::$nameMap[$entry['code']] = $entry['name'];
		}
	}

	public function __construct(string $code)
	{
		$this->code    = $code;
		$this->name    = static::getLanguageName($code);
		$this->country = $this->findCountry($code);
	}

	/**
	 * Extract the country component from a language code. If the language code does not have a country we return 'xx',
	 * a shorthand for "no country".
	 *
	 * @param   string $code The language code, e.g. "en-GB"
	 *
	 * @return  string  The lowercase country code, e.g. "gb"
	 */
	private function findCountry(string $code): string
	{
		if (strpos($code, '-') === false)
		{
			return 'xx';
		}

		$parts = explode('-', $code);

		return strtolower(array_pop($parts));
	}

	/**
	 * Get the language code, e.g. "en-GB"
	 *
	 * @return  string
	 */
	public function getCode(): string
	{
		return $this->code;
	}

	/**
	 * Get the human readable language name, e.g. "English (United Kingdom)"
	 *
	 * @return  string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Get the country component of the language as a lowercase ISO 3166-1 alpha-2 code, e.g. "gb"
	 *
	 * @return  string
	 */
	public function getCountry(): string
	{
		return $this->country;
	}
}
