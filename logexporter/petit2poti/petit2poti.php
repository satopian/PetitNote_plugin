<?php

// Petit Note → POTI-board ログコンバータ。
// (c)2022-2023 さとぴあ(satopian) 
// Licence MIT
// lot.230314

/* ------------- 設定項目ここから ------------- */

/* ------------- 画像ファイル名 -------------- */

//同じファイル名の画像が出力先にあるときは別名で保存
//デフォルト 0

$save_at_synonym=0;// 1.する 0.しない

//別名で保存オプションは、
//秒単位の同時刻の投稿画像を別名で保存するためのものです。

//1.する の時にコンバートを複数回行うと
//同じ画像が別名で出力されてしまいます。


/* -------------- サムネイル設定 -------------- */

$usethumb=1;//サムネイルを作成する する 1 しない 0
$max_w=800;//この幅を超えたらサムネイル
$max_h=800;//この高さを超えたらサムネイル
// この値をあまり小さくしないでください。例えば100に設定すると幅や高さが100以上のときにサムネイルを作ります。
//しかし、全ログファイルの一括処理のためそれではサーバに大きな負荷がかかります。
//もしもサーバ負荷の懸念がある場合は、「サムネイルを作成しない」にしたほうが無難です。

define('THUMB_Q', 92);//サムネイルのjpg劣化率
//問題がなければこのまま
define('RE_SAMPLED', 1);

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

$logfiles_arr =(glob('log/{*.log}', GLOB_BRACE));//ログファイルをglob

if(!$logfiles_arr){
	error($en?'Failed to read the BBS Note log file. The setting of the log file heading character is incosect.':'BBSNoteのログファイルの読み込みに失敗しました。BBSNoteのログファイルの頭文字や拡張子の設定が間違っている可能性があります。');
}
natcasesort($logfiles_arr);

$newlog=[];

$fp=fopen('log/alllog.log',"r");
while ($_line = fgets($fp)) {
		if(!trim($_line)){
			continue;
		}
		list($_no)=explode("\t",trim($_line));
		$log_nos[]=$_no;	
	}
fclose($fp);



