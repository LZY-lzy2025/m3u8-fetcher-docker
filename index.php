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
$jsCode = <<<EOT
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({
    // 【关键修改】指定Chromium的可执行路径
    executablePath: '/usr/bin/chromium',

    headless: "new", // 使用新的无头模式
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--no-first-run',
      '--no-zygote',
      '--single-process',
      '--disable-gpu'
    ]
  });

  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');
    
    const targetUrl = '{$url}';
    await page.goto(targetUrl, { waitUntil: 'networkidle2', timeout: 30000 });

    const m3u8Url = await page.evaluate(() => {
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

    if (m3u8Url) {
        console.log(JSON.stringify({ success: true, url: m3u8Url }));
    } else {
        console.error(JSON.stringify({ success: false, error: 'Could not find M3U8 URL in the page.' }));
    }

  } catch (error) {
    console.error(JSON.stringify({ success: false, error: error.message }));
  } finally {
    await browser.close();
  }
})();
EOT;

// 3. 使用 exec 执行 Node.js 脚本
$command = 'node -e ' . escapeshellarg($jsCode);
$command .= ' 2>&1'; // 合并错误流

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

$outputString = implode("\n", $output);

// 4. 根据返回码和输出内容，构造统一的 JSON 响应
if ($return_var !== 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Node.js script execution failed.',
        'details' => $outputString,
        'code' => $return_var
    ]);
} else {
    $decodedOutput = json_decode($outputString, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo $outputString;
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Node.js script produced non-JSON output.',
            'details' => $outputString
        ]);
    }
}

?>
