<?
/**
  * This plugin can be used to insert RSS Newsfeeds to your blog
  * Based on http://readinged.com/articles/rssparser/
             by ed@readinged.com
  * History:
  *      v0.9 : initial plugin
  *      v0.91: minor bugfixes
  *      v0.92: added posibillity to show site meta information
  *      v0.93: added options to toggle meta info on or off
  *             added the option to open links from a feed in a named target (like __blank or _top)
  *      v0.93b: problems with 2 blogs on the same url, now the query string is in included in the cache file (again) sorry Trent
  *      v0.94: somewhere there was a " too much in the output. 
  *      v0.95: added supportsFeature
  *             fixed ask 4 get 3 bug
  *             add option to control whether to display feed temp unava message
  *
  */
 
class NP_NewsFeed extends NucleusPlugin {
    function getName()    {return 'NewsFeed: Import RSS / XML News feeds in your weblog.'; }
    function getAuthor()  {return '-=Xiffy=- | admun (Edmond Hui)'; }
    function getURL()     {return 'http://xiffy.nl/weblog/'; }
    function getVersion() {return '0.95'; }
    function getDescription() {
	return 'Call this to import a newsfeed. Currently all feeds work with the same defaults.';
    }
 
    function supportsFeature($what) {
	switch($what) {
	    case 'SqlTablePrefix':
		return 1;
	    default:
		return 0;
	}
    }
 
    function install() {
	// create some options
	$this->createOption('Cachedir','Directory where to put the cached data (relative from MEDIADIR)','text','rsscache/');
	$this->createOption('Titlediv','Classname for the titleDiv item-title','text','feedTitle');
	$this->createOption('Linkdiv', 'Classname for the links when shown seperatly','text','feedLink');
	$this->createOption('Descriptiondiv','Classname for the description in de feed','text','feedDesc');
	$this->createOption('cacheTime','Time before cache gets refreshed','text','60');
	$this->createOption('showLogoAndTitle','Should we include the sites meta data as logo and title?','yesno','yes');
	$this->createOption('target','If filled with anyname all links will include target="anyname" (can be _blank)','text','');
	$this->createOption('linktext','text visible for "read whole item" only for type 2 and 4 calls','text','[read on]');
	$this->createOption('showFeedNotAvail','Should we give warning to a unavailbale feed?','yesno','yes');
    }
 
    function doSkinVar($skintype, $newsfeedURL, $what = 1, $amount = 10) {
	global $manager, $blog, $CONF, $i; 
	// go get the requested newsfeed.
 
	$feed = $this->readFeed($newsfeedURL);
 
	$titlediv       = $this->getOption(Titlediv);
	$linkdiv        = $this->getOption(Linkdiv);
	$descriptiondiv = $this->getOption(Descriptiondiv);
	$target         = $this->getOption(target);
	$linktext       = $this->getOption(linktext);
 
	if (!$feed)
	{
	    if ($this->getOption('showFeedNotAvail') == 'yes')
		echo "<div class='$titlediv'>Feed temporarily unavailable</div>\n";
	    return;
	}		
 
        // Now insert the newsfeed in your weblog
        // what. 1 Headlines only; headline = link
        //       2 Headlines + link apart
        //       3 Headlines, headline = link + description
        //       4 Headlines, link and description
	if ($this->getOption(showLogoAndTitle) == "yes")
	    $i = 0;
	else {
	    $i = 1;
	    $amount = $amount+1;
	}
 
	foreach ( $feed as $feeditem ) {
	    if ($i <= $amount) {
		$linkUrl = "<a href=\"".$feeditem[ "link" ]."\"";
		if (($what % 2) == 1) {
		    $linkUrl .= " title=\"".stripslashes(htmlspecialchars(strip_tags($feeditem[ "description" ])))."\"";
		    if ($target <> "" ) {
			$linkUrl .= " target=\"".$target."\"";
		    }
		    $linkUrl .= ">" . stripslashes($feeditem[ "title" ]) ."</a>";
		}
		if (($what % 2) == 0) {
		    if ($target <> "" ) {
			$linkUrl .= " target=\"" . $target . "\"";
		    }
		    $linkUrl .= "\">" .$linktext."</a>";
		}  // well we have the linkUrl at last ;-) all those options make it a mess !!
 
		if ($i == 0 && $feeditem[ "sitetitle"] <> "" )  {
		    echo "<div class='title'>". stripslashes($feeditem[ "sitetitle" ]) ."</div>";
		    if ($feeditem[ "url" ] <> "") {
			echo "<img class=\"centered\" src=\"" . $feeditem[ "url" ] ."\" alt=\"". stripslashes($feeditem[ "sitetitle" ]) . "\" title=\"". stripslashes($feeditem[ "sitetitle" ]) . "\" />";
		    }
 
		} else if ($what == 1 || $what == 3) {
		    echo "<div class='$titlediv'>" .$linkUrl."</div>\n";
		} else {
		    echo "<div class='$titlediv'>". $feeditem[ "title" ] ."</div>";
		    echo "<div class='$linkdiv'>" . $linkUrl ."</div>\n";
		}
		if ($what == 3 || $what == 4) {
		    echo "<div class='$descriptiondiv'>"  . stripslashes($feeditem[ "description" ]) . "</div>\n";
		}
		$i++;
	    }
	}
    }
 
