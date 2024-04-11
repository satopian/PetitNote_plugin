<?php
// POTI-board → Petit Note ログコンバータ。
// (c)2022-2024 さとぴあ(satopian) 
//Licence MIT
//lot.240411

/* ------------- 設定項目ここから ------------- */

/* -------------- 日付の計算 -------------- */
// 2022/02/25 のような日付から投稿時刻を計算するかどうか?
//する: true 
//しない: false 

//する: true の時は、テキストの日付をユニックスタイムに変換して、PetitNoteの日付として使います。
//ただし、この設定の時は精度が低く、分単位で重複した投稿をただしく処理できません。
//しない: false の時は、記録されているユニックスタイム+3桁を投稿された日付に使います。

//基本的には false のままで変更しません。

$date_to_timestamp=false;
// $date_to_timestamp=true; 

//設定項目ここまで

//設定ファイルの読み込み
if ($err = check_file(__DIR__.'/config.php')) {
	die(h($err));
}
require(__DIR__.'/config.php');

/* -------------- パーミッション -------------- */

//正常に動作しているときは変更しない。
//画像やHTMLファイルのパーミッション。
defined('PERMISSION_FOR_DEST') or define('PERMISSION_FOR_DEST', 0606);
//ブラウザから直接呼び出さないログファイルのパーミッション
defined('PERMISSION_FOR_LOG') or define('PERMISSION_FOR_LOG', 0600);
//POTIディレクトリのパーミッション
define('PERMISSION_FOR_PETIT', 0705);//初期値 PERMISSION_FOR_PETIT
//画像や動画ファイルを保存するディレクトリのパーミッション
defined('PERMISSION_FOR_DIR') or define('PERMISSION_FOR_DIR', 0707);

if ($err = check_file(__DIR__.'/'.LOGFILE)) {
	die(h($err));
}
if ($err = check_file(__DIR__.'/'.TREEFILE)) {
	die(h($err));
}

$fp=fopen(LOGFILE,"r");
while($_line = fgets($fp)){
	
	if(!trim($_line)){
		continue;
	}
	mb_language('Japanese');
	$_line=mb_convert_encoding($_line, "UTF-8", "auto");

	$line[]=$_line;
}

$tp=fopen(TREEFILE,"r");
while($_tree = fgets($tp)){
	if(!trim($_tree)){
		continue;
	}
	list($_no,)=explode(",",$_tree);
	$trees[$_no]=$_tree;
}
fclose($tp);
fclose($fp);
ksort($trees);
$trees=array_values($trees);
//ディレクトリを確認して無ければ作る
check_petit('petit');
check_dir('petit/log');
check_dir('petit/src');
check_dir('petit/thumbnail');
check_dir('petit/webp');

