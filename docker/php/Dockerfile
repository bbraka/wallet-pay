FROM php:8.3-fpm

# Arguments
ARG NODE_VERSION=20

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    sudo \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libssl-dev \
    libmariadb-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libxslt1-dev \
    libmemcached-dev \
    sassc \
    gnupg \
    ca-certificates \
    xvfb \
    # Java Runtime Environment for OpenAPI generator
    default-jre \
    # Playwright/Chrome dependencies
    libnss3 \
    libnspr4 \
    libdbus-1-3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libgbm1 \
    libxkbcommon0 \
    libgtk-3-0 \
    libasound2 \
    fonts-liberation \
    libxshmfence1 \
    libx11-xcb1 \
    libxcb-dri3-0 \
    libxss1 \
    libgconf-2-4 \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-xpm

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    xml \
    curl \
    intl \
    xsl \
    opcache \
    sockets \
    soap

# Install PECL extensions
RUN pecl install redis memcached \
    && docker-php-ext-enable redis memcached

# Install global npm packages
RUN npm install -g @anthropic-ai/claude-code @google/gemini-cli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u 1000 -d /home/devuser devuser
RUN mkdir -p /home/devuser/.composer && \
    chown -R devuser:devuser /home/devuser

# Add www-data to sudoers for necessary operations (no password required)
RUN echo 'www-data ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers
RUN echo 'devuser ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers

# Copy existing application directory permissions
COPY --chown=devuser:www-data . /var/www/html

# Ensure Laravel storage directories have proper permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy and setup entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install npm dependencies as devuser first
USER devuser
RUN cd /var/www/html && \
    if [ -f package.json ]; then \
        npm install; \
    fi

# Install Google Chrome for both Laravel Dusk and Playwright MCP
USER root
RUN wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | apt-key add - && \
    echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list && \
    apt-get update && \
    apt-get install -y google-chrome-stable && \
    rm -rf /var/lib/apt/lists/*

# Install Playwright Chrome only (since we're using Chrome for everything)
RUN cd /var/www/html && \
    if [ -f package.json ]; then \
        npx playwright install chrome --with-deps; \
    fi

# Set up Playwright environment and permissions
ENV PLAYWRIGHT_BROWSERS_PATH=/home/devuser/.cache/ms-playwright
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=false
RUN mkdir -p /home/devuser/.cache/ms-playwright && \
    chown -R devuser:devuser /home/devuser/.cache

# Switch back to devuser for runtime
USER devuser

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
