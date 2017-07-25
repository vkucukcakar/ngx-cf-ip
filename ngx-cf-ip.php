#!/usr/bin/env php
<?php
###
# ngx-cf-ip
# Cloudflare IP updater for Nginx ngx_http_realip_module
# Copyright (c) 2017 Volkan Kucukcakar
#
# ngx-cf-ip is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# ngx-cf-ip is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# This copyright notice and license must be retained in all files and derivative works.
###

class ngx_cf_ip {

	# Short name
	private static $app_name="ngx-cf-ip";
	# Version
	private static $app_version="1.0.0";
	# Description
	private static $app_description="Cloudflare IP Updater for Nginx ngx_http_realip_module";
	# PID file
	private static $pid_file="/var/run/ngx-cf-ip.pid";

	# The following properties reflect command-line parameters also

	# Update
	public static $update=false;
	# Force update
	public static $force=false;
	# Reload Nginx (true|false)
	public static $reload=false;
	# Nginx reload command
	public static $command="nginx -s reload";
	# Timeout (Seconds)
	public static $timeout=30;
	# No certificate check (true|false)
	public static $nocert=false;
	# Output filename
	public static $output="";
	# IP list download URLs separated by space
	public static $sources="https://www.cloudflare.com/ips-v4 https://www.cloudflare.com/ips-v6";
	# Display help
	public static $help=false;
	# Display version and license information
	public static $version=false;


	/*
	* Shutdown callback
	*/
	static function shutdown() {
		unlink(self::$pid_file);
	}// function

	/*
	* Custom error exit function that writes error string to STDERR and exits with 1
	*/
	static function error($error_str) {
		fwrite(STDERR, $error_str);
		exit(1);
	}// function

	/*
	* Display version and license information
	*/
	static function version() {
		echo self::$app_name." v".self::$app_version."\n"
			.self::$app_description."\n"
			."Copyright (c) 2017 Volkan Kucukcakar \n"
			."License GPLv2+: GNU GPL version 2 or later\n"
			." <https://www.gnu.org/licenses/gpl-2.0.html>\n"
			."Use option \"-h\" for help\n";
		exit;
	}// function

	/*
	* Display help
	*/
	static function help($long=false) {
		echo self::$app_name."\n".self::$app_description."\n";
		if ($long) {
			echo "Usage: ".self::$app_name.".php [OPTIONS]\n\n"
				."Available options:\n"
				." -u, --update *\n"
				."     Download IP lists and update the configuration files\n"
				." -f, --force\n"
				."     Force update\n"
				." -r, --reload\n"
				."     Make Nginx reload configuration\n"
				." -c <command>, --command=<command>\n"
				."     Set Nginx reload command\n"
				." -t <seconds>, --timeout=<seconds>\n"
				."     Set download timeout\n"
				." -n, --nocert\n"
				."     No certificate check\n"
				." -o <filename>, --output=<filename> *\n"
				."     Write output to a new Nginx configuration file\n"
				." -s <urls>, --sources=<urls>\n"
				."     Override download sources (space separated URLs)\n"
				." -v, --version\n"
				."     Display version and license information\n"
				." -h, --help\n"
				."     Display help\n"
				."\nExamples:\n"
				.self::$app_name.".php -u -r -o \"/tmp/cf.conf\"\n"
				.self::$app_name.".php -u --reload --command=\"nginx -s reload\" --output=\"/tmp/cf.conf\"\n"
				."\n";
			exit;
		} else {
			echo "Use option \"-h\" for help\n";
			exit (1);
		}
	}// function