    function isCurrent($filename, $minutes) {
         return ceil((time() - filemtime($filename)) / 60) < $minutes;
    }
 
    function readFeed($feedURL) {
	// which URL to get
	global $manager, $blog, $saxparser, $CONF, $contents, $cache_age, $cache_time, $cache_path, $last_modified_time, $DIR_MEDIA;
	// get the cache path
	$cache_path    = $this->getOption(Cachedir);
	$cache_time    = $this->getOption(cacheTime);
	$feedURL_parts = parse_url($feedURL);
	$path          = isset($feedURL_parts["path"]) ? $feedURL_parts["path"] : "/";
	$filename      = isset($feedURL_parts["host"]) ? $feedURL_parts["host"] : "feedfile";
	$unique        = isset($feedURL_parts["query"]) ? $feedURL_parts["query"] : "";
	$filename      = $filename . $path . $unique;	
	$filename      = str_replace("/","_",$filename);
	$filename      = $DIR_MEDIA.$cache_path.$filename;
	$writedir      = $DIR_MEDIA.$cache_path;
	$contents      = "";
	$data          = "";
 
	    // create cache dir if non-excistent
        if (!@is_dir($writedir)){
	    if (!@mkdir($writedir, 0777))
		return _ERROR_BADPERMISSIONS;
	}
 
	if (!file_exists($filename)   ||
		(file_exists($filename) && !$this->isCurrent($filename, $cache_time))) {
 
	    $tag    = "";
	    $isItem = false;
	    $i      = 0;
	    unset($saxparser);
	    $saxparser = xml_parser_create();
 
	    xml_parser_set_option($saxparser, XML_OPTION_CASE_FOLDING, false);
	    xml_set_element_handler($saxparser, 'sax_start', 'sax_end');
	    xml_set_character_data_handler($saxparser, 'sax_data');
 
 
	    if (!function_exists('sax_start')) {
		function sax_start($parser, $name, $attribs) {
		    global $tag, $isItem, $i, $isChannel;
		    switch ($name){
			case "channel":
			    $i++;
			$isChannel = true;
			break;
			case "item":
			    $i++;
			$isItem = true;
			break;
			case "image";
			case "url";
			case "docs";
			case "language";
			case "generator";
			case "copyright";
			case "title":
			case "link":
			case "pubDate";
			case "description":
			case "author";
			case "category":
			case "guid":
			    if ($isItem || $isChannel) $tag = $name;
			    break;
			default:
			    $isItem = false;
			    $isChannel = false;
			    break;
		    }
		}
	    }
 
	    if (!function_exists('sax_end')) {
		function sax_end($parser, $name) {
		}
	    }
 
	    if (!function_exists('sax_data')) {
		function sax_data($parser, $data) {
		    global $tag, $isItem, $contents, $isChannel, $i;
		    if ($data != "\n" && $isItem) {
			switch ($tag) {
			    case "title";
			    case "link":
			    case "description":
				(!isset($contents[$i-1][$tag]) || !strlen($contents[$i-1][$tag])) ?
				$contents[$i-1][$tag] = addslashes($data) :
				$contents[$i-1][$tag].= addslashes($data);
			        break;
			}
		    } else if ($data != "\n" && $isChannel) {
			switch ($tag) {
			    case "title";
			        if ($tag == "title") {$tag = "sitetitle";}
				(!isset($contents[$i-1][$tag]) || !strlen($contents[$i-1][$tag])) ?
			            $contents[$i-1][$tag] = addslashes($data) :
			    	    $contents[$i-1][$tag] = addslashes($data);
				break;
			    case "url":
			    case "image":
			        if ($tag == "title") {$tag = "sitetitle";}
			        (!isset($contents[$i-1][$tag]) || !strlen($contents[$i-1][$tag])) ?
			            $contents[$i-1][$tag] = addslashes($data) :
				    $contents[$i-1][$tag] .= addslashes($data);
			        break;
                         }
                    }
		}
	    }
 
	    $fp = fopen($feedURL, "r");
	    while ($data = fread($fp, 4096)) {
		$parsedOkay = xml_parse($saxparser, $data, feof($fp));
 
		if (!$parsedOkay && xml_get_error_code($saxparser) != XML_ERROR_NONE) {
		    die("XML Error in File: ".xml_error_string(xml_get_error_code($saxparser)).
			    " at line ".xml_get_current_line_number($saxparser));
		}
	    }
 
	    xml_parser_free($saxparser);
	    fclose($fp);
 
	    $cache = @fopen($filename, "w");
	    if ($cache) {
		fwrite($cache, serialize($contents));
		fclose($cache);
	    }
	    $cache_age = 0;
	}
	else  {
	    $cache_age = ceil((time() - filemtime($filename)) / 60);
	    $fp = @fopen($filename, "r");
	    if ($fp) {
		$data = fread($fp, filesize($filename));
	    }
	    fclose($fp);
	    $contents = unserialize($data);
	}
	return $contents;
    }
}
?>