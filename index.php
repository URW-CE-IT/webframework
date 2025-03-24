<?php
/**
 * index.php
 * 
 * WebFramework Starting File - Code Execution begins here.
 * All Web Requests are redirected to this file using the .htaccess in project root.
 * 
 * @author Patrick Matthias Garske <patrick@garske.link>
 * @since 0.1
 */

include_once("WebFramework/autoload.php");
foreach(glob('pages/*.php') as $file) {     # Include all PageHandlers in pages directory
    include_once $file;
}

use WebFramework as WF;

define("PROJ_DIR", __DIR__);                # Set Project Directory to allow Template Engine to find required resources
define("DEBUG", 1);                         # 0: No Debug; 1: WARNings only; 2: WARNings and INFOrmational messages
define("ALLOW_INLINE_COMPONENTS", TRUE);    # Inline Component Processing could impact performance. To improve performance, you can disable it if its not needed.

$path = "index";
if(php_sapi_name() == 'cli-server') {
    $path = substr($_SERVER['REQUEST_URI'], 1);
    if(is_file(__DIR__."/".$path)) {
        if(preg_match('/.(js|css|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|otf|eot|mp4|mp3|wav|avi|mov|pdf|zip|rar|md)$/', $path)) {
            echo file_get_contents(__DIR__."/".$path);
        }
    }
    if(empty($path)) $path = "index";
}

if(isset($_GET["rpath"])){
    $path = $_GET["rpath"];
}

$rh = new WF\RoutingHandler();

$rh->register("index", new IndexHandler());

echo $rh->handle($path);