<?php

// Petit Note → POTI-board ログコンバータ。
// (c)2022-2026 さとぴあ(satopian) 
// Licence MIT
// lot.20260502


/* ------------- 設定項目ここから ------------- */

/* ------------- 画像ファイル名 -------------- */

//同じファイル名の画像が出力先にあるときは別名で保存
//デフォルト 0

$save_at_synonym=0;// 1.する 0.しない

//別名で保存オプションは、
//秒単位の同時刻の投稿画像を別名で保存するためのものです。

//1.する の時にコンバートを複数回行うと
//同じ画像が別名で出力されてしまいます。

/* ------------- 非表示の動画を表示する -------------- */
$copy_hide_animation = 0; //非表示の動画もコピーする 1.する 0.しない
//コピーするとPetitNoteでは非表示の動画はPOTI-boardでは表示になります。


/* ------------- 日付の書式 ------------- */

//※<1> に漢字の曜日(土・日・月など)が入ります
//※<2> に漢字の曜日(土曜・日曜・月曜など)が入ります
//※他は下記のURL参照
//  http://www.php.net/manual/ja/function.date.php
//define(DATE_FORMAT, 'Y/m/d(<1>) H:i');
define('DATE_FORMAT', 'Y/m/d(D) H:i');

/* --------------- タイムゾーン --------------- */

define('DEFAULT_TIMEZONE','Asia/Tokyo');

/* -------------- パーミッション -------------- */
//正常に動作しているときは変更しない。
//画像やHTMLファイルのパーミッション。
define('PERMISSION_FOR_DEST', 0606);//初期値 0606
//ブラウザから直接呼び出さないログファイルのパーミッション
define('PERMISSION_FOR_LOG', 0600);//初期値 0600
//POTIディレクトリのパーミッション
define('PERMISSION_FOR_POTI', 0705);//初期値 0705
//画像や動画ファイルを保存するディレクトリのパーミッション
define('PERMISSION_FOR_DIR', 0707);//初期値 0707

/* ----------- ここから下設定項目なし ----------- */

check_petit('poti');
check_dir('poti/src');
check_dir('poti/thumb');
//サムネイル

date_default_timezone_set(DEFAULT_TIMEZONE);

$en=lang_en();


$newlog=[];

$fp=fopen('log/alllog.log',"r");

if(!$fp){
	error($en?'Failed to read the Petit Note log file.':'Petit Noteのログファイルの読み込みに失敗しました。');
}
$log_nos=[];
while ($_line = fgets($fp)) {
		if(!trim($_line)){
			continue;
		}
		[$_no]=explode("\t",trim($_line));
		$log_nos[]=$_no;	
	}
fclose($fp);

natcasesort($log_nos);
$log_nos=array_values($log_nos);

$arr_logs=[];
foreach($log_nos as $i=>$log_no){//ログファイルを一つずつ開いて読み込む

	$log_no = basename($log_no); 
	if(!is_file("log/{$log_no}.log")){
		continue;
	}
	$rp = fopen("log/{$log_no}.log", "r");//個別スレッドのログを開く
	while($line =fgets($rp)){
			if(!trim($line)){
				continue;
			}
			$line = str_replace(",", "&#44;", $line);
			$arr_line=explode("\t",$line);
				$count_arr_line=count($arr_line);
				if($count_arr_line<5){
					error($en?'Failed to read the log file. The settings may be incorrect.':'ログファイルの読み込みに失敗しました。設定が間違っている可能性があります。');
				}
			$arr_logs[$i][]=$line;//1スレッド分
		}
		fclose($rp);
		
	}
	ksort($arr_logs);
	$arr_logs=array_values($arr_logs);

	$__no=1;
	$newlog=[];
	$treeline=[];
	foreach($arr_logs as $i=>$logs){
	
		$tree=[];

		foreach($logs as $k=>$val){//1スレッド分のログを処理
	
	
			[$no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$log_img_hash,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya]=explode("\t",$val);
			$time = basename($time);
			$origin_time=$time;
			$time=(strlen($time)>15) ? substr($time,0,-3) : $time;
			$ext = $imgfile ? '.'.pathinfo($imgfile,PATHINFO_EXTENSION ) :'';
			$ext = basename($ext); 
			//POTI-board形式のファイル名に変更してコピー
			if($ext && is_file("src/$imgfile")){//画像
				if($save_at_synonym && is_file("poti/src/{$time}{$ext}")){
					$time=$time+1;
				}
				if(!is_file("poti/src/{$time}{$ext}")){
					copy("src/$imgfile","poti/src/{$time}{$ext}");
					chmod("poti/src/{$time}{$ext}",PERMISSION_FOR_DEST);
				}
				$thumbnail="";
				if(thumbnail_gd::thumb("src/",$imgfile,$time,$w,$h)){
					$thumbnail='thumbnail';
				}
			}
			$_pchext = check_pch_ext("src/{$origin_time}");
			//動画を表示しない設定のpch、tgkrをコピーするかどうか
			$pchext = (!$copy_hide_animation && ((strpos($pchext,'hide')!==false))) ? "" : $_pchext;
			if($pchext && is_file("src/{$origin_time}{$pchext}")){//動画
				if(!is_file("poti/src/{$time}{$pchext}")){
					copy("src/{$origin_time}{$pchext}","poti/src/{$time}{$pchext}");
					chmod("poti/src/{$time}{$pchext}",PERMISSION_FOR_DEST);
				}
			}

				//フォーマット
				if(!$url||!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('{\Ahttps?://}', $url)) $url="";
				$name = str_replace("◆", "◇", $name);

			
				// 改行コード
				$com = str_replace('"\n"',"<br>",$com);	//改行文字の前に HTMLの改行タグ
				$email='';
				$now_time = substr($time,0,-3);
				$now=now_date((int)$now_time);
				$now .=  $userid ? " ID:" . $userid : "";
				$tool =switch_tool($tool);
				$newlog[]="$__no,$now,$name,$email,$sub,$com,$url,$host,$hash,$ext,$w,$h,$time,$log_img_hash,$painttime,,$pchext,$thumbnail,$tool,6\n";

				$tree[]=$__no;
	
				++$__no;
		}
		$treeline[]=implode(",",$tree)."\n";
		unset($tree);
	
	}

