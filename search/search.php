<?php
$max_search=300;

//設定の読み込み
require_once(__DIR__.'/config.php');

$lang = ($http_langs = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
  ? explode( ',', $http_langs )[0] : '';
$en= (stripos($lang,'ja')!==0);

$jquery='jquery-3.6.0.min.js';
check_file(__DIR__.'/lib/'.$jquery);

//HTMLテンプレート

$skindir = 'template/'.$skindir;

//filter_input

$imgsearch=(bool)filter_input(INPUT_GET,'imgsearch',FILTER_VALIDATE_BOOLEAN);
$page=(int)filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT);
$q=(string)filter_input(INPUT_GET,'q');
$q=urldecode($q);
$q=mb_convert_kana($q, 'rn', 'UTF-8');
$q=str_replace(array(" ", "　"), "", $q);
$q=str_replace("〜","～",$q);//波ダッシュを全角チルダに
$radio =filter_input(INPUT_GET,'radio',FILTER_VALIDATE_INT);

if($imgsearch){
	$disp_count_of_page=20;//画像検索の時の1ページあたりの表示件数
}
else{
	$disp_count_of_page=30;//通常検索の時の1ページあたりの表示件数
}

//ログの読み込み
$arr=[];
$i=0;
$j=0;
$fp=fopen("log/alllog.log","r");
while ($log = fgets($fp)) {

	list($resno)=explode("\t",$log);
	$resno=basename($resno);
		if(is_file("log/{$resno}.log")){
			$cp=fopen("log/{$resno}.log","r");
		while($line=fgets($cp)){

				list($no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$log_md5,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya)=explode("\t",$line);

			$continue_to_search=true;
			if($imgsearch){//画像検索の場合
				$continue_to_search=($imgfile&&is_file(IMG_DIR.$imgfile));//画像があったら
			}

			if($continue_to_search){
				if($radio===1||$radio===2||$radio===null){
					$s_name=mb_convert_kana($name, 'rn', 'UTF-8');//全角英数を半角に
					$s_name=str_replace(array(" ", "　"), "", $s_name);
					$s_name=str_replace("〜","～", $s_name);//波ダッシュを全角チルダに
				}
				else{
					$s_sub=mb_convert_kana($sub, 'rn', 'UTF-8');//全角英数を半角に
					$s_sub=str_replace(array(" ", "　"), "", $s_sub);
					$s_sub=str_replace("〜","～", $s_sub);//波ダッシュを全角チルダに
					$s_com=mb_convert_kana($com, 'rn', 'UTF-8');//全角英数を半角に
					$s_com=str_replace(array(" ", "　"), "", $s_com);
					$s_com=str_replace("〜","～", $s_com);//波ダッシュを全角チルダに
				}
				
				//ログとクエリを照合
				if($q===''||//空白なら
						$q!==''&&$radio===3&&stripos($s_com,$q)!==false||//本文を検索
						$q!==''&&$radio===3&&stripos($s_sub,$q)!==false||//題名を検索
						$q!==''&&($radio===1||$radio===null)&&stripos($s_name,$q)===0||//作者名が含まれる
						$q!==''&&($radio===2&&$s_name===$q)//作者名完全一致
				){
					$hidethumb = ($thumbnail==='hide_thumbnail'||$thumbnail==='hide_');

					$thumb= ($thumbnail==='hide_thumbnail'||$thumbnail==='thumbnail');

					$arr[]=[$no,$name,$sub,$com,$imgfile,$w,$h,$time,$hidethumb,$thumb];
					++$i;
					if($i>=$max_search){break 2;}//1掲示板あたりの最大検索数
				}
				
			}


		}
		fclose($cp);
	}
	if($j>=5000){break;}//1掲示板あたりの最大行数
	++$j;
}
fclose($fp);

