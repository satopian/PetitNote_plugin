<?php

// Petit Note → POTI-board ログコンバータ。
// (c)2022-2023 さとぴあ(satopian) 
// Licence MIT
// lot.230414

/* ------------- 設定項目ここから ------------- */

/* ------------- 画像ファイル名 -------------- */

//同じファイル名の画像が出力先にあるときは別名で保存
//デフォルト 0

$save_at_synonym=0;// 1.する 0.しない

//別名で保存オプションは、
//秒単位の同時刻の投稿画像を別名で保存するためのものです。

//1.する の時にコンバートを複数回行うと
//同じ画像が別名で出力されてしまいます。


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
	
	
			list($no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$log_md5,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya)=explode("\t",$val);
			$origin_time=$time;
			$time=(strlen($time)>15) ? substr($time,0,-3) : $time;
			$ext = $imgfile ? '.'.pathinfo($imgfile,PATHINFO_EXTENSION ) :'';
			$ext = basename($ext); 
				//POTI-board形式のファイル名に変更してコピー
				if($ext && is_file("src/$imgfile")){//画像
					if($save_at_synonym && is_file("poti/src/{$time}{$ext}")){
							$time=$time+1;
					}
					copy("src/$imgfile","poti/src/{$time}{$ext}");
					chmod("poti/src/{$time}{$ext}",PERMISSION_FOR_DEST);
					if(($thumbnail==='thumbnail'||$thumbnail==='hide_thumbnail')&&is_file("thumbnail/{$origin_time}s.jpg")){
						copy("thumbnail/{$origin_time}s.jpg","poti/thumb/{$time}s.jpg");
						chmod("poti/thumb/{$time}s.jpg",PERMISSION_FOR_DEST);
					}
				}
	
				if(in_array($pchext,['.pch','.spch','.chi','.psd']) && is_file("src/{$origin_time}{$pchext}")){//動画
					copy("src/{$origin_time}{$pchext}","poti/src/{$time}{$pchext}");
					chmod("poti/src/{$time}{$pchext}",PERMISSION_FOR_DEST);
				}
	
				//フォーマット
				if(!$url||!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('{\Ahttps?://}', $url)) $url="";
				$name = str_replace("◆", "◇", $name);
			
				// 改行コード
				$com = str_replace('"\n"',"<br>",$com);	//改行文字の前に HTMLの改行タグ
				$email='';
				$now=now_date($time);
				$newlog[]="$__no,$now,$name,$email,$sub,$com,$url,$host,$hash,$ext,$w,$h,$time,$log_md5,$painttime,\n";
	
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

