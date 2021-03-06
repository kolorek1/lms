<?php

/*
 * LMS version 1.11-git
 *
 *  (C) Copyright 2001-2012 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id: common.php,v 1.130 2012/01/02 11:01:28 alec Exp $
 */

// Common functions, that making it in class would be nonsense :)

// Execute a system program. return a trim()'d result.
// does very crude pipe checking.  you need ' | ' for it to work
// ie $program = execute_program('netstat', '-anp | grep LIST');
// NOT $program = execute_program('netstat', '-anp|grep LIST');

function bsd_grab_key ($key)
{
	return execute_program('sysctl', '-n '.$key);
}

function find_program ($program)
{
	$path = array('/bin', '/sbin', '/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin');
	while ($this_path = current($path))
	{
		if (is_executable($this_path.'/'.$program))
		{
			return $this_path.'/'.$program;
		}
		next($path);
	}
	return;
}

function execute_program ($program, $args = '')
{
	$buffer = '';
	$program = find_program($program);

	if (!$program) { return; }

	// see if we've gotten a |, if we have we need to do patch checking on the cmd

	if ($args)
	{
		$args_list = preg_split('/ /', $args);
		for ($i = 0; $i < count($args_list); $i++)
		{
			if ($args_list[$i] == '|')
			{
				$cmd = $args_list[$i + 1];
				$new_cmd = find_program($cmd);
				$args = preg_replace('/\| '.preg_quote($cmd, '/').'/', '| '.$new_cmd, $args);
			}
		}
	}

	// we've finally got a good cmd line.. execute it

	if ($fp = popen($program.' '.$args, 'r'))
	{
		while (!feof($fp))
		{
			$buffer .= fgets($fp, 4096);
		}
		return trim($buffer);
	}
}

function hostname()
{
	switch(PHP_OS)
	{
		case 'Linux':
			exec('hostname -f',$return);
			$hostname=$return[0];
			break;
		case 'Darwin':
		case 'FreeBSD':
		case 'OpenBSD':
		case 'NetBSD':
		case 'WinNT':
			exec('hostname',$return);
			$hostname=$return[0];
			break;
		default:
			$return = trans('unknown OS ($a)', PHP_OS);
	}
	
	if(!$hostname)
		$hostname = $_ENV['HOSTNAME'] ? $_ENV['HOSTNAME'] : $_SERVER['SERVER_NAME'];
	if(!$hostname)
		$hostname='N.A.';
		
	return $hostname;
}

function ip_long($sip)
{
	if(check_ip($sip)){
		return sprintf('%u',ip2long($sip));
	}else{
		return 0;
	}
}

function check_ip($ip)
{
	return (bool) preg_match('/^((25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)\.){3}(25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)$/', $ip);
}

function check_ipv6($ip)
{
        // fast exit for localhost
	if (strlen($ip) < 3)
	        return $IP == '::';
	
	// Check if part is in IPv4 format
	if (strpos($ip, '.')) {
		$lastcolon = strrpos($ip, ':');
		if (!($lastcolon && check_ip(substr($ip, $lastcolon + 1))))
		        return false;

		// replace IPv4 part with dummy
		$ip = substr($ip, 0, $lastcolon) . ':0:0';
	}
	
	// check uncompressed
	if (strpos($ip, '::') === false) {
		return preg_match('/^(?:[a-f0-9]{1,4}:){7}[a-f0-9]{1,4}$/i', $ip);
	}
	
	// check colon-count for compressed format
	if (substr_count($ip, ':') < 8) {
		return preg_match('/^(?::|(?:[a-f0-9]{1,4}:)+):(?:(?:[a-f0-9]{1,4}:)*[a-f0-9]{1,4})?$/i', $ip);
	}
	
	return false;
}

function check_mask($mask)
{
	$i=0;
	$j=0;
	$maskb=decbin(ip2long($mask));
	if (strlen($maskb) < 32)
		return FALSE;
	else
	{
		while (($i<32) && ($maskb[$i] == '1'))
		{
			$i++;
		}
		$j=$i+1;
		while (($j<32) && ($maskb[$j] == '0'))
		{
			$j++;
		}
		if ($j<32)
			return FALSE;
		else
			return TRUE;
	}
}