foreach($log_nos as $i=>$log_no){//ログファイルを一つずつ開いて読み込む
	$arr_logs=[];
	$log_no = basename($log_no); 
	$rp = fopen(LOG_DIR."{$log_no}.log", "r");//個別スレッドのログを開く
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

	foreach($arr_logs as $i=>$logs){
	
		$tree=[];

		foreach($logs as $k=>$val){//1スレッド分のログを処理
	
	
			list($no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$log_md5,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya)=explode("\t",$val);
			$time=substr($time,0,13);//13桁のUNIXタイムスタンプ
				$ext = $imgfile ? '.'.pathinfo($imgfile,PATHINFO_EXTENSION ) :'';
	
				$ext = (!in_array($ext, ['.pch', '.spch'])) ? basename($ext) : ''; 
				$pchext =  (in_array($pchext, ['pch', 'spch'])) ? $pchext : '';
				$W='';
				$H='';
				//POTI-board形式のファイル名に変更してコピー
				if($ext && is_file("src/$imgfile")){//画像
					if($save_at_synonym && is_file("poti/src/{$time}{$ext}")){
							$time=$time+1;
					}
					copy("src/$imgfile","poti/src/{$time}{$ext}");
					chmod("poti/src/{$time}{$ext}",PERMISSION_FOR_DEST);
					if($usethumb&&($thumbnail_size=thumb("poti/src/",$time,$ext,$max_w,$max_h))){//作成されたサムネイルのサイズ
						$W=$thumbnail_size['w'];
						$H=$thumbnail_size['h'];
					}else{
						list($W,$H)=getimagesize("poti/src/{$time}{$ext}");
					}
				}
	
				if($pchext && is_file("src/$pch")){//動画
					copy("src/{$time}{$pchext}","poti/src/{$time}{$pchext}");
					chmod("poti/src/$time.$pchext",PERMISSION_FOR_DEST);
				}
	
				//フォーマット
				if(!$url||!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('{\Ahttps?://}', $url)) $url="";
					$name = str_replace("◆", "◇", $name);
			
				// 改行コード
				$com = str_replace('"\n"',"<br>",$com);	//改行文字の前に HTMLの改行タグ
				$email='';
				$now=now_date($time);
				$no=(int)$i+1;
				$newlog[]="$no,$now,$name,$email,$sub,$com,$url,$host,$hash,$ext,$W,$H,$time,$log_md5,$painttime,\n";
	
				$tree[$i]=$no;
	
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
file_put_contents('poti/tree.log',$treeline, LOCK_EX);
chmod('poti/tree.log',PERMISSION_FOR_LOG);
krsort($newlog);
file_put_contents('poti/img.log',$newlog,LOCK_EX);
chmod('poti/img.log',PERMISSION_FOR_LOG);

echo $en ? 'Conversion is complete. Please do not reload.' : '変換終了。リロードしないでください。'; 
;

function lang_en(){//言語が日本語以外ならtrue。
	$lang = ($http_langs = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
	? explode( ',', $http_langs )[0] : '';
  return (stripos($lang,'ja')!==0) ? true : false;
  
}

//タブ除去
function t($str){
	return str_replace("\t","",$str);
}

/**
 * pchかspchか、それともファイルが存在しないかチェック
 * @param $filepath
 * @return string
 */
function check_pch_ext ($filepath) {
	if (is_file($filepath . ".pch") && is_neo($filepath . ".pch")) {
		return ".pch";
	} elseif (is_file($filepath . ".spch")) {
		return ".spch";
	}
	return '';
}

function is_neo($src) {//neoのPCHかどうか調べる
	$fp = fopen("$src", "rb");
	$is_neo=(fread($fp,3)==="NEO");
	fclose($fp);
	return $is_neo;
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

function thumb($path,$tim,$ext,$max_w,$max_h){
	if(!gd_check()||!function_exists("ImageCreate")||!function_exists("ImageCreateFromJPEG"))return;
	$fname=$path.$tim.$ext;
	$size = GetImageSize($fname); // 画像の幅と高さとタイプを取得
	if(!$size){
		return;
	}
	// リサイズ
	if($size[0] > $max_w || $size[1] > $max_h){
		$key_w = $max_w / $size[0];
		$key_h = $max_h / $size[1];
		($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
		$out_w = ceil($size[0] * $keys);//端数の切り上げ
		$out_h = ceil($size[1] * $keys);
	}else{
		return;
	}
	
	switch (mime_content_type($fname)) {
		case "image/gif";
		if(function_exists("ImageCreateFromGIF")){//gif
				$im_in = @ImageCreateFromGIF($fname);
				if(!$im_in)return;
			}
			else{
				return;
			}
		break;
		case "image/jpeg";
		$im_in = @ImageCreateFromJPEG($fname);//jpg
			if(!$im_in)return;
		break;
		case "image/png";
		if(function_exists("ImageCreateFromPNG")){//png
				$im_in = @ImageCreateFromPNG($fname);
				if(!$im_in)return;
			}
			else{
				return;
			}
			break;
		case "image/webp";
		if(function_exists("ImageCreateFromWEBP")){//webp
			$im_in = @ImageCreateFromWEBP($fname);
			if(!$im_in)return;
		}
		else{
			return;
		}
		break;

		default : return;
	}
	// 出力画像（サムネイル）のイメージを作成
	$nottrue = 0;
	if(function_exists("ImageCreateTrueColor")&&get_gd_ver()=="2"){
		$im_out = ImageCreateTrueColor($out_w, $out_h);
		if(function_exists("ImageColorAlLocate") && function_exists("imagefill")){
			$background = ImageColorAlLocate($im_out, 0xFF, 0xFF, 0xFF);//背景色を白に
			imagefill($im_out, 0, 0, $background);
		}
	// コピー＆再サンプリング＆縮小
		if(function_exists("ImageCopyResampled")&&RE_SAMPLED){
			ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
		}else{$nottrue = 1;}
	}else{$im_out = ImageCreate($out_w, $out_h);$nottrue = 1;}
	// コピー＆縮小
	if($nottrue) ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
	// サムネイル画像を保存
	ImageJPEG($im_out, 'poti/thumb/'.$tim.'s.jpg',THUMB_Q);
	// 作成したイメージを破棄
	ImageDestroy($im_in);
	ImageDestroy($im_out);
	if(!chmod('poti/thumb/'.$tim.'s.jpg',PERMISSION_FOR_DEST)){
		return;
	}

	$thumbnail_size = [
		'w' => $out_w,
		'h' => $out_h,
	];
return $thumbnail_size;

}

function error($str) {
	echo htmlspecialchars($str,ENT_QUOTES,"utf-8",false);
	exit;
	}
	
function initial_error_message(){
	$en=lang_en();
	$msg['041']=$en ? ' does not exist.':'がありません。'; 
	$msg['042']=$en ? ' is not readable.':'を読めません。'; 
	$msg['043']=$en ? ' is not writable.':'に書けません。'; 
return $msg;	
}

// ファイル存在チェック
function check_file ($path,$check_writable='') {
	$msg=initial_error_message();
	if (!is_file($path)) return $path . $msg['041']."<br>";
	if (!is_readable($path)) return $path . $msg['042']."<br>";
	if($check_writable){//書き込みが必要なファイルのチェック
		if (!is_writable($path)) return $path . $msg['043']."<br>";
	}
	return '';
}
// 日付
function now_date($time){
	$time=(int)substr((string)$time,0,10);
	$youbi = array('日','月','火','水','木','金','土');
	$yd = $youbi[date("w", $time)] ;
	$date = date(DATE_FORMAT, $time);
	$date = str_replace("<1>", $yd, $date); //漢字の曜日セット1
	$date = str_replace("<2>", $yd.'曜', $date); //漢字の曜日セット2
	return $date;
}
