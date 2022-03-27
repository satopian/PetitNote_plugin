<?php
// Petit Noteの最新画像をサイトの入り口のHTMLファイルに呼び出すphp
// newimg.php(c)さとぴあ(satopian) 2020-2022 lot.220327
// Licence MIT
//---------------- 設定 ----------------

// 画像がない時に表示する画像を指定
$default='';
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
		if(is_file('./log/'."{$resno}.log")){
		$cp=fopen('./log/'."{$resno}.log","r");
			while($line=fgets($cp)){
				
				list($no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$log_md5,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya)=explode("\t",$line);

				if ($imgfile){
					if($thumbnail){
						$imgfile='thumbnail/'.$time.'s.jpg';
					}else{
						$arr[$time]='src/'.$imgfile;
					}
				}
			}
			++$i;
			if($i>=3){break;}
			fclose($cp);	
		}
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

switch ($img_type):
	case 'image/png':
		header('Content-Type: image/png');
		break;
	case 'image/jpeg':
		header('Content-Type: image/jpeg');
		break;
	case 'image/gif':
		header('Content-Type: image/gif');
		break;
	case 'image/webp':
		header('Content-Type: image/webp');
		break;
	default :
		header('Content-Type: image/png');
	endswitch;
		
readfile($filename);


