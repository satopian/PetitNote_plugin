<?php
// POTI-board → Petit Note ログコンバータ。
// (c)2022-2024 さとぴあ(satopian) 
// Licence MIT
// lot.240411

/* ------------- 設定項目ここから ------------- */

/* ------------- BBSNoteログ設定 ------------- */

//BBSNoteのconfig.cgiの設定にあわせます。
//参考例は、BBSNotev7、BBSNotev8のデフォルト値

//ログファイルのディレクトリ
$bbsnote_log_dir = 'data/';

// BBSNoteのログファイルの頭文字

//  $bbsnote_filehead_logs = 'MSG';//v7は、'MSG'
$bbsnote_filehead_logs = 'LOG';//v8は'LOG'

//BBSNoteのログファイルの拡張子

//  $bbsnote_log_ext = 'log';//v7は、'log'
$bbsnote_log_ext = 'cgi';//v8は'cgi'

/* --------------- relmから変換 --------------- */

// BBSNoteと仕様が近いrelmのログも変換できます。
// relmが何かわからない方は変更しないでください。

$relm=0; //relmのログを変換する時は 1
// $relm=1; でrelmから変換。 
// デフォルト 0 

/* ------------- 画像ファイル名 -------------- */

//同じファイル名の画像が出力先にあるときは別名で保存
$save_at_synonym=0;// 1.する 0.しない

//別名で保存オプションは、
//秒単位の同時刻の投稿画像を別名で保存するためのものです。

//1.する の時にコンバートを複数回行うと
//同じ画像が別名で出力されてしまいます。

//デフォルト 0

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


/* ----------------- url設定 ----------------- */

//BBSNoteのログには'http://'、'https://'が記録されていないため
//どちらにするか選んでください。
$http='http://';//または 'https://'

/* ----------------- 題名が空欄の時 ----------------- */

$defalt_subject = '無題';

/* ----------------- 名前が空欄の時 ----------------- */

$defalt_name = '';//初期値は空欄のまま

/* -------------- パーミッション -------------- */

//正常に動作しているときは変更しない。
//画像やHTMLファイルのパーミッション。
define('PERMISSION_FOR_DEST', 0606);//初期値 0606
//ブラウザから直接呼び出さないログファイルのパーミッション
define('PERMISSION_FOR_LOG', 0600);//初期値 0600
//POTIディレクトリのパーミッション
define('PERMISSION_FOR_PETIT', 0705);//初期値 PERMISSION_FOR_PETIT
//画像や動画ファイルを保存するディレクトリのパーミッション
define('PERMISSION_FOR_DIR', 0707);//初期値 0707

/* -------------- タイムゾーン -------------- */

//タイムゾーン 日本時間で良ければ初期値 "asia/tokyo"

date_default_timezone_set("asia/tokyo");


/* ----------- ここから下設定項目なし ----------- */

//設定項目ここまで
//ここから下には設定項目はありません。

check_petit('petit');
check_dir('petit/log');
check_dir('petit/src');
check_dir('petit/thumbnail');
check_dir('petit/webp');

$en=lang_en();

$logfiles_arr =(glob($bbsnote_log_dir.'{'.$bbsnote_filehead_logs.'*.'.$bbsnote_log_ext.'}', GLOB_BRACE));//ログファイルをglob