function getbraddr($ip,$mask)
{
	if(check_ip($ip) && check_mask($mask))
	{
		$net = ip2long(getnetaddr($ip, $mask));
		$mask = ip2long($mask);

		return long2ip($net | (~$mask));
	}
	else
		return false;
}

function getnetaddr($ip,$mask)
{
	if(check_ip($ip) && check_mask($mask))
	{
		$ip = ip2long($ip);
		$mask = ip2long($mask);

		return long2ip($ip & $mask);
	}
	else
		return false;
}

function prefix2mask($prefix)
{
	if($prefix>=0&&$prefix<=32)
	{	
		$out = '';
		for($ti=0;$ti<$prefix;$ti++)
			$out .= '1';
		for($ti=$prefix;$ti<32;$ti++)
			$out .= '0';
		return long2ip(bindec($out));
	}
	else
		return false;
}

function mask2prefix($mask)
{
	if(check_mask($mask))
	{
		return strlen(str_replace('0','',decbin(ip2long($mask))));
	}
	else
	{
		return -1;
	}
}

/*
 * mac checking function - requires macaddr passed as reference,
 * so it can fix mac address instantly to valid string
 */

function check_mac(&$macaddr)
{
	// save passed macaddr for future use
	
	$oldmac = $macaddr;

	// strip EVERYTHING that doesnt match 0-9 and a-f,
	// so $macaddr should contains 12 hex digits, and that's
	// will be base for our test

	$macaddr = preg_replace('/[^0-9a-f]/i', '', $macaddr);

	if(!preg_match('/^[0-9a-f]{12}$/i', $macaddr))
	{
		// mac address isn't valid, restore it (cause we working on
		// reference) and return false
	
		$macaddr = $oldmac;
	
		return FALSE;
	}
	else
	{
		// mac address is valid, return nice mac address that LMS
		// uses.

		$macaddr = $macaddr[0].$macaddr[1].':'.
			$macaddr[2].$macaddr[3].':'.
			$macaddr[4].$macaddr[5].':'.
			$macaddr[6].$macaddr[7].':'.
			$macaddr[8].$macaddr[9].':'.
			$macaddr[10].$macaddr[11];
		return TRUE;
	}
}

function textwrap($text, $wrap=76, $break = "\n")
{
	// This function is takem from newsportal

	$len = strlen($text);
	if ($len > $wrap)
	{
		$h = '';	// massaged text
		$lastWhite = 0; // position of last whitespace char
		$lastChar = 0;  // position of last char
		$lastBreak = 0; // position of last break
		// while there is text to process
		while ($lastChar < $len)
		{
			$char = substr($text, $lastChar, 1); // get the next character
			// if we are beyond the wrap boundry and there is a place to break
			if (($lastChar - $lastBreak > $wrap) && ($lastWhite > $lastBreak))
			{
				$h .= substr($text, $lastBreak, ($lastWhite - $lastBreak)) . $break;
				$lastChar = $lastWhite + 1;
				$lastBreak = $lastChar;
			}
			// You may wish to include other characters as valid whitespace...
			if ($char == ' ' || $char == chr(13) || $char == chr(10))
				$lastWhite = $lastChar; // note the position of the last whitespace
			
			$lastChar = $lastChar + 1; // advance the last character position by one
		}
		
		$h .= substr($text, $lastBreak); // build line
	} 
	else 
	{
		$h = $text; // in this case everything can fit on one line
	}
	return $h;
}

function isipin($ip,$net,$mask)
{
	if(ip_long($ip) > ip_long(getnetaddr($net,$mask)) && ip_long($ip) < ip_long(getbraddr($net,$mask)))
		return true;
	else
		return false;
}

function isipinstrict($ip,$net,$mask)
{
	if(ip_long($ip) >= ip_long(getnetaddr($net,$mask)) && ip_long($ip) <= ip_long(getbraddr($net,$mask)))
		return true;
	else
		return false;
}

