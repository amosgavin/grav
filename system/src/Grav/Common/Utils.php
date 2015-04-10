<?php
namespace Grav\Common;

/**
 * Misc utilities.
 *
 * @package Grav\Common
 */
abstract class Utils
{
    /**
     * @param  string  $haystack
     * @param  string  $needle
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }

    /**
     * @param  string  $haystack
     * @param  string  $needle
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * @param  string  $haystack
     * @param  string  $needle
     * @return bool
     */
    public static function contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

    /**
     * Merge two objects into one.
     *
     * @param  object $obj1
     * @param  object $obj2
     * @return object
     */
    public static function mergeObjects($obj1, $obj2)
    {
        return (object) array_merge((array) $obj1, (array) $obj2);
    }

    /**
     * Recursive remove a directory - DANGEROUS! USE WITH CARE!!!!
     *
     * @param $dir
     * @return bool
     */
    public static function rrmdir($dir)
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \DirectoryIterator $fileinfo */
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (false === rmdir($fileinfo->getRealPath())) {
                    return false;
                }
            } else {
                if (false === unlink($fileinfo->getRealPath())) {
                    return false;
                }
            }
        }

        return rmdir($dir);
    }

    /**
     * Recursive copy of one directory to another
     *
     * @param $src
     * @param $dest
     *
     * @return bool
     */
    public static function rcopy($src, $dest)
    {

        // If the src is not a directory do a simple file copy
        if (!is_dir($src)) {
            copy($src, $dest);
            return true;
        }

        // If the destination directory does not exist create it
        if (!is_dir($dest)) {
            if (!mkdir($dest)) {
                // If the destination directory could not be created stop processing
                return false;
            }
        }

        // Open the source directory to read in files
        $i = new \DirectoryIterator($src);
        /** @var \DirectoryIterator $f */
        foreach ($i as $f) {
            if ($f->isFile()) {
                copy($f->getRealPath(), "$dest/" . $f->getFilename());
            } else {
                if (!$f->isDot() && $f->isDir()) {
                    static::rcopy($f->getRealPath(), "$dest/$f");
                }
            }
        }
        return true;
    }

    /**
     * Truncate HTML by text length.
     *
     * @param  string $text
     * @param  int    $length
     * @param  string $ending
     * @param  bool   $exact
     * @param  bool   $considerHtml
     * @return string
     */
    public static function truncateHtml($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true)
    {
        $open_tags = array();
        if ($considerHtml) {
            // if the plain text is shorter than the maximum length, return the whole text
            if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }
            // splits all html-tags to scanable lines
            preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
            $total_length = strlen($ending);
            $truncate = '';
            foreach ($lines as $line_matchings) {
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($line_matchings[1])) {
                    // if it's an "empty element" with or without xhtml-conform closing slash
                    if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                        // do nothing
                    // if tag is a closing tag
                    } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                        // delete tag from $open_tags list
                        $pos = array_search($tag_matchings[1], $open_tags);
                        if ($pos !== false) {
                            unset($open_tags[$pos]);
                        }
                    // if tag is an opening tag
                    } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                        // add tag to the beginning of $open_tags list
                        array_unshift($open_tags, strtolower($tag_matchings[1]));
                    }
                    // add html-tag to $truncate'd text
                    $truncate .= $line_matchings[1];
                }
                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
                if ($total_length+$content_length> $length) {
                    // the number of characters which are left
                    $left = $length - $total_length;
                    $entities_length = 0;
                    // search for html entities
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                        // calculate the real length of all entities in the legal range
                        foreach ($entities[0] as $entity) {
                            if ($entity[1]+1-$entities_length <= $left) {
                                $left--;
                                $entities_length += strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }
                    $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                    // maximum length is reached, so get off the loop
                    break;
                } else {
                    $truncate .= $line_matchings[2];
                    $total_length += $content_length;
                }
                // if the maximum length is reached, get off the loop
                if ($total_length >= $length) {
                    break;
                }
            }
        } else {
            if (strlen($text) <= $length) {
                return $text;
            } else {
                $truncate = substr($text, 0, $length - strlen($ending));
            }
        }
        // if the words shouldn't be cut in the middle...
        if (!$exact) {
            // ...search the last occurance of a space...
            $spacepos = strrpos($truncate, ' ');
            if (isset($spacepos)) {
                // ...and cut the text in this position
                $truncate = substr($truncate, 0, $spacepos);
            }
        }
        // add the defined ending to the text
        $truncate .= $ending;
        if ($considerHtml) {
            // close all unclosed html-tags
            foreach ($open_tags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }
        return $truncate;
    }

    /**
     * Generate a random string of a given length
     *
     * @param int $length
     *
     * @return string
     */
    public static function generateRandomString($length = 5)
    {
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
    }

    /**
     * Provides the ability to download a file to the browser
     *
     * @param      $file            the full path to the file to be downloaded
     * @param bool $force_download  as opposed to letting browser choose if to download or render
     */
    public static function download($file, $force_download = true)
    {
        if (file_exists($file)) {
            $file_parts = pathinfo($file);
            $filesize = filesize($file);
            $range = false;

            set_time_limit(0);
            ignore_user_abort(false);
            ini_set('output_buffering', 0);
            ini_set('zlib.output_compression', 0);

            if ($force_download) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename='.$file_parts['basename']);
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
            } else {
                header("Content-Type: " . Utils::getMimeType($file_parts['extension']));
            }
            header('Content-Length: ' . $filesize);

            // 8kb chunks for now
            $chunk = 8 * 1024;

            $fh = fopen($file, "rb");

            if ($fh === false) {
                return;
            }

            // Repeat reading until EOF
            while (!feof($fh)) {
                echo fread($fh, $chunk);

                ob_flush();  // flush output
                flush();
            }

            exit;
        }
    }

    /**
     * Return the mimetype based on filename
     *
     * @param $extension Extension of file (eg .txt)
     *
     * @return string
     */
    public static function getMimeType($extension)
    {
        $extension = strtolower($extension);

        switch($extension)
        {
            case "js":
                return "application/x-javascript";

            case "json":
                return "application/json";

            case "jpg":
            case "jpeg":
            case "jpe":
                return "image/jpg";

            case "png":
            case "gif":
            case "bmp":
            case "tiff":
                return "image/" . $extension;

            case "css":
                return "text/css";

            case "xml":
                return "application/xml";

            case "doc":
            case "docx":
                return "application/msword";

            case "xls":
            case "xlt":
            case "xlm":
            case "xld":
            case "xla":
            case "xlc":
            case "xlw":
            case "xll":
                return "application/vnd.ms-excel";

            case "ppt":
            case "pps":
                return "application/vnd.ms-powerpoint";

            case "rtf":
                return "application/rtf";

            case "pdf":
                return "application/pdf";

            case "html":
            case "htm":
            case "php":
                return "text/html";

            case "txt":
                return "text/plain";

            case "mpeg":
            case "mpg":
            case "mpe":
                return "video/mpeg";

            case "mp3":
                return "audio/mpeg3";

            case "wav":
                return "audio/wav";

            case "aiff":
            case "aif":
                return "audio/aiff";

            case "avi":
                return "video/msvideo";

            case "wmv":
                return "video/x-ms-wmv";

            case "mov":
                return "video/quicktime";

            case "zip":
                return "application/zip";

            case "tar":
                return "application/x-tar";

            case "swf":
                return "application/x-shockwave-flash";

            default:
                return "application/octet-stream";
        }
    }
}
