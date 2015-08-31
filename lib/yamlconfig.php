<?php

// Fixed prefix of configuration-page's name
defined('PKWK_CONFIG_PREFIX') || define('PKWK_CONFIG_PREFIX', ':config/');

define('PKWK_YAMLCONFIG_HIGHLIGHTER', 'code(perl)');
define('PKWK_YAMLCONFIG_HEAD', "* Config [#Config]\n#" . PKWK_YAMLCONFIG_HIGHLIGHTER . "{{\n");
define('PKWK_YAMLCONFIG_TAIL', "}}\n\n");
define('PKWK_YAMLCONFIG_PATTERN', '/\*\sConfig\s\[#Config\]\s+#' . preg_quote(PKWK_YAMLCONFIG_HIGHLIGHTER) . '\{\{\s+([\s\S]+?)\}\}\s+/');

class YamlConfig extends ArrayIterator
{
	private $name, $page; // Page name

	public function YamlConfig($name)
	{
		$this->name = $name;
		$this->page = PKWK_CONFIG_PREFIX . $name;
	}

	// Load the configuration-page
	public function read()
	{
		if (! is_page($this->page)){
			return FALSE;
		}
		$source = get_source($this->page, FALSE, TRUE);
		if ($source === FALSE){
			return FALSE;
		}
		$values = preg_match(PKWK_YAMLCONFIG_PATTERN, $source, $matches) ?
			yaml_parse($matches[1]) :
			yaml_parse($source);
		if ($values === FALSE){
			return FALSE;
		}
		foreach($values as $key => $value){
			$this[$key] = $value;
		}
		return TRUE;
	}

	// Save to the configuration-page
	public function write()
	{
		$output = PKWK_YAMLCONFIG_HEAD . yaml_emit($this->getArrayCopy()) . PKWK_YAMLCONFIG_TAIL;
		$source = get_source($this->page, TRUE, TRUE);
		$source = $source != FALSE && preg_match(PKWK_YAMLCONFIG_PATTERN, $source) ?
			preg_replace(PKWK_YAMLCONFIG_PATTERN, $output, $source) :
			$output;
		page_write($this->page, $source);
		return $source;
	}
}
?>
