<?php

namespace yzh52521\middleware;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array(
        'config/plugin/yzh52521/throttle' => 'config/plugin/yzh52521/throttle',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        self::putFile(app_path() . '/middleware/Throttle.php', 'middleware.tpl');
        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        self::uninstallByRelation();
    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parent_dir = base_path() . '/' . substr($dest, 0, $pos);
                if (!is_dir($parent_dir)) {
                    mkdir($parent_dir, 0777, true);
                }
            }
            copy_dir(__DIR__ . "/$source", base_path() . "/$dest");
        }
    }

    /**
     * uninstallByRelation
     * @return void
     */
    public static function uninstallByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path() . "/$dest";
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            remove_dir($path);
        }
    }

    private static function putFile(string $targetFile, string $originFile)
    {
        if (!is_file($targetFile)) {
            $code = file_get_contents(__DIR__ . '/' . $originFile);
            $code .= PHP_EOL . '//:' . md5($code);
            file_put_contents($targetFile, $code);
        }
    }

}
