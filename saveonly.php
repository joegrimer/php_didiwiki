<?php
//echo '<B>saveonly.php (c) B Grimer 2011</B>.  Current PHP version: ' . phpversion() . '<br>';

$content = $_POST['editor']; 
//$content2=strtr ($content,chr(13),''); // replace carriage return with nothing

if (get_magic_quotes_gpc()) $content = stripslashes($content);

$file = $_POST['savefile']; 
$Saved_File = fopen($file, 'w'); 
fwrite($Saved_File, $content); 
fclose($Saved_File); 
$magic1 = get_magic_quotes_gpc();

$response = "Saved: ".$_POST['savefile']."<small> (size: ".strlen($content)." modified: ".date("d/m/Y H:i:s", filemtime($file)).") [magic]=$magic1</small>";
echo $response;
?>
