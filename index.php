<?php

/*

    musica: web application for browsing and listening to your music
    Copyright (C) 2000-2010 Dustin Kirkland <dustin.kirkland@gmail.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, version 3 of the
    License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

/* A list of file formats we will recognize */
$FORMATS = array("mp3", "oga", "ogg", "wav", "flac");
$JPLAYER_LIST = "";
$JPLAYER_OGG = "false";

if (file_exists("/etc/musica/config.php")) {
	include("/etc/musica/config.php");
}

function sanity_check($string) {
	$string = urldecode($string);
	$string = stripslashes($string);
	if (preg_match("/\.\.\//", $string)) {
		exit;
	}
	if (preg_match("/^\//", $string)) {
		exit;
	}
	if (preg_match("/;/", $string)) {
		exit;
	}
	return $string;
}

function visible($string) {
	if (preg_match("/^[\._]/", $string)) {
		return 0;
	}
	return 1;
}

function htmlformat($str) {
	$search = array("' '", "'&'", "'\''");
	$replace = array("+", "%26", "%27");
	$str = preg_replace($search, $replace, $str);
	return $str;
}

function is_song($str) {
	global $FORMATS;
	foreach ($FORMATS as $ext) {
		if (preg_match("/\.$ext$/i", $str)) {
			return true;
		}
	}
	return false;
}

function findsubdirs($dir) {
	$dir = escapeshellcmd($dir);
	$dirs = glob("$dir/");
	foreach (glob("$dir/{.[^.]*,*}", GLOB_BRACE|GLOB_ONLYDIR) as $sub_dir){
		$list = findsubdirs($sub_dir);
		$dirs = array_merge($dirs, $list);
	}
	return $dirs;
}

function has_files($dir) {
	foreach (scandir($dir) as $file) {
		if (is_file("$dir/$file")) {
			return true;
		}
	}
	return false;
}

function split2($path) {
	// Artist is everything before the first slash,
	// Album is everything after
	return preg_split("/\/+/", $path, 2);
}

function split3($path) {
	// Artist is everything before the first slash,
	// Song is everything after the last slash
	// Album is everything in between
	$results = preg_split("/\/+/", $path);
	$artist = array_shift($results);
	$song = array_pop($results);
	$album = array_shift($results);
	foreach ($results as $i) {
		$album = "$album/$i";
	}
	return array($artist, $album, $song);
}

function urlencode_album($album) {
	// Must allow "/" in album names, for nested albums
	return preg_replace("/%2F/", "/", urlencode($album));
}

$top = sanity_check($_REQUEST["top"]);
$middle = sanity_check($_REQUEST["middle"]);
$blank = sanity_check($_REQUEST["blank"]);
$artist = sanity_check($_REQUEST["artist"]);
$album = sanity_check($_REQUEST["album"]);
$song = sanity_check($_REQUEST["song"]);
$about = sanity_check($_REQUEST["about"]);
$misc = sanity_check($_REQUEST["misc"]);
$playlist = sanity_check($_REQUEST["playlist"]);
$download_album = sanity_check($_REQUEST["download_album"]);
$search = sanity_check($_REQUEST["search"]);
$rating = sanity_check($_REQUEST["rating"]);
$user = sanity_check($_SERVER["PHP_AUTH_USER"]);
$pw = sanity_check($_SERVER["PHP_AUTH_PW"]);

$PROTO = "http";
if ($_SERVER["HTTPS"] == "on") {
	$PROTO .= "s";
}
$PREAMBLE = "$PROTO://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["SCRIPT_NAME"]) . "/music/";

