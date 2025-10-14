<?php

/**
 * Optimizer.php
 * 
 * Provides (static) functions to optimize CSS, JS and image files.
 * 
 * @author Patrick Matthias Garske <patrick@garske.link>
 * @since 0.4
 */

namespace VeloFrame;

class Optimizer
{

    /**
     * Checks if an optimized image exists in the cache directory and serves it. If not, it will optimize the image, save it in the cache directory and serve it.
     *
     * @param  string $filepath Path to the image file to be optimized.
     * @return string Returns the optimized image content or the original image content if optimization fails or is not enabled.
     */
    public static function serveOptimizedImage(string $filepath): string
    {
        if (defined("CACHE_DIR")) {
            if (!is_dir(CACHE_DIR)) {
                mkdir(CACHE_DIR, 0751, true);
            }
            $cacheFile = CACHE_DIR . '/' . md5($filepath) . '.webp';
            $cacheValid = file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($filepath);

            if ($cacheValid) {
                header('Content-Type: image/webp');
                return file_get_contents($cacheFile);
            }
            // If not, check if auto-optimization is enabled and optimize the image
            if (defined("AUTO_OPTIMIZE_IMAGES") && AUTO_OPTIMIZE_IMAGES) {
                if (self::optimizeImage($filepath)) {
                    header('Content-Type: image/webp');
                    return file_get_contents($cacheFile);
                } else {
                    header('Content-Type: image/' . pathinfo($filepath, PATHINFO_EXTENSION));
                    return file_get_contents($filepath);
                }
            } else {
                header('Content-Type: image/' . pathinfo($filepath, PATHINFO_EXTENSION));
                return file_get_contents($filepath);
            }
        }
        header('Content-Type: image/' . pathinfo($filepath, PATHINFO_EXTENSION));
        return file_get_contents($filepath);
    }

    /**
     * Optimizes an image file and saves it in the cache directory as a WebP file.
     * Will automatically downscale (preserve aspect ratio) the image to maximum resolution defined in config flag AUTO_OPTIMIZE_IMAGES_MAX_RESOLUTION unless overwritten 
     * by the $max_res parameter.
     *
     * @param  string $filepath
     * @param  int $max_res Maximum resolution to downscale the image to (default: AUTO_OPTIMIZE_IMAGES_MAX_RESOLUTION)
     * @return bool Returns true if the image was successfully optimized and saved, false otherwise.
     */
    public static function optimizeImage(string $filepath, int $max_res = AUTO_OPTIMIZE_IMAGES_MAX_RESOLUTION): bool
    {
        if (!extension_loaded('gd')) {
            return false; // GD library not available
        }
        if (!file_exists($filepath)) {
            return false; // File does not exist
        }

        $image = null;
        $image_type = exif_imagetype($filepath);
        switch ($image_type) {
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($filepath);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($filepath);
                break;
            default:
                return false; // Unsupported image type
        }

        if ($image === false) {
            return false; // Failed to create image from file
        }

        // Resize the image if necessary (max. resolution to match usual web client resolutions => 1920x1080) while preserving aspect ratio
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $max_res || $height > $max_res) {
            $aspect_ratio = $width / $height;
            if ($width > $height) {
                $new_width = $max_res;
                $new_height = round($max_res / $aspect_ratio);
            } else {
                $new_height = $max_res;
                $new_width = round($max_res * $aspect_ratio);
            }
            $resized_image = imagecreatetruecolor($new_width, $new_height);
            imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagedestroy($image);
            $image = $resized_image;
        }

