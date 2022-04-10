<?php
// POTI-board → Petit Note ログコンバータ。
// (c)2022 さとぴあ(satopian) 
//Licence MIT
//lot.220410

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
	die($err);
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

$trees = file(TREEFILE);
$line = file(LOGFILE);

$trees=array_reverse($trees, false);
//ディレクトリを確認して無ければ作る
check_petit('petit');
check_dir('petit/log');
check_dir('petit/src');
check_dir('petit/thumbnail');
	
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
				list($_no,$date,$name,$email,$sub,$com,$url,$host,$hash,$ext,$w,$h,$_time,$img_md5,$_ptime,)
				=explode(",",rtrim(t($line[$j])));


				$painttime=is_numeric($_ptime) ? $_ptime :''; 
				//名前とトリップを分離
				list($name, $trip) = separateNameAndTrip($name);

				list($userid,) = separateDatetimeAndId($date);

				$date=substr($date,0,21);
				$date= preg_replace('/\(.+\)/', '', $date);//曜日除去
				$time= $date_to_timestamp ? strtotime($date).'000': substr($_time,-13);
				$imgfile='';
				if($ext && is_file(IMG_DIR."{$_time}{$ext}")){//画像
					$imgfile=$time.$ext;
					copy(IMG_DIR.$_time.$ext,"petit/src/{$imgfile}");
					chmod("petit/src/{$imgfile}",PERMISSION_FOR_DEST);
				}
				$thumbnail='';
				if($ext && is_file(THUMB_DIR."{$_time}s.jpg")){//画像
					$thumbnail='thumbnail';
					copy(THUMB_DIR."{$_time}s.jpg","petit/thumbnail/{$time}s.jpg");
					chmod("petit/thumbnail/{$time}s.jpg",PERMISSION_FOR_DEST);
				}
				
				$pchext=check_pch_ext (PCH_DIR.$_time);
				
				if($pchext){//動画
					copy(PCH_DIR."{$_time}{$pchext}","petit/src/{$time}{$pchext}");
					chmod("petit/src/{$time}{$pchext}",PERMISSION_FOR_DEST);
				}
	
				$com = preg_replace("#<br( *)/?>#i",'"\n"',$com); //<br />を"\n"に
				$com=strip_tags($com);

				$tool='';
				
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
					default:
						if($ext){
							$tool='???';
						}
						break;
				}

				$url=(strlen($url) < 200) ? $url :'';

				if($k===0){//スレッドの親の時

					$oya = "$no\t$sub\t$name\t\t$com\t$url\t$imgfile\t$w\t$h\t$thumbnail\t$painttime\t$img_md5\t$tool\t$pchext\t$time\t$time\t$host\t$userid\t$hash\toya\n";

					$thread[$i][]=$oya;
					$oya_arr[]=$oya;
				}else{
				
					$res = "$no\t$sub\t$name\t\t$com\t$url\t$imgfile\t$w\t$h\t$thumbnail\t$painttime\t$img_md5\t$tool\t$pchext\t$time\t$time\t$host\t$userid\t$hash\tres\n";

					$thread[$i][]=$res;

				}
			}
				file_put_contents('petit/log/'.$no.'.log',$thread[$i]);
				chmod('petit/log/'.$no.'.log',PERMISSION_FOR_LOG);	

}

$oya_arr=array_reverse($oya_arr, false);
file_put_contents('petit/log/alllog.log',$oya_arr);
chmod('petit/log/alllog.log',PERMISSION_FOR_LOG);	

echo lang_en() ? 'Conversion is complete. Please do not reload.' : '変換終了。リロードしないでください。'; 
;

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
		if($value !==''){
			list($no,) = explode(",", $value);
			if(!is_numeric($no)){//記事Noが正しく読み込まれたかどうかチェック
				// error(MSG019);
			};
			$lineindex[$no] = $i; // 値にkey keyに記事no
		}
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
 * pchかspchか、それともファイルが存在しないかチェック
 * @param $filepath
 * @return string
 */
function check_pch_ext ($filepath) {
	if (is_file($filepath . ".pch") && is_neo($filepath . ".pch")) {
		return ".pch";
	} elseif (is_file($filepath . ".spch")) {
		return ".spch";
	} elseif (is_file($filepath . ".chi")) {
		return ".chi";
	} elseif (is_file($filepath . ".psd")) {
		return ".psd";
	}
	return '';
}

function is_neo($src) {//neoのPCHかどうか調べる
	$fp = fopen("$src", "rb");
	$is_neo=(fread($fp,3)==="NEO");
	fclose($fp);
	return $is_neo;
}