function getmicrotime()
{
	// This function has been taken from PHP manual

	list($usec, $sec) = explode(' ',microtime());
	return ((float)$usec + (float)$sec);
}

function writesyslog($message,$type)
{
	// Untested on *BSD. Can anyone chek this out on *BSD machine? Thanx.

	switch(PHP_OS)
	{
		case 'OpenBSD':
		case 'Linux':
			$access = date('Y/m/d H:i:s');
			// open syslog, include the process ID and also send
			// the log to standard error, and use a user defined
			// logging mechanism
			openlog('lms-php', LOG_PID | LOG_NDELAY, LOG_AUTH);
			syslog($type,$message.' (at '.$access.' from '.$_SERVER['REMOTE_ADDR'].' ('.$_SERVER['HTTP_USER_AGENT'].'))');
			closelog();
		break;
		default:
			return FALSE;
		break;
	}

	return TRUE;
}

// Creates directories tree
function rmkdir($dir)
{
	if($dir[0]!='/')
		$dir = getcwd() . '/' . $dir;
	$directories = explode('/',$dir);
	$makedirs = 0;
	for($i=1;$i<sizeof($directories);$i++)
	{
		$cdir = '';
		for($j=1;$j<$i+1;$j++)
			$cdir .= '/'.$directories[$j];
		if(!is_dir($cdir))
		{
			$result = mkdir($cdir,0777);
			$makedirs ++;
		}
	}
	if(!$result && $makedirs)
		return $result;
	else
		return $makedirs;
}

// Deletes directory and all subdirs and files in it
function rrmdir($dir)
{
    $files = glob($dir . '/*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file))
            rrmdir($file);
        else
            unlink($file);
    }
    if (is_dir($dir))
        rmdir($dir);
}

function striphtml($text)
{
	$search = array ("'<script[^>]*?>.*?</script>'si",  // Strip out javascript
	"'<[\/\!]*?[^<>]*?>'si",		// Strip out html tags
	"'([\r\n])[\s]+'",			// Strip out white space
	"'&(quot|#34);'i",			// Replace html entities
	"'&(amp|#38);'i",
	"'&(lt|#60);'i",
	"'&(gt|#62);'i",
	"'&(nbsp|#160);'i",
	"'&(iexcl|#161);'i",
	"'&(cent|#162);'i",
	"'&(pound|#163);'i",
	"'&(copy|#169);'i",
	"'&#(\d+);'e");				// evaluate as php

	$replace = array ('',
	'',
	"\\1",
	'"',
	'&',
	'<',
	'>',
	' ',
	chr(161),
	chr(162),
	chr(163),
	chr(169),
	"chr(\\1)");

	return preg_replace ($search, $replace, $text);
}

function check_email( $email )
{
	$length = strlen( $email );

	if(
			!$email
			|| substr($email,0,1) == '@'
			|| substr($email,0,1) == '.'
			|| strrpos($email,'@') == ($length - 1)
			|| strrpos($email,'.') == ($length - 1)
			|| substr_count($email,'@') != 1
			|| !substr_count(substr($email,strpos($email,'@')),'.')
			|| substr_count($email,'..')
			|| ($length-strrpos($email,'.'))<3
	)
		return FALSE;

	$email_charset = 'qwertyuiopasdfghjklzxcvbnm1234567890@-._';
	$i = 0;
	while ( $i < $length )
	{
		$char = $email[$i++];
		if ( stristr( $email_charset, $char ) === false )
			return FALSE;
	}

	return TRUE;
}

function get_producer($mac)
{
	$mac = strtoupper(str_replace(':','-',substr($mac,0,8)));

	if (!$mac)
	    return '';

	if($macfile = fopen(LIB_DIR.'/ethercodes.txt','r'))
		while(!feof($macfile))
		{
			$line = trim(fgets($macfile,4096));
			if($line)
				list($prefix,$producer) = explode(':', $line);
			if($mac == $prefix)
				break;
		}
	fclose($macfile);
	return $producer;
}

