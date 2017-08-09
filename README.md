# ngx-cf-ip

Cloudflare IP updater for Nginx ngx_http_realip_module

* Downloads Cloudflare IPv4 and IPv6 lists and merge
* IP address and list validation just in case
* Creates a new nginx configuration file using set_real_ip_from directives and IP addresses
* Configuration file ready to be included
* Reloads Nginx


## Requirements

* PHP-CLI with openssl extension
* Nginx with ngx_http_realip_module


## Installation

* Install PHP-CLI with openssl extension if not installed (OS dependent)

* Install Nginx with ngx_http_realip_module if not installed (OS dependent)

* Install ngx-cf-ip.php to an appropriate location and give execute permission

	$ cd /usr/local/src/
	
	$ git clone https://github.com/vkucukcakar/ngx-cf-ip.git
	
	$ cp ngx-cf-ip/ngx-cf-ip.php /usr/local/bin/
	
* Give execute permission if not cloned from github

	$ chmod +x /usr/local/bin/ngx-cf-ip.php

## Usage

Usage: ngx-cf-ip.php [OPTIONS]

Available options:

-u, --update                       *: Download IP lists and update the configuration files

-f, --force                         : Force update

-r, --reload                        : Make Nginx reload configuration

-c <command>, --command=<command>   : Set Nginx reload command

-t <seconds>, --timeout=<seconds>   : Set download timeout

-n, --nocert                        : No certificate check

-o <filename>, --output=<filename> *: Write output to a new Nginx configuration file

-s <urls>, --sources=<urls>         : Override download sources (space separated URLs)

-v, --version                       : Display version and license information

-h, --help                          : Display usage

 
## Examples

	$ ngx-cf-ip.php -u -r -o "/etc/nginx/cf.conf"
	$ ngx-cf-ip.php -u --reload --command="nginx -s reload" --output="/etc/nginx/cf.conf"

### Nginx Configuration

	real_ip_header CF-Connecting-IP;
	real_ip_recursive off;
	include /etc/nginx/cf.conf;

	
## Caveats

* Nginx reload command can be platform dependent. That's why there is a --command parameter implemented.

## Docker users

* There is also a ready to use Alpine based Docker image for this tool at Docker hub: [vkucukcakar/ngx-cf-ip](https://hub.docker.com/r/vkucukcakar/ngx-cf-ip/ )
* Of course you can use your own shared cron container if you do not want to use the official Docker image.
