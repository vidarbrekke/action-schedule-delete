#!/bin/bash

echo "Clearing PHP OPcache..."
if command -v wp &> /dev/null; then
    wp eval 'if(function_exists("opcache_reset")) { opcache_reset(); echo "OPcache cleared.\n"; } else { echo "OPcache not available.\n"; }'
else 
    echo "WP-CLI not available. Please clear cache manually."
fi

# For Apache
if command -v apachectl &> /dev/null; then
    echo "Restarting Apache..."
    sudo apachectl restart
fi

# For PHP-FPM
if [[ -e /usr/local/etc/php-fpm.d/www.conf ]]; then
    echo "Restarting PHP-FPM..."
    sudo brew services restart php
elif [[ -e /etc/php-fpm.conf ]]; then
    echo "Restarting PHP-FPM..."
    sudo systemctl restart php-fpm
fi

echo "Clearing WordPress object cache..."
if command -v wp &> /dev/null; then
    wp cache flush
fi

echo "Restart complete." 