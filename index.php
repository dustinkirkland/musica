<?php

function sanity_check($string) {
	$string = urldecode($string);
	$string = stripslashes($string);
	if (preg_match("/\.\./", $string)) {
		exit;
	}
	if (preg_match("/\//", $string)) {
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


$top = sanity_check($_REQUEST["top"]);
$middle = sanity_check($_REQUEST["middle"]);
$blank = sanity_check($_REQUEST["blank"]);
$artist = sanity_check($_REQUEST["artist"]);
$album = sanity_check($_REQUEST["album"]);
$song = sanity_check($_REQUEST["song"]);
$misc = sanity_check($_REQUEST["misc"]);
$playlist = sanity_check($_REQUEST["playlist"]);
$download_album = sanity_check($_REQUEST["download_album"]);
$search = sanity_check($_REQUEST["search"]);
$rating = sanity_check($_REQUEST["rating"]);
$user = sanity_check($_SERVER["PHP_AUTH_USER"]);
$pw = sanity_check($_SERVER["PHP_AUTH_PW"]);

$PREAMBLE = "http://" . $_SERVER["HTTP_HOST"] . "/music/";

?>

<html>
<head>
<title>Music</title>
<style>
<!--
a {text-decoration: none; color: blue}
a:hover {text-decoration: underline; }
a:visted {text-decoration: none;}
a.head {text-decoration: none; color: white}
a.head:hover {text-decoration: underline; }
a.head:visted {text-decoration: none;}
body {font-size: 13px; font-family: verdana,arial,helvetica,sans-serif; font-weight: 400; color: #000000;}
-->
</style>
</head>



<?

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
	}
	sort($artists);
	return $artists;
}

function get_all_albums($search="") {
	$artists = get_all_artists();
	$albums = array();
	for ($i=0; $i<sizeof($artists); $i++) {
		$a =  get_albums_by_artist($artists[$i]);
		for ($j=0; $j<sizeof($a); $j++) {
			list($artist, $album) = preg_split("/\//", $a[$j]);
			if (!$search || preg_match("/$search/i", $album)) {
				array_push($albums, $a[$j]);
			}
		}
	}
	sort($albums);
	return $albums;
}

function get_all_songs($search="", $rating=0) {
	if ($rating) {
		$ratings = get_artist_ratings();
		$artists = array_keys($ratings);
	} else {
		$artists = get_all_artists();
	}
	$songs = array();
	for ($i=0; $i<sizeof($artists); $i++) {
		$s = get_songs_by_album($artists[$i]);
		for ($j=0; $j<sizeof($s); $j++) {
			list($artist, $album, $song) = preg_split("/\//", $s[$j]);
			if (!$search || preg_match("/$search/", $song)) {
				if (!$rating || $ratings["$artist"] >= $rating) {
					array_push($songs, $s[$j]);
				}
			}
		}
	}
	$albums = get_all_albums();
	for ($i=0; $i<sizeof($albums); $i++) {
		list($artist, $album) = preg_split("/\//", $albums[$i]);
		$s = get_songs_by_album($artist, $album);
		for ($j=0; $j<sizeof($s); $j++) {
			list($artist, $album, $song) = preg_split("/\//", $s[$j]);
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

function get_albums_by_artist($artist) {
	$albums = array();
	if (is_dir("music/$artist")) {
		if ($dir = opendir("music/$artist")) {
			while (($album = readdir($dir)) !== false){
				if (is_dir("music/$artist/$album") && visible($artist) && visible($album)) {
					array_push($albums, "$artist/$album");
				}
			}
			closedir($dir);
		}
	}
	sort($albums);
	return $albums;
}

function get_all_ratings() {
	$file = "music/.ratings";
	if (file_exists($file)) {
		$lines = file($file);
	}
	$ratings = array();
	foreach ($lines as $line) {
		list($a, $r) = preg_split("/\t/", $line);
		$r = preg_replace("/\s+/", "", $r);
		$ratings["$a"] = $r;
	}
	return $ratings;
}

function get_rating($artist) {
	$ratings = get_all_ratings();
	$str = "";
	for ($i=0; $i<=5; $i++) {
		$str .= "<a href=?rating=$i&artist=" . urlencode($artist) . ">$i</a> ";
	}
	$r = $ratings["$artist"];
	if (isset($r)) {
		$str = preg_replace("/>$r</" , "><b><u><big>$r</big></u></b><", $str);
	}
	return $str;
}

function set_rating($artist, $rating) {
	if ($rating>=0 && $rating<=5) {
		$ratings = get_all_ratings();
		$ratings["$artist"] = $rating;
		$artists = array_keys($ratings);
		sort($artists);
		$fp = fopen("music/.ratings", "w");
		foreach ($artists as $a) {
			fprintf($fp, "%s\t%s\n", $a, $ratings["$a"]);
		}
		fclose($fp);
	}
}

function get_songs_by_album($artist, $album="") {
	$songs = array();
	if (is_dir("music/$artist/$album")) {
		if ($dir = opendir("music/$artist/$album")) {
			while (($song = readdir($dir)) !== false){
				if (preg_match("/\.mp3$/i", "music/$artist/$album/$song") && visible($artist) && visible($album) && visible($song)) {
					array_push($songs, "$artist/$album/$song");
				}
			}
			closedir($dir);
		}
	}
	sort($songs);
	return $songs;
}

function get_songs_by_artist($artist) {
	$songs = array();
	$s = get_songs_by_album($artist);
	for ($i=0; $i<sizeof($s); $i++) {
		list($artist, $album, $song) = preg_split("/\//", $s[$i]);
		if (!$search || preg_match("/$search/", $song)) {
			if (!$rating || $ratings["$artist"] >= $rating) {
				array_push($songs, $s[$i]);
			}
		}
	}
	$albums = get_albums_by_artist($artist);
	for ($i=0; $i<sizeof($albums); $i++) {
		list($artist, $album) = preg_split("/\//", $albums[$i]);
		$s = get_songs_by_album($artist, $album);
		for ($j=0; $j<sizeof($s); $j++) {
			list($artist, $album, $song) = preg_split("/\//", $s[$j]);
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
	$songs = get_songs_by_album($artist, $album);
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
	/* These shell commands are 7x faster than array counting in php */
	$artists = `ls -F music/ | grep "/\$" | wc -l` + 0;
	$albums = `ls -F music/*/ | grep "/\$" | wc -l` + 0; /* */
	$songs = `find music/ -type f -iname "*.mp3" | wc -l` + 0;
	$counts = array($artists, $albums, $songs);
	return $counts;
}

function get_artist_ratings() {
	$artists = get_all_artists();
	$ratings = array();
	for ($i=0; $i<sizeof($artists); $i++) {
		$r = "";
		list($r) = file("music/$artists[$i]/.rating");
		if (!$r) { $r = 2; }	
		$ratings["$artists[$i]"] = $r;
	}
	return $ratings;
}

function filename($file) {
	$parts = preg_split("/\/+/", $file);
	return array_pop($parts);
}

function get_temp_filename($extension) {
	global $_SERVER;
	foreach (glob("tmp/*$extension") as $filename) {
		unlink($filename);
	}
	$tempfile = tempnam("tmp", "tempfile_");
	rename($tempfile, "$tempfile$extension");
	$tempfile .= "$extension";
	$tempfile = "tmp/" . filename($tempfile);
	return $tempfile;
}

/*******************/
/* print functions */
/*******************/

function print_artist($artist) {
	if (is_dir("music/$artist") && visible($artist)) {
		$href = "?artist=" . urlencode($artist);
		print("<li><img src=group.png>&nbsp;<a href=\"$href\" target=_albums>$artist</a></li>\n");
	}
}

function print_album($artist, $album) {
	if (visible($artist) && visible($album)) {
		$href = "?artist=" . urlencode($artist) . "&album=" . urlencode($album);
		print("<a href=$href target=_songs><img border=0 src=cd.png>&nbsp;$album</a><br>");
	}
}

function print_song($artist, $album, $song) {
	global $PREAMBLE;
	if (preg_match("/.mp3$/i", $song) && visible($artist) && visible($album) && visible($song)) {
		$href = $PREAMBLE . urlencode($artist) . "/" . urlencode($album) . "/" . urlencode("$song");
		$href = $line = preg_replace("/\+/", "%20", $href);
		$track = preg_replace("/\.mp3$/", "", $song);
		print("<a href=\"$href\"><img src=disk.png border=0></a><a href=\"?playlist=1&artist=$artist&album=$album&song=$song\">");
		print("<img src=music.png border=0>&nbsp;$track</a><br>");
	}
}

function print_artists($search="") {
	if ($search) {
		$title = "Matching Artists";
	} else {
		$title = "All Artists";
	}
	print("<center><img src=group.png>&nbsp;<big><b>$title</b></big></center><ol>");
	$artists = get_all_artists($search);
	for ($i=0; $i<sizeof($artists); $i++) {
		print_artist($artists[$i]);
	}
	$complete = 1;
	$popular  = 4;
	print("
			</ol>
			<p align=right>(very big) <a href=?playlist=$complete target=_songs>Complete Playlist</a><br>
			<a href=?playlist=$popular target=_songs>Popular Playlist</a><br>
			<a href=?artist=_random target=_albums>Random Artist</a></p>
	");
}

function print_albums_by_artist($artist) {
	global $rating;
	if (is_numeric($rating)) {
		set_rating($artist, $rating);
	}
	$albums = get_albums_by_artist($artist);
	print("<p align=center><img src=group.png>&nbsp;<big><b>$artist</b></big><br>");
	$wiki = preg_replace("/\s*\(.*/", "", $artist);
	$wiki = urlencode($wiki);
	$wiki = preg_replace("/\+/", "_", $wiki);
	print("<a target=_new href=http://en.wikipedia.org/wiki/$wiki><img width=16 heigh=16 src=book_open.png border=0> wikipedia</a><br>");
	print("<sub>[" . get_rating($artist) . "]</sub></p><ol>");
	$loadmisc = 0;
	for ($i=0; $i<sizeof($albums); $i++) {
		list($artist, $album) = preg_split("/\//", $albums[$i]);
		print("<li>");
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
		list($artist, $album) = preg_split("/\//", $albums[$i]);
		print_artist($artist);
		print_album($artist, $album);
	}
	print("</ol>");
}

function print_songs_by_album($artist, $album) {
	print("<p align=center><img src=group.png>&nbsp;<b><big>$artist</b></big><br><img src=cd.png>&nbsp;<b>$album</b><br>\n");
	$wiki = preg_replace("/\s*\(.*/", "", $album);
	$wiki = urlencode($wiki);
	$wiki = preg_replace("/\+/", "_", $wiki);
	print("<a target=_new href=http://en.wikipedia.org/wiki/$wiki><img width=16 heigh=16 src=book_open.png border=0> wikipedia</a><br></p>");
	$songs = get_songs_by_album($artist, $album);
	for ($i=0; $i<sizeof($songs); $i++) {
		list($artist, $album, $song) = preg_split("/\//", $songs[$i]);
		print_song($artist, $album, $song);
	}
	$size = get_size_of_album($artist, $album);
        print("<p align=right>
<a href=?playlist=1&artist=" . urlencode($artist) . "&album=" . urlencode($album) . "><img src=control_play_blue.png border=0>&nbsp;play album</a><br>
<a href=?download_album=1&artist=" . urlencode($artist) . "&album=" . urlencode($album) . "><img src=disk.png border=0>&nbsp;download</a> $size
	");
}

function print_misc_songs_by_artist($artist) {
	print("<center><img src=group.png>&nbsp;<big><b>$artist</b></big><br><img src=music.png>&nbsp;<b>Miscellaneous</b></center><br>");
	$songs = get_songs_by_album($artist);
	for ($i=0; $i<sizeof($songs); $i++) {
		list($artist, $album, $song) = preg_split("/\//", $songs[$i]);
		print_song($artist, $album, $song);
	}
	print("<p align=right><a href=?playlist=1&artist=" . urlencode($artist) . "><img src=control_play_blue.png border=0>&nbsp;play all by artist</a></p> ");
}

function print_songs_by_search($search) {
        print("<ol>");
	$songs = get_all_songs($search);
	for ($i=0; $i<sizeof($songs); $i++) {
		list($artist, $album, $song) = preg_split("/\//", $songs[$i]);
		print_artist($artist);
		print_album($artist, $album);
		print_song($artist, $album, $song);

	}
	print("</ol>");
}


/**********/
/* Frames */
/**********/

/*************************************************************************/
/* Outermost frame */
if (		!$artist && 
		!$album && 
		!$song && 
		!$playlist && 
		!$download_album && 
		!$blank && 
		!$top && 
		!$middle) 
{
	print ("
			<frameset rows='85,*' border=0>
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
			<frameset cols='33%,33%,33%' border=0>
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
      <img src=group.png>&nbsp;<b>Artists ($artists)</b><br>
    </td>
    <td width=33%>
      <img src=cd.png>&nbsp;<b>Albums ($albums)</b>
    </td>
    <td width=33%>
      <img src=music.png>&nbsp;<b>Songs ($songs)</b>
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
  <tr>
    <td colspan=3 align=center>
      <small><small>Copyright &copy; 2000-2008 <a href=mailto:dustin.kirkland@gmail.com>Dustin Kirkland</a>, the <a href=https://launchpad.net/musica target=_top>Musica Browser</a> is free code under <a href=gpl.txt target=_top>GPLv3</a>.  <a href=http://www.famfamfam.com/lab/icons/silk/ target=_top>Icons</a> are under <a href=http://creativecommons.org/licenses/by/2.5/ target=_top>CCA2.5</a>.</small></small>
    </td>
  </tr>
</table></body>");
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
	/* cleanup old playlists */
	$tempfile = get_temp_filename(".m3u");
	$fp = fopen($tempfile, "w");
	$songs = get_all_songs("", $playlist);
	for ($i=0; $i<sizeof($songs); $i++) {
		$line = $PREAMBLE . urlencode($songs[$i]);
		$line = preg_replace("/\+/", "%20", $line);
		$line = preg_replace("/\%2F/", "/", $line);
		fputs($fp, "$line\n");
	}
	fclose($fp);
	print("
<body topmargin=0 leftmargin=0 bottommargin=0 rightmargin=0>
<a href=$tempfile>Download the Complete Playlist</a>
</body></html>
	");
	exit;
}
/*************************************************************************/




/*************************************************************************/
/* Single album playlist */
if ($playlist) {
	print("<h2>Now playing</h2>");
	if ($song) {
		print_song($artist, $album, $song);
	} elseif ($album) {
		print_songs_by_album($artist, $album);
	}
	$songs = array();
	if ($song) {
		array_push($songs, "$artist/$album/$song");
	} elseif ($album) {
		$songs = get_songs_by_album($artist, $album);
	} elseif ($artist) {
		$songs = get_songs_by_artist($artist);
	}
        $tempfile = get_temp_filename(".m3u");
	$fp = fopen($tempfile, "w");
	for ($i=0; $i<sizeof($songs); $i++) {
		$line = $PREAMBLE . "/" . urlencode($songs[$i]);
		$line = preg_replace("/\+/", "%20", $line);
		$line = preg_replace("/%2F/", "/", $line);
		fputs($fp, "$line\n");
	}
	fclose($fp);
	print("
<body topmargin=0 leftmargin=0 bottommargin=0 rightmargin=0>
<meta http-equiv=\"refresh\" content=\"0;URL=$tempfile\">
</body></html>
	");
	exit;
}
/*************************************************************************/




/*************************************************************************/
/* Download full album tarball */
if ($download_album) {
	print("<h2>Now downloading...</h2><center><b><big>$artist</big><br>$album</b></center>");
        $tempfile = get_temp_filename(".tar");
	`tar cf $tempfile "music/$artist/$album/"`;
	print("
<body topmargin=0 leftmargin=0 bottommargin=0 rightmargin=0>
<meta http-equiv=\"refresh\" content=\"0;URL=$tempfile\">
</body></html>
	");
	exit;
}
/*************************************************************************/
?>