function setunits($data)  // for traffic data
{
	if ( $data >= (1024*1024*1024*1024))
	{
		$number = $data / (1024*1024*1024*1024);
		$unit = "TiB";
	}
	elseif ( $data >= (1024*1024*1024))
	{
		$number = $data / (1024*1024*1024);
		$unit = "GiB";
	}
	elseif ( $data >= (1024*1024))
	{
		$number = $data / (1024*1024);
		$unit = "MiB";
	} 
	else
	{
		$number = $data / 1024;
		$unit = "KiB";
	}
	return array($number, $unit);
}

function r_trim($array)
{
	foreach($array as $key => $value)
		if(is_array($value))
			$array[$key] = r_trim($value);
		else
			$array[$key] = trim($value);
	
	return $array;
}

// config value testers
function isboolean($value)
{
	if(preg_match('/^(1|y|on|yes|true|tak|t|0|n|no|off|false|nie|enabled|disabled)$/i', $value))
		return TRUE;
	else
		return FALSE;
}

function moneyf($value)
{
	global $LANGDEFS, $_language;
	return sprintf($LANGDEFS[$_language]['money_format'], $value);
}

if (!function_exists('bcmod'))
{
    function bcmod( $x, $y )
    {
	// how many numbers to take at once? carefull not to exceed (int)
        $take = 5;   
	$mod = '';
        do
	{
	    $a = (int)$mod.substr( $x, 0, $take );
	    $x = substr( $x, $take );
	    $mod = $a % $y;   
	}
	while ( strlen($x) );
	    return (int)$mod;
    }
}

function docnumber($number=NULL, $template=NULL, $time=NULL, $ext_num='')
{
	$number = $number ? $number : 1;
	$template = $template ? $template : DEFAULT_NUMBER_TEMPLATE;
	$time = $time ? $time : time();
	
	// extended number part
	$result = str_replace('%I', $ext_num, $template);

	// main document number
	$result = preg_replace_callback('/%(\\d*)N/',
		function ($m) use ($number) {
			return sprintf('%0' . $m[1] . 'd', $number);
		}, $result);
	
	// time conversion specifiers
	return strftime($result, $time);
}

// our finance round
function f_round($value)
{
	$value = str_replace(',','.', $value);
	$value = round ( (float) $value, 2);
	return $value;
}

function fetch_url($url)
{
	$url_parsed = parse_url($url);
	$host = $url_parsed['host'];
	$path = $url_parsed['path'];
	$port = isset($url_parsed['port']) ? $url_parsed['port'] : 0; //sometimes port is undefined

	if ($port==0)
		$port = 80;
	if ($url_parsed['query'] != '')
		$path .= '?'.$url_parsed['query'];

	$request = "GET $path HTTP/1.0\r\nHost: $host\r\n\r\n";

	$fp = @fsockopen($host, $port, $errno, $errstr, 3);

	if(!$fp) return FALSE;

	if (!stream_set_timeout($fp, 3)) return FALSE;

	fwrite($fp, $request);
	$info = stream_get_meta_data($fp);
	if ($info['timed_out']) return FALSE;

	$body = FALSE;
	$out = '';

	while(!feof($fp))
	{
		$s = fgets($fp, 1024);
		$info = stream_get_meta_data($fp);
		if ($info['timed_out']) return FALSE;

		if($body)
			$out .= $s;
		if($s == "\r\n")
			$body = TRUE;
	}

	fclose($fp);
	return $out;
}

// quoted-printable encoding
function qp_encode($string)
{
        // ASCII only - don't encode
        if(!preg_match('#[\x80-\xFF]{1}#', $string))
        	return $string;

        $encoded = preg_replace_callback(
	'/([\x2C\x3F\x80-\xFF])/',
	create_function('$m', 'return "=".sprintf("%02X", ord($m[1]));'),
	$string);
        
        // replace spaces with _
        $encoded = str_replace(' ', '_', $encoded);

        return '=?UTF-8?Q?'.$encoded.'?=';
}

