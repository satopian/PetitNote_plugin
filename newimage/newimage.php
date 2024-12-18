<?php
// Petit Noteの最新画像をサイトの入り口のHTMLファイルに呼び出すphp
// newimg.php(c)さとぴあ(satopian) 2020-2023 lot.231020
// Licence MIT
//---------------- 設定 ----------------

// デフォルト画像。
// 画像がない時または閲覧注意画像の時に代わりに表示する画像を指定。
$default='./thumbnail/ogimage.png';
//例
// $default='https://example.com/bbs/image.png';
//設定しないなら初期値の
// $default='';
//で。

//--------- 説明と設定ここまで ---------

$arr=[];

	$i=0;

	$fp=fopen('./log/'."alllog.log","r");
	while ($log = fgets($fp)) {

		list($resno)=explode("\t",$log);
		$resno=basename($resno);
		if(is_file('./log/'."{$resno}.log")){
		$cp=fopen('./log/'."{$resno}.log","r");
			while($line=fgets($cp)){
				
				list($no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$log_md5,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya)=explode("\t",$line);
				$imgfile=basename($imgfile);
				$time=basename($time);
				if ($imgfile){
					if(strpos($thumbnail,'thumbnail_webp')!==false){
						$arr[$time]='thumbnail/'.$time.'s.webp';
					}elseif(strpos($thumbnail,'thumbnail')!==false){
						$arr[$time]='thumbnail/'.$time.'s.jpg';
					}else{
						$arr[$time]='src/'.$imgfile;
					}
					if(strpos($thumbnail,'hide_')!==false){
						$arr[$time]=$default ? $default :$arr[$time];
					}
				}
			}
			fclose($cp);	
		}
		++$i;
		if($i>=3){break;}
	}
	fclose($fp);

if($arr){
krsort($arr);//連想配列をキーでソート
$arr=array_values($arr);
$filename=$arr[0];
}else{
	$filename=$default;//デフォルト画像を表示
}
//画像を出力
$img_type=mime_content_type($filename);
if (!in_array($img_type, ['image/gif', 'image/jpeg', 'image/png','image/webp'])) {
	return;
}

header('Content-Type: '.$img_type);
readfile($filename);

