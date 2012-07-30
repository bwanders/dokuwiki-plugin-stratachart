<?php

/*
**
** CONFIGURATION
**
*/

$conf = array(
    // an array of hexadecimal color codes
    'colors' => array(
        'a4a4ff',
        'a4ffa4',
        'ffa4a4',
        'a4ffff',
        'ffa4ff',
        'ffffa4',
    ),

    // default settings (can be overriden)
    'defaults' => array(
        'width' => 340,
        'height' => 300,

        // show a legend?
        'legend' => true,

        // siginficance of rendered numbers (-1 for auto)
        'significance' => -1,

        // sort pie slices large to small?
        'sort' => true,

        // colour settings
        'background-color' => 'ffffff',
        'color' => '000001',

        // antialias the pie?
        'antialias' => true
    )
);

/*
**
** UTILITIES
**
*/

// color allocation function
function hexcolor($image, $hex) {
    $i = hexdec($hex);
    return imagecolorallocate($image, 0xFF & ($i >> 0x10), 0xFF & ($i >> 0x8), 0xFF & ($i));
}

/*
**
**  ERROR HANDLING
**
*/

/**
 * Draws a simple error box with the given message.
 * This function handles \n and \t correctly, interpreting a \t as
 * being 4 spaces.
 */
function error($message) {
    // Determine text sizes
    $lines = explode("\n",str_replace("\t",'   ',$message));
    $maxline = 0;
    $lineCount = 0;
    foreach($lines as $line) {
        $lineCount++;
        $maxline = max($maxline,strlen($line));
    }

    // calculate image box
    $h = (imagefontheight(2)+1) * $lineCount;
    $w = imagefontwidth(2) * $maxline;

    // create image and prep border
    $im = imagecreatetruecolor($w + 6, $h + 4);
    $red = imagecolorallocate($im,255,0,0);
    $white = imagecolorallocate($im,255,255,255);
    $black = imagecolorallocate($im,0,0,0);

    // draw border and text
    imagefilledrectangle($im,0,0,$w+6,$h+4,$red);
    imagefilledrectangle($im,2,2,$w+3,$h+1,$white);
    $curline = 2;
    foreach($lines as $line) {
        imagestring($im, 2, 4, $curline, $line,$black);
        $curline += imagefontheight(2)+1;
    }

    // output image
    header('content-type: image/png');
    imagepng($im);
    imagedestroy($im);
    die();
}

function onError($errno , $errstr, $errfile, $errline) {
    error("$errfile:$errline\nError No: $errno\nMessage: ".$errstr);
}

set_error_handler('onError');

/*
**
** INPUT
**
*/

$width = $conf['defaults']['width'];
$height = $conf['defaults']['height'];
$data = array();
$settings = $conf['defaults'];

$width = isset($_REQUEST['w']) ? intval($_REQUEST['w']) : $width;
$height = isset($_REQUEST['h']) ? intval($_REQUEST['h']) : $height;

if(isset($_REQUEST['s'])) {
    foreach(explode('|',$_REQUEST['s']) as $s) {
        list($key,$value) = explode(':',$s);
        switch($key) {
            case 'legend': $settings['legend'] = $value=='on'; break;
            case 'significance': $settings['significance'] = intval($value); break;
            case 'sort': $settings['sort'] = $value=='on'; break;
            case 'background-color': $settings['background-color'] = $value; break;
            case 'color': $settings['color'] = $value; break;
            default: error("Unknown setting '$key'");
        }
    }
}

if(isset($_REQUEST['d'])) {
    // explode data
    $dx = explode('|',$_REQUEST['d']);

    // sanity check data
    if(count($dx)%2 != 0) error('Data string incomplete. (It\'s not a complete key|value sequence)');

    // unpkac data for use
    for($i=0;$i<count($dx);$i+=2) {
        $data[$dx[$i]] = $dx[$i+1];
    }
} else {
    error('No data');
}


// auto-detect significance
if($settings['significance'] < 0) {
    foreach($data as $value) {
        $settings['significance'] = max(
            $settings['significance'], 
            strlen(strval($value-floor($value)))-2
        );
    }
}


/*
**
** RENDERING
**
*/

