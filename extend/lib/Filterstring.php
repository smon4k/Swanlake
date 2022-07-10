<?php
/**
 * 字符串安全过滤
 * 防止Xss攻击
 */
namespace lib;

class Filterstring
{
    public static function EscapeStringFilter($string)
    {
        global $_POST;
        $search = array(
            '/</i',
            '/>/i',
            '/\">/i',
            '/\bunion\b/i',
            '/load_file(\s*(\/\*.*\*\/)?\s*)+\(/i',
            '/into(\s*(\/\*.*\*\/)?\s*)+outfile/i',
            '/\bor\b/i',
            '/\<([\/]?)script([^\>]*?)\>/si',
            '/\<([\/]?)iframe([^\>]*?)\>/si',
            '/\<([\/]?)frame([^\>]*?)\>/si',
        );
        $replace = array(
            '<',
            '>',
            '">',
            'union ',
            'load_file  (',
            'into  outfile',
            'or ',
            '<\\1script\\2>',
            '<\\1iframe\\2>',
            '<\\1frame\\2>',
        );
        if (is_array($string)) {
            $key = array_keys($string);
            $size = sizeof($key);
            for ($i = 0; $i < $size; $i++) {
                $string[$key[$i]] = escape($string[$key[$i]]);
            }
        } else {
            // if (!$_POST['stats_code'] && !$_POST['ad_type_code_content']) {
                $string = str_replace(array(
                    '\n',
                    '\r',
                ), array(
                    chr(10),
                    chr(13),
                ), preg_replace($search, $replace, $string));
                $string = self::RemoveXss($string);
            // } else {
            //     $string = $string;
            // }
        }
        return $string;
    }

    public static function RemoveXss($val)
    {
        $val = preg_replace('/([\x00-\x08\x0b-\x0c\x0e-\x19])/', '', $val);

        $search = 'abcdefghijklmnopqrstuvwxyz';
        $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $search .= '1234567890!@#$%^&*()';
        $search .= '~`";:?+/={}[]-_|\'\\';
        for ($i = 0; $i < strlen($search); $i++) {

            $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val);

            $val = preg_replace('/(�{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val);
        }

        $ra1 = array(
            'javascript',
            'vbscript',
            'expression',
            'applet',
            'meta',
            'xml',
            'blink',
            'script',
            'object',
            'iframe',
            'frame',
            'frameset',
            'ilayer',
            'bgsound',
        );
        $ra2 = array(
            'onabort',
            'onactivate',
            'onafterprint',
            'onafterupdate',
            'onbeforeactivate',
            'onbeforecopy',
            'onbeforecut',
            'onbeforedeactivate',
            'onbeforeeditfocus',
            'onbeforepaste',
            'onbeforeprint',
            'onbeforeunload',
            'onbeforeupdate',
            'onblur',
            'onbounce',
            'oncellchange',
            'onchange',
            'onclick',
            'oncontextmenu',
            'oncontrolselect',
            'oncopy',
            'oncut',
            'ondataavailable',
            'ondatasetchanged',
            'ondatasetcomplete',
            'ondblclick',
            'ondeactivate',
            'ondrag',
            'ondragend',
            'ondragenter',
            'ondragleave',
            'ondragover',
            'ondragstart',
            'ondrop',
            'onerror',
            'onerrorupdate',
            'onfilterchange',
            'onfinish',
            'onfocus',
            'onfocusin',
            'onfocusout',
            'onhelp',
            'onkeydown',
            'onkeypress',
            'onkeyup',
            'onlayoutcomplete',
            'onload',
            'onlosecapture',
            'onmousedown',
            'onmouseenter',
            'onmouseleave',
            'onmousemove',
            'onmouseout',
            'onmouseover',
            'onmouseup',
            'onmousewheel',
            'onmove',
            'onmoveend',
            'onmovestart',
            'onpaste',
            'onpropertychange',
            'onreadystatechange',
            'onreset',
            'onresize',
            'onresizeend',
            'onresizestart',
            'onrowenter',
            'onrowexit',
            'onrowsdelete',
            'onrowsinserted',
            'onscroll',
            'onselect',
            'onselectionchange',
            'onselectstart',
            'onstart',
            'onstop',
            'onsubmit',
            'onunload',
        );
        $ra = array_merge($ra1, $ra2);

        $found = true;
        while ($found == true) {
            $val_before = $val;
            for ($i = 0; $i < sizeof($ra); $i++) {
                $pattern = '/';
                for ($j = 0; $j < strlen($ra[$i]); $j++) {
                    if ($j > 0) {
                        $pattern .= '(';
                        $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                        $pattern .= '|';
                        $pattern .= '|(�{0,8}([9|10|13]);)';
                        $pattern .= ')*';
                    }
                    $pattern .= $ra[$i][$j];
                }
                $pattern .= '/i';
                $replacement = substr($ra[$i], 0, 2) . ' ' . substr($ra[$i], 2);
                $val = preg_replace($pattern, $replacement, $val);
                if ($val_before == $val) {

                    $found = false;
                }
            }
        }
        return $val;
    }
}
