FROM php:8.2-apache

# Avoid prompt questions during package installations
ENV DEBIAN_FRONTEND=noninteractive

# Install essential command utilities and packages
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    python3 \
    jq \
    nano \
    vim \
    openssh-server \
    procps \
    net-tools \
    iproute2 \
    sudo \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache port to 8080 (matching config.json)
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Grant www-data user sudo permissions to run the challenge setup scripts without password
RUN echo "www-data ALL=(ALL) NOPASSWD: /app/scripts/*" >> /etc/sudoers

# Set up working directory
WORKDIR /app

# Copy files
COPY . /app

# Symlink web application to Apache document root
RUN rm -rf /var/www/html && ln -s /app/src/web /var/www/html

# Grant execution rights on scripts
RUN chmod +x /app/src/scripts/entry.sh
RUN chmod +x /app/scripts/*.sh

ENTRYPOINT ["/bin/bash", "/app/src/scripts/entry.sh"]