function render($width, $height, $data, $settings) {
    global $conf;

    //create image
    $image = imagecreatetruecolor($width, $height);
    $translucent = imagecolorallocatealpha($image,0,0,0,127);
    imagefilledrectangle($image,0,0,$width,$height,$translucent);

    $slices = array();
    $sliceColors = array();
    $sum = 0;

    $longestKey = '';

    foreach($data as $key=>$value) {
        $key = "$key (".number_format($value,$settings['significance'],"."," ").")";

        $slices[$key] = $value;
        $sum = $sum + $value;

        if(strlen($longestKey) < strlen($key)) $longestKey = $key;
    }

    if($settings['sort']) {
        // sort slices from largest to smallest
        arsort($slices, SORT_NUMERIC);
    }

    $numSlices = 0;
    foreach($slices as $key=>$value) {
        $sliceColors[$key] = hexcolor($image,$conf['colors'][$numSlices % count($conf['colors'])]);
        $numSlices++;
    }

    /** measures **/
    $fontsize = 4; //size of font
    $graphPadding = 5; //space between components and border
    $graphSpacing = 5; //space between components

    $legendPadding = 2; //space between legend border and content
    $legendVerticalSpacing = 2; //vertical space between rows in legend
    $legendColorboxSpacing = 2; //spacing between colored box and key

    $legendTextWidth =
        imagefontwidth($fontsize) * strlen($longestKey); // width of longest key in legend

    $legendTextHeight = 
        imagefontheight($fontsize); //height of text in legend

    $legendColorboxSize = $legendTextHeight-1; //size of colored box in legend

    $legendWidth = 
        2*$legendPadding 
        + $legendColorboxSize 
        + $legendColorboxSpacing 
        + $legendTextWidth; //needed width for legend

    $legendHeight = 
        2*$legendPadding 
        + $numSlices*($legendVerticalSpacing + $legendTextHeight); //needed height for legend

    if(!$settings['legend']) {
        $legendWidth = $legendHeight = 0;
    }

    $horzPieSize = min( 
        $width 
        - 2*$graphPadding 
        - $graphSpacing 
        - $legendWidth,
        $height
        - 2*$graphPadding); //Largest pie size for horizontal layout

    $vertPieSize = min(
        $height 
        - 2*$graphPadding 
        - $graphSpacing 
        - $legendHeight,
        $width
        - 2*$graphPadding); //largest pie size for vertical layout

    $pieSize = max($horzPieSize, $vertPieSize); //choose best size
    $verticalLayout = $vertPieSize > $horzPieSize;

    /** Draw Pie Chart **/
    if($settings['antialias']) {
        // antialiased render (by way of resampling superscaled render)
        $canvas = imagecreatetruecolor($pieSize*4+8, $pieSize*4+8);
        $canvasbg = hexcolor($canvas, $settings['background-color']);
        imagefilledrectangle($canvas, 0,0, $pieSize*4+8, $pieSize*4+8, $canvasbg);

        $cx = $cy = $pieSize*2+4;
        $arcw = $arch = $pieSize*4;
    } else {
        $canvas = $image;

        // center of pie
        $cx = $cy = $graphPadding + $pieSize/2;
        $arcw = $arch = $pieSize;
    }

    $startAngle = -90;
    foreach($slices as $key => $value) {
        if($value > 0) {
            $endAngle = $startAngle + ($value/$sum) * 360;
            imagefilledarc($canvas, $cx, $cy, $arcw, $arch, $startAngle, $endAngle, $sliceColors[$key], IMG_ARC_PIE);
            $startAngle = $endAngle;
        }
    }

    // finishe antialiasing business
    if($settings['antialias']){
        imagecopyresampled($image, $canvas, $graphPadding,$graphPadding, 2,2, $pieSize,$pieSize, $pieSize*4+4,$pieSize*4+4);
        imagedestroy($canvas);
    }


    /** Draw Legend **/
    if($settings['legend']) {
        if($verticalLayout) {
            $lx = $graphPadding;
            $ly = $graphPadding + $pieSize + $graphSpacing;
        } else {
            $lx = $graphPadding + $pieSize + $graphSpacing;
            $ly = $graphPadding;
        }

        $white = hexcolor($image, $settings['background-color']);
        $black = hexcolor($image, $settings['color']);

        imagefilledrectangle($image,$lx,$ly,$lx+$legendWidth,$ly+$legendHeight,$white);
        imagerectangle($image,$lx,$ly,$lx+$legendWidth,$ly+$legendHeight,$black);

        $px = $lx + $legendPadding;
        $py = $ly + $legendPadding;

        foreach($slices as $key=>$value) {
            $color = $sliceColors[$key];
            imagefilledrectangle($image, $px, $py, $px+$legendColorboxSize, $py+$legendColorboxSize,$color);
            imagestring($image,$fontsize,$px+$legendColorboxSize+$legendColorboxSpacing, $py, $key, $black);
            $py += $legendVerticalSpacing + $legendTextHeight;
        }
    }


    /** Dump image **/

    imagecolortransparent($image, $translucent);
    //imagerectangle($image,0,0,$width-1,$height-1,$black);

    return $image;
}

// do actual render
$image = render($width, $height, $data, $settings);

header('content-type: image/png');
imagepng($image);
