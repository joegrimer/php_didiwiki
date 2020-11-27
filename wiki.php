<?php  

/* 4apr2017 this program emulates didiwiki, but in PHP... it is based heavily on texteditor and (obviously) the design of didiwiki
 * the regex functionality is from here: http://php.net/manual/en/function.preg-replace.php
 * 6apr2017 I've now gone through 10 iterations and completely finished the program.
  * It now does exactly what it was meant to, and serves as an (almost) indistinguishable clone of the original DidiWiki written in C
  * This should be fairly well commented, and refactored.
  * The worst problems were all regex related, in order:
  * the pre-tag gave me hell (which explains the botches) followed by the table tag, bold and italic text
  * One last bug: the - command needs to be a one-liner otherwise it get's sucked into unsuspecting links
 */

$filename = $_GET['p']; // get the page name
$mode = $_GET['e']; // get the editing status
$wikiDir = 'content'; // directory where unparsed wiki files are found

if ($filename=='') $filename = "WikiHome"; // default to WikiHome if no filename given - there may be a way to incorporate this into the else
$path = $wikiDir."/".$filename;

// this long series of if and else's are for the various page types that may be displayed
if ($filename=="Search") { // checking for Search
  $string = $_GET['expr']; // get search term. This is copied from the original term used

  if ($dh = opendir($wikiDir)){
    while (($file = readdir($dh)) !== false){
      if ($file=="."||$file=="..") continue;
      $content = file_get_contents("$wikiDir/$file");
      if (strpos($content, $string) !== false) {
        $output .= ("<a href='#'>" . $file . "</a><br>");
      }
    }
    closedir($dh);
  }

} else if ($mode==1) { // newfile
  $filename="Create New Page";
  $output="<input type='text' id='titleInput'></input><br /><br />
<button type='button' id='butSave1' onclick='newPageRequest()'>Create</button>";
} else if ($filename=="Changes") { // This is a custom page
//  echo "Changes page bug";
  $buffer = fread($file,filesize($path));
  fclose($file); // not sure if I need this... it seems to be kind of spontaneously in the code ;)
  $output = ""; // Setting or blanking the output, in case the Changes page exists!

  if ($dh = opendir("$wikiDir")){
//    echo "directory open";
    while (($file = readdir($dh)) !== false){
      if ($file=="."||$file=="..") continue;
      $output .= ("<a href='#'>" . $file . "</a> ".date ("Y-F-d H:i", filemtime('$wikiDir/'.$file))."<br>");
    }
    closedir($dh);
  }

} else if (!($file = fopen($path, "r"))) { // page not find edit new file 
  $output="<textarea id='wikitext' rows='20' cols='80' wrap='virtual'></textarea><p>
<button type='button' id='butSave1' onclick='saveOnly(\"$path\")'>Save</button>
</p>";
} else if ($mode==2) { // edit existing file
  $buffer = fread($file,filesize($path));
  fclose($file);
  $output="<textarea id='wikitext' rows='20' cols='80' wrap='virtual'>$buffer</textarea><p>
<button type='button' id='butSave1' onclick='saveOnly(\"$path\")'>Save</button></p>";
} else { // show file - standard mode... get ready for the parsing!
  $buffer = fread($file,filesize($path));
  fclose($file);

  $editable = 1;
  $buffer = "\n".$buffer; //botch
  $output = $buffer; // legacy - keeping it, as it makes the code clearer for me

///  welcome to REGEX HEAVEN!
  $output = preg_replace_callback(
    '/((\n[^\s].*)|(\n$))+/im', // the un-preformatted (destiny)
    function ($matchAry) {
      $matchStr = $matchAry[0]; // for some reason the last match is sent as the second element!

    // declare (non pre) regexs' array
      $patterns = array();
      $replacements = array();

    // escape current html (as mentioned near the end of the help page)
    // Bug? This escaping still does have a slight "\" flaw which may come up with italics..,
    // though I think the end at a "." or " " should solve that, if it's still there
      $patterns[1] = '/</';
      $replacements[1] = '&lt;';
      $patterns[2] = '/</';
      $replacements[2] = '&gt;';

    // horizontal lines
      $patterns[11] = '/^(\-){4,}/mi';
      $replacements[11] = '<hr \/>';

    // links and image (since it has very similar syntax, and ordinarily makes sense here)

      $patterns[20] = '/(^|^[^ ].*)\[(https?\:\/\/.*) (.*)\]/imU';
      $replacements[20] = '$1<a href="$2">$3</a>'; // outbound complex

      $patterns[21] = '/(^|^[^ ].*)\[(https?\:\/\/[^ ]*)\]/mi';
      $replacements[21] = '$1<a href="$2">$2</a>'; // outbound simple

      $patterns[22] = '/(^[^(\!|\"| )])(https?\:\/\/[^ ]*(\.jpg|\.png|\.gif|\.jpeg))/Uim';
      $replacements[22] = '$1<img src="$2"\>'; // image

      $patterns[23] = '/(^[^ ].*[^(\!|\")])(https?\:\/\/.+)([ |\n])/imU';
      $replacements[23] = '$1<a href="$2">$2</a>$3'; // outbound inline

      $patterns[24] = '/(^|^[^ ].*)\[\?edit (.*)\]/imU';
      $replacements[24] = '$1<a href="wiki.php?p='.$filename.'&e=2">$2</a>'; // inbound edit ALONE

      $patterns[25] = '/(^|^[^ ].*)\[[\/]?([^ ]*?)\]/m';
      $replacements[25] = '$1<a href="wiki.php?p=$2">$2</a>'; // inbound simple

      $patterns[26] = '/(^|^[^ ].*)\[[\/]?([^ ]*) (.*)\]/m';
      $replacements[26] = '$1<a href="wiki.php?p=$2">$3</a>'; // inbound complex

      $patterns[27] = '/(\n|\n[^ ].* )(([A-Z][a-z]+){2,})/m';
      $replacements[27] = '$1<a href="wiki.php?p=$2">$2</a>'; // inbound CamelCase

    // text styles

      $patterns[30] = '/\*([^\s])(.*)\*/mUi';
      $replacements[30] = '<b>$1$2</b>'; // bold text 1

      $patterns[33] = '/([ |.|\n|,])\/(.+)\/([ |.|\n|,])/miU';
      $replacements[33] = '$1<i>$2</i>$3'; // italic text MERGING THESE TWO IS HARDER THAN FOR BOLD

      $patterns[34] = '/\_(.*)\_/mUi';
      $replacements[34] = '<u>$1</u>'; // underlined text... beautifully simple

      $patterns[36] = '/\-([^[>|$]]*)\-( |.)/i';
      $replacements[36] = '<s>$1</s>$2'; // strikethrough text... having a multiline problem with url's

    // unordered lists

      $patterns[41] = '/(\n\*.+)+/mi';
      $replacements[41] = '<ul>$0</ul>'; // ul

      $patterns[43] = '/^\*\* (.*)$/mi';
      $replacements[43] = '<li class="subli">$1</li>'; // double li

      $patterns[44] = '/^(\*) (.+)$/mi';
      $replacements[44] = '<li>$2</li>'; // normal li

    // ordered lists

      $patterns[45] = '/(\n\#.+)+/mi';
      $replacements[45] = '<ol>$0</ol>'; // ul

      $patterns[46] = '/^\#\# (.*)$/mi';
      $replacements[46] = '<li class="subli">$1</li>'; // double li

      $patterns[47] = '/^(\#) (.+)$/mi';
      $replacements[47] = '<li>$2</li>'; // normal li

    // tables // still has some minor bugs // FIXED (the pre fix helped me out)

      $patterns[48] = '/^\| (.*)$/mi';
      $replacements[48] = '<tr>|$1</tr>'; // normal tr

      $patterns[49] = '/\|([^\|]*)/';
      $replacements[49] = '<td>$1</td>'; // normal td

      $patterns[50] = '/(\n<tr>.+)+/m';
      $replacements[50] = '<table class="wikitable" cellspacing="0" cellpadding="4"><tbody>$0</tbody></table>'; // tbody

    // headings

      $patterns[51] = '/^(======)(.*)$/mi';
      $replacements[51] = '<h6>$2</h6>';

      $patterns[52] = '/^(=====)(.*)$/mi';
      $replacements[52] = '<h5>$2</h5>';

      $patterns[53] = '/^(====)(.*)$/mi';
      $replacements[53] = '<h4>$2</h4>';

      $patterns[54] = '/^(===)(.*)$/mi';
      $replacements[54] = '<h3>$2</h3>';

      $patterns[55] = '/^(==)(.*)$/mi';
      $replacements[55] = '<h2>$2</h2>';

      $patterns[56] = '/^(=)(.*)$/mi';
      $replacements[56] = '<h1>$2</h1>';

    // paragraphs FIXME? works.

      $patterns[61] = '/(.*)($^){2}/mi'; // interesting things happen when you remove the "m"
      $replacements[61] = '<p>$1</p>$2';

      ksort($patterns);
      ksort($replacements);
      $matchStr = preg_replace($patterns, $replacements, $matchStr);

      return "</pre>". $matchStr . "<pre>";
    },
    $buffer
  );
  $output = substr($output,6,-5); // substr is there to cull the trailing pre's.. this can be later incorporated into the previous command

}

