<?php

//設定ファイル
if ($err = check_file(__DIR__.'/config.php')) {
	echo $err;
	exit;
}
//Template設定ファイル
if ($err = check_file(__DIR__.'/templates/'.'template_ini.php')) {
	echo $err;
	exit;
}


require(__DIR__.'/config.php');
require(__DIR__.'/templates/template_ini.php');

	$trees = file(LOGFILE);
	$line = file(TREEFILE);

	$trees=array_reverse($trees, false);
	if (!is_dir('petit')){
		mkdir('petit', 606);
	}
	if (!is_dir('petit/log')){
		mkdir('petit/log', 606);
	}
	if (!is_dir('petit/src')){
		mkdir('petit/src', 606);
	}
	if (!is_dir('petit/thumb')){
		mkdir('petit/thumb', 606);
	}

$lineindex = get_lineindex($line); // 逆変換テーブル作成
foreach($trees as $i=>$tree){//PAGE_DEF分のスレッドを表示
			$treeline = explode(",", rtrim($tree));
			// レス省略
			//レス作成
			$thread=[];
			foreach($treeline as $k => $disptree){
				if(!isset($lineindex[$disptree])) continue;
				$j=$lineindex[$disptree];

				$no=$i+1;

				list($_no,$date,$name,$email,$sub,$com,$url,$host,$hash,$ext,$w,$h,$_time,$img_md5,$_ptime,,$pchext,$thumbnail,$painttime)
				=explode(",",rtrim($line[$j]));

				list($userid, $now) = separateDatetimeAndId($date);
				//日付と編集マークを分離
				list($now, $updatemark) = separateDatetimeAndUpdatemark($now);
				//名前とトリップを分離
				list($name, $trip) = separateNameAndTrip($name);

				$time=substr($_time,-13);

				if($ext && is_file(IMG_DIR."{$_time}{$ext}")){//画像
					copy("src/{$_time}{$ext}","petit/src/{$time}{$ext}");
					chmod("petit/src/{$time}{$ext}",0606);
				}
				if($ext && is_file(THUMB_DIR."{$_time}s.jpg")){//画像
					$thumbnail='thumbnail';
					copy("src/{$_time}s.jpg","petit/src/{$time}s.jpg");
					chmod("petit/thumbnail/{$time}s.jpg",0606);
				}
				
				if($pchext && is_file(PCH_DIR."{$_time}{$pchext}")){//動画
					copy(PCH_DIR."{$_time}{$pchext}","petit/src/{$time}{$pchext}");
					chmod("petit/src/{$time}{$pchext}",0606);
				}
	
				$imgfile=$ext ? $imgfile=$time.$ext:'';


				$com=str_replace('<br />','"\n"',$com);

				$tool='';
				
				if($pchext==='.spch'){
					$tool='しぃペインター';
					}
					elseif($pchext==='.pch'){
					$tool='PaintBBS NEO';
					}
					elseif($pchext==='.chi'){
					$tool='ChickenPaint';
					}
					elseif($ext){
					$tool='???';
					}

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
	$msg['001']=defined('MSG041') ? MSG041 :($en ? ' does not exist.':'がありません。'); 
	$msg['002']=defined('MSG042') ? MSG042 :($en ? ' is not readable.':'を読めません。'); 
	$msg['003']=defined('MSG043') ? MSG043 :($en ? ' is not writable.':'を書けません。'); 
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
 * 日付と編集マークを分離
 * @param $date
 * @return array
 */
function separateDatetimeAndUpdatemark ($date) {
	if (UPDATE_MARK && strpos($date, UPDATE_MARK) !== false){
		return [str_replace(UPDATE_MARK,"",$date), UPDATE_MARK];
	}
	return [$date, ''];
}

