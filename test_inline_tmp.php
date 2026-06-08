<?php
// Must run from the php/ directory
chdir(__DIR__);
require __DIR__ . '/helpers.php';
require __DIR__ . '/parser.php';

$cases = [
    '[b站视频](https://www.bilibili.com/video/BV1oF411F7wD?spm_id_from=333.337.search-card.all.click&vd_source=9f568660d497311d3f945e5dce319705)',
    '[普通链接](https://example.com)',
    '[带参数锚点](https://example.com/path?a=1&b=2#sec)',
    '文本 **加粗** 和 [Github](https://github.com) 混合',
    '[危险链接](javascript:alert(1))',
    '纯文本 & 特殊字符 <script>alert(1)</script>',
];

foreach ($cases as $c) {
    echo "IN : " . $c . PHP_EOL;
    echo "OUT: " . inline_md($c) . PHP_EOL . PHP_EOL;
}
