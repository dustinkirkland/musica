<?php
/*
 * jPlayer
 * http://www.happyworm.com/jquery/jplayer
 *
 * Copyright (c) 2010 Happyworm Ltd
 * Dual licensed under the MIT and GPL licenses.
 *  - http://www.opensource.org/licenses/mit-license.php
 *  - http://www.gnu.org/copyleft/gpl.html
 *
 * Customized by Dustin Kirkland <dustin.kirkland@gmail.com>
 */

if (isset($_REQUEST["popout"])) {
	$autoplay = "true";
} else {
	$autoplay = "false";
}
?>

<!-- local copy of jquery.min.js is currently broken (1.6.2) -->
<!-- <script type="text/javascript" src="js/jquery.min.js"></script> -->
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery.jplayer.js"></script>
<script type="text/javascript">
<!--
$(document).ready(function(){

	var playItem = 0;

	var myPlayList = [
<?php
	echo($JPLAYER_LIST);
?>
	];

	// Local copy of jQuery selectors, for performance.
	var jpPlayTime = $("#jplayer_play_time");
	var jpTotalTime = $("#jplayer_total_time");

	$("#jquery_jplayer").jPlayer({
		ready: function() {
			displayPlayList();
			playListInit(<?php echo $autoplay; ?>); // Parameter is a boolean for autoplay.
		},
		nativeSupport: false,
		customCssIds: false,
		oggSupport: <?php echo $JPLAYER_OGG; ?>
	})
	.jPlayer("onProgressChange", function(loadPercent, playedPercentRelative, playedPercentAbsolute, playedTime, totalTime) {
		jpPlayTime.text($.jPlayer.convertTime(playedTime));
		jpTotalTime.text($.jPlayer.convertTime(totalTime));
	})
	.jPlayer("onSoundComplete", function() {
		playListNext();
	});

	$("#jplayer_previous").click( function() {
		playListPrev();
		$(this).blur();
		return false;
	});

	$("#jplayer_next").click( function() {
		playListNext();
		$(this).blur();
		return false;
	});

	function displayPlayList() {
		$("#jplayer_playlist ul").empty();
		for (i=0; i < myPlayList.length; i++) {
			var listItem = (i == myPlayList.length-1) ? "<li class='jplayer_playlist_item_last'>" : "<li>";
			var href = myPlayList[i].mp3
			if (href == undefined) {
				href = myPlayList[i].ogg
			}
			if (href == undefined) {
				href = myPlayList[i].m4a
			}
			listItem += "<a id='jplayer_playlist_get_mp3_"+i+"' href='" + href + "' tabindex='1'><img src='silk/disk.png' width='12'></a> <a href='#' id='jplayer_playlist_item_"+i+"' tabindex='1'>"+ myPlayList[i].name +"</a></li>";
			$("#jplayer_playlist ul").append(listItem);
			$("#jplayer_playlist_item_"+i).data( "index", i ).click( function() {
				var index = $(this).data("index");
				if (playItem != index) {
					playListChange( index );
				} else {
					$("#jquery_jplayer").jPlayer("play");
				}
				$(this).blur();
				return false;
			});
		}
	}

	function playListInit(autoplay) {
		if(autoplay) {
			playListChange( playItem );
		} else {
			playListConfig( playItem );
		}
	}

	function playListConfig( index ) {
		$("#jplayer_playlist_item_"+playItem).removeClass("jplayer_playlist_current").parent().removeClass("jplayer_playlist_current");
		$("#jplayer_playlist_item_"+index).addClass("jplayer_playlist_current").parent().addClass("jplayer_playlist_current");
		playItem = index;
		$("#jquery_jplayer").jPlayer("setFile", myPlayList[playItem].mp3, myPlayList[playItem].ogg);
	}

	function playListChange( index ) {
		playListConfig( index );
		$("#jquery_jplayer").jPlayer("play");
	}

	function playListNext() {
		var index = (playItem+1 < myPlayList.length) ? playItem+1 : 0;
		playListChange( index );
	}

	function playListPrev() {
		var index = (playItem-1 >= 0) ? playItem-1 : myPlayList.length-1;
		playListChange( index );
	}
});
-->
</script>
<table border=0 width=100%><tr><td>&nbsp;</td><td width=1>
		<div id="jquery_jplayer"></div>

		<div class="jp-playlist-player">
			<div class="jp-interface">
				<ul class="jp-controls">

					<li><a href="#" id="jplayer_play" class="jp-play" tabindex="1">play</a></li>
					<li><a href="#" id="jplayer_pause" class="jp-pause" tabindex="1">pause</a></li>
					<li><a href="#" id="jplayer_stop" class="jp-stop" tabindex="1">stop</a></li>
					<li><a href="#" id="jplayer_volume_min" class="jp-volume-min" tabindex="1">min volume</a></li>
					<li><a href="#" id="jplayer_volume_max" class="jp-volume-max" tabindex="1">max volume</a></li>
					<li><a href="#" id="jplayer_previous" class="jp-previous" tabindex="1">previous</a></li>

					<li><a href="#" id="jplayer_next" class="jp-next" tabindex="1">next</a></li>
				</ul>
				<div class="jp-progress">
					<div id="jplayer_load_bar" class="jp-load-bar">
						<div id="jplayer_play_bar" class="jp-play-bar"></div>
					</div>
				</div>
				<div id="jplayer_volume_bar" class="jp-volume-bar">

					<div id="jplayer_volume_bar_value" class="jp-volume-bar-value"></div>
				</div>
				<div id="jplayer_play_time" class="jp-play-time"></div>
				<div id="jplayer_total_time" class="jp-total-time"></div>
			</div>
			<div id="jplayer_playlist" class="jp-playlist">
				<ul>
					<!-- The function displayPlayList() uses this unordered list -->
					<li></li>

				</ul>
			</div>
		</div>
</td><td>&nbsp;</td></tr></table>
