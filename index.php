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
    headless: "new", // 使用新的无头模式
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--no-first-run',
      '--no-zygote',
      '--single-process', // 尝试在单进程中运行，有时可以解决资源问题
      '--disable-gpu'
    ]
  });

  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');
    
    const targetUrl = '{$url}';
    
    // 增加超时设置，防止页面卡住
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
//    使用 escapeshellarg 包装命令和 JS 代码，防止注入
$command = 'node -e ' . escapeshellarg($jsCode);

// 【关键修改】将 stderr 合并到 stdout，这样我们可以捕获所有输出
$command .= ' 2>&1';

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

$outputString = implode("\n", $output);

// 4. 根据返回码和输出内容，构造统一的 JSON 响应
if ($return_var !== 0) {
    // 如果返回码不为0，说明命令执行出错
    echo json_encode([
        'success' => false,
        'error' => 'Node.js script execution failed.',
        'details' => $outputString,
        'code' => $return_var
    ]);
} else {
    // 即使返回码为0，也要检查输出是否是合法的 JSON
    // 因为我们的 JS 脚本可能内部报错，但仍然正常退出了
    $decodedOutput = json_decode($outputString, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo $outputString; // 如果输出是合法JSON，直接输出
    } else {
        // 如果不是，说明有未捕获的错误
        echo json_encode([
            'success' => false,
            'error' => 'Node.js script produced non-JSON output.',
            'details' => $outputString
        ]);
    }
}

?>