// escape quotes and backslashes, newlines, etc.
function escape_js($string)
{
    	return strtr($string, array('\\'=>'\\\\',"'"=>"\\'",'"'=>'\\"',"\r"=>'\\r',"\n"=>'\\n','</'=>'<\/'));
}

function lms_ucwords($str)
{
	$result = array();
	$arr = preg_split('/\s+/', $str);
	
	foreach($arr as $word)
		$result[] = mb_strlen($word) > 1 ? mb_convert_case($word, MB_CASE_TITLE) : $word;
	
	return implode(' ', $result);
}

// replace national character with ASCII equivalent
function clear_utf($str)
{
	$r = '';
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
	for ($i=0, $len=strlen($s); $i<$len; $i++)
	{
		$ch1 = $s[$i];
		$ch2 = mb_substr($str, $i, 1);
		$r .= $ch1=='?' ? $ch2 : $ch1;
	}
	return $r;
}

function lastonline_date($timestamp)
{
    global $CONFIG;

    if (!$timestamp)
        return null;

    $delta = time()-$timestamp;
    if ($delta > $CONFIG['phpui']['lastonline_limit']) {
        if($delta>59)
            return trans('$a ago ($b)', uptimef($delta), date('Y/m/d, H:i', $timestamp));
        else
            return date('(Y/m/d, H:i)', $timestamp);
    }

    return trans('online');
}

function is_leap_year($year)
{
    if ($year % 4) return false;
    if ($year % 100) return true;
    if ($year % 400) return false;
    return true;
}

function truncate_str($string, $length, $etc='...')
{
    if ($length == 0)
        return '';

    if (mb_strlen($string) > $length) {
        $length -= min($length, mb_strlen($etc));
        return mb_substr($string, 0, $length) . $etc;
    } else {
        return $string;
    }
}

function location_str($data)
{
    $location = $data['city_name'];

    if ($data['location_flat']) {
        $h = $CONFIG['phpui']['house_template'] ? $CONFIG['phpui']['house_template'] : '%h/%f';
        $h = str_replace('%h', $data['location_house'], $h);
        $h = str_replace('%f', $data['location_flat'], $h);
    }
    else
        $h = $data['location_house'];

    if ($data['street_name']) {
        $street = $data['street_type'] .' '. $data['street_name'];
        $location .= ($location ? ', ' : '') . $street;
    }

    if ($h)
        $location .= ' ' . $h;

    return $location;
}

function set_timer($label = 0)
{
    $GLOBALS['lms_timer'][$label] = microtime(true);
}

function get_timer($label = 0)
{
    return sprintf('%.4f', microtime(true) - $GLOBALS['lms_timer'][$label]);
}

/* Functions for modularized LMS */
function plugin_handle($name)
{
    global $PLUGINS;

	if(isset($PLUGINS[$name]))
		foreach($PLUGINS[$name] as $plugin)
			include($plugin);
}

function clearheader()
{
    global $ExecStack, $layout;

	$ExecStack->replaceTemplate('core', 'header', 'core', 'clearheader');
	//$ExecStack->dropTemplate('core', 'menu');
}

/*
	Registering "plugin" function is for use in actions.
	$handle - handle name
	$plugin - template or action for including in handle. Example of use:

	register_plugin('nodeinfobox-end', '/path/sometemplate.html');
*/
function register_plugin($handle, $plugin)
{
        global $PLUGINS;

        $PLUGINS[$handle][] = $plugin;
}