        // Save the optimized image as WebP in the cache directory
        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0755, true); // Create cache directory if it doesn't exist
        }

        return imagewebp($image, CACHE_DIR . '/' . md5($filepath) . '.webp', 80); // Save with quality 80
    }

    /**
     * Checks if an optimized CSS file exists in the cache directory and serves it. If not, it will optimize the CSS, save it in the cache directory and serve it.
     *
     * @param string $filepath Path to the CSS file to be optimized.
     * @return string Returns the optimized CSS content or the original CSS content if optimization fails or is not enabled.
     */
    public static function serveOptimizedCSS(string $filepath): string
    {
        if (defined("CACHE_DIR") && is_dir(CACHE_DIR)) {
            $cacheFile = CACHE_DIR . '/' . md5($filepath) . '.min.css';
            $cacheValid = file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($filepath);
            if ($cacheValid) {
                header('Content-Type: text/css');
                return file_get_contents($cacheFile);
            }
            if (defined("AUTO_OPTIMIZE_CSS") && AUTO_OPTIMIZE_CSS) {
                if (self::optimizeCSS($filepath)) {
                    header('Content-Type: text/css');
                    return file_get_contents($cacheFile);
                } else {
                    header('Content-Type: text/css');
                    return file_get_contents($filepath);
                }
            } else {
                header('Content-Type: text/css');
                return file_get_contents($filepath);
            }
        }
        header('Content-Type: text/css');
        return file_get_contents($filepath);
    }

    /**
     * Optimizes a CSS file (minifies it) and saves it in the cache directory.
     *
     * @param string $filepath
     * @return bool Returns true if the CSS was successfully optimized and saved, false otherwise.
     */
    public static function optimizeCSS(string $filepath): bool
    {
        if (!file_exists($filepath)) {
            return false;
        }
        $css = file_get_contents($filepath);

        // Improved CSS minification
        // 1. Remove comments
        $minified = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // 2. Remove newlines and tabs
        $minified = str_replace(["\r\n", "\r", "\n", "\t"], '', $minified);

        // 3. Remove multiple spaces (but keep single spaces for now)
        $minified = preg_replace('/\s+/', ' ', $minified);

        // 4. Remove spaces around specific characters, but preserve important ones
        $minified = preg_replace('/\s*([:;{},>~+])\s*/', '$1', $minified);

        // 5. Remove space before !important
        $minified = preg_replace('/\s*!important/', '!important', $minified);

        // 6. Remove space after ( and before )
        $minified = preg_replace('/\(\s+/', '(', $minified);
        $minified = preg_replace('/\s+\)/', ')', $minified);

        // 7. Trim whitespace at the beginning and end
        $minified = trim($minified);

        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0755, true);
        }
        return file_put_contents(CACHE_DIR . '/' . md5($filepath) . '.min.css', $minified) !== false;
    }

    /**
     * Checks if an optimized JS file exists in the cache directory and serves it. If not, it will optimize the JS, save it in the cache directory and serve it.
     *
     * @param string $filepath Path to the JS file to be optimized.
     * @return string Returns the optimized JS content or the original JS content if optimization fails or is not enabled.
     */
    public static function serveOptimizedJS(string $filepath): string
    {
        if (defined("CACHE_DIR") && is_dir(CACHE_DIR)) {
            $cacheFile = CACHE_DIR . '/' . md5($filepath) . '.min.js';
            $cacheValid = file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($filepath);
            if ($cacheValid) {
                header('Content-Type: application/javascript');
                return file_get_contents($cacheFile);
            }
            if (defined("AUTO_OPTIMIZE_JS") && AUTO_OPTIMIZE_JS) {
                if (self::optimizeJS($filepath)) {
                    header('Content-Type: application/javascript');
                    return file_get_contents($cacheFile);
                } else {
                    header('Content-Type: application/javascript');
                    return file_get_contents($filepath);
                }
            } else {
                header('Content-Type: application/javascript');
                return file_get_contents($filepath);
            }
        }
        header('Content-Type: application/javascript');
        return file_get_contents($filepath);
    }

    /**
     * Optimizes a JS file (minifies it) and saves it in the cache directory.
     *
     * @param string $filepath
     * @return bool Returns true if the JS was successfully optimized and saved, false otherwise.
     */
    public static function optimizeJS(string $filepath): bool
    {
        if (!file_exists($filepath)) {
            return false;
        }
        $js = file_get_contents($filepath);

        // Improved JS minification
        // 1. Remove multi-line comments /* */
        $minified = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);

        // 2. Remove single-line comments // but preserve URLs like http://
        $minified = preg_replace('~//(?![:\"]).*~', '', $minified);

        // 3. Remove newlines and tabs
        $minified = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $minified);

        // 4. Remove multiple spaces
        $minified = preg_replace('/\s+/', ' ', $minified);

        // 5. Remove spaces around operators and punctuation (but be careful with keywords)
        $minified = preg_replace('/\s*([=+\-*\/%<>!&|,;:{}()\[\]])\s*/', '$1', $minified);

        // 6. Trim whitespace
        $minified = trim($minified);

        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0755, true);
        }
        return file_put_contents(CACHE_DIR . '/' . md5($filepath) . '.min.js', $minified) !== false;
    }
}
