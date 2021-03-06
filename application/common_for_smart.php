<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
use think\Log;

/**
 * 写日志函数
 */
function log_info() {
    $args = func_get_args();
    if (!empty($args)) {
        $function = \ClassLibrary\ClCache::getFunctionHistory(2);
        //日志
        $str = '[' . $function . ']' . call_user_func_array(['\ClassLibrary\ClString', 'toString'], $args);
        Log::record($str, Log::LOG);
    }
}

/**
 * 输出信息
 */
function echo_info() {
    $args = func_get_args();
    if (!empty($args)) {
        $function = \ClassLibrary\ClCache::getFunctionHistory(2);
        //日志
        $str = '[' . $function . ']' . call_user_func_array(['\ClassLibrary\ClString', 'toString'], $args);
        if (request()->isCli() || request()->isAjax()) {
            echo $str . "\n";
        } else {
            echo $str . '<br/>';
        }
    }
}

/**
 * 记录日志and输出
 */
function le_info() {
    $args = func_get_args();
    if (!empty($args)) {
        $function = \ClassLibrary\ClCache::getFunctionHistory(2);
        //日志
        $str = '[' . $function . ']' . call_user_func_array(['\ClassLibrary\ClString', 'toString'], $args);
        Log::record($str, Log::LOG);
        //输出
        if (request()->isCli() || request()->isAjax()) {
            echo $str . "\n";
        } else {
            echo $str . '<br/>';
        }
    }
}

/**
 * 获取参数
 * @param string $key 键
 * @param array $verifies 校验器，请采用ClApiParams生成
 * @param string $desc 参数描述，用于自动生成api文档
 * @param null $default 默认值，参考input方法
 * @param string $filter 过滤器，参考input方法
 * @return mixed
 */
function get_param($key = '', $verifies = [], $desc = '', $default = null, $filter = 'trim') {
    if (strpos($desc, ',') !== false) {
        exit(sprintf('%s含有非法字符","，请改成中文"，"', $desc));
    }
    //如果默认值为int类型，则过滤器则要添加floatval
    if ($default !== null) {
        if (is_numeric($default)) {
            if (strpos($filter, 'floatval') === false) {
                $filter .= ',floatval';
            }
        }
    }
    try {
        $value = input($key, $default, $filter);
    } catch (\InvalidArgumentException $exception) {
        if (strpos($key, '/') == false) {
            //尝试数组方式获取
            $value = input($key . '/a', $default, $filter);
        } else {
            throw $exception;
        }
    }
    //校验参数
    if (is_null($value) || $value != $default) {
        \ClassLibrary\ClFieldVerify::verifyFields([$key => $value], [$key => $verifies]);
    }
    return $value;
}

/**
 * 分页数
 */
const PAGES_NUM = 15;

/**
 * json结果返回
 * @param $data
 * @param bool $is_log
 * @return \think\response\Json|\think\response\Jsonp
 */
function json_return($data, $is_log = false) {
    //调试模式下，记录信息
    if (\think\App::$debug || $is_log) {
        //将请求地址加入返回数组中，用于区别请求内容
        log_info(json_encode($data, JSON_UNESCAPED_UNICODE), $data);
    }
    $type = isset($_GET['callback']) ? 'JSONP' : 'JSON';
    if ($type == 'JSON') {
        return json($data);
    } else if ($type == 'JSONP') {
        return jsonp($data);
    }
}

/**
 * 加解密key
 */
const CRYPT_KEY = 'Api';

