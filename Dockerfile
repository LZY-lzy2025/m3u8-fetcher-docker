# 使用官方的PHP镜像作为基础镜像，并包含Apache
FROM php:8.2-apache

USER root

# 【关键优化】使用 --no-install-recommends 来避免安装非必要的软件包，以加快构建速度和减小镜像体积
# 合并所有系统安装到一个 RUN 指令中，以减少 Docker 镜像层数
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
    # Chromium 浏览器
    chromium \
    # 清理 apt 缓存以减小镜像大小
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 验证 Chromium 是否安装成功
RUN chromium --version

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 切换回 www-data 用户进行应用层操作
USER www-data

# 复制 package.json 并安装 Node.js 依赖
COPY --chown=www-data:www-data package.json ./
RUN npm install

# 复制项目代码
COPY --chown=www-data:www-data . .

# Apache 配置
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