function html2pdf($content, $subject=NULL, $title=NULL, $type=NULL, $id=NULL, $orientation='P', $margins=array(5, 10, 5, 10), $save=false, $copy=false)
{
	global $layout, $DB;
	require_once(LIB_DIR.'/html2pdf/html2pdf.class.php');

	if (isset($margins))
		if (!is_array($margins))
			$margins = array(5, 10, 5, 10); /* default */

	$html2pdf = new HTML2PDF($orientation, 'A4', 'en', true, 'UTF-8', $margins);
	/* disable font subsetting to improve performance */
	$html2pdf->pdf->setFontSubsetting(false);

	if ($id) {
		$info = $DB->GetRow('SELECT di.name, di.description FROM divisions di
			LEFT JOIN documents d ON (d.divisionid = di.id)
			WHERE d.id = ?', array($id));
	}

	$html2pdf->pdf->SetProducer('LMS Developers');
	$html2pdf->pdf->SetCreator('LMS '.$layout['lmsv']);
	if ($info)
		$html2pdf->pdf->SetAuthor($info['name']);
	if ($subject)
		$html2pdf->pdf->SetSubject($subject);
	if ($title)
		$html2pdf->pdf->SetTitle($title);

	$html2pdf->pdf->SetDisplayMode('fullpage', 'SinglePage', 'UseNone');
	$html2pdf->AddFont('arial', '', 'arial.php');
	$html2pdf->AddFont('arial', 'B', 'arialb.php');
	$html2pdf->AddFont('arial', 'I', 'ariali.php');
	$html2pdf->AddFont('arial', 'BI', 'arialbi.php');
	$html2pdf->AddFont('times', '', 'times.php');

	/* if tidy extension is loaded we repair html content */
	if (extension_loaded('tidy')) {
		$config = array(
			'indent' => true,
			'output-html' => true,
			'indent-spaces' => 4,
			'join-styles' => true,
			'join-classes' => true,
			'fix-bad-comments' => true,
			'fix-backslash' => true,
			'repeated-attributes' => 'keep-last',
			'drop-proprietary-attribute' => true,
			'sort-attributes' => 'alpha',
			'hide-comments' => true,
			'new-blocklevel-tags' => 'page, page_header, page_footer, barcode',
			'wrap' => 200);

		$tidy = new tidy;
		$content = $tidy->repairString($content, $config, 'utf8');
	}

	$html2pdf->WriteHTML($content);

	if ($copy) {
		/* add watermark only for contract & annex */
		if(($type == DOC_CONTRACT) || ($type == DOC_ANNEX)) {
			$html2pdf->AddFont('courier', '', 'courier.php');
			$html2pdf->AddFont('courier', 'B', 'courierb.php');
			$html2pdf->pdf->SetTextColor(255, 0, 0);

			$PageWidth = $html2pdf->pdf->getPageWidth();
			$PageHeight = $html2pdf->pdf->getPageHeight();
			$PageCount = $html2pdf->pdf->getNumPages();
			$txt = trim(preg_replace("/(.)/i", "\${1} ", trans('COPY')));
			$w = $html2pdf->pdf->getStringWidth($txt, 'courier', 'B', 120);
			$x = ($PageWidth / 2) - (($w / 2) * sin(45));
			$y = ($PageHeight / 2) + 50;

			for($i = 1; $i <= $PageCount; $i++) {
				$html2pdf->pdf->setPage($i);
				$html2pdf->pdf->SetAlpha(0.2);
				$html2pdf->pdf->SetFont('courier', 'B', 120);
				$html2pdf->pdf->StartTransform();
				$html2pdf->pdf->Rotate(45, $x, $y);
				$html2pdf->pdf->Text($x, $y, $txt);
				$html2pdf->pdf->StopTransform();
			}
			$html2pdf->pdf->SetAlpha(1);
		}
	}

	if(($type == DOC_CONTRACT) || ($type == DOC_ANNEX)) {
		/* set signature additional information */
		$info = array(
			'Name' => $info['name'],
			'Location' => $subject,
			'Reason' => $title,
			'ContactInfo' => $info['description'],
		);

		/* setup your cert & key file */
		$cert = 'file://'.LIB_DIR.'/tcpdf/config/lms.cert';
		$key = 'file://'.LIB_DIR.'/tcpdf/config/lms.key';

		/* set document digital signature & protection */
		if (file_exists($cert) && file_exists($key)) {
			$html2pdf->pdf->setSignature($cert, $key, 'lms-documents', '', 1, $info);
		}
	}

	$html2pdf->pdf->SetProtection(array('modify', 'annot-forms', 'fill-forms', 'extract', 'assemble'), '', PASSWORD_CHANGEME, '1');

	if ($save) {
		if (function_exists('mb_convert_encoding'))
			$filename = mb_convert_encoding($title, "ISO-8859-2", "UTF-8");
		else
			$filename = iconv("UTF-8", "ISO-8859-2//TRANSLIT", $title);
		$html2pdf->Output($filename.'.pdf', 'D');
	} else {
		$html2pdf->Output();
	}
}

function is_natural($var) {
	return preg_match('/^[1-9][0-9]*$/', $var);
}

function check_password_strength($password) {
	return (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)
		&& preg_match('/[0-9]/', $password) && mb_strlen($password) >= 8);
}

