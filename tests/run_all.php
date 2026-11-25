<?php
/**
 * 全量回归：依次执行各 Phase 测试脚本并汇总结果。
 * 用法：php tests/run_all.php
 */
$root = __DIR__;
$scripts = [
    'smoke.php',
    'e2e.php',
    'phase2.php',
    'phase3.php',
    'phase4.php',
    'phase5.php',
    'phase7.php',
];

$php = PHP_BINARY;
$summary = [];
$allOk = true;

foreach ($scripts as $script) {
    $path = $root . DIRECTORY_SEPARATOR . $script;
    if (!is_file($path)) {
        $summary[] = str_pad($script, 14) . ' [跳过: 文件不存在]';
        continue;
    }
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($path);
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    $text = implode("\n", $output);

    // 部分测试把明细写入 *_result.txt 文件，一并读取用于统计
    $resultFile = $root . DIRECTORY_SEPARATOR . pathinfo($script, PATHINFO_FILENAME) . '_result.txt';
    if (is_file($resultFile)) {
        $text .= "\n" . file_get_contents($resultFile);
    }

    // 提取统计行
    $pass = '?'; $fail = '?';
    if (preg_match('/PASS=(\d+)\s+FAIL=(\d+)/', $text, $m)) {
        $pass = $m[1]; $fail = $m[2];
    } elseif (preg_match_all('/\b(PASS|FAIL)\b/', $text, $mm)) {
        $pass = count(array_filter($mm[1], fn($x) => $x === 'PASS'));
        $fail = count(array_filter($mm[1], fn($x) => $x === 'FAIL'));
    }
    $status = ($code === 0) ? 'OK  ' : 'FAIL';
    if ($code !== 0) { $allOk = false; }
    $summary[] = str_pad($script, 14) . " $status  通过={$pass} 失败={$fail}";
}

echo "\n========================================\n";
echo "  堃景PHP / KunJingPHP  全量回归结果\n";
echo "========================================\n";
foreach ($summary as $line) {
    echo $line . "\n";
}
echo "----------------------------------------\n";
echo $allOk ? "总体：全部通过 ✅\n" : "总体：存在失败 ❌\n";
echo "========================================\n";
exit($allOk ? 0 : 1);
