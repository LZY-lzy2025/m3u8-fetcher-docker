# 使用官方的PHP镜像作为基础镜像，并包含Apache
FROM php:8.2-apache

USER root

# 【关键配置】设置环境变量，告诉 Puppeteer 不要下载浏览器，并使用系统已安装的 Chromium
ENV PUPPETEER_SKIP_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# 安装系统依赖
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Puppeteer/Chromium 运行时依赖
    libnss3 \
    libatk-bridge2.0-0 \
    libdrm2 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libgbm1 \
    libxss1 \
    libasound2 \
    ca-certificates \
    fonts-liberation \
    # 系统工具
    wget \
    gnupg \
    # Node.js 和 npm
    nodejs \
    npm \
    # Chromium 浏览器
    chromium \
    # 清理 apt 缓存
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 验证 Chromium 是否安装成功
RUN chromium --version

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 【调整位置】在这里执行需要 root 权限的 Apache 配置
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 【修复权限】创建 npm 缓存目录并将其所有权设置为 www-data 用户
RUN mkdir -p /var/www/.npm && chown -R www-data:www-data /var/www/.npm

# 切换回 www-data 用户进行应用层操作
USER www-data

# 复制 package.json 并安装 Node.js 依赖
COPY --chown=www-data:www-data package.json ./
RUN npm install

# 复制项目代码
COPY --chown=www-data:www-data . .