$lineindex = get_lineindex($line); // 逆変換テーブル作成
foreach($trees as $i=>$tree){//ツリーの読み込み
		$treeline = explode(",", rtrim($tree));
		// レス省略
		//レス作成
		$thread=[];
		foreach($treeline as $k => $disptree){
			if(!isset($lineindex[$disptree])) continue;
			$j=$lineindex[$disptree];

			$no=$i+1;

			// list($_no,$date,$name,$email,$sub,$com,$url,$host,$hash,$ext,$w,$h,$_time,$img_md5,$_ptime,,$pchext,$thumbnail,$painttime)
			list($_no,$date,$name,$email,$sub,$com,$url,$host,$hash,$ext,$w,$h,$_time,$img_md5,$_ptime,,,,$tool,$logver)
			=explode(",",rtrim(t($line[$j])).',,,,,,,,');
			
			$paintsec=is_numeric($_ptime) ? $_ptime :'';
			$painttime= isset($painttime) ? $painttime : $paintsec;

			//名前とトリップを分離
			list($name, $trip) = separateNameAndTrip($name);

			list($userid,) = separateDatetimeAndId($date);

			$date=substr($date,0,21);
			$date= preg_replace('/\(.+\)/', '', $date);//曜日除去
			$time= $date_to_timestamp ? strtotime($date).'000000': (($logver==="6") ? $_time.'000' : substr($_time,-13).'000');
			$imgfile='';
			$time=basename($time);
			$_time=basename($_time);
			$ext=basename($ext);
			if($ext && is_file(IMG_DIR."{$_time}{$ext}")){//画像
				$imgfile=$time.$ext;
				$imgfile=basename($imgfile);
				if(!is_file("petit/src/{$imgfile}")){
					copy(IMG_DIR.$_time.$ext,"petit/src/{$imgfile}");
					chmod("petit/src/{$imgfile}",PERMISSION_FOR_DEST);
				}
				//webpサムネイル
				if(!is_file("petit/webp/{$time}t.webp")){
					thumb("petit/src/",$imgfile,$time,300,800,['webp'=>true]);
				}
			}
			$thumbnail='';
			if($ext && is_file(THUMB_DIR."{$_time}s.jpg")){//画像
				if(!is_file("petit/thumbnail/{$time}s.jpg")){
					copy(THUMB_DIR."{$_time}s.jpg","petit/thumbnail/{$time}s.jpg");
					chmod("petit/thumbnail/{$time}s.jpg",PERMISSION_FOR_DEST);
				}
				if(is_file("petit/thumbnail/{$time}s.jpg")){
					$thumbnail='thumbnail';
				}
				if($thumbnail && thumb('petit/src/',$imgfile,$time,$w,$h,['thumbnail_webp'=>true])){
					$thumbnail='thumbnail_webp';
				}
		
			}

			$pchext=check_pch_ext (PCH_DIR.$_time);
				
			if($pchext && in_array($pchext,[".pch",".tgkr",".chi",".psd"])){//動画
					
				copy(PCH_DIR."{$_time}{$pchext}","petit/src/{$time}{$pchext}");
				chmod("petit/src/{$time}{$pchext}",PERMISSION_FOR_DEST);
			}
	
			$com = preg_replace("#<br( *)/?>#i",'"\n"',$com); //<br />を"\n"に
			$com=strip_tags($com);

			$tool = switch_tool($tool);
			if(!$tool){
				switch($pchext){
					case '.pch':
						$tool='neo';
						break;
					case '.spch':
						$tool='shi-Painter';
						break;
					case '.chi':
						$tool='chi';
						break;
					case '.psd':	
						$tool='klecks';
						break;
					case '.tgkr':	
						$tool='tegaki';
						break;
					default:
						if($ext){
							$tool='';
						}
						break;
				}
			}

			$url=(strlen($url) < 200) ? $url :'';
			
			$sub = $sub ? $sub : DEF_SUB; 
			if($k===0){//スレッドの親の時

				$thread[$i][]="$no\t$sub\t$name\t\t$com\t$url\t$imgfile\t$w\t$h\t$thumbnail\t$painttime\t$img_md5\t$tool\t$pchext\t$time\t$time\t$host\t$userid\t$hash\toya\n";


				$strcut_com=mb_strcut($com,0,120);
				$oya_arr[$i]=	"$no\t$sub\t$name\t\t$strcut_com\t$url\t$imgfile\t$w\t$h\t$thumbnail\t$painttime\t$img_md5\t$tool\t$pchext\t$time\t$time\t$host\t$userid\t$hash\toya\n";

			}else{
			
				$res = "$no\t$sub\t$name\t\t$com\t$url\t$imgfile\t$w\t$h\t$thumbnail\t$painttime\t$img_md5\t$tool\t$pchext\t$time\t$time\t$host\t$userid\t$hash\tres\n";

				$thread[$i][]=$res;

			}
		}
		file_put_contents('petit/log/'.$no.'.log',$thread[$i]);
		chmod('petit/log/'.$no.'.log',PERMISSION_FOR_LOG);	

}

krsort($oya_arr);
file_put_contents('petit/log/alllog.log',$oya_arr);
chmod('petit/log/alllog.log',PERMISSION_FOR_LOG);	

echo lang_en() ? 'Conversion is complete. Please do not reload.' : '変換終了。リロードしないでください。'; 