function access_denied() {
	global $SMARTY, $SESSION;

	$SMARTY->display('noaccess.html');
	$SESSION->close();
	die;
}

function addLogs($message = NULL, $string = NULL, $diff = NULL)
{
	global $DB, $AUTH;
	if (empty($message)) return NULL;
	
	$cid = 0;
	$nid = 0;
	$oid = 0;
	$uid = ($AUTH->id ? $AUTH->id : 0);
	$event = _ADD_;
	$module = MOD_OTHER;
	
	if (!is_null($diff) && !empty($diff))
	{
		if (is_array($diff)) 
		    $diff = serialize($diff);
		else 
		    $diff = NULL;
	}
	else 
	    $diff = NULL;
	
	if (!empty($string) && is_string($string))
	{
		$string = strtolower($string);
		$tmp = explode(';',$string);
		$count = sizeof($tmp);
		for ( $i = 0; $i < $count; $i++ ) if (!empty($tmp[$i]))
		{
			$t = explode('=',$tmp[$i]);
			switch ($t[0]) 
			{
				case 'c'	: // customerid
				case 'cus'	:
				case 'cid'	:
				case 'customer' :
						$cid = (int)$t[1]; 
				break;
				
				case 'n'	: // nodeid
				case 'nid'	:
				case 'node'	:
						$nid = (int)$t[1]; 
				break;
				
				case 'u'	:
				case 'uid'	:
				case 'user'	:
						$uid = (int)$t[1];
				break;
				
				case 'id'	:
				case 'oid'	:
						$oid = intval($t1[1]);
				break;
				
				case 'e'	: // event
				case 'event'	:
						if (in_array($t[1],array('add','rm','del','up','mov','err','inf','warn','acl')))
							switch ($t[1])
							{
								case 'add'	: $event = _ADD_;	break;
								case 'rm'	: 
								case 'del'	: $event = _RM_;	break;
								case 'up'	: $event = _UP_;	break;
								case 'mov'	: $event = _MOV_;	break;
								case 'err'	: $event = _ERR_;	break;
								case 'inf'	: $event = _INF_;	break;
								case 'warn'	: $event = _WARN_;	break;
								case 'acl'	: $event = _ACL_;	break;
							} // end switch ($t[1]) 
				break;
				
				case 'm'	: //modules
				case 'mod'	:
				case 'module'	:
						switch($t[1])
						{
							case 'oth'	: case 'other'		: $module = MOD_OTHER;		break;
							case 'cus'	: case 'customer'	: $module = MOD_CUSTOMER;	break;
							case 'node'				: $module = MOD_NODE;		break;
							case 'netdev'				: $module = MOD_NETDEV;		break;
							case 'fin'	: case 'finances'	: $module = MOD_FINANCES;	break;
							case 'doc'	: case 'documents'	: $module = MOD_DOCUMENTS;	break;
							case 'hd'	: case 'helpdesk'	: $module = MOD_HELPDESK;	break;
							case 'tt'	: case 'timetable'	: $module = MOD_TIMETABLE;	break;
							case 'conf'	: case 'config'		: $module = MOD_CONFIG;		break;
							case 'admin'				: $module = MOD_ADMIN;		break;
							case 'voip'				: $module = MOD_VOIP;		break;
							case 'hosting'				: $module = MOD_HOSTING;	break;
							case 'sl'	: case 'syslog'		: $module = MOD_SYSLOG;		break;
							case 'mon'	: case 'monitoring'	: $module = MOD_MONITORING;	break;
							case 'mag'	: case 'magazyn'	: $module = MOD_MAGAZYN;	break;
							case 'con'	: case 'contractor'	: $module = MOD_CONTRACTOR;	break;
							case 'dem'	: case 'daemon'		: $module = MOD_DAEMON;		break;
							case 'tariff'				: $module = MOD_TARIFF;		break;
							case 'cc'	: case 'callcenter'	: $module = MOD_CALLCENTER;	break;
						}
				break;
			}
		}
	}
	// tymczasowo bez diff'a
	$diff = null;
	if (!$DB->Execute('INSERT INTO syslog (cdate, uid, cid, nid, module, event, msg, diff, oid) VALUES (?,?,?,?,?,?,?,?,?) ;',array(time(),$uid,$cid,$nid,$module,$event,$message,$diff,$oid)))
	{
		$DB->LockTables('syslog');
		$DB->Execute('INSERT INTO syslog (cdate, uid, cid, nid, module, event, msg, diff, oid) VALUES (?,?,?,?,?,?,?,?,?) ;',array(time(),$uid,$cid,$nid,$module,$event,$message,$diff,$oid));
		$DB->UnlockTables();
	}
	return $DB->GetLastInsertID('syslog');
} // end addlogs

