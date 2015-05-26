<?php
global $UMC_SETTING, $WS_INIT, $UMC_BANNERS;

$UMC_BANNERS = array(
    'patterns' => array(
        'GRADIENT' => 'gra',
        'GRADIENT_UP' => 'gru',
        'BRICKS' => 'bri',
        'HALF_HORIZONTAL' => 'hh',
        'HALF_HORIZONTAL_MIRROR' => 'hhb',
        'HALF_VERTICAL' => 'vh',
        'HALF_VERTICAL_MIRROR' => 'vhr',
        'STRIPE_TOP' => 'ts',
        'STRIPE_BOTTOM' => 'bs',
        'STRIPE_LEFT' => 'ls',
        'STRIPE_RIGHT' => 'rs',
        'DIAGONAL_LEFT' => 'ld',
        'DIAGONAL_RIGHT_MIRROR' => 'rud',
        'DIAGONAL_LEFT_MIRROR' => 'lud',
        'DIAGONAL_RIGHT' => 'rd',
        'CROSS' => 'cr',
        'STRIPE_DOWNLEFT' => 'dls',
        'STRIPE_DOWNRIGHT' => 'drs',
        'STRAIGHT_CROSS' => 'sc',
        'STRIPE_CENTER' => 'cs',
        'STRIPE_MIDDLE' => 'ms',
        'SQUARE_TOP_LEFT' => 'tl',
        'SQUARE_BOTTOM_LEFT' => 'bl',
        'SQUARE_TOP_RIGHT' => 'tr',
        'SQUARE_BOTTOM_RIGHT' => 'br',
        'TRIANGLE_TOP' => 'tt',
        'TRIANGLE_BOTTOM' => 'bt',
        'RHOMBUS_MIDDLE' => 'mr',
        'CIRCLE_MIDDLE' => 'mc',
        'TRIANGLES_BOTTOM' => 'bts',
        'TRIANGLES_TOP' => 'tts',
        'STRIPE_SMALL' => 'ss',
        'BORDER' => 'bo',
        'CURLY_BORDER' => 'cbo',
        'FLOWER' => 'flo',
        'CREEPER' => 'cre',
        'SKULL' => 'sku',
        'MOJANG' => 'moj',
    ),
    'colors' => array(
        'BLACK' => 0,
        'RED' => 1,
        'GREEN' => 2,
        'BROWN' => 3,
        'BLUE' => 4,
        'PURPLE' => 5,
        'CYAN' => 6,
        'SILVER' => 7,
        'GRAY' => 8,
        'PINK' => 9,
        'LIME' => 10,
        'YELLOW' => 11,
        'LIGHT_BLUE' => 12,
        'MAGENTA' => 13,
        'ORANGE' => 14,
        'WHITE' => 15,
    ),
);

$WS_INIT['banner'] = array(  // the name of the plugin
    'disabled' => true,
    'events' => false,
    'default' => array(
        'help' => array(
            'title' => 'Banners',  // give it a friendly title
            'short' => 'Get Banners',  // a short description
            'long' => "Manages Banners", // a long add-on to the short  description
            ),
    ),
    'wear' => array( // this is the base command if there are no other commands
        'help' => array(
            'short' => 'Wear a banner',
            'long' => "Wears a banner that you are currently holding",
        ),
        'function' => 'umc_banner_wear',
    ),
);

/**
 * convert a serialized string to a /give code
 */
function umc_banner_get_data($data) {
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    global $UMC_BANNERS;
    // In-game meta-code: {BlockEntityTag:{Base:15,Patterns:[{Pattern:ts,Color:6},{Pattern:hh,Color:15},{Pattern:bs,Color:2}]}}
    // JSON: {\"Patterns\":[{\"PatternType\":\"STRIPE_TOP\",\"Color\":\"CYAN\"},{\"PatternType\":\"HALF_HORIZONTAL\",\"Color\":\"WHITE\"},{\"PatternType\":\"STRIPE_BOTTOM\",\"Color\":\"GREEN\"}],\"BaseColor\":\"WHITE\"}
    // array 
    /*
    Patterns ⇒
        0 ⇒
            PatternType ⇒ "STRIPE_TOP"
            Color ⇒ "CYAN"
        1 ⇒
            PatternType ⇒ "HALF_HORIZONTAL"
            Color ⇒ "WHITE"
        2 ⇒
            PatternType ⇒ "STRIPE_BOTTOM"
            Color ⇒ "GREEN"
    BaseColor ⇒ "WHITE"
     */
    
    $base = key($data);
    $basecolor = $UMC_BANNERS['colors'][$base];
    $out = " {BlockEntityTag:{Base:$basecolor,Patterns:[";
    $outelements = array();
    $patterns = $data[$base];
    foreach ($patterns as $index => $pattern) {
        XMPP_ERROR_trace($index, $pattern);
        $color = $UMC_BANNERS['colors'][$pattern['Color']];
        $shape = $UMC_BANNERS['patterns'][$pattern['PatternType']];
        $outelements[] .= "{Pattern:$shape,Color:$color}";
    }
    $out .= implode(",", $outelements);
    
    $out .= "]}}";
    XMPP_ERROR_trace("Banner command: " . $out);
    return $out;
}

/**
 * This will allow a user to wear a banner that is in their hands
 */
function umc_banner_wear() {

}

/**
 * This generates a website that allows users to create their own banner
 */
