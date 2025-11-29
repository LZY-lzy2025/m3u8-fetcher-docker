# 使用官方的PHP镜像作为基础镜像，并包含Apache
FROM php:8.2-apache

# 切换到root用户进行系统安装
USER root

# 1. 安装系统依赖和无头浏览器Chromium
#    - git, curl 用于Composer
#    - chromium 是我们需要的浏览器
#    - 其他各种是Puppeteer在Linux下运行所需的依赖库
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libnss3 \
    libatk-bridge2.0-0 \
    libdrm2 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libgbm1 \
    libxss1 \
    libasound2 \
    chromium \
    && rm -rf /var/lib/apt/lists/*

# 2. 安装Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 3. 切换回Apache默认用户，后续操作都在此用户下进行
USER www-data

# 4. 复制 package.json 并安装 Node.js 依赖
COPY --chown=www-data:www-data package.json ./
RUN npm install

# 5. 复制项目代码
#    --chown确保文件所有权正确
COPY --chown=www-data:www-data . .

# 6. Apache配置：启用.htaccess重写和设置正确的目录权限
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
