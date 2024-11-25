<?php

// Petit Note → POTI-board ログコンバータ。
// (c)2022-2024 さとぴあ(satopian) 
// Licence MIT
// lot.240411

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

while ($_line = fgets($fp)) {
		if(!trim($_line)){
			continue;
		}
		list($_no)=explode("\t",trim($_line));
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
	foreach($arr_logs as $i=>$logs){
	
		$tree=[];

		foreach($logs as $k=>$val){//1スレッド分のログを処理
	
	
			list($no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$log_img_hash,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya)=explode("\t",$val);
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
				if(thumb("src/",$imgfile,$time,$w,$h)){
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
				$now=now_date($now_time);
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
foreach($treeline as $val){
	list($_oya,)=explode(',',rtrim($val));
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
function now_date($time){
	$youbi = array('日','月','火','水','木','金','土');
	$yd = $youbi[date("w", $time)] ;
	$date = date(DATE_FORMAT, $time);
	$date = str_replace("<1>", $yd, $date); //漢字の曜日セット1
	$date = str_replace("<2>", $yd.'曜', $date); //漢字の曜日セット2
	return $date;
}

//タブ除去
function t($str){
	return str_replace("\t","",$str);
}

function check_dir ($path) {

	if (!is_dir($path)) {
			mkdir($path, PERMISSION_FOR_DIR,true);
			chmod($path, PERMISSION_FOR_DIR);
	}
}
function check_petit ($path) {

	if (!is_dir($path)) {
			mkdir($path, PERMISSION_FOR_POTI,true);
			chmod($path, PERMISSION_FOR_POTI);
	}
}

function switch_tool($tool){
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
			$tool='ChickenPaint';
			break;
		case 'klecks';
			$tool='Klecks';
			break;
		case 'tegaki';
			$tool='Tegaki';
			break;
		case 'axnos';
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
function check_pch_ext ($filepath) {
	
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
	$path='src/';
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
		$outfile='poti/webp/'.$time.'t.webp';
		ImageWEBP($im_out, $outfile,90);

	}elseif(isset($options['thumbnail_webp'])){
		$outfile='poti/thumb/'.$time.'s.webp';
		ImageWEBP($im_out, $outfile,90);
	}else{
		$outfile='poti/thumb/'.$time.'s.jpg';
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