function umc_banner_web(){
    $out = '<link rel="stylesheet" type="text/css" href="/admin/banner.css" />
<div onload="banner_init()">
    <div id="hidey"></div>
    <div class="fix" id="main_banner">
        <div id="screenshot">
            <div class="canvas-cont">
                <div id="base-overlay"></div>
                <div id="preview"></div>
                <div class="header">
                    <div>Preview</div>
                </div>
                <div id="canvas">
                    <i ptn="base" id="base" clr="0"></i>
                </div>
                <div class="canv-tool">
                    <div class="button" style="background:#CE893A;" onmousedown="chaos()">Random</div>
                    <div class="button clearall bred" onmousedown="clearAll()">Clear All</div>
                </div>
                <div id="saved-gal-cont" class="hidden">
                    <div class="header"><div>URL Banners</div></div>
                    <div id="saved-gal" onmousedown="savedGalHandler(event)"></div>
                </div>
            </div>
            <div class="layers-cont">
                <div class="header">
                    <div>Layers</div>
                    <div class="header-button" onmousedown="compact(event)">Compact mode</div>
                </div>
                <div id="layers">
                    <div class="layer">
                        <div class="layer-color black"></div>
                        <div class="layer-pattern">
                            <div class="layer-hover">
                                <div class="layer-color-container">
                                    <div class="tb-color black" title="black"></div>
                                    <div class="tb-color dark_gray" title="dark_gray"></div>
                                    <div class="tb-color gray" title="gray"></div>
                                    <div class="tb-color white" title="white"></div>
                                    <div class="tb-color pink" title="pink"></div>
                                    <div class="tb-color magenta" title="magenta"></div>
                                    <div class="tb-color purple" title="purple"></div>
                                    <div class="tb-color blue" title="blue"></div>
                                    <div class="tb-color cyan" title="cyan"></div>
                                    <div class="tb-color light_blue" title="light_blue"></div>
                                    <div class="tb-color green" title="green"></div>
                                    <div class="tb-color lime" title="lime"></div>
                                    <div class="tb-color yellow" title="yellow"></div>
                                    <div class="tb-color orange" title="orange"></div>
                                    <div class="tb-color brown" title="brown"></div>
                                    <div class="tb-color red" title="red"></div>
                                </div>
                            </div>
                            <i class="tb-ptn" clr="0" ptn="base"></i>
                        </div>
                        <div class="layer-craft"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="toolbar-cont">
            <div class="overtool">
                <span class="url-desc">Share link:</span>
                <input type="text" id="url" onclick="this.select()" />
            </div>
            <div class="header">
                <div>Layer Creation</div>
            </div>
            <div id="toolbar">
                <div id="toolbar-colors" onmousedown="colorsHandler(event)">
                    <div class="tb-color black" title="black"></div>
                    <div class="tb-color dark_gray" title="dark_gray"></div>
                    <div class="tb-color gray" title="gray"></div>
                    <div class="tb-color white sel"></div>
                    <div class="tb-color pink" title="pink"></div>
                    <div class="tb-color magenta" title="magenta"></div>
                    <div class="tb-color purple" title="purple"></div>
                    <div class="tb-color blue" title="blue"></div>
                    <div class="tb-color cyan" title="cyan"></div>
                    <div class="tb-color light_blue" title="light_blue"></div>
                    <div class="tb-color green" title="green"></div>
                    <div class="tb-color lime" title="lime"></div>
                    <div class="tb-color yellow" title="yellow"></div>
                    <div class="tb-color orange" title="orange"></div>
                    <div class="tb-color brown" title="brown"></div>
                    <div class="tb-color red" title="red"></div>
                </div>
                <div id="toolbar-patterns" onmousedown="patternsHandler(event)" onmouseover="showPreview(event)" onmouseout="hidePreview(event)">
                    <div class="tbc"><i class="tb-ptn" ptn="gra" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="gru" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="bri" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="hh" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="hhb" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="vh" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="vhr" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="ts" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="bs" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="ls" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="rs" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="ld" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="rud" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="lud" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="rd" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="cr" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="dls" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="drs" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="sc" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="cs" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="ms" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="tl" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="bl" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="tr" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="br" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="tt" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="bt" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="mr" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="mc" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="bts" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="tts" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="ss" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="bo" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="cbo" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="flo" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="cre" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="sku" clr="15"></i></div>
                    <div class="tbc"><i class="tb-ptn" ptn="moj" clr="15"></i></div>
                </div>
            </div>
            <div class="header"><div>Code</div></div>
            <div class="generate">
                <div class="button" onclick="jsonOutput(0)">/give Code</div>
                <div class="button" onclick="jsonOutput(1)">/setblock Code</div>
                <div class="button" onclick="jsonOutput(2)">/replaceitem Code</div>
                <div class="button" style="background:#CE893A;" onclick="screenshot()">Screenshot</div>
                <span id="scrdl"><span></span></span>
                <textarea id="code"></textarea>
                <div class="button" style="padding: 0 40px; margin-top: 4px;" onclick="jsonInput()">Import Code</div>
            </div>
            <div class="header"><div>Saved Banners</div></div>
            <div id="saved" onmousedown="savedHandler(event)">
                <div class="saved-add">+</div>
            </div>
            <div class="undertool">
                <span class="url-desc">Share saved banners:</span><input type="text" id="url-gal" onclick="this.select()"/>
            </div>
        </div>
    </div>
</div>
<script src="/admin/js/banner_main.js"></script>
<script src="/admin/js/banner_html2canvas.js"></script>
<script src="/admin/js/banner_Sortable.js"></script>';
    return $out;
}