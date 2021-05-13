<?php
if(strpos(php_sapi_name(), 'cli') === false) {
    die('【ERROR】只能运行在cli模式下');
}
set_time_limit(0);
ini_set('display_errors', 'On');
error_reporting(E_ALL);

$dir = __DIR__;             // 需要打包的目录
$file = 'apollo-clientd.phar';      // 包的名称, 注意它不仅仅是一个文件名, 在stub中也会作为入口前缀
if(file_exists($file)) {
    unlink($file);
}
$phar = new Phar(__DIR__ . '/' . $file, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $file);
//开始打包
$phar->startBuffering();//缓冲对归档做出的修改
$phar->buildFromDirectory(dirname($dir));//遍历指定的目录并把其中的文件加入到归档中
//$phar->delete('/bin/build-phar.php');//把build.php本身摘除
//设置入口,定义文件存根（stub）
$phar->setStub("<?php
Phar::mapPhar('{$file}');
require 'phar://{$file}/bin/apollo-clientd.php';
__HALT_COMPILER();
?>");
//停止缓冲，尽管不一定要执行上述操作，这样做可以改善创建或修改归档的性能，因为它避免了每次在脚本中修改归档时对做出的修改进行保存。
$phar->stopBuffering();
// 打包完成
echo "Finished {$file}\n";