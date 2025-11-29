# -----------------------------------------------------------------
# 阶段一: "node-builder" - 安装 Node.js 和 Puppeteer
# -----------------------------------------------------------------
FROM node:20-slim AS node-builder

WORKDIR /usr/src/app

# 复制 package.json 来安装依赖
COPY package*.json ./

# 安装 puppeteer。设置环境变量跳过内置的 Chromium 下载，
# 因为我们将在最终镜像中使用系统安装的 chromium，更小更快。
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
RUN npm install puppeteer

# -----------------------------------------------------------------
# 阶段二: "final" - 创建最终的 PHP+Apache 镜像
# -----------------------------------------------------------------
FROM php:8.2-apache-bullseye

# 安装系统依赖：Chromium浏览器和Node.js本身，以及一些必要的库
RUN apt-get update && apt-get install -y \
    # 安装 Node.js 的前置步骤
    curl \
    gnupg \
    ca-certificates \
    # Chromium 浏览器及其运行时依赖
    chromium \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libgtk-3-0 \
    libgbm1 \
    libasound2 \
    # 安装 Node.js
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    # 清理 apt 缓存以减小镜像大小
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 启用 Apache 的 URL 重写模块
RUN a2enmod rewrite

# 【关键改动1】复制 node_modules 从构建阶段到最终的网站根目录
# 这样，当 node 在 /var/www/html 目录下运行时，就能找到它了
COPY --from=node-builder /usr/src/app/node_modules /var/www/html/node_modules

# 复制我们的 PHP 脚本到网站根目录
COPY index.php /var/www/html/

# 设置正确的文件权限
RUN chown -R www-data:www-data /var/www/html

# 暴露 80 端口
EXPOSE 80