//検索結果の出力
$j=0;
$comments=[];
if($arr){
	foreach($arr as $i => $val){
		if($i >= $page){//$iが表示するページになるまで待つ
			list($no,$name,$sub,$com,$imgfile,$w,$h,$time,$hidethumb,$thumb)=$val;
			$img='';
			if($imgfile){
				if($thumb&&is_file(THUMB_DIR.$time.'s.jpg')){//サムネイルはあるか？
					$img=THUMB_DIR.$time.'s.jpg';
				}
				elseif($imgsearch||is_file(IMG_DIR.$imgfile)){
					$img=IMG_DIR.$imgfile;
					}
				}

			$postedtime=(strlen($time)>15) ? substr($time,0,-6) : substr($time,0,-3);

			$postedtime =$postedtime ? (date("Y/m/d G:i", (int)$postedtime)) : '';
			$sub=h($sub);
			$com=str_replace('<br />',' ',$com);
			$com=str_replace('"\n"',' ',$com);
			// マークダウン
			$com= preg_replace("{\[([^\[\]\(\)]+?)\]\((https?://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)\)}","\\1",$com);
			$com=h(strip_tags($com));
			$com=mb_strcut($com,0,180);
			$name=h($name);
			$encoded_name=urlencode($name);
			//変数格納
			$comments[]= compact('no','name','encoded_name','sub','img','w','h','com','time','postedtime','hidethumb');

		}
			$j=$i+1;//表示件数
			if($i >= $page+$disp_count_of_page-1){break;}
	}
}
unset($sub,$name,$no);
unset($i,$val);
// var_dump($comments);

$search_type='';
if($imgsearch){
	$search_type='&imgsearch=on';
	$img_or_com=$en ? 'images' : 'イラスト';
	$mai_or_ken=$en ? ' ' : '枚';
}
else{
	$img_or_com=$en ? 'comments' : 'コメント';
	$mai_or_ken=$en ? ' ' : '件';
}
$imgsearch= $imgsearch ? true : false;

//ラジオボタンのチェック
$radio_chk1=false;//作者名
$radio_chk2=false;//完全一致
$radio_chk3=false;//本文題名	
if($q!==''&&$radio===3){//本文題名
	$radio_chk3=true;
}
elseif($q!==''&&$radio===2){//完全一致
	$radio_chk2=true;	
}
elseif($q!==''&&($radio===null||$radio===1)){//作者名
	$radio_chk1=true;
}
else{//作者名	
	$radio_chk1=true;
}

$page=(int)$page;

$pageno=0;
if($j&&$page>=2){
	$pageno = ($page+1).'-'.$j.$mai_or_ken;
}
else{
	$pageno = $j.$mai_or_ken;
}
if($q!==''&&$radio===3){
	$h1=($en ? $pageno.' '.$img_or_com.' of '.$q : $q."の".$img_or_com);//h1タグに入る
}
elseif($q!==''){
	$h1=$en ? 'Posts by '.$q : $q.'さんの';
}
else{
	$h1=$en ? 'Recent '.$pageno.' Posts' : $boardname.'に投稿された最新の';
	$pageno=$en ? '':$pageno;
}

$q_l=$q ? '&q='.h(urlencode($q)):'';//クエリを次ページにgetで渡す

$q=h($q);
//ページング

$nextpage=$page+$disp_count_of_page;//次ページ
$prevpage=$page-$disp_count_of_page;//前のページ
$countarr=count($arr);//配列の数
$prev=false;
$next=false;

//
$countarr=count($arr);//配列の数

//ページング
$pagedef=$disp_count_of_page;
$start_page=$page-$pagedef*8;
$end_page=$page+($pagedef*8);
if($page<$pagedef*17){
	$start_page=0;
	$end_page=$pagedef*17;
}
//prev next 
$next=(($page+$pagedef)<$countarr) ? $page+$pagedef : false;//ページ番号がmaxを超える時はnextのリンクを出さない
$prev=((int)$page<=0) ? false : ($page-$pagedef) ;//ページ番号が0の時はprevのリンクを出さない



//最終更新日時を取得
$postedtime='';
$lastmodified='';
if(!empty($arr)){
	list($no,$name,$sub,$com,$imgfile,$w,$h,$postedtime)=$arr[0];

	$postedtime=(strlen($postedtime)>15) ? substr($postedtime,0,-6) : substr($postedtime,0,-3);
	$lastmodified=date("Y/m/d G:i", (int)$postedtime);
}

unset($arr);


// ファイル存在チェック
function check_file ($path) {
	$msg=initial_error_message();

	if (!is_file($path)) return die(h($path) . $msg['001']);
	if (!is_readable($path)) return die(h($path) . $msg['002']);
}
function initial_error_message(){
	global $en;
	$msg['001']=$en ? ' does not exist.':'がありません。'; 
	$msg['002']=$en ? ' is not readable.':'を読めません。'; 
	$msg['003']=$en ? ' is not writable.':'を書けません。'; 
return $msg;	
}

//エスケープ
function h($str){
	if($str===0 || $str==='0'){
		return '0';
	}
	if(!$str){
		return '';
	}
	return htmlspecialchars($str,ENT_QUOTES,"utf-8",false);
}

//HTML出力
$templete='search.html';
return include __DIR__.'/'.$skindir.$templete;