if(!$logfiles_arr){
	error($en?'Failed to read the BBS Note log file. The setting of the log file heading character is incosect.':'BBSNoteのログファイルの読み込みに失敗しました。BBSNoteのログファイルの頭文字や拡張子の設定が間違っている可能性があります。');
}
sort($logfiles_arr);

	$arr_logs=[];
	foreach($logfiles_arr as $i=>$logfile){//ログファイルを一つずつ開いて読み込む

		$fp=fopen($logfile,"r");
		while($line =fgets($fp)){
			if(!trim($line)){
				continue;
			}
			$line=mb_convert_encoding($line, "UTF-8", "sjis");
			if($relm){//relm
				$line = t($line);
				$arr_line=explode("<>",$line);
				$count_arr_line=count($arr_line);
				if($count_arr_line<5){
					error($en?'Failed to read the log file. The settings may be incorrect.':'ログファイルの読み込みに失敗しました。設定が間違っている可能性があります。');
				}
			}else{//BBSNote
				$arr_line=explode("\t",$line);
				$count_arr_line=count($arr_line);
				if($count_arr_line<5){
					error($en?'Failed to read the log file. The settings may be incorrect.':'ログファイルの読み込みに失敗しました。設定が間違っている可能性があります。');
				}
			}
			$arr_logs[$i][]=$line;//1スレッド分
		}
		fclose($fp);
	
	}

	ksort($arr_logs);
	$arr_logs=array_values($arr_logs);

	$oya_arr=[];
	$thread=[];

	foreach($arr_logs as $i=> $log){
	
		$pchext='';
		$tool='';
		$resub='';
		$thread=[];
		foreach($log as $k=>$val){//1スレッド分のログを処理
			$no=$i+1;
			if($k===0){//スレッドの親
				if($relm){//relm
				list($threadno,$_no,$now,$name,,$sub,$email,$url,$com,$time,$ip,$host,,,,,$agent,,$filename,$W,$H,,$_thumbnail,$pch,,,$painttime,)
					=explode("<>",$val);
				}else{//BBSNote
				$painttime='';
				list($_no,$name,$now,$sub,$email,$url,$com,$host,$ip,$agent,$filename,$W,$H,,,$pch,,$applet,$_thumbnail)
				=explode("\t",$val."\t"."\t"."\t"."\t"."\t"."\t"."\t"."\t"."\t");
				$time= $now ? preg_replace('/\(.+\)/', '', $now):0;//曜日除去
				$time=(int)strtotime($time);//strからUNIXタイムスタンプ
				}

				$time=$time ? $time.'000000' : 0; 
				$time=basename($time);
				$ext = $filename ? '.'.pathinfo($filename,PATHINFO_EXTENSION ) :'';
				$filename = $filename ? basename($filename) : '';
				$_pchext = pathinfo($pch,PATHINFO_EXTENSION );

				$ext = (!in_array($ext, ['.pch', '.spch'])) ? basename($ext) : ''; 
				$_pchext =  (in_array($_pchext, ['pch', 'spch'])) ? $_pchext : '';
				//Petit Note形式のファイル名に変更してコピー
				$imgfile='';
				$W=$H='';
				$thumbnail='';
				if($ext && is_file("data/$filename")){//画像

					if($save_at_synonym && is_file("petit/src/{$time}{$ext}")){
							$time=$time+1;
					}

					$imgfile=$time.$ext;
					copy("data/$filename","petit/src/{$imgfile}");
					chmod("petit/src/{$imgfile}",PERMISSION_FOR_DEST);

					list($W,$H)=getimagesize("petit/src/{$imgfile}");
					list($W,$H)=image_reduction_display($W,$H,$max_w,$max_h);

					if($usethumb && thumb("petit/src/",$imgfile,$time,$max_w,$max_h,['thumbnail_webp'=>true])){
						$thumbnail='thumbnail_webp';
					}
					if($usethumb && !$thumbnail && thumb("petit/src/",$imgfile,$time,$max_w,$max_h)){
						$thumbnail='thumbnail';
					}
			
					//webpサムネイル
					thumb("petit/src/",$imgfile,$time,300,800,['webp'=>true]);
			
				}
				$pch_fname=pathinfo($pch,PATHINFO_FILENAME);
				
				$pchext=$_pchext ? check_pch_ext($bbsnote_log_dir.$pch_fname):'';
				if($pchext){//動画
					copy($bbsnote_log_dir.$pch_fname.$pchext,"petit/src/{$time}{$pchext}");
					chmod("petit/src/{$time}{$pchext}",PERMISSION_FOR_DEST);
				}

				$tool='';
					
				switch($pchext){
					case '.pch':
						$tool='neo';
						if(!is_neo("petit/src/{$time}.pch")){
							$tool = 'PaintBBS';
							$pchext = '';
						};
					break;
					case 'PaintBBS':
						$tool='PaintBBS';
						break;
					case '.spch':
						$tool='shi-Painter';
						break;
					default:
						if($ext){
							$tool='???';
						}
						break;
				}
				
				$url=$url ? $http.$url :'';
				if(!$url||!filter_var($url,FILTER_VALIDATE_URL)){
					$url="";
				}
				$sub = $sub ? $sub : $defalt_subject;
				$name = $name ? $name : $defalt_name;

				$com = preg_replace("#<br( *)/?>#i",'"\n"',$com); //<br />を"\n"に
				$com=strip_tags($com);
				$no=(int)$no;
				$thread[$i][] = "$no\t$sub\t$name\t\t$com\t$url\t$imgfile\t$W\t$H\t$thumbnail\t$painttime\t\t$tool\t$pchext\t$time\t$time\t$host\t\t\toya\n";

				$strcut_com=mb_strcut($com,0,120);
				$oya_arr[$i] = "$no\t$sub\t$name\t\t$strcut_com\t$url\t$imgfile\t$W\t$H\t$thumbnail\t$painttime\t\t$tool\t$pchext\t$time\t$time\t$host\t\t\toya\n";


				$resub=$sub ? "Re: {$sub}" :'';

			}else{//スレッドの子
				unset($threadno,$_no,$now,$name,$sub,$email,$url,$com,$time,$ip,$host,$agent,$filename,$W,$painttime,$thumbnail,$pch,$applet);
				$W=$H=$pch=$painttime=$ext=$time=$ip='';
				if($relm){//relm
					list($threadno,$_no,$now,$name,,$sub,$email,$url,$com,$time,$ip,$host)
					=explode("<>",$val);
				}else{//BBSNote
					list($_no,$name,$now,$com,,$host,$email,$url)
					=explode("\t",$val);
					$time= $now ? preg_replace('/\(.+\)/', '', $now):0;//曜日除去
					$time=(int)strtotime($time);//strからUNIXタイムスタンプ
				}

				$time=$time ? $time.'000000' : 0; 
				$url=$url ? $http.$url :'';
				if(!$url||!filter_var($url,FILTER_VALIDATE_URL)){
					$url="";
				}
				$thumbnail='';
				$painttime='';
				$com = preg_replace("#<br( *)/?>#i",'"\n"',$com); //<br />を"\n"に
				$com=strip_tags($com);
				$no=(int)$no;
				$name = $name ? $name : $defalt_name;
				$res = "$no\t$resub\t$name\t\t$com\t$url\t\t\t\t$thumbnail\t$painttime\t\t$tool\t$pchext\t$time\t$time\t$host\t\t\tres\n";
				$thread[$i][]=$res;

			}
			file_put_contents('petit/log/'.$no.'.log',implode("",$thread[$i]));
			chmod('petit/log/'.$no.'.log',PERMISSION_FOR_LOG);	

		}
	}

krsort($oya_arr);
file_put_contents('petit/log/alllog.log',implode("",$oya_arr));
chmod('petit/log/alllog.log',PERMISSION_FOR_LOG);	

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
 * pchかchiかpsdか、それともファイルが存在しないかチェック
 * @param $filepath
 * @return string
 */
function check_pch_ext ($filepath) {
	
	$exts=[".pch",".spch"];

	foreach($exts as $i => $ext){

		if (is_file($filepath . $ext)) {
			if(!in_array(mime_content_type($filepath . $ext),["application/octet-stream","application/gzip"])){
				return '';
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

//縮小表示
function image_reduction_display($w,$h,$max_w,$max_h){
	if(!is_numeric($w)||!is_numeric($h)){
		return ['',''];
	}

    if ($w > $max_w || $h > $max_h) {
        $w_ratio = $max_w / $w;
        $h_ratio = $max_h / $h;
        $ratio = min($w_ratio, $h_ratio);
        $w = ceil($w * $ratio);
        $h = ceil($h * $ratio);
    }
    $reduced_size = [$w,$h];
    return $reduced_size;
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