function lang_en(){//言語が日本語以外ならtrue。
	$lang = ($http_langs = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
	? explode( ',', $http_langs )[0] : '';
  return (stripos($lang,'ja')!==0) ? true : false;
  
}
function initial_error_message(){
	$en=lang_en();
	$msg['001']=$en ? ' does not exist.':'がありません。'; 
	$msg['002']=$en ? ' is not readable.':'を読めません。'; 
	$msg['003']=$en ? ' is not writable.':'を書けません。'; 
return $msg;	
}

function check_dir ($path) {

	if (!is_dir($path)) {
			mkdir($path, PERMISSION_FOR_DIR,true);
			chmod($path, PERMISSION_FOR_DIR);
	}
}
function check_petit ($path) {

	if (!is_dir($path)) {
			mkdir($path, PERMISSION_FOR_PETIT,true);
			chmod($path, PERMISSION_FOR_PETIT);
	}
}

// ファイル存在チェック
function check_file ($path,$check_writable='') {
	$msg=initial_error_message();
	if (!is_file($path)) return $path . $msg['001'];
	if (!is_readable($path)) return $path . $msg['002'];
	if($check_writable){//書き込みが必要なファイルのチェック
		if (!is_writable($path)) return $path . $msg['003'];
	}
}
//逆変換テーブル作成
function get_lineindex ($line){
	$lineindex = [];
	foreach($line as $i =>$value){
		if(!trim($value)){
			continue;
		}
			list($no,) = explode(",", $value);
			if(!is_numeric($no)){//記事Noが正しく読み込まれたかどうかチェック
				// error(MSG019);
			};
			$lineindex[$no] = $i; // 値にkey keyに記事no
	}
	return $lineindex;
}

//タブ除去
function t($str){
	return str_replace("\t","",$str);
}

//エスケープ
function h($str){
	return htmlspecialchars($str,ENT_QUOTES,"utf-8",false);
}

/**
 * 名前とトリップを分離
 * @param $name
 * @return array
 */
function separateNameAndTrip ($name) {
	$name=strip_tags($name);//タグ除去
	if(preg_match("/(◆.*)/", $name, $regs)){
		return [preg_replace("/(◆.*)/","",$name), $regs[1]];
	}
	return [$name, ''];
}
/**
 * 日付とIDを分離
 * @param $date
 * @return array
 */
function separateDatetimeAndId ($date) {
	if (preg_match("/( ID:)(.*)/", $date, $regs)){
		return [$regs[2], preg_replace("/( ID:.*)/","",$date)];
	}
	return ['', $date];
}

/**
 * pchかchiかpsdか、それともファイルが存在しないかチェック
 * @param $filepath
 * @return string
 */
function check_pch_ext ($filepath) {
	
	$exts=[".pch",".spch",".tgkr",".chi",".psd"];

	foreach($exts as $i => $ext){

		if (is_file($filepath . $ext)) {
			if(!in_array(mime_content_type($filepath . $ext),["application/octet-stream","application/gzip","image/vnd.adobe.photoshop"])){
				return '';
			}
			if($ext==='.pch'){
				if(!is_neo($filepath . ".pch")){
					return 'PaintBBS';
				};
			}
			return $ext;
		}
	}
	return '';
}

function is_neo($src) {//neoのPCHかどうか調べる
	$fp = fopen("$src", "rb");
	$is_neo=(fread($fp,3)==="NEO");
	fclose($fp);
	return $is_neo;
}

//GD版が使えるかチェック
function gd_check(){
	$check = array("ImageCreate","ImageCopyResized","ImageCreateFromJPEG","ImageJPEG","ImageDestroy");

	//最低限のGD関数が使えるかチェック
	if(get_gd_ver() && (ImageTypes() & IMG_JPG)){
		foreach ( $check as $cmd ) {
			if(!function_exists($cmd)){
				return false;
			}
		}
	}else{
		return false;
	}

	return true;
}

//gdのバージョンを調べる
function get_gd_ver(){
	if(function_exists("gd_info")){
	$gdver=gd_info();
	$phpinfo=$gdver["GD Version"];
	$end=strpos($phpinfo,".");
	$phpinfo=substr($phpinfo,0,$end);
	$length = strlen($phpinfo)-1;
	$phpinfo=substr($phpinfo,$length);
	return $phpinfo;
	} 
	return false;
}


function thumb($path,$fname,$time,$max_w,$max_h,$options=[]){
	$path='petit/src/';
	$fname=basename($fname);
	$time=basename($time);
	$fname=$path.$fname;
	if(!is_file($fname)){
		return;
	}
	if(!gd_check()||!function_exists("ImageCreate")||!function_exists("ImageCreateFromJPEG")){
		return;
	}
	if((isset($options['webp'])||isset($options['thumbnail_webp'])) && (!function_exists("ImageWEBP")||version_compare(PHP_VERSION, '7.0.0', '<'))){
		return;
	}

	$fsize = filesize($fname);    // ファイルサイズを取得
	list($w,$h) = GetImageSize($fname); // 画像の幅と高さとタイプを取得
	$w_h_size_over=($w > $max_w || $h > $max_h);
	$f_size_over=!isset($options['toolarge']) ? ($fsize>1024*1024) : false;
	if(!$w_h_size_over && !$f_size_over && !isset($options['webp'])){
		return;
	}
	// リサイズ
	$w_ratio = $max_w / $w;
	$h_ratio = $max_h / $h;
	$ratio = min($w_ratio, $h_ratio);
	$out_w = $w_h_size_over ? ceil($w * $ratio):$w;//端数の切り上げ
	$out_h = $w_h_size_over ? ceil($h * $ratio):$h;

	switch ($mime_type = mime_content_type($fname)) {
		case "image/gif";
			if(!function_exists("ImageCreateFromGIF")){//gif
				return;
			}
				$im_in = @ImageCreateFromGIF($fname);
				if(!$im_in)return;
		
		break;
		case "image/jpeg";
			$im_in = @ImageCreateFromJPEG($fname);//jpg
				if(!$im_in)return;
			break;
		case "image/png";
			if(!function_exists("ImageCreateFromPNG")){//png
				return;
			}
				$im_in = @ImageCreateFromPNG($fname);
				if(!$im_in)return;
			break;
		case "image/webp";
			if(!function_exists("ImageCreateFromWEBP")||version_compare(PHP_VERSION, '7.0.0', '<')){//webp
				return;
			}
			$im_in = @ImageCreateFromWEBP($fname);
			if(!$im_in)return;
		break;

		default : return;
	}
	// 出力画像（サムネイル）のイメージを作成
	$exists_ImageCopyResampled = false;
	if(function_exists("ImageCreateTrueColor")&&get_gd_ver()=="2"){
		$im_out = ImageCreateTrueColor($out_w, $out_h);
		if((isset($options['toolarge'])||isset($options['webp'])||isset($options['thumbnail_webp'])) && in_array($mime_type,["image/png","image/gif","image/webp"])){
			if(function_exists("imagealphablending") && function_exists("imagesavealpha")){
				imagealphablending($im_out, false);
				imagesavealpha($im_out, true);//透明
			}
			}else{
				if(function_exists("ImageColorAlLocate") && function_exists("imagefill")){
					$background = ImageColorAlLocate($im_out, 0xFF, 0xFF, 0xFF);//背景色を白に
					imagefill($im_out, 0, 0, $background);
				}
			}
		// コピー＆再サンプリング＆縮小
		if(function_exists("ImageCopyResampled")){
			ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $w, $h);
			$exists_ImageCopyResampled = true;
		}
	}else{$im_out = ImageCreate($out_w, $out_h);}
	// コピー＆縮小
	if(!$exists_ImageCopyResampled) ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $w, $h);
	if(isset($options['toolarge'])){
		$outfile=$fname;
	//本体画像を縮小
		switch ($mime_type) {
			case "image/gif";
				if(function_exists("ImagePNG")){
					ImagePNG($im_out, $outfile,3);
				}else{
					ImageJPEG($im_out, $outfile,98);
				}
				break;
			case "image/jpeg";
				ImageJPEG($im_out, $outfile,98);
				break;
			case "image/png";
				if(function_exists("ImagePNG")){
					ImagePNG($im_out, $outfile,3);
				}else{
					ImageJPEG($im_out, $outfile,98);
				}
				break;
			case "image/webp";
				if(function_exists("ImageWEBP")&&version_compare(PHP_VERSION, '7.0.0', '>=')){
					ImageWEBP($im_out, $outfile,98);
				}else{
					ImageJPEG($im_out, $outfile,98);
				}
				break;

			default : return;
		}

	}elseif(isset($options['webp'])){
		$outfile='petit/webp/'.$time.'t.webp';
		ImageWEBP($im_out, $outfile,90);

	}elseif(isset($options['thumbnail_webp'])){
		$outfile='petit/thumbnail/'.$time.'s.webp';
		ImageWEBP($im_out, $outfile,90);
	}else{
		$outfile='petit/thumbnail/'.$time.'s.jpg';
		// サムネイル画像を保存
		ImageJPEG($im_out, $outfile,90);
	}
	// 作成したイメージを破棄
	ImageDestroy($im_in);
	ImageDestroy($im_out);

	if(!chmod($outfile,PERMISSION_FOR_DEST)){
		return;
	}

	return is_file($outfile);

}
function switch_tool($tool){
	switch($tool){
		case 'PaintBBS NEO':
			$tool='neo';
			break;
		case 'PaintBBS':
			$tool='PaintBBS';
			break;
		case 'Shi-Painter':
			$tool='shi-Painter';
			break;
		case 'ChickenPaint':
			$tool='chi';
			break;
		case 'Klecks';
			$tool='klecks';
			break;
		case 'Tegaki';
			$tool='tegaki';
			break;
		case 'Upload':
			'upload';
			break;
		default:
			$tool='';
			break;
	}
	return $tool;
}