?>
<!doctype html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$filename?></title>
<link rel="stylesheet" type="text/css" href="didistyle.css" />
<link rel='SHORTCUT ICON' href='./favicon.ico' />
<script>
// gets a new request object (windows or otherwise)
function getXMLReqObj(window) {
  if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
    return (new XMLHttpRequest());
  } else { // code for IE6, IE5
    return (new ActiveXObject("Microsoft.XMLHTTP"));
  }
}
function saveOnly(filespec) { // , content
    var content = document.getElementById("wikitext").value
    var xmlhttp = getXMLReqObj(window);
    xmlhttp.open("POST","saveonly.php",true);
    xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    xmlhttp.send("editor="+encodeURIComponent(content)+"&savefile="+filespec);
    window.location.replace("wiki.php?p=<?=$filename?>"); // now it's not really saveonly!
}
function newPageRequest(){
    var title = document.getElementById("titleInput").value
    window.location.replace("wiki.php?p="+title); // redirect
}
function search(){
    var title = document.getElementById("expr").value
    window.location.replace("wiki.php?p=Search&expr="+title); // redirect
}
</script>
</head>

<body>
<div id='header'>
<table border='0' width='100%'><tr>
<!--This table really is not my style, but I wanted to follow the original Didiwiki as closely as possible-->
  <td align='left' >
    <strong><?=$filename?></strong>
    <? if ($editable): ?>
     ( <a href='wiki.php?p=<?=$filename?>&e=2' title='Edit this wiki page contents. [alt-j]' accesskey='j'>Edit</a> )
    <? endif; ?>
  </td>
  <td align='right' >
    <a href='wiki.php?p=WikiHome' title='Visit Wiki home page. [alt-z]' accesskey='z'>Home</a> |
    <a href='wiki.php?p=Changes' title='List recent changes in the wiki. [alt-r]' accesskey='r' >Changes</a> | 
    <a href='wiki.php?e=1' title='Create a new wiki page by title. [alt-c]' accesskey='c'>New</a> | 
    <a href='http://ccgi.coldcall.plus.com/joe/didi/wiki.php?p=WikiHelp' title='Get help on wiki usage and formatting.'>Help</a> |
    <input type='search' id='expr' size='15' title='Enter text to search for and press return.'  onsearch="search()"/>
  </td>
</tr></table>
</div>
<div id='wikidata'>
<? echo $output ?><!-- an afterthought! -->
</div>
<div id='footer'>PHP DidiWiki, Version: 0.5</div>
</body>
</html>