function check_modules($mod = NULL)
{
    global $AUTH;
    
    if (empty($mod) || is_null($mod)) 
	return TRUE;
    
    if (empty($AUTH->nomodules)) 
	return TRUE;
    
    if (in_array($mod,$AUTH->nomodules)) 
	return FALSE;

    return TRUE;
}

function protect_email($email)
{
    $output = '';
    if ($email !='')
	for ($i=0; $i<strlen($email); $i++)
	    $output .= '&#'.ord($email[$i]).';';
    return $output;
}

function get_user_login($id)
{
    global $DB;
    if ($tmp = $DB->GetOne('SELECT login FROM users WHERE id = ? LIMIT 1;',array(intval($id))))
    return $tmp;
    else
    return NULL;
}

function list_dir($dir,$maska=NULL)
{
	    $lista = array();
	    if (!is_null($maska)) $maska = '.'.$maska;
	    if (is_dir($dir))
	    {
		foreach(array_diff(scandir($dir),array('.','..')) as $file)
		{
		    if (!is_null($maska))
		    {
			if ($myfile = stristr($file,$maska)) $lista[] = $file;
			
		    } else $lista[] = $file;
		}
	    }
	    return $lista;
}

function seconds_to_hours($seconds)
{
    if ($seconds<=0) return '00:00:00';
    $h = intval($seconds/pow(60,2));
    $m = intval($seconds/60)%60;
    $s = $seconds%60;
    return sprintf("%02d:%02d:%02d",$h,$m,$s);
}

function check_date($data) {
	$data = str_replace('-','/',$data);
	if(preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/',$data))
	{
		list($y, $m, $d) = explode('/', $data);
		if(checkdate($m, $d, $y))
			return TRUE;
		else
			return FALSE;
	} else
	    return FALSE;
}


function check_natural($val) {

    if(!empty($val)) {
	if (!preg_match('/^[0-9]+\.[0-9]{1,2}+$/ ', $val) && !preg_match('/^[0-9]+$/', $val))
	    return FALSE;
	else
	    return TRUE;
    } else
	return FALSE;
}

function check_fileexists($filename)
{
    if (file_exists($filename) && is_readable($filename))
	return true;
    else
	return false;
}


function calculate_distance_gps($latitude1,$longitude1,$latitude2,$longitude2,$miles=false)
{
    if ($miles) $out = 60;
    else $out = 111.195;
    $distance = (rad2deg(acos((sin(deg2rad($latitude1)) * sin(deg2rad($latitude2))) + (cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($longitude1 - $longitude2))))) * $out);
    $distance = str_replace(',','.',$distance);
    $distance = sprintf('%0.1f',$distance);
    $distance = str_replace(',','.',$distance);
    return $distance;
}

function addRight($module,$list)
{
    
}

?>