if (!function_exists('mime_content_type')) {

    /**
     * 替换函数
     * @param $filename
     * @return mixed|string
     */
    function mime_content_type($filename) {
        $mime_types     = [
            'ez'          => 'application/andrew-inset',
            'aw'          => 'application/applixware',
            'atom'        => 'application/atom+xml',
            'atomcat'     => 'application/atomcat+xml',
            'atomsvc'     => 'application/atomsvc+xml',
            'ccxml'       => 'application/ccxml+xml',
            'cdmia'       => 'application/cdmi-capability',
            'cdmic'       => 'application/cdmi-container',
            'cdmid'       => 'application/cdmi-domain',
            'cdmio'       => 'application/cdmi-object',
            'cdmiq'       => 'application/cdmi-queue',
            'cu'          => 'application/cu-seeme',
            'davmount'    => 'application/davmount+xml',
            'dbk'         => 'application/docbook+xml',
            'dssc'        => 'application/dssc+der',
            'xdssc'       => 'application/dssc+xml',
            'ecma'        => 'application/ecmascript',
            'emma'        => 'application/emma+xml',
            'epub'        => 'application/epub+zip',
            'exi'         => 'application/exi',
            'pfr'         => 'application/font-tdpfr',
            'gml'         => 'application/gml+xml',
            'gpx'         => 'application/gpx+xml',
            'gxf'         => 'application/gxf',
            'stk'         => 'application/hyperstudio',
            'ipfix'       => 'application/ipfix',
            'jar'         => 'application/java-archive',
            'ser'         => 'application/java-serialized-object',
            'class'       => 'application/java-vm',
            'js'          => 'application/javascript',
            'json'        => 'application/json',
            'jsonml'      => 'application/jsonml+json',
            'lostxml'     => 'application/lost+xml',
            'hqx'         => 'application/mac-binhex40',
            'cpt'         => 'application/mac-compactpro',
            'mads'        => 'application/mads+xml',
            'mrc'         => 'application/marc',
            'mrcx'        => 'application/marcxml+xml',
            'mathml'      => 'application/mathml+xml',
            'mbox'        => 'application/mbox',
            'mscml'       => 'application/mediaservercontrol+xml',
            'metalink'    => 'application/metalink+xml',
            'meta4'       => 'application/metalink4+xml',
            'mets'        => 'application/mets+xml',
            'mods'        => 'application/mods+xml',
            'mp4s'        => 'application/mp4',
            'mxf'         => 'application/mxf',
            'oda'         => 'application/oda',
            'opf'         => 'application/oebps-package+xml',
            'ogx'         => 'application/ogg',
            'omdoc'       => 'application/omdoc+xml',
            'oxps'        => 'application/oxps',
            'xer'         => 'application/patch-ops-error+xml',
            'pdf'         => 'application/pdf',
            'pgp'         => 'application/pgp-encrypted',
            'prf'         => 'application/pics-rules',
            'p10'         => 'application/pkcs10',
            'p7s'         => 'application/pkcs7-signature',
            'p8'          => 'application/pkcs8',
            'ac'          => 'application/pkix-attr-cert',
            'cer'         => 'application/pkix-cert',
            'crl'         => 'application/pkix-crl',
            'pkipath'     => 'application/pkix-pkipath',
            'pki'         => 'application/pkixcmp',
            'pls'         => 'application/pls+xml',
            'cww'         => 'application/prs.cww',
            'pskcxml'     => 'application/pskc+xml',
            'rdf'         => 'application/rdf+xml',
            'rif'         => 'application/reginfo+xml',
            'rnc'         => 'application/relax-ng-compact-syntax',
            'rl'          => 'application/resource-lists+xml',
            'rld'         => 'application/resource-lists-diff+xml',
            'rs'          => 'application/rls-services+xml',
            'gbr'         => 'application/rpki-ghostbusters',
            'mft'         => 'application/rpki-manifest',
            'roa'         => 'application/rpki-roa',
            'rsd'         => 'application/rsd+xml',
            'rss'         => 'application/rss+xml',
            'rtf'         => 'application/rtf',
            'sbml'        => 'application/sbml+xml',
            'scq'         => 'application/scvp-cv-request',
            'scs'         => 'application/scvp-cv-response',
            'spq'         => 'application/scvp-vp-request',
            'spp'         => 'application/scvp-vp-response',
            'sdp'         => 'application/sdp',
            'setpay'      => 'application/set-payment-initiation',
            'setreg'      => 'application/set-registration-initiation',
            'shf'         => 'application/shf+xml',
            'rq'          => 'application/sparql-query',
            'srx'         => 'application/sparql-results+xml',
            'gram'        => 'application/srgs',
            'grxml'       => 'application/srgs+xml',
            'sru'         => 'application/sru+xml',
            'ssdl'        => 'application/ssdl+xml',
            'ssml'        => 'application/ssml+xml',
            'tfi'         => 'application/thraud+xml',
            'tsd'         => 'application/timestamped-data',
            'plb'         => 'application/vnd.3gpp.pic-bw-large',
            'psb'         => 'application/vnd.3gpp.pic-bw-small',
            'pvb'         => 'application/vnd.3gpp.pic-bw-var',
            'tcap'        => 'application/vnd.3gpp2.tcap',
            'pwn'         => 'application/vnd.3m.post-it-notes',
            'aso'         => 'application/vnd.accpac.simply.aso',
            'imp'         => 'application/vnd.accpac.simply.imp',
            'acu'         => 'application/vnd.acucobol',
            'fcdt'        => 'application/vnd.adobe.formscentral.fcdt',
            'xdp'         => 'application/vnd.adobe.xdp+xml',
            'xfdf'        => 'application/vnd.adobe.xfdf',
            'ahead'       => 'application/vnd.ahead.space',
            'azf'         => 'application/vnd.airzip.filesecure.azf',
            'azs'         => 'application/vnd.airzip.filesecure.azs',
            'azw'         => 'application/vnd.amazon.ebook',
            'acc'         => 'application/vnd.americandynamics.acc',
            'ami'         => 'application/vnd.amiga.ami',
            'apk'         => 'application/vnd.android.package-archive',
            'atx'         => 'application/vnd.antix.game-component',
            'mpkg'        => 'application/vnd.apple.installer+xml',
            'm3u8'        => 'application/vnd.apple.mpegurl',
            'swi'         => 'application/vnd.aristanetworks.swi',
            'iota'        => 'application/vnd.astraea-software.iota',
            'aep'         => 'application/vnd.audiograph',
            'mpm'         => 'application/vnd.blueice.multipass',
            'bmi'         => 'application/vnd.bmi',
            'rep'         => 'application/vnd.businessobjects',
            'cdxml'       => 'application/vnd.chemdraw+xml',
            'mmd'         => 'application/vnd.chipnuts.karaoke-mmd',
            'cdy'         => 'application/vnd.cinderella',
            'cla'         => 'application/vnd.claymore',
            'rp9'         => 'application/vnd.cloanto.rp9',
            'c11amc'      => 'application/vnd.cluetrust.cartomobile-config',
            'csp'         => 'application/vnd.commonspace',
            'cdbcmsg'     => 'application/vnd.contact.cmsg',
            'cmc'         => 'application/vnd.cosmocaller',
            'clkx'        => 'application/vnd.crick.clicker',
            'clkk'        => 'application/vnd.crick.clicker.keyboard',
            'clkp'        => 'application/vnd.crick.clicker.palette',
            'clkt'        => 'application/vnd.crick.clicker.template',
            'clkw'        => 'application/vnd.crick.clicker.wordbank',
            'wbs'         => 'application/vnd.criticaltools.wbs+xml',
            'pml'         => 'application/vnd.ctc-posml',
            'ppd'         => 'application/vnd.cups-ppd',
            'car'         => 'application/vnd.curl.car',
            'pcurl'       => 'application/vnd.curl.pcurl',
            'dart'        => 'application/vnd.dart',
            'rdz'         => 'application/vnd.data-vision.rdz',
            'fe_launch'   => 'application/vnd.denovo.fcselayout-link',
            'dna'         => 'application/vnd.dna',
            'mlp'         => 'application/vnd.dolby.mlp',
            'dpg'         => 'application/vnd.dpgraph',
            'dfac'        => 'application/vnd.dreamfactory',
            'kpxx'        => 'application/vnd.ds-keypoint',
            'ait'         => 'application/vnd.dvb.ait',
            'svc'         => 'application/vnd.dvb.service',
            'geo'         => 'application/vnd.dynageo',
            'mag'         => 'application/vnd.ecowin.chart',
            'nml'         => 'application/vnd.enliven',
            'esf'         => 'application/vnd.epson.esf',
            'msf'         => 'application/vnd.epson.msf',
            'qam'         => 'application/vnd.epson.quickanime',
            'slt'         => 'application/vnd.epson.salt',
            'ssf'         => 'application/vnd.epson.ssf',
            'ez2'         => 'application/vnd.ezpix-album',
            'ez3'         => 'application/vnd.ezpix-package',
            'fdf'         => 'application/vnd.fdf',
            'mseed'       => 'application/vnd.fdsn.mseed',
            'gph'         => 'application/vnd.flographit',
            'ftc'         => 'application/vnd.fluxtime.clip',
            'fnc'         => 'application/vnd.frogans.fnc',
            'ltf'         => 'application/vnd.frogans.ltf',
            'fsc'         => 'application/vnd.fsc.weblaunch',
            'oas'         => 'application/vnd.fujitsu.oasys',
            'oa2'         => 'application/vnd.fujitsu.oasys2',
            'oa3'         => 'application/vnd.fujitsu.oasys3',
            'fg5'         => 'application/vnd.fujitsu.oasysgp',
            'bh2'         => 'application/vnd.fujitsu.oasysprs',
            'ddd'         => 'application/vnd.fujixerox.ddd',
            'xdw'         => 'application/vnd.fujixerox.docuworks',
            'fzs'         => 'application/vnd.fuzzysheet',
            'txd'         => 'application/vnd.genomatix.tuxedo',
            'ggb'         => 'application/vnd.geogebra.file',
            'ggt'         => 'application/vnd.geogebra.tool',
            'gxt'         => 'application/vnd.geonext',
            'g2w'         => 'application/vnd.geoplan',
            'g3w'         => 'application/vnd.geospace',
            'gmx'         => 'application/vnd.gmx',
            'kml'         => 'application/vnd.google-earth.kml+xml',
            'kmz'         => 'application/vnd.google-earth.kmz',
            'gac'         => 'application/vnd.groove-account',
            'ghf'         => 'application/vnd.groove-help',
            'gim'         => 'application/vnd.groove-identity-message',
            'grv'         => 'application/vnd.groove-injector',
            'gtm'         => 'application/vnd.groove-tool-message',
            'tpl'         => 'application/vnd.groove-tool-template',
            'vcg'         => 'application/vnd.groove-vcard',
            'hal'         => 'application/vnd.hal+xml',
            'hbci'        => 'application/vnd.hbci',
            'les'         => 'application/vnd.hhe.lesson-player',
            'hpgl'        => 'application/vnd.hp-hpgl',
            'hpid'        => 'application/vnd.hp-hpid',
            'hps'         => 'application/vnd.hp-hps',
            'jlt'         => 'application/vnd.hp-jlyt',
            'pcl'         => 'application/vnd.hp-pcl',
            'pclxl'       => 'application/vnd.hp-pclxl',
            'sfd-hdstx'   => 'application/vnd.hydrostatix.sof-data',
            'mpy'         => 'application/vnd.ibm.minipay',
            'irm'         => 'application/vnd.ibm.rights-management',
            'sc'          => 'application/vnd.ibm.secure-container',
            'igl'         => 'application/vnd.igloader',
            'ivp'         => 'application/vnd.immervision-ivp',
            'ivu'         => 'application/vnd.immervision-ivu',
            'igm'         => 'application/vnd.insors.igm',
            'i2g'         => 'application/vnd.intergeo',
            'qbo'         => 'application/vnd.intu.qbo',
            'qfx'         => 'application/vnd.intu.qfx',
            'rcprofile'   => 'application/vnd.ipunplugged.rcprofile',
            'irp'         => 'application/vnd.irepository.package+xml',
            'xpr'         => 'application/vnd.is-xpr',
            'fcs'         => 'application/vnd.isac.fcs',
            'jam'         => 'application/vnd.jam',
            'rms'         => 'application/vnd.jcp.javame.midlet-rms',
            'jisp'        => 'application/vnd.jisp',
            'joda'        => 'application/vnd.joost.joda-archive',
            'karbon'      => 'application/vnd.kde.karbon',
            'chrt'        => 'application/vnd.kde.kchart',
            'kfo'         => 'application/vnd.kde.kformula',
            'flw'         => 'application/vnd.kde.kivio',
            'kon'         => 'application/vnd.kde.kontour',
            'ksp'         => 'application/vnd.kde.kspread',
            'htke'        => 'application/vnd.kenameaapp',
            'kia'         => 'application/vnd.kidspiration',
            'sse'         => 'application/vnd.kodak-descriptor',
            'lasxml'      => 'application/vnd.las.las+xml',
            '123'         => 'application/vnd.lotus-1-2-3',
            'apr'         => 'application/vnd.lotus-approach',
            'pre'         => 'application/vnd.lotus-freelance',
            'nsf'         => 'application/vnd.lotus-notes',
            'org'         => 'application/vnd.lotus-organizer',
            'scm'         => 'application/vnd.lotus-screencam',
            'lwp'         => 'application/vnd.lotus-wordpro',
            'portpkg'     => 'application/vnd.macports.portpkg',
            'mcd'         => 'application/vnd.mcd',
            'mc1'         => 'application/vnd.medcalcdata',
            'cdkey'       => 'application/vnd.mediastation.cdkey',
            'mwf'         => 'application/vnd.mfer',
            'mfm'         => 'application/vnd.mfmp',
            'flo'         => 'application/vnd.micrografx.flo',
            'igx'         => 'application/vnd.micrografx.igx',
            'mif'         => 'application/vnd.mif',
            'daf'         => 'application/vnd.mobius.daf',
            'dis'         => 'application/vnd.mobius.dis',
            'mbk'         => 'application/vnd.mobius.mbk',
            'mqy'         => 'application/vnd.mobius.mqy',
            'msl'         => 'application/vnd.mobius.msl',
            'plc'         => 'application/vnd.mobius.plc',
            'txf'         => 'application/vnd.mobius.txf',
            'mpn'         => 'application/vnd.mophun.application',
            'mpc'         => 'application/vnd.mophun.certificate',
            'xul'         => 'application/vnd.mozilla.xul+xml',
            'cil'         => 'application/vnd.ms-artgalry',
            'cab'         => 'application/vnd.ms-cab-compressed',
            'xlam'        => 'application/vnd.ms-excel.addin.macroenabled.12',
            'xlsm'        => 'application/vnd.ms-excel.sheet.macroenabled.12',
            'eot'         => 'application/vnd.ms-fontobject',
            'chm'         => 'application/vnd.ms-htmlhelp',
            'ims'         => 'application/vnd.ms-ims',
            'lrm'         => 'application/vnd.ms-lrm',
            'thmx'        => 'application/vnd.ms-officetheme',
            'cat'         => 'application/vnd.ms-pki.seccat',
            'stl'         => 'application/vnd.ms-pki.stl',
            'ppam'        => 'application/vnd.ms-powerpoint.addin.macroenabled.12',
            'sldm'        => 'application/vnd.ms-powerpoint.slide.macroenabled.12',
            'ppsm'        => 'application/vnd.ms-powerpoint.slideshow.macroenabled.12',
            'potm'        => 'application/vnd.ms-powerpoint.template.macroenabled.12',
            'wpl'         => 'application/vnd.ms-wpl',
            'xps'         => 'application/vnd.ms-xpsdocument',
            'mseq'        => 'application/vnd.mseq',
            'mus'         => 'application/vnd.musician',
            'msty'        => 'application/vnd.muvee.style',
            'taglet'      => 'application/vnd.mynfc',
            'nlu'         => 'application/vnd.neurolanguage.nlu',
            'nnd'         => 'application/vnd.noblenet-directory',
            'nns'         => 'application/vnd.noblenet-sealer',
            'nnw'         => 'application/vnd.noblenet-web',
            'ngdat'       => 'application/vnd.nokia.n-gage.data',
            'rpst'        => 'application/vnd.nokia.radio-preset',
            'rpss'        => 'application/vnd.nokia.radio-presets',
            'edm'         => 'application/vnd.novadigm.edm',
            'edx'         => 'application/vnd.novadigm.edx',
            'ext'         => 'application/vnd.novadigm.ext',
            'odc'         => 'application/vnd.oasis.opendocument.chart',
            'odb'         => 'application/vnd.oasis.opendocument.database',
            'odf'         => 'application/vnd.oasis.opendocument.formula',
            'odg'         => 'application/vnd.oasis.opendocument.graphics',
            'odi'         => 'application/vnd.oasis.opendocument.image',
            'odp'         => 'application/vnd.oasis.opendocument.presentation',
            'ods'         => 'application/vnd.oasis.opendocument.spreadsheet',
            'odt'         => 'application/vnd.oasis.opendocument.text',
            'odm'         => 'application/vnd.oasis.opendocument.text-master',
            'oth'         => 'application/vnd.oasis.opendocument.text-web',
            'xo'          => 'application/vnd.olpc-sugar',
            'dd2'         => 'application/vnd.oma.dd2+xml',
            'oxt'         => 'application/vnd.openofficeorg.extension',
            'mgp'         => 'application/vnd.osgeo.mapguide.package',
            'dp'          => 'application/vnd.osgi.dp',
            'esa'         => 'application/vnd.osgi.subsystem',
            'paw'         => 'application/vnd.pawaafile',
            'str'         => 'application/vnd.pg.format',
            'ei6'         => 'application/vnd.pg.osasli',
            'efif'        => 'application/vnd.picsel',
            'wg'          => 'application/vnd.pmi.widget',
            'plf'         => 'application/vnd.pocketlearn',
            'pbd'         => 'application/vnd.powerbuilder6',
            'box'         => 'application/vnd.previewsystems.box',
            'mgz'         => 'application/vnd.proteus.magazine',
            'qps'         => 'application/vnd.publishare-delta-tree',
            'ptid'        => 'application/vnd.pvi.ptid1',
            'bed'         => 'application/vnd.realvnc.bed',
            'mxl'         => 'application/vnd.recordare.musicxml',
            'musicxml'    => 'application/vnd.recordare.musicxml+xml',
            'cryptonote'  => 'application/vnd.rig.cryptonote',
            'cod'         => 'application/vnd.rim.cod',
            'rm'          => 'application/vnd.rn-realmedia',
            'rmvb'        => 'application/vnd.rn-realmedia-vbr',
            'link66'      => 'application/vnd.route66.link66+xml',
            'st'          => 'application/vnd.sailingtracker.track',
            'see'         => 'application/vnd.seemail',
            'sema'        => 'application/vnd.sema',
            'semd'        => 'application/vnd.semd',
            'semf'        => 'application/vnd.semf',
            'ifm'         => 'application/vnd.shana.informed.formdata',
            'ipk'         => 'application/vnd.shana.informed.package',
            'mmf'         => 'application/vnd.smaf',
            'teacher'     => 'application/vnd.smart.teacher',
            'dxp'         => 'application/vnd.spotfire.dxp',
            'sfs'         => 'application/vnd.spotfire.sfs',
            'sdc'         => 'application/vnd.stardivision.calc',
            'sda'         => 'application/vnd.stardivision.draw',
            'sdd'         => 'application/vnd.stardivision.impress',
            'smf'         => 'application/vnd.stardivision.math',
            'smzip'       => 'application/vnd.stepmania.package',
            'sm'          => 'application/vnd.stepmania.stepchart',
            'sxc'         => 'application/vnd.sun.xml.calc',
            'stc'         => 'application/vnd.sun.xml.calc.template',
            'sxd'         => 'application/vnd.sun.xml.draw',
            'std'         => 'application/vnd.sun.xml.draw.template',
            'sxi'         => 'application/vnd.sun.xml.impress',
            'sxm'         => 'application/vnd.sun.xml.math',
            'sxw'         => 'application/vnd.sun.xml.writer',
            'sxg'         => 'application/vnd.sun.xml.writer.global',
            'stw'         => 'application/vnd.sun.xml.writer.template',
            'svd'         => 'application/vnd.svd',
            'xsm'         => 'application/vnd.syncml+xml',
            'bdm'         => 'application/vnd.syncml.dm+wbxml',
            'xdm'         => 'application/vnd.syncml.dm+xml',
            'tmo'         => 'application/vnd.tmobile-livetv',
            'tpt'         => 'application/vnd.trid.tpt',
            'mxs'         => 'application/vnd.triscape.mxs',
            'tra'         => 'application/vnd.trueapp',
            'utz'         => 'application/vnd.uiq.theme',
            'umj'         => 'application/vnd.umajin',
            'unityweb'    => 'application/vnd.unity',
            'uoml'        => 'application/vnd.uoml+xml',
            'vcx'         => 'application/vnd.vcx',
            'vis'         => 'application/vnd.visionary',
            'vsf'         => 'application/vnd.vsf',
            'wbxml'       => 'application/vnd.wap.wbxml',
            'wmlc'        => 'application/vnd.wap.wmlc',
            'wmlsc'       => 'application/vnd.wap.wmlscriptc',
            'wtb'         => 'application/vnd.webturbo',
            'nbp'         => 'application/vnd.wolfram.player',
            'wpd'         => 'application/vnd.wordperfect',
            'wqd'         => 'application/vnd.wqd',
            'stf'         => 'application/vnd.wt.stf',
            'xar'         => 'application/vnd.xara',
            'xfdl'        => 'application/vnd.xfdl',
            'hvd'         => 'application/vnd.yamaha.hv-dic',
            'hvs'         => 'application/vnd.yamaha.hv-script',
            'hvp'         => 'application/vnd.yamaha.hv-voice',
            'osf'         => 'application/vnd.yamaha.openscoreformat',
            'saf'         => 'application/vnd.yamaha.smaf-audio',
            'spf'         => 'application/vnd.yamaha.smaf-phrase',
            'cmp'         => 'application/vnd.yellowriver-custom-menu',
            'zaz'         => 'application/vnd.zzazz.deck+xml',
            'vxml'        => 'application/voicexml+xml',
            'wgt'         => 'application/widget',
            'hlp'         => 'application/winhlp',
            'wsdl'        => 'application/wsdl+xml',
            'wspolicy'    => 'application/wspolicy+xml',
            '7z'          => 'application/x-7z-compressed',
            'abw'         => 'application/x-abiword',
            'ace'         => 'application/x-ace-compressed',
            'dmg'         => 'application/x-apple-diskimage',
            'aam'         => 'application/x-authorware-map',
            'aas'         => 'application/x-authorware-seg',
            'bcpio'       => 'application/x-bcpio',
            'torrent'     => 'application/x-bittorrent',
            'bz'          => 'application/x-bzip',
            'vcd'         => 'application/x-cdlink',
            'cfs'         => 'application/x-cfs-compressed',
            'chat'        => 'application/x-chat',
            'pgn'         => 'application/x-chess-pgn',
            'nsc'         => 'application/x-conference',
            'cpio'        => 'application/x-cpio',
            'csh'         => 'application/x-csh',
            'dgc'         => 'application/x-dgc-compressed',
            'wad'         => 'application/x-doom',
            'ncx'         => 'application/x-dtbncx+xml',
            'dtb'         => 'application/x-dtbook+xml',
            'res'         => 'application/x-dtbresource+xml',
            'dvi'         => 'application/x-dvi',
            'evy'         => 'application/x-envoy',
            'eva'         => 'application/x-eva',
            'bdf'         => 'application/x-font-bdf',
            'gsf'         => 'application/x-font-ghostscript',
            'psf'         => 'application/x-font-linux-psf',
            'pcf'         => 'application/x-font-pcf',
            'snf'         => 'application/x-font-snf',
            'arc'         => 'application/x-freearc',
            'spl'         => 'application/x-futuresplash',
            'gca'         => 'application/x-gca-compressed',
            'ulx'         => 'application/x-glulx',
            'gnumeric'    => 'application/x-gnumeric',
            'gramps'      => 'application/x-gramps-xml',
            'gtar'        => 'application/x-gtar',
            'hdf'         => 'application/x-hdf',
            'install'     => 'application/x-install-instructions',
            'iso'         => 'application/x-iso9660-image',
            'jnlp'        => 'application/x-java-jnlp-file',
            'latex'       => 'application/x-latex',
            'mie'         => 'application/x-mie',
            'application' => 'application/x-ms-application',
            'lnk'         => 'application/x-ms-shortcut',
            'wmd'         => 'application/x-ms-wmd',
            'wmz'         => 'application/x-ms-wmz',
            'xbap'        => 'application/x-ms-xbap',
            'mdb'         => 'application/x-msaccess',
            'obd'         => 'application/x-msbinder',
            'crd'         => 'application/x-mscardfile',
            'clp'         => 'application/x-msclip',
            'mny'         => 'application/x-msmoney',
            'pub'         => 'application/x-mspublisher',
            'scd'         => 'application/x-msschedule',
            'trm'         => 'application/x-msterminal',
            'wri'         => 'application/x-mswrite',
            'nzb'         => 'application/x-nzb',
            'p7r'         => 'application/x-pkcs7-certreqresp',
            'rar'         => 'application/x-rar-compressed',
            'ris'         => 'application/x-research-info-systems',
            'sh'          => 'application/x-sh',
            'shar'        => 'application/x-shar',
            'swf'         => 'application/x-shockwave-flash',
            'xap'         => 'application/x-silverlight-app',
            'sql'         => 'application/x-sql',
            'sit'         => 'application/x-stuffit',
            'sitx'        => 'application/x-stuffitx',
            'srt'         => 'application/x-subrip',
            'sv4cpio'     => 'application/x-sv4cpio',
            'sv4crc'      => 'application/x-sv4crc',
            't3'          => 'application/x-t3vm-image',
            'gam'         => 'application/x-tads',
            'tar'         => 'application/x-tar',
            'tcl'         => 'application/x-tcl',
            'tex'         => 'application/x-tex',
            'tfm'         => 'application/x-tex-tfm',
            'obj'         => 'application/x-tgif',
            'ustar'       => 'application/x-ustar',
            'src'         => 'application/x-wais-source',
            'fig'         => 'application/x-xfig',
            'xlf'         => 'application/x-xliff+xml',
            'xpi'         => 'application/x-xpinstall',
            'xz'          => 'application/x-xz',
            'xaml'        => 'application/xaml+xml',
            'xdf'         => 'application/xcap-diff+xml',
            'xenc'        => 'application/xenc+xml',
            'dtd'         => 'application/xml-dtd',
            'xop'         => 'application/xop+xml',
            'xpl'         => 'application/xproc+xml',
            'xslt'        => 'application/xslt+xml',
            'xspf'        => 'application/xspf+xml',
            'yang'        => 'application/yang',
            'yin'         => 'application/yin+xml',
            'zip'         => 'application/zip',
            'adp'         => 'audio/adpcm',
            's3m'         => 'audio/s3m',
            'sil'         => 'audio/silk',
            'eol'         => 'audio/vnd.digital-winds',
            'dra'         => 'audio/vnd.dra',
            'dts'         => 'audio/vnd.dts',
            'dtshd'       => 'audio/vnd.dts.hd',
            'lvp'         => 'audio/vnd.lucent.voice',
            'pya'         => 'audio/vnd.ms-playready.media.pya',
            'ecelp4800'   => 'audio/vnd.nuera.ecelp4800',
            'ecelp7470'   => 'audio/vnd.nuera.ecelp7470',
            'ecelp9600'   => 'audio/vnd.nuera.ecelp9600',
            'rip'         => 'audio/vnd.rip',
            'weba'        => 'audio/webm',
            'aac'         => 'audio/x-aac',
            'caf'         => 'audio/x-caf',
            'flac'        => 'audio/x-flac',
            'mka'         => 'audio/x-matroska',
            'm3u'         => 'audio/x-mpegurl',
            'wax'         => 'audio/x-ms-wax',
            'wma'         => 'audio/x-ms-wma',
            'rmp'         => 'audio/x-pn-realaudio-plugin',
            'wav'         => 'audio/x-wav',
            'xm'          => 'audio/xm',
            'cdx'         => 'chemical/x-cdx',
            'cif'         => 'chemical/x-cif',
            'cmdf'        => 'chemical/x-cmdf',
            'cml'         => 'chemical/x-cml',
            'csml'        => 'chemical/x-csml',
            'xyz'         => 'chemical/x-xyz',
            'ttc'         => 'font/collection',
            'otf'         => 'font/otf',
            'ttf'         => 'font/ttf',
            'woff'        => 'font/woff',
            'woff2'       => 'font/woff2',
            'bmp'         => 'image/bmp',
            'cgm'         => 'image/cgm',
            'g3'          => 'image/g3fax',
            'gif'         => 'image/gif',
            'ief'         => 'image/ief',
            'ktx'         => 'image/ktx',
            'png'         => 'image/png',
            'btif'        => 'image/prs.btif',
            'sgi'         => 'image/sgi',
            'psd'         => 'image/vnd.adobe.photoshop',
            'sub'         => 'image/vnd.dvb.subtitle',
            'dwg'         => 'image/vnd.dwg',
            'dxf'         => 'image/vnd.dxf',
            'fbs'         => 'image/vnd.fastbidsheet',
            'fpx'         => 'image/vnd.fpx',
            'fst'         => 'image/vnd.fst',
            'mmr'         => 'image/vnd.fujixerox.edmics-mmr',
            'rlc'         => 'image/vnd.fujixerox.edmics-rlc',
            'mdi'         => 'image/vnd.ms-modi',
            'wdp'         => 'image/vnd.ms-photo',
            'npx'         => 'image/vnd.net-fpx',
            'wbmp'        => 'image/vnd.wap.wbmp',
            'xif'         => 'image/vnd.xiff',
            'webp'        => 'image/webp',
            '3ds'         => 'image/x-3ds',
            'ras'         => 'image/x-cmu-raster',
            'cmx'         => 'image/x-cmx',
            'ico'         => 'image/x-icon',
            'sid'         => 'image/x-mrsid-image',
            'pcx'         => 'image/x-pcx',
            'pnm'         => 'image/x-portable-anymap',
            'pbm'         => 'image/x-portable-bitmap',
            'pgm'         => 'image/x-portable-graymap',
            'ppm'         => 'image/x-portable-pixmap',
            'rgb'         => 'image/x-rgb',
            'tga'         => 'image/x-tga',
            'xbm'         => 'image/x-xbitmap',
            'xpm'         => 'image/x-xpixmap',
            'xwd'         => 'image/x-xwindowdump',
            'dae'         => 'model/vnd.collada+xml',
            'dwf'         => 'model/vnd.dwf',
            'gdl'         => 'model/vnd.gdl',
            'gtw'         => 'model/vnd.gtw',
            'mts'         => 'model/vnd.mts',
            'vtu'         => 'model/vnd.vtu',
            'appcache'    => 'text/cache-manifest',
            'css'         => 'text/css',
            'csv'         => 'text/csv',
            'n3'          => 'text/n3',
            'dsc'         => 'text/prs.lines.tag',
            'rtx'         => 'text/richtext',
            'tsv'         => 'text/tab-separated-values',
            'ttl'         => 'text/turtle',
            'vcard'       => 'text/vcard',
            'curl'        => 'text/vnd.curl',
            'dcurl'       => 'text/vnd.curl.dcurl',
            'mcurl'       => 'text/vnd.curl.mcurl',
            'scurl'       => 'text/vnd.curl.scurl',
            'fly'         => 'text/vnd.fly',
            'flx'         => 'text/vnd.fmi.flexstor',
            'gv'          => 'text/vnd.graphviz',
            '3dml'        => 'text/vnd.in3d.3dml',
            'spot'        => 'text/vnd.in3d.spot',
            'jad'         => 'text/vnd.sun.j2me.app-descriptor',
            'wml'         => 'text/vnd.wap.wml',
            'wmls'        => 'text/vnd.wap.wmlscript',
            'java'        => 'text/x-java-source',
            'nfo'         => 'text/x-nfo',
            'opml'        => 'text/x-opml',
            'etx'         => 'text/x-setext',
            'sfv'         => 'text/x-sfv',
            'uu'          => 'text/x-uuencode',
            'vcs'         => 'text/x-vcalendar',
            'vcf'         => 'text/x-vcard',
            '3gp'         => 'video/3gpp',
            '3g2'         => 'video/3gpp2',
            'h261'        => 'video/h261',
            'h263'        => 'video/h263',
            'h264'        => 'video/h264',
            'jpgv'        => 'video/jpeg',
            'ogv'         => 'video/ogg',
            'dvb'         => 'video/vnd.dvb.file',
            'fvt'         => 'video/vnd.fvt',
            'pyv'         => 'video/vnd.ms-playready.media.pyv',
            'viv'         => 'video/vnd.vivo',
            'webm'        => 'video/webm',
            'f4v'         => 'video/x-f4v',
            'fli'         => 'video/x-fli',
            'flv'         => 'video/x-flv',
            'm4v'         => 'video/x-m4v',
            'mng'         => 'video/x-mng',
            'vob'         => 'video/x-ms-vob',
            'wm'          => 'video/x-ms-wm',
            'wmv'         => 'video/x-ms-wmv',
            'wmx'         => 'video/x-ms-wmx',
            'wvx'         => 'video/x-ms-wvx',
            'avi'         => 'video/x-msvideo',
            'movie'       => 'video/x-sgi-movie',
            'smv'         => 'video/x-smv',
            'ice'         => 'x-conference/x-cooltalk'
        ];
        $filename_array = explode('.', $filename);
        $ext            = strtolower(array_pop($filename_array));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        } elseif (function_exists('finfo_open')) {
            $f_info    = finfo_open(FILEINFO_MIME);
            $mime_type = finfo_file($f_info, $filename);
            finfo_close($f_info);
            return $mime_type;
        } else {
            return 'application/octet-stream';
        }
    }

}
