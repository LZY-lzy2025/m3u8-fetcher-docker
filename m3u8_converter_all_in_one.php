<?php

// ---------------------------------------------------------------------------
// 设置响应头
// ---------------------------------------------------------------------------
header("Access-Control-Allow-Origin:*");
header("Content-type: application/json; charset=utf-8");

// ---------------------------------------------------------------------------
// 第一步：获取并验证输入
// ---------------------------------------------------------------------------
$url = $_GET['url'] ?? '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => '无效或缺少URL参数。']);
    exit;
}

// ---------------------------------------------------------------------------
// 第二步：定义内嵌的Node.js/Puppeteer脚本
// ---------------------------------------------------------------------------
// 这是我们之前写的 fetcher_v2.js 的内容，现在它是一个PHP字符串
// 我们用 heredoc 语法来保持代码的可读性
$nodeScript = <<<EOD
const puppeteer = require('puppeteer');

// 从PHP传来的URL会通过命令行参数传入，我们在这里接收
const url = process.argv[2];

if (!url) {
    console.log('Error: No URL provided.');
    process.exit(1);
}

(async () => {
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');

        page.on('response', response => {
            const requestUrl = response.url();
            if (requestUrl.includes('.m3u8') && (requestUrl.includes('szsummer.cn') || requestUrl.includes('livehwc4.com'))) {
                console.log(requestUrl);
                browser.close();
                process.exit(0);
            }
        });

        await page.goto(url, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(5000);

        console.log('Error: M3U8 request not found within the timeout period.');
        await browser.close();

    } catch (error) {
        console.log('Error: An exception occurred.', error.message);
        if (browser) {
            await browser.close();
        }
    }
})();
EOD;

// ---------------------------------------------------------------------------
// 第三步：执行内嵌的JavaScript代码
// ---------------------------------------------------------------------------
// 使用 proc_open 是更强大和安全的方式来执行外部命令
// 它允许我们直接向进程的标准输入写入JS代码，而无需创建临时文件

$escapedUrl = escapeshellarg($url);
$command = "node - 2>&1"; // - 表示从标准输入读取代码

$descriptorspec = [
    0 => ['pipe', 'r'], // 标准输入，我们将从这里写入JS代码
    1 => ['pipe', 'w'], // 标准输出，我们将从这里读取结果
    2 => ['pipe', 'w'], // 标准错误，我们也要捕获它
];

$process = proc_open($command, $descriptorspec, $pipes);

if (is_resource($process)) {
    // 将JavaScript代码写入到进程的标准输入
    fwrite($pipes[0], $nodeScript);
    fclose($pipes[0]);

    // 从标准输出和错误中读取所有内容
    $output = stream_get_contents($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    
    fclose($pipes[1]);
    fclose($pipes[2]);

    // 关闭进程并获取退出码
    $exit_code = proc_close($process);

    // ---------------------------------------------------------------------------
    // 第四步：处理结果并返回
    // ---------------------------------------------------------------------------
    $m3u8Url = trim($output);

    if ($exit_code !== 0 || empty($m3u8Url) || strpos($m3u8Url, 'Error:') === 0) {
        // 如果Node.js进程异常退出，或者返回了错误信息
        $errorMsg = $m3u8Url ?: ($errorOutput ?: '未知错误，Node.js进程异常退出。');
        echo json_encode(['error' => $errorMsg]);
    } else {
        // 成功
        $responseData = [
            'title' => 'Live Stream',
            'url'   => $m3u8Url
        ];
        echo json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

} else {
    http_response_code(500);
    echo json_encode(['error' => '无法启动Node.js进程，请检查服务器环境。']);
}

?>
