<?php

// POTI-board → Petit Note ログコンバータ。
// (c)2022 さとぴあ(satopian) 
//Licence MIT

/* ------------- 設定項目ここから ------------- */

/* --------------- パスワード ---------------- */

//管理者以外実行できないようにパスワードをセット
$admin_pass='hoge';//必ず変更してください

/* ------------- BBSNoteログ設定 ------------- */

//BBSNoteのconfig.cgiの設定にあわせます。
//参考例は、BBSNotev7、BBSNotev8のデフォルト値

//ログファイルのディレクトリ
$bbsnote_log_dir = 'data/';

// BBSNoteのログファイルの頭文字

// $bbsnote_filehead_logs = 'MSG';//v7は、'MSG'
$bbsnote_filehead_logs = 'LOG';//v8は'LOG'

//BBSNoteのログファイルの拡張子

// $bbsnote_log_exe = 'log';//v7は、'log'
$bbsnote_log_exe = 'cgi';//v8は'cgi'

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



//設定項目ここまで
//ここから下には設定項目はありません。

	if (!is_dir('petit')){
		mkdir('petit', 0606);
	}
	if (!is_dir('petit/log')){
		mkdir('petit/log', 0606);
	}
	if (!is_dir('petit/src')){
		mkdir('petit/src', 0606);
	}
	if (!is_dir('petit/thumbnail')){
		mkdir('petit/thumbnail', 0606);
	}
	$logfiles_arr =(glob($bbsnote_log_dir.'{'.$bbsnote_filehead_logs.'*.'.$bbsnote_log_exe.'}', GLOB_BRACE));//ログファイルをglob
	if(!$logfiles_arr){
		error('BBSNoteのログファイルの読み込みに失敗しました。BBSNoteのログファイルの頭文字や拡張子の設定が間違っている可能性があります。');
	}
	

	$oya=[];
	foreach($logfiles_arr as $logfile){//ログファイルを一つずつ開いて読み込む
		$fp=fopen($logfile,"r");
		while($line =fgets($fp)){
			$line=mb_convert_encoding($line, "UTF-8", "sjis");
			$line = str_replace(",", "&#44;", $line);
			if($relm){//relm
				$line = t($line);
				$arr_line=explode("<>",$line);
				$count_arr_line=count($arr_line);
				if($count_arr_line<5){
					error('ログファイルの読み込みに失敗しました。設定が間違っている可能性があります。');
				}
				if($count_arr_line>20){//スレッドの親?
					$no=$arr_line[1];
				}
			}else{//BBSNote
				$arr_line=explode("\t",$line);
				$count_arr_line=count($arr_line);
				if($count_arr_line<5){
					error('ログファイルの読み込みに失敗しました。設定が間違っている可能性があります。');
				}
				if($count_arr_line>11){//スレッドの親?
					$no=$arr_line[0];
				}
			}
			$logs[$no][]=$line;//1スレッド分
		}
		fclose($fp);
	
	}
	// var_dump($log);

	ksort($logs);
	$logs=array_values($logs);
	$oya_arr=[];
	$thread=[];

	foreach($logs as $i=> $log){
	
		foreach($log as $k=>$val){//1スレッド分のログを処理
		// var_dump($val);
		$no=$i+1;
		if($k===0){//スレッドの親
			if($relm){//relm
			list($threadno,$_no,$now,$name,,$sub,$email,$url,$com,$time,$ip,$host,,,,,$agent,,$filename,$W,$H,,$_thumbnail,$pch,,,$ptime,)
				=explode("<>",$val);
			}else{//BBSNote
			list($_no,$name,$now,$sub,$email,$url,$com,$host,$ip,$agent,$filename,$W,$H,,,$pch,$painttime,$applet,$_thumbnail)
				=explode("\t",$val);
			$time= $now ? preg_replace('/\(.+\)/', '', $now):0;//曜日除去
			$time=(int)strtotime($time);//strからUNIXタイムスタンプ
			}

			$time=$time ? $time*1000 : 0; 
			$ext = $filename ? '.'.pathinfo($filename,PATHINFO_EXTENSION ) :'';
			$_pchext = pathinfo($pch,PATHINFO_EXTENSION );

			$ext = (!in_array($ext, ['.pch', '.spch'])) ? $ext : ''; 
			$_pchext =  (in_array($_pchext, ['pch', 'spch'])) ? $_pchext : '';
			$is_img=false;
			//POTI-board形式のファイル名に変更してコピー
			if($ext && is_file("data/$filename")){//画像
				if($save_at_synonym && is_file("petit/src/{$time}{$ext}")){
						$time=$time+1;
				}

				$is_img=true;	
				$imgfile=$time.$ext;
				copy("data/$filename","petit/src/{$imgfile}");
				chmod("petit/src/{$imgfile}",PERMISSION_FOR_DEST);
			}
			$pch_fname=pathinfo($pch,PATHINFO_FILENAME);
			
			$pchext=check_pch_ext($bbsnote_log_dir.$pch_fname);
			if($pchext && is_file($bbsnote_log_dir.$pch)){//動画
				copy($bbsnote_log_dir.$pch,"petit/src/$time.$pchext");
				chmod("petit/src/$time.$pchext",PERMISSION_FOR_DEST);
				$pchext=$_pchext;
			}

			$tool='';
				
			switch($pchext){
				case '.pch':
					$tool='neo';
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
			$thumbnail='';
			if($usethumb&&$is_img&&($thumbnail_size=thumb("petit/src/",$time,$ext,$max_w,$max_h))){//作成されたサムネイルのサイズ
				$W=$thumbnail_size['w'];
				$H=$thumbnail_size['h'];
				$thumbnail='thumbnail';
			}

			$url = str_replace([" ","　","\t"],'',$url);
			if(!$url||stripos('sage',$url)!==false||preg_match("/&lt;|</i",$url)){
				$url="";
			}
			$url=$url ? $http.$url :'';
			$sub = $sub ? $sub : $defalt_subject;
			$com = preg_replace("#<br( *)/?>#i",'"\n"',$com); //<br />を"\n"に
			$com=strip_tags($com);

			$oya = "$no\t$sub\t$name\t\t$com\t$url\t$imgfile\t$W\t$H\t$thumbnail\t$painttime\t\t$tool\t$pchext\t$time\t$time\t$host\t\t\toya\n";
			$oya_arr[]=$oya;
			$thread[$i][]=$oya;


			$resub=$sub ? "Re: {$sub}" :'';

		}else{//スレッドの子
			unset($threadno,$_no,$now,$name,$sub,$email,$url,$com,$time,$ip,$host,$agent,$filename,$W,$painttime,$thumbnail,$pch,$applet);
			$W=$H=$pch=$ptime=$ext=$time=$ip='';
			if($relm){//relm
				list($threadno,$_no,$now,$name,,$sub,$email,$url,$com,$time,$ip,$host)
				=explode("<>",$val);
			}else{//BBSNote
				list($_no,$name,$now,$com,,$host,$email,$url)
				=explode("\t",$val);
				$time= $now ? preg_replace('/\(.+\)/', '', $now):0;//曜日除去
				$time=(int)strtotime($time);//strからUNIXタイムスタンプ
			}

			$time=$time ? $time*1000 : 0; 
			$url = str_replace([" ","　","\t"],'',$url);
			if(!$url||stripos('sage',$url)!==false||preg_match("/&lt;|</i",$url)){
				$url="";
			}
			$url=$url ? $http.$url :'';
			$thumbnail='';
			$painttime='';
			$com = preg_replace("#<br( *)/?>#i",'"\n"',$com); //<br />を"\n"に
			$com=strip_tags($com);

			$res = "$no\t$resub\t$name\t\t$com\t$url\t\t\t\t$thumbnail\t$painttime\t\t$tool\t$pchext\t$time\t$time\t$host\t\t\tres\n";
			$thread[$i][]=$res;


		}
		file_put_contents('petit/log/'.$no.'.log',$thread[$i]);


	}
}

$oya_arr=array_reverse($oya_arr, false);
file_put_contents('petit/log/alllog.log',$oya_arr);

echo'変換終了。リロードしないでください。';

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

// ファイル存在チェック
function check_file ($path,$check_writable='') {
	$msg=initial_error_message();
	if (!is_file($path)) return $path . $msg['001']."<br>";
	if (!is_readable($path)) return $path . $msg['002']."<br>";
	if($check_writable){//書き込みが必要なファイルのチェック
		if (!is_writable($path)) return $path . $msg['003']."<br>";
	}
}

//タブ除去
function t($str){
	return str_replace("\t","",$str);
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
		// コピー＆再サンプリング＆縮小
		if(function_exists("ImageCopyResampled")&&RE_SAMPLED){
			ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
		}else{$nottrue = 1;}
	}else{$im_out = ImageCreate($out_w, $out_h);$nottrue = 1;}
	// コピー＆縮小
	if($nottrue) ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
	// サムネイル画像を保存
	ImageJPEG($im_out, 'petit/thumbnail/'.$tim.'s.jpg',THUMB_Q);
	// 作成したイメージを破棄
	ImageDestroy($im_in);
	ImageDestroy($im_out);
	if(!chmod('petit/thumbnail/'.$tim.'s.jpg',PERMISSION_FOR_DEST)){
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
	