	/*
	* Update IP addresses
	*/
	static function update() {
		// Download ip list
		$options = array(
			'http' => array(
				'timeout' => self::$timeout
			),
			'ssl' => array(
				'verify_peer' => ! self::$nocert
			)
		);
		$context  = stream_context_create($options);
		$raw_data='';
		foreach (preg_split('~\s+~',self::$sources) as $url) {
			$download_data=file_get_contents($url, false, $context);
			if (false!==$download_data) {
				$raw_data.=$download_data."\n";
			} else {
				self::error("Error: Download failed: ".$url."\n");
			}
		}
		// Parse ip list
		$raw_ip_list=preg_split('~\r?\n+~is',$raw_data);
		$ip_list=array();
		foreach ($raw_ip_list as $value) {
			// remove comments if there any
			$ip=preg_replace('~[\s]*[;#].*$~','',trim($value));
			// validate ip after extracting mask
			list($x) = explode("/",$ip);
			if (!filter_var($x, FILTER_VALIDATE_IP) === false) {
				$ip_list[]=$ip;
			}
		}//foreach
		// Validate ip list
		if (count($ip_list)<3) self::error("Error: IP list downloaded is not valid.\n");
		// Calculate hash of ip list
		$ip_list_hash=sha1(implode('',$ip_list));
		// Check if ip list updated and service reload required (change detected)
		$conf_old=(file_exists(self::$output)) ? (string) @file_get_contents(self::$output) : '';
		if ( (!self::$force) && (''!=$conf_old) && (preg_match('~### ngx-cf-ip BLOCK START ###.*### HASH ([^ ]+) ###.*### ngx-cf-ip BLOCK END ###~is',$conf_old,$matches)) && ($ip_list_hash==$matches[1]) ) {
			// IP list not updated
			echo "No changes detected, IP list is up to date.\n";
		} else {
			// Create/update configuration file data
			$block="\n### ngx-cf-ip BLOCK START ###\n";
			$block.="# WARNING:\n";
			$block.="#  Please do not manually edit this block as any update will overwrite changes.\n";
			$block.="#  Please do not touch the markers.\n";
			$block.="# Generated at ".date('Y-m-d H:i')." by ngx-cf-ip\n";
			foreach ($ip_list as $value) {
				$block.="set_real_ip_from ".$value.";\n";
			}
			$block.="### HASH ".$ip_list_hash." ###\n";
			$block.="### ngx-cf-ip BLOCK END ###\n";
			// Replace or add block
			$conf_new=preg_replace('~\n?### ngx-cf-ip BLOCK START ###.*### ngx-cf-ip BLOCK END ###\n?~is', $block, $conf_old, -1, $replaced);
			if (! $replaced) {
				$conf_new=$conf_old.$block;
			}
			// Write output file
			if (false!==file_put_contents(self::$output,$conf_new)) {
				echo"Updated IP list.\n";
			} else {
				self::error("Error: Output file \"".self::$output."\" could not be written.\n");
			}
			// Reload nginx
			if (self::$reload) {
				passthru(self::$command, $return_var);
				if (! $return_var) {
				    echo"Realod command successfull.\n";
				} else {
				    self::error("Error: Reload command failed.\n");
				}
			}
		}// if
	}// function

	/*
	* Initial function to run
	*/
	static function run() {
		// Set error reporting to report all errors except E_NOTICE
		error_reporting(E_ALL ^ E_NOTICE);
		// Set script time limit just in case
		set_time_limit(900);
		// Set script memory limit just in case
		ini_set('memory_limit', '32M');
		// PHP version check
		if (version_compare(PHP_VERSION, '5.3.0', '<')) {
			self::error("Error: This application requires PHP 5.3.0 or later to run. PHP ".PHP_VERSION." found. Please update PHP-CLI.\n");
		}
		// Single instance check
		$pid=@file_get_contents(self::$pid_file);
		if (false!==$pid) {
			// Check if process is running using POSIX functions or checking for /proc/PID as a last resort
			$pid_running=(function_exists('posix_getpgid')) ? (false!==posix_getpgid($pid)) : file_exists('/proc/'.$pid);
			if ($pid_running) {
				self::error("Error: Another instance of script is already running. PID:".$pid."\n");
			} else {
				//process is not really running, delete pid file
				unlink(self::$pid_file);
			}
		}
		file_put_contents(self::$pid_file,getmypid());
		register_shutdown_function(array(__CLASS__, 'shutdown'));
		// Load "openssl" required for file_get_contents() from "https"
		if ( (!extension_loaded('openssl'))&&(function_exists('dl')) ) {
			dl('openssl.so');
		}
		// Check if allow_url_fopen enabled
		if ( 0==ini_get("allow_url_fopen") ) self::error("Error: 'allow_url_fopen' is not enabled in php.ini for php-cli.\n");
		// Check if openssl loaded
		if (!extension_loaded('openssl')) self::error("Error: 'openssl' extension is not loaded php.ini for php-cli and cannot be loaded by dl().\n");
		// Parse command line arguments and gets options
		$options=getopt("ufrc:t:no:s:vh", array("update", "force", "reload", "command:", "timeout:", "nocert", "output:", "sources:", "version", "help"));
		$stl=array("u"=>"update", "f"=>"force", "r"=>"reload", "c"=>"command", "t"=>"timeout", "n"=>"nocert", "o"=>"output", "s"=>"sources", "v"=>"version", "h"=>"help");
		foreach ($options as $key=>$value) {
			if (1==strlen($key)) {
				// Translate short command line options to long ones
				self::$$stl[$key]=($value<>'') ? $value : true;
			} else {
				// Set class variable using option value or true if option do not accept a value
				self::$$key=($value<>'') ? $value : true;
			}
		}
		// Keep timeout value in a meaningful range
		self::$timeout=(self::$timeout<=300) ? ((self::$timeout>5) ? self::$timeout : 5) : 300;
		// Display version and license information
		if (self::$version)
			self::version();
		// Display long help & usage (on demand)
		if (self::$help)
			self::help(true);
		// Display short help (on error)
		if (! self::$update || ''==self::$output)
			self::help(false);
		// Update IP addresses
		self::update();
	}// function

}// class

ngx_cf_ip::run();
