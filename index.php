<?php
// 设置响应头为 JSON
header('Content-Type: application/json');

// 1. 从 URL 参数中获取目标 URL
$url = $_GET['url'] ?? '';

// 如果 URL 参数为空，则返回错误
if (empty($url)) {
    echo json_encode(['error' => 'Error: No URL provided.']);
    exit;
}

// 2. 准备要传递给 Node.js 的 JavaScript 代码
//    我们将 PHP 变量 $url 安全地嵌入到 JS 字符串中
$jsCode = <<<EOT
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({
    headless: "new", // 使用新的无头模式
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--no-first-run',
      '--no-zygote',
      '--disable-gpu'
    ]
  });

  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');
    
    // 【关键修改】使用从 PHP 传递过来的变量
    const targetUrl = '{$url}';
    
    await page.goto(targetUrl, { waitUntil: 'networkidle2' });

    const m3u8Url = await page.evaluate(() => {
      // ... 页面内抓取逻辑保持不变 ...
      if (typeof videojs !== 'undefined' && videojs.getPlayers && videojs.getPlayers().vjs_video_1) {
        const player = videojs.getPlayers().vjs_video_1;
        const src = player.currentSrc();
        return src;
      }
      const scripts = document.querySelectorAll('script');
      for (let script of scripts) {
        if (script.textContent.includes('sources')) {
          const match = script.textContent.match(/['"]file['"]:\s*['"]([^'"]+)['"]/);
          if (match && match[1]) {
            return match[1];
          }
        }
      }
      return null;
    });

    console.log(JSON.stringify({ url: m3u8Url }));

  } catch (error) {
    console.error(JSON.stringify({ error: error.message }));
  } finally {
    await browser.close();
  }
})();
EOT;

// 3. 使用 exec 执行 Node.js 脚本
//    使用 escapeshellarg 是一个非常重要的安全措施，可以防止命令注入
$command = 'node -e ' . escapeshellarg($jsCode);

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// 4. 将输出结果返回给浏览器
echo implode("\n", $output);

?>