unset($oya);

//ツリーログ
$_treeline=[];
foreach($treeline as $val){
	[$_oya,]=explode(',',rtrim($val));
	$_treeline[$_oya]=$val;
}
$treeline=$_treeline;
ksort($treeline);
foreach($treeline as $i => $val){
	$ko=explode(',',rtrim($val));
	$oya=$ko[0];

	unset($ko[0]);
	foreach($ko as $k =>$v){
		if(isset($treeline[$v])){
			unset($ko[$k]);
			$_ko=implode(",",$ko);
			if($_ko){
				$treeline[$i]="$oya,$_ko\n";
			}else{
				$treeline[$i]="$oya\n";
			}
		}
	}
}
krsort($treeline);
file_put_contents('poti/tree.log',implode("",$treeline), LOCK_EX);
chmod('poti/tree.log',PERMISSION_FOR_LOG);
krsort($newlog);
file_put_contents('poti/img.log',implode("",$newlog),LOCK_EX);
chmod('poti/img.log',PERMISSION_FOR_LOG);

echo $en ? 'Conversion is complete. Please do not reload.' : '変換終了。リロードしないでください。'; 
;

function lang_en(){//言語が日本語以外ならtrue。
	$lang = ($http_langs = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
	? explode( ',', $http_langs )[0] : '';
  return (stripos($lang,'ja')!==0) ? true : false;
  
}

// 日付
function now_date(int $time){
	$youbi = array('日','月','火','水','木','金','土');
	$yd = $youbi[(int)date("w", $time)] ;
	$date = date(DATE_FORMAT, $time);
	$date = str_replace("<1>", $yd, $date); //漢字の曜日セット1
	$date = str_replace("<2>", $yd.'曜', $date); //漢字の曜日セット2
	return $date;
}

//タブ除去
function t(?string $str){
	return str_replace("\t","",$str);
}

function check_dir (?string $path) {

	if (!is_dir($path)) {
			mkdir($path, PERMISSION_FOR_DIR,true);
			chmod($path, PERMISSION_FOR_DIR);
	}
}
function check_petit (?string $path) {

	if (!is_dir($path)) {
			mkdir($path, PERMISSION_FOR_POTI,true);
			chmod($path, PERMISSION_FOR_POTI);
	}
}

function switch_tool(?string $tool){
	switch($tool){
		case 'neo':
			$tool='PaintBBS NEO';
			break;
		case 'PaintBBS':
			$tool='PaintBBS';
			break;
		case 'shi-Painter':
			$tool='Shi-Painter';
			break;
		case 'chi':
			$tool='litaChix';
			break;
		case 'klecks':
			$tool='Klecks';
			break;
		case 'tegaki':
			$tool='Tegaki';
			break;
		case 'axnos':
			$tool='Axnos Paint';
			break;
		case 'upload':
			$tool='Upload';
			break;
		default:
			$tool='';
			break;
	}
	return $tool;
}
/**
 * pchかspchか、それともファイルが存在しないかチェック
 * @param $filepath
 * @return string
 */
function check_pch_ext (?string $filepath) {
	
	$exts=[".pch",".spch",".tgkr",".chi",".psd"];

	foreach($exts as $i => $ext){

		if (is_file($filepath . $ext)) {
			if(!in_array(mime_content_type($filepath . $ext),["application/octet-stream","application/gzip","image/vnd.adobe.photoshop"])){
				return '';
			}
			return $ext;
		}
	}
	return '';
}

// thumbnail_gd.inc.php for PetitNote (C)さとぴあ @satopian 2021-2026 MIT License
// https://paintbbs.sakura.ne.jp/
// originalscript (C)SakaQ 2005 http://www.punyu.net/php/

$thumbnail_gd_ver=20260501;
class thumbnail_gd {

/**
 * @param int|string|null $max_w
 * @param int|string|null $max_h
*/

	public static function thumb(?string $path,?string $fname,?string $time,$max_w,$max_h,array $options=[]): string {
		$path=basename($path).'/';
		$fname=basename($fname);
		$time=basename($time);
		if(!ctype_digit($time)) {
			return '';
		}
		$fname=$path.$fname;
		if(!is_file($fname)){
			return '';
		}
		if(!self::gd_check()||!function_exists("ImageCreate")||!function_exists("ImageCreateFromJPEG")){
			return '';
		}
		if(isset($options['png2webp'])||isset($options['png2jpeg'])){
			$options['2webp']=true;//互換処理
		}
		if((isset($options['webp'])||isset($options['2webp'])||isset($options['thumbnail_webp'])) && !function_exists("ImageWEBP")){
			return '';
		}
		if((isset($options['avif'])||isset($options['2avif'])||isset($options['thumbnail_avif'])) && !function_exists("ImageAVIF")){
			return '';
		}

		$fsize = filesize($fname); // ファイルサイズを取得
		[$w,$h] = GetImageSize($fname); // 画像の幅と高さを取得
		$w_h_size_over = $max_w && $max_h && ($w > $max_w || $h > $max_h);
		$f_size_over = !isset($options['toolarge']) ? ($fsize>1024*1024) : false;
		if(!$w_h_size_over && !$f_size_over && !isset($options['webp']) && !isset($options['2webp']) && !isset($options['2png']) && !isset($options['2jpeg'])){//リサイズも変換もしない
			return '';
		}
		if(!$w_h_size_over || isset($options['2webp']) || isset($options['2png']) || !$max_w || !$max_h){//リサイズしない
			$out_w = $w;
			$out_h = $h;
		}else{// リサイズ
			$w_ratio = $max_w / $w;
			$h_ratio = $max_h / $h;
			$ratio = min($w_ratio, $h_ratio);
			$out_w = ceil($w * $ratio);//端数の切り上げ
			$out_h = ceil($h * $ratio);
		}

		$mime_type = mime_content_type($fname);
		if(!$im_in = self::createImageResource($fname,$mime_type)){
			return '';
		};
		// 出力画像（サムネイル）のイメージを作成
		if(function_exists("ImageCreateTrueColor")){
			$im_out = ImageCreateTrueColor($out_w, $out_h);

			if(self::isTransparencyEnabled($options, $mime_type)){//透明度を扱う時
					imagealphablending($im_out, false);
					imagesavealpha($im_out, true);//透明
			}else{//透明度を扱わない時
				if(function_exists("ImageColorAlLocate") && function_exists("imagefill")){
					$background = ImageColorAlLocate($im_out, 0xFF, 0xFF, 0xFF);//背景色を白に
					imagefill($im_out, 0, 0, $background);
				}
			}

		}else{
			$im_out = ImageCreate($out_w, $out_h);
		}

		// コピー＆再サンプリング＆縮小
		if(function_exists("ImageCopyResampled")){
			ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $w, $h);
		}else{
			ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $w, $h);//"ImageCopyResampled"が無効の時
		}

		if(isset($options['toolarge'])){//元画像を縮小してPNGで上書き
			$outfile = self::overwriteResizedImageWithPNG($im_out, $fname);
		}else{
			$outfile = self::createThumbnailImage($im_out, $time, $options);
		}
		// 作成したイメージを破棄
		self::safeImageDestroy($im_in);
		self::safeImageDestroy($im_out);

		if(!$outfile){
			return '';
		}

		if(!chmod($outfile,PERMISSION_FOR_DEST)){
			return '';
		}

		if(is_file($outfile)){
			return $outfile;
		}
		return '';

	}
	//GD版が使えるかチェック
	private static function gd_check(): bool {
		// GDモジュールが有効化されているか
		if (!extension_loaded('gd')) {
				return false;
		}
		// GDモジュールが動作可能か
		if (!function_exists('gd_info')) {
				return false;
		}
		// JPEGのサポートを確認
		if (!(ImageTypes() & IMG_JPG)) {
				return false;
		}
		// JPEG出力関数の存在を確認
		if (!function_exists('ImageJPEG')) {
				return false;
		}
		return true;
	}

	/**
	 * GDのイメージを破棄 
	 * @param resource|\GdImage|null $gdImage
	*/
	private static function safeImageDestroy($gdImage): void {
		if(PHP_VERSION_ID < 80000) {//PHP8.0未満の時は
			imagedestroy($gdImage);
		}
	}

	// 透明度の処理を行う必要があるかを判断
	private static function isTransparencyEnabled(array $options,?string $mime_type): bool {
		// 透明度を扱うオプションが設定されているか確認
		$transparencyOptionsSet = isset($options['toolarge']) || isset($options['webp']) || isset($options['thumbnail_webp']) || isset($options['2webp']) || isset($options['2png']);
		
		// 対象の画像形式で透明度がサポートされているか確認
		$transparencySupportedFormats = ["image/png", "image/gif", "image/webp", "image/avif"];
		
		// 透明度を扱うための関数が存在するか確認
		$transparencyFunctionsAvailable = function_exists("imagealphablending") && function_exists("imagesavealpha");
		
		return $transparencyOptionsSet && in_array($mime_type, $transparencySupportedFormats) && $transparencyFunctionsAvailable;
	}
	/**
	 *各画像フォーマットのリソースを作成
	 * @param string|bool $mime_type
	 */
	private static function createImageResource(?string $fname,$mime_type) {
		switch ($mime_type) {
			case "image/gif":
				if(!function_exists("ImageCreateFromGIF")) {//gif
					return null;
				}
					$im_in = @ImageCreateFromGIF($fname);
					if(!$im_in)return null;
				break;
			case "image/jpeg":
				$im_in = @ImageCreateFromJPEG($fname);//jpg
					if(!$im_in)return null;
				break;
			case "image/png":
				if(!function_exists("ImageCreateFromPNG")) {//png
					return null;
				}
				$im_in = @ImageCreateFromPNG($fname);
					if(!$im_in)return null;
				break;
			case "image/webp":
				if(!function_exists("ImageCreateFromWEBP")) {//webp
					return null;
				}
					$im_in = @ImageCreateFromWEBP($fname);
					if(!$im_in)return null;
				break;
			case "image/avif":
				if(!function_exists("ImageCreateFromAVIF")) {//avif
					return null;
				}
					$im_in = @ImageCreateFromAVIF($fname);
					if(!$im_in)return null;
				break;

			default : return null;
		}
		return $im_in;
	}

	/**
	 * 縮小してPNGで上書き 
	 * @param resource|\GdImage|null $im_out
	*/
	private static function overwriteResizedImageWithPNG($im_out, ?string $fname): ?string {
		$outfile=(string)$fname;
		//本体画像を縮小
			if(function_exists("ImagePNG")) {
				ImagePNG($im_out, $outfile,3);
			} else {
				ImageJPEG($im_out, $outfile,98);
			}
		return $outfile;
	}
	/**
	 * サムネイル作成 
	 * @param resource|\GdImage|null $im_out
	*/
	private static function createThumbnailImage($im_out,?string $time,array $options): ?string {

		if(isset($options['2png'])) {

			$outfile=TEMP_DIR.$time.'.png.tmp';//一時ファイル
			ImagePNG($im_out, $outfile,3);
		
		} elseif(isset($options['2jpeg'])) {

			$outfile=TEMP_DIR.$time.'.jpeg.tmp';//一時ファイル
			imagejpeg($im_out, $outfile,98);

		} elseif(isset($options['2webp'])) {

			$outfile=TEMP_DIR.$time.'.webp.tmp';//一時ファイル
			ImageWEBP($im_out, $outfile,98);
		
		} elseif(isset($options['2avif'])) {

			$outfile=TEMP_DIR.$time.'.avif.tmp';//一時ファイル
			imageavif($im_out, $outfile,90);
		
		} elseif(isset($options['webp'])) {

			$outfile='webp/'.$time.'t.webp';
			ImageWEBP($im_out, $outfile,90);
		
		} elseif(isset($options['avif'])) {

			$outfile='avif/'.$time.'t.avif';
			imageavif($im_out, $outfile,80);
		
		} elseif(isset($options['thumbnail_webp'])) {

			$outfile=THUMB_DIR.$time.'s.webp';
			ImageWEBP($im_out, $outfile,90);

		} elseif(isset($options['thumbnail_avif'])) {

			$outfile=THUMB_DIR.$time.'s.avif';
			imageavif($im_out, $outfile,80);

		} else {

			$outfile=THUMB_DIR.$time.'s.jpg';
			// サムネイル画像を保存
			ImageJPEG($im_out, $outfile,90);

		}
			return $outfile;
	}
}
