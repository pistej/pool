FROM phpswoole/swoole:php8.3

# Install pcov for PHPUnit code coverage
RUN pecl install pcov && docker-php-ext-enable pcov

# Set working directory to /var/www for mounted code execution
WORKDIR /var/www
