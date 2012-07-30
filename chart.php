<?php

/*
**
** CONFIGURATION
**
*/

$conf = array(
    // an array of hexadecimal color codes
    'colors' => array(
        '5050ff',
        '7070ff',
        '9090ff',
        'b0b0ff',
        'd0d0ff',
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
        'background' => 'ffffff',
        'legend-background' => 'ffffff',
        'legend-color' => '000000',
        'legend-border' => '000000',

        // antialias the pie?
        'antialias' => false
    )
);

/*
**
** UTILITIES
**
*/

// color allocation function
function hexcolor($image, $hex) {
    list($r,$g,$b) = hex2rgb($hex);
    return imagecolorallocate($image, $r, $g, $b);
}

function hex2rgb($hex) {
    $i = hexdec($hex);
    return array(0xFF & ($i >> 0x10), 0xFF & ($i >> 0x8), 0xFF & ($i));
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

foreach($_GET as $key=>$value) {
    switch($key) {
        case 'legend': $settings['legend'] = $value=='on'; break;
        case 'significance': $settings['significance'] = intval($value); break;
        case 'sort': $settings['sort'] = $value=='on'; break;
        case 'legend-background': $settings['legend-background'] = $value; break;
        case 'legend-color': $settings['legend-color'] = $value; break;
        case 'legend-border': $settings['legend-border'] = $value; break;
        case 'background': $settings['background'] = $value; break;
        case 'aa': $settings['antialias'] = $value=='on'; break;
        case 'w': $width = intval($value); break;
        case 'h': $height = intval($value); break;
        case 'd': continue; // skip data for later
        default: error("Unknown setting '$key'");
    }
}

if(isset($_GET['d'])) {
    if(trim($_GET['d']) != '') {
        // explode data
        $dx = explode('|',$_GET['d']);

        // sanity check data
        if(count($dx)%2 != 0) error('Data string incomplete. (It\'s not a complete key|value sequence)');

        // unpack data for use
        for($i=0;$i<count($dx);$i+=2) {
            $data[] = array($dx[$i], $dx[$i+1]);
        }
    } else {
        $settings['message'] = 'No data available';
    }
} else {
    error('No data');
}


// auto-detect significance
if($settings['significance'] < 0) {
    foreach($data as $entry) {
        list($key, $value) = $entry;
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

    $fontsize = 3;

    //create image
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $translucent = imagecolorallocatealpha($image,0,0,0,127);
    imagefilledrectangle($image,0,0,$width,$height,$translucent);

    if(isset($settings['message'])) {
        $mw = imagefontwidth($fontsize)*strlen($settings['message']);
        $mh = imagefontheight($fontsize);
        $messageBg = hexcolor($image, $settings['legend-background']);
        $messageFg = hexcolor($image, $settings['legend-color']);
        $messageBc = hexcolor($image, $settings['legend-border']);
        $mboxw = $width/2-$mw/2;
        $mboxh = $height/2-$mh/2;
        imagefilledrectangle($image, 
            $mboxw - 10, 
            $mboxh - 8,
            $mboxw + $mw+10,
            $mboxh + $mh+8,
            $messageBg);
        imagerectangle($image, 
            $mboxw - 10, 
            $mboxh - 8,
            $mboxw + $mw+10,
            $mboxh + $mh+8,
            $messageBc);

        imagestring($image, $fontsize, $mboxw, $mboxh, $settings['message'], $messageFg);
        return $image;
    }

    $slices = array();
    $sliceColors = array();
    $sum = 0;

    $longestKey = '';

    foreach($data as $entry) {
        list($key, $value) = $entry;
        $key = "$key (".number_format($value,$settings['significance'],"."," ").")";

        $slices[] = array($key, $value);
        $sum = $sum + $value;

        if(strlen($longestKey) < strlen($key)) $longestKey = $key;
    }

    if($settings['sort']) {
        // sort slices from largest to smallest
        usort($slices, function($a, $b) {
            return $b[1] - $a[1];
        });
    }

    $numSlices = 0;
    foreach($slices as $index=>$entry) {
        $sliceColors[$index] = hexcolor($image,$conf['colors'][$numSlices % count($conf['colors'])]);
        $numSlices++;
    }

    /** measures **/

    $graphPadding = 1; //space between components and border
    $graphSpacing = 5; //space between components

    $legendPadding = 2; //space between legend border and content
    $legendVerticalSpacing = 2; //vertical space between rows in legend
    $legendColorboxSpacing = 2; //spacing between colored box and key
    $legendBorder = 1;

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
        + $numSlices*($legendVerticalSpacing + $legendTextHeight) - $legendVerticalSpacing
        + $legendBorder; //needed height for legend

    if(!$settings['legend']) {
        // disable legend dimensions
        $legendWidth = $legendHeight = 0;
        // we have no components to space
        $graphSpacing = 0;
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

    if($pieSize <= 0) {
        error("No space to draw pie chart\n(very small image combined\nwith enabled legend?).");
    }

    /** Draw Pie Chart **/
    if($settings['antialias']) {
        // antialiased render (by way of resampling superscaled render)
        $aa = 4;

        $canvas = imagecreatetruecolor($pieSize*$aa+8, $pieSize*$aa+8);
        $canvasbg = hexcolor($canvas, $settings['background']);
        imagefilledrectangle($canvas, 0,0, $pieSize*$aa+8, $pieSize*$aa+8, $canvasbg);

        $cx = $cy = $pieSize*($aa/2)+4;
        $arcw = $arch = $pieSize*$aa;
    } else {
        // clear render, so use direct canvas
        $canvas = $image;

        // center of pie
        $cx = $cy = $graphPadding + $pieSize/2;
        $arcw = $arch = $pieSize;
    }

    // draw actual pie
    $startAngle = -90;
    foreach($slices as $index=>$entry) {
        list($key,$value) = $entry;
        if($value > 0) {
            $endAngle = $startAngle + ($value/$sum) * 360;
            imagefilledarc($canvas, $cx, $cy, $arcw, $arch, $startAngle, $endAngle, $sliceColors[$index], IMG_ARC_PIE);
            $startAngle = $endAngle;
        }
    }

    // finishe antialiasing business
    if($settings['antialias']){
        imagecopyresampled($image, $canvas, $graphPadding,$graphPadding, 2,2, $pieSize,$pieSize, $pieSize*$aa+4,$pieSize*$aa+4);
        imagedestroy($canvas);
    }


    /** Draw Legend **/
    if($settings['legend']) {
        putenv('GDFONTPATH=' . realpath('.'));
        if($verticalLayout) {
            $lx = $graphPadding;
            $ly = $graphPadding + $pieSize + $graphSpacing;
        } else {
            $lx = $graphPadding + $pieSize + $graphSpacing;
            $ly = $graphPadding;
        }

        $legendBg = hexcolor($image, $settings['legend-background']);
        $legendFg = hexcolor($image, $settings['legend-color']);
        $legendBc = hexcolor($image, $settings['legend-border']);

        imagefilledrectangle($image,$lx,$ly,$lx+$legendWidth,$ly+$legendHeight,$legendBg);
        imagerectangle($image,$lx,$ly,$lx+$legendWidth,$ly+$legendHeight,$legendBc);

        $px = $lx + $legendPadding + $legendBorder;
        $py = $ly + $legendPadding + $legendBorder;

        imagealphablending($image, true);
        foreach($slices as $index=>$entry) {
            list($key, $value) = $entry;
            $color = $sliceColors[$index];
            imagefilledrectangle($image, $px, $py, $px+$legendColorboxSize, $py+$legendColorboxSize,$color);
            imagettftext($image, 8, 0, $px+$legendColorboxSize+$legendColorboxSpacing, $py+10, $legendFg, 'Vera', $key);
            $py += $legendVerticalSpacing + $legendTextHeight;
        }
        imagealphablending($image, false);
    }


    /** Dump image **/

    return $image;
}

// do actual render
$image = render($width, $height, $data, $settings);

header('content-type: image/png');
imagepng($image);