if (!$playlist && ! $download_album) {
?>

<html>
<head>
<title>Musica</title>
<link href="skin/jplayer.blue.monday.css" rel="stylesheet" type="text/css" />
<style>
<!--
a {text-decoration: none; color: blue}
a:hover {text-decoration: underline; }
a:visited {text-decoration: none;}
a.head {text-decoration: none; color: white}
a.head:hover {text-decoration: underline; }
a.head:visited {text-decoration: none;}
img { border: none; }
body {font-size: 13px; font-family: verdana,arial,helvetica,sans-serif; font-weight: 400; color: #000000;}
-->
</style>
<link rel="shortcut icon" href="/musica/favicon.ico" type="image/x-icon">
</head>

<?
}

/*****************/
/* get functions */
/*****************/

function get_all_artists($search="") {
	$artists = array();
	if ($dir = opendir("music")) {
		while (($artist = readdir($dir)) !== false) {
			if (is_dir("music/$artist") && visible($artist)) {
				if (!$search || preg_match("/$search/i", $artist)) {
					array_push($artists, $artist);
				}
			}
		}
	} else {
		print("<b>ERROR</b><br>No music found.<br><br>Create a symlink to your music folder at<pre>" . dirname($_SERVER["SCRIPT_FILENAME"]) . "/music</pre>");
		exit;
	}
	sort($artists);
	return $artists;
}

function get_all_albums($search="") {
	$albums = array();
	if ($search) {
		$contents = @file("/var/lib/musica/albums");
		$albums = preg_grep("/.*\/.*$search/i", $contents);
	} else {
		$artists = get_all_artists();
		for ($i=0; $i<sizeof($artists); $i++) {
			$a =  get_albums_by_artist($artists[$i]);
			for ($j=0; $j<sizeof($a); $j++) {
				list($artist, $album) = split2($a[$j]);
				if (!$search || preg_match("/$search/i", $album)) {
					array_push($albums, $a[$j]);
				}
			}
		}
	}
	sort($albums);
	return $albums;
}

function get_all_songs($search="", $rating=0) {
	if ($rating) {
		$ratings = get_all_ratings();
		$artists = array_keys($ratings);
	} else {
		$artists = get_all_artists();
	}
	$songs = array();
	$images = array();
	if ($search) {
		$contents = @file("/var/lib/musica/songs");
		$songs = preg_grep("/.*\/.*\/.*$search/i", $contents);
	} else {
		for ($i=0; $i<sizeof($artists); $i++) {
			list($s, $images) = get_songs_by_album($artists[$i]);
			for ($j=0; $j<sizeof($s); $j++) {
				list($artist, $album, $song) = split3($s[$j]);
				if (!$rating || $ratings["$artist"] >= $rating) {
					array_push($songs, $s[$j]);
				}
			}
		}
		$albums = get_all_albums();
		for ($i=0; $i<sizeof($albums); $i++) {
			list($artist, $album) = split2($albums[$i]);
			list($s, $images) = get_songs_by_album($artist, $album);
			for ($j=0; $j<sizeof($s); $j++) {
				list($artist, $album, $song) = split3($s[$j]);
				if (!$rating || $ratings["$artist"] >= $rating) {
					array_push($songs, $s[$j]);
				}
			}
		}
	}
	sort($songs);
	return $songs;
}

function get_albums_by_artist($artist) {
	$albums = array();
	if (is_dir("music/$artist")) {
		$albums = findsubdirs("music/$artist");
	}
	array_shift($albums);
	for ($i=0; $i<sizeof($albums); $i++) {
		if (! has_files($albums[$i])) {
			$albums[$i] = "";
		} else {
			$albums[$i] = preg_replace("/^music\//", "", $albums[$i]);
			$albums[$i] = preg_replace("/\/$/", "", $albums[$i]);
		}
	}
	sort($albums);
	return $albums;
}

function get_all_ratings() {
	$file = "music/.ratings";
	$ratings = array();
	if (file_exists($file)) {
		$lines = @file($file);
	} else {
		return $ratings;
	}
	foreach ($lines as $line) {
		list($a, $r) = preg_split("/\t/", $line);
		$r = preg_replace("/\s+/", "", $r);
		$ratings["$a"] = $r;
	}
	return $ratings;
}

function get_rating($artist) {
	$ratings = get_all_ratings();
	$r = $ratings["$artist"];
	if (!isset($r)) {
		$r = 0;
	}
	$str = "";
	for ($i=1; $i<=$r; $i++) {
		$str .= "<a href=?rating=$i&artist=" . urlencode($artist) . "><img border=0 src=silk/star.png></a> ";
	}
	return $str;
}

function set_rating($artist, $rating) {
	if ($rating>=0 && $rating<=5) {
		$ratings = get_all_ratings();
		$ratings["$artist"] = $rating;
		$artists = array_keys($ratings);
		sort($artists);
		$fp = @fopen("music/.ratings", "w");
		if (!$fp) {
			print("<b>ERROR</b><br>Incorrect permissions on .ratings<p>To use this feature, the file<br><pre>  " . dirname($_SERVER["SCRIPT_FILENAME"]) . "/music/.ratings</pre>must be readable and writeable by the user<pre>  ". $_ENV["APACHE_RUN_USER"] . "</pre></p>");
			exit;
		}
		foreach ($artists as $a) {
			fprintf($fp, "%s\t%s\n", $a, $ratings["$a"]);
		}
		fclose($fp);
	}
}

function get_songs_by_album($artist, $album="") {
	$songs = array();
	$images = array();
	if (is_dir("music/$artist/$album")) {
		if ($dir = opendir("music/$artist/$album")) {
			while (($file = readdir($dir)) !== false){
				if (visible($artist) && visible($album) && visible($file)) {
					if (is_song("music/$artist/$album/$file")) {
						array_push($songs, "$artist/$album/$file");
					} elseif (exif_imagetype("music/$artist/$album/$file")) {
						array_push($images, "music/$artist/$album/$file");
					}
				}
			}
			closedir($dir);
		}
	}
	sort($songs);
	return array($songs, $images);
}

function get_songs_by_artist($artist) {
	$songs = array();
	$images = array();
	list($s, $images) = get_songs_by_album($artist);
	for ($i=0; $i<sizeof($s); $i++) {
		list($artist, $album, $song) = split3($s[$i]);
		if (!$search || preg_match("/$search/", $song)) {
			if (!$rating || $ratings["$artist"] >= $rating) {
				array_push($songs, $s[$i]);
			}
		}
	}
	$albums = get_albums_by_artist($artist);
	for ($i=0; $i<sizeof($albums); $i++) {
		list($artist, $album) = split2($albums[$i]);
		list($s, $images) = get_songs_by_album($artist, $album);
		for ($j=0; $j<sizeof($s); $j++) {
			list($artist, $album, $song) = split3($s[$j]);
			if (!$search || preg_match("/$search/i", $song)) {
				if (!$rating || $ratings["$artist"] >= $rating) {
					array_push($songs, $s[$j]);
				}
			}
		}
	}
	sort($songs);
	return $songs;
}

function get_size_of_album($artist, $album) {
	$songs = array();
	$images = array();
	list($songs, $images) = get_songs_by_album($artist, $album);
	$size = 0;
	for ($i=0; $i<sizeof($songs); $i++) {
		$size += filesize("music/$songs[$i]");
	}
	/* Conservative size estimate in MB */
        $size = round($size/1000/1000);
	return "~$size MB";
}

function get_random_artist() {
	$artists = get_all_artists();
	$artist = $artists[rand(0, sizeof($artists) - 1)];
	$artist = preg_replace("/\\\'/", "'", $artist);
	return $artist;
}

function get_counts() {
	# Updated by an hourly cronjob
	$artists = @file("/var/lib/musica/artists.count");
	$albums = @file("/var/lib/musica/albums.count");
	$songs = @file("/var/lib/musica/songs.count");
	$counts = array(rtrim($artists[0]), rtrim($albums[0]), rtrim($songs[0]));
	return $counts;
}

function get_artist_ratings() {
	$ratings = array();
	list($r) = @file("music/.ratings");
	for ($i=0; $i<sizeof($r); $i++) {
		list($artist, $rating) = preg_split("/\t/", $r, 2);
		$ratings[$artist] = $rating;
	}
	return $ratings;
}

/*******************/
/* print functions */
/*******************/

function print_artist($artist) {
	if (is_dir("music/$artist") && visible($artist)) {
		$href = "?artist=" . urlencode($artist);
		print("<li><a href=\"$href\" target=_albums><img src=silk/group.png>&nbsp;$artist</a></li>\n");
	}
}

function print_album($artist, $album, $with_artist=0) {
	if (visible($artist) && visible($album) && isset($album)) {
		$href = "?artist=" . urlencode($artist) . "&album=" . urlencode_album($album);
		if ($with_artist) {
			print("<li><a href=\"$href\" target=_songs><img src=silk/group.png>&nbsp;$artist</a><br>&nbsp;&nbsp;<a href=$href target=_songs><img border=0 src=silk/cd.png>&nbsp;$album</a><br>");
		} else {
			print("<li><a href=$href target=_songs><img border=0 src=silk/cd.png>&nbsp;$album</a><br>");
		}
	}
}

function print_song($artist, $album, $song) {
	global $PREAMBLE, $JPLAYER_LIST, $JPLAYER_OGG;
	print("<noscript>");
	if (is_song($song) && visible($artist) && visible($album) && visible($song)) {
		$href = $PREAMBLE . urlencode($artist) . "/" . urlencode_album($album) . "/" . urlencode("$song");
		$href = $line = preg_replace("/\+/", "%20", $href);
		$parts = pathinfo($song);
		if (preg_match("/^og/i", $parts['extension'])) {
			$JPLAYER_OGG = "true";
			$parts['extension'] = "ogg";
		}
		print("<a href=\"$href\"><img src=silk/disk.png border=0></a><a href=\"?playlist=1&artist=$artist&album=$album&song=$song\">");
		print("<img src=silk/music.png border=0>&nbsp;" . $parts["filename"] . "</a><br>");
		$JPLAYER_LIST .= "{name:\"" . $parts["filename"] . "\"," . $parts["extension"] . ":\"$href\"},\n";
	}
	print("</noscript>");
}

function print_artists($search="") {
	print("<ol>");
	$artists = get_all_artists($search);
	for ($i=0; $i<sizeof($artists); $i++) {
		print_artist($artists[$i]);
	}
	print("
		</ol>
		<hr>
		<ul>
		  <li><a href=?artist=_random target=_albums>Random Artist</a></li>
		  <li>Playlist of all songs with artist rating</li>
		  <ul>
	");
	for ($i=5; $i>=1; $i--) {
		print("<li><a href=?playlist=$i target=_songs>");
		for ($j=1; $j<=$i; $j++) {
			print("<img border=0 src=silk/star.png>");
		}
		print("</a></li>");
	}
	print("</ul></ul>");
}

function print_albums_by_artist($artist) {
	global $rating;
	if (is_numeric($rating)) {
		set_rating($artist, $rating);
	}
	$albums = get_albums_by_artist($artist);
	print("<p align=center><img src=silk/group.png>&nbsp;<big><b>$artist</b></big><br>");
	$wiki = preg_replace("/\s*\(.*/", "", $artist);
	$wiki = urlencode($wiki);
	$wiki = preg_replace("/\+/", "_", $wiki);
	print("<a target=_new href=http://en.wikipedia.org/wiki/$wiki><img width=16 heigh=16 src=silk/book_open.png border=0> Wikipedia</a><br>");
	print( get_rating($artist) . "</p><ol>");
	$loadmisc = 0;
	for ($i=0; $i<sizeof($albums); $i++) {
		list($artist, $album) = preg_split("/\//", $albums[$i], 2);
		print_album($artist, $album);
	}
	if (!$loadmisc) {
		print ("<script>parent._songs.location.href='?artist=$artist&misc=1';</script>");
	}
	print("</ol>");
}

function print_albums_by_search($search) {
	$albums = get_all_albums($search);
	print("<center><big><b>Matching Artists or Albums</b></big></center><ol>");
	print("<ol>");
	for ($i=0; $i<sizeof($albums); $i++) {
		list($artist, $album) = split2(rtrim($albums[$i]));
		print_album($artist, $album, 1);
	}
	print("</ol>");
}

function print_songs_by_album($artist, $album) {
	global $JPLAYER_LIST, $JPLAYER_OGG;
	print("<p align=center><img src=silk/group.png>&nbsp;<b><big>$artist</b></big><br><img src=silk/cd.png>&nbsp;<b>$album</b><br>\n");
	$wiki = preg_replace("/\s*\(.*/", "", $album);
	$wiki = urlencode($wiki);
	$wiki = preg_replace("/\+/", "_", $wiki);
	print("<a target=_new href=http://en.wikipedia.org/wiki/$wiki><img width=16 heigh=16 src=silk/book_open.png border=0> Wikipedia</a><br></p>");
	list($songs, $images) = get_songs_by_album($artist, $album);
	print_album_cover($images, "$artist/$album");
	for ($i=0; $i<sizeof($songs); $i++) {
		list($artist, $album, $song) = split3($songs[$i]);
		print_song($artist, $album, $song);
	}
	if ($JPLAYER_LIST != "") {
		include("jplayer.php");
	}
	$size = get_size_of_album($artist, $album);
        print("<p align=right>
<a target=_new href=" . $_SERVER["REQUEST_URI"] . "&popout=1><img src=silk/application_double.png border=0>&nbsp;play in a new window</a><br>
<a href=?playlist=1&artist=" . urlencode($artist) . "&album=" . urlencode_album($album) . "><img src=silk/control_play_blue.png border=0>&nbsp;play album</a><br>
<a href=?download_album=1&artist=" . urlencode($artist) . "&album=" . urlencode_album($album) . "><img src=silk/disk.png border=0>&nbsp;download</a> $size
	");
}

function print_misc_songs_by_artist($artist) {
	global $JPLAYER_LIST, $JPLAYER_OGG, $_SERVER;
	print("<center><img src=silk/group.png>&nbsp;<big><b>$artist</b></big><br><img src=silk/music.png>&nbsp;<b>Miscellaneous</b></center><br>");
	list($songs, $images) = get_songs_by_album($artist);
	print_album_cover($images, $artist);
	for ($i=0; $i<sizeof($songs); $i++) {
		list($artist, $album, $song) = split3($songs[$i]);
		print_song($artist, $album, $song);
	}
	if ($JPLAYER_LIST != "") {
		include("jplayer.php");
	}
	print("<p align=right>
<a target=_new href=" . $_SERVER["REQUEST_URI"] . "&popout=1><img src=silk/application_double.png border=0>&nbsp;play in a new window</a><br>
<a href=?playlist=1&artist=" . urlencode($artist) . "><img src=silk/control_play_blue.png border=0>&nbsp;play all by artist</a></p> ");
}

function print_songs_by_search($search) {
	global $JPLAYER_LIST, $JPLAYER_OGG;
        print("<ol>");
	$songs = get_all_songs($search);
	for ($i=0; $i<sizeof($songs); $i++) {
		list($artist, $album, $song) = split3(rtrim($songs[$i]));
		//print_artist($artist);
		//print_album($artist, $album);
		print_song($artist, $album, $song);

	}
	if ($JPLAYER_LIST != "") {
		include("jplayer.php");
	}
	print("</ol>");
}

function print_album_cover($images, $dir) {
	$width = 200;
	$js = "src = [";
	for ($i=0; $i<sizeof($images); $i++) {
		if ($i > 0) {
			$js .= ",";
		}
		$js .= "'" . $images[$i] . "'";
	}
	$js .= "]";
	if ($i>= 1) {
		# From http://www.javascriptkit.com/script/script2/incrementslide.shtml
		print("
<script>
" . $js . "
pics=[]; i=0;
function switch_pic() {
  var n=(i+1)%src.length;
  if (pics[n] && (pics[n].complete || pics[n].complete==null)) {
    document['albumart'].src = pics[i=n].src;
  }
  pics[n=(i+1)%src.length] = new Image;
  pics[n].src = src[n];
  setTimeout('switch_pic()',4000);
} onload = function(){
  if (document.images)
    switch_pic();
}
</script>");
		$img = "<center><img name=albumart width=$width src='$images[0]'></center><br>";
	} else {
		$tmp = "/var/lib/musica/tmp";
		$output = `eyeD3 -i $tmp "/usr/share/musica/music/$dir" 2>&1`;
		$output = preg_replace("/.*Writing /", "", $output);
		list($filename, $rest) = preg_split("/\.\.\./", $output);
		if (file_exists($filename)) {
			$img = "<center><img width=$width src=data:image/gif;base64," . base64_encode(file_get_contents("$filename")) . $img . "></center><br>";
			unlink($filename);
		}
	}
	print($img);
}


/**********/
/* Frames */
/**********/

/*************************************************************************/
/* Outermost frame */
if (		!$artist && 
		!$album && 
		!$song && 
		!$about && 
		!$playlist && 
		!$download_album && 
		!$blank && 
		!$top && 
		!$middle) 
{
	print ("
			<frameset rows='65,*' border=0>
			<frame src=?top=1 name=_upper>
			<frame src=?middle=1 name=_middle>
			</frameset>
		");
	exit;
} 
/*************************************************************************/



/*************************************************************************/
/* Blank, empty frame */
if ($blank) {
	print("<html></html>");
	exit;
}
/*************************************************************************/



/*************************************************************************/
/* Middle Frame, which includes three more frames */
if ($middle) {
	print ("
			<frameset cols='33%,33%,34%' border=0>
			<frame src=?artist=_all name=_artists>
			<frame src=?blank=1 name=_albums>
			<frame src=?blank=1 name=_songs>
			</frameset>
		");
	exit;
}
/*************************************************************************/



/*************************************************************************/
/* Header Frame with counts and search boxes */
if ($top) {
	list($artists, $albums, $songs) = get_counts();
	print("
<body topmargin=0 leftmargin=0 bottommargin=0 rightmargin=0>
<table border=0 width=100% cellpadding=0 cellspacing=0>
  <tr align=center>
    <td width=33%>
      <img src=silk/group.png>&nbsp;<b>Artists ($artists)</b><br>
    </td>
    <td width=33%>
      <img src=silk/cd.png>&nbsp;<b>Albums ($albums)</b>
    </td>
    <td width=33%>
      <img src=silk/music.png>&nbsp;<b>Songs ($songs)</b>
    </td>
    <td width=1%>
      <a href=?about=1 target=_songs title='About Musica'><img src=silk/help.png></a>
    </td>
  </tr>
  <tr align=center>
    <td>
      <form method=post action=?artist=_all target=_artists>
        <input type=text name=search>
        <input type=submit value=find>
        <input type=submit value=all onfocus=javascript:document.forms[0].search.value=\"\"></form>
    </td>
    <td>
      <form method=post action=?artist=_search&album=_search target=_albums>
        <input type=text name=search>
        <input type=submit value=find>
        <input type=submit value=all onfocus=javascript:document.forms[0].search.value=\"\"></form>
    </td>
    <td>
      <form method=post action=?artist=_search&album=_search&song=_search target=_songs>
        <input type=text name=search>
        <input type=submit value=find>
        <input type=submit value=all onfocus=javascript:document.forms[0].search.value=\"\"></form>
    </td>
  </tr>
</table></body>");
	exit;
}
/*************************************************************************/

/*************************************************************************/
/* About Musica*/
if ($about) {
	print("
<big><b>About Musica</b></big><br><br>

<a href=https://launchpad.net/musica target=_top>Musica</a> is Free Software under the <a href=http://www.gnu.org/licenses/agpl-3.0.txt target=_top>GNU AGPL 3</a>, Copyright &copy;2000-2010 <a href=http://blog.dustinkirkland.com>Dustin Kirkland</a>.<br><br>

<a href=http://www.famfamfam.com/lab/silk/silk/ target=_top>FamFamFam Silk Icons</a> are used under the <a href=http://creativecommons.org/licenses/by/3.0/ target=_top>CCA3.0</a> license.<br><br>

<a href=http://www.happyworm.com/jquery/jplayer/ target=_top>jPlayer audio player plugin</a> is developed by <a href=http://www.happyworm.com/ target=_top>Happyworm</a>, and is Free, Open Source and dual licensed under the <a href=http://www.opensource.org/licenses/mit-license.php target=_top>MIT</a> and <a href=http://www.gnu.org/copyleft/gpl.html target=_top>GPL</a> licenses.<br><br>
	");
	exit;
}
/*************************************************************************/


/*************************************************************************/
/* Artist Frame: Display list of artists */

if ($artist == "_all") {
	print_artists($search);
	exit;
}
/*************************************************************************/


/*************************************************************************/
/* Album Frame: Display list of albums */
if ($artist && !$album && !$misc && !$playlist && !$download_album) {
	if ($artist == "_random") {
		$artist = get_random_artist();
	}
	print_albums_by_artist($artist);
	exit;
}
if ($artist=="_search" && $album=="_search" && !$song) {
	print_albums_by_search($search);
	exit;
}
/*************************************************************************/



/*************************************************************************/
/* Song Frame: Display tracks */
if ($artist && $album && !$song && !$misc && !$playlist && !$download_album) {
	print_songs_by_album($artist, $album);
	exit; 
} elseif ($artist=="_search" && $album=="_search" && $song=="_search") {
        print_songs_by_search($search);
        exit;
}

/*************************************************************************/


/*************************************************************************/
/* Misc Songs (not in an album) */
if ($artist && $misc && !$song && !$playlist && !$download_album) {
	print_misc_songs_by_artist($artist);
	exit;
}
/*************************************************************************/



/*************************************************************************/
/* Complete playlist */
if ($playlist && !$artist && !$album) {
	$songs = get_all_songs("", $playlist);
	$filename = "all_" . $playlist . ".m3u";
	header("Content-type: application/download");
	header("Content-disposition: attachment; filename=$filename");
	for ($i=0; $i<sizeof($songs); $i++) {
		$line = $PREAMBLE . urlencode(rtrim($songs[$i]));
		$line = preg_replace("/\+/", "%20", $line);
		$line = preg_replace("/\%2F/", "/", $line);
		print("$line\n");
	}
	exit;
}
/*************************************************************************/




/*************************************************************************/
/* Single album playlist */
if ($playlist) {
	$songs = array();
	if ($song) {
		array_push($songs, "$artist/$album/$song");
		$filename = urlencode($artist) . "_" . urlencode($album) . "_" . urlencode($song) . ".m3u";
	} elseif ($album) {
		list($songs, $images) = get_songs_by_album($artist, $album);
		$filename = urlencode($artist) . "_" . urlencode($album) . ".m3u";
	} elseif ($artist) {
		$songs = get_songs_by_artist($artist);
		$filename = urlencode($artist) . ".m3u";
	}
	header("Content-type: application/download");
	header("Content-disposition: attachment; filename=$filename");
	for ($i=0; $i<sizeof($songs); $i++) {
		$line = $PREAMBLE . "/" . urlencode($songs[$i]);
		$line = preg_replace("/\+/", "%20", $line);
		$line = preg_replace("/%2F/", "/", $line);
		print("$line\n");
	}
	exit;
}
/*************************************************************************/




/*************************************************************************/
/* Download full album tarball */
if ($download_album) {
	$filename = urlencode($artist) . "_" . urlencode($album) . ".tar";
	header("Content-type: application/download");
	header("Content-transfer-encodig: binary");
	header("Content-disposition: attachment; filename=$filename");
	$dir = escapeshellcmd("music/$artist/$album/");
	print(system("tar cf - '$dir'"));
	exit;
}
/*************************************************************************/
?>
