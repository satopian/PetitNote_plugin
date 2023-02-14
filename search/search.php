<?php
$max_search=180;

//設定の読み込み
require(__DIR__.'/config.php');
const JQUERY ='jquery-3.6.0.min.js';
$en= lang_en();

if($err=check_file(__DIR__.'/lib/'.JQUERY)){
	die($err);
}
$jquery=JQUERY;

//HTMLテンプレート

$skindir = 'template/'.$skindir;

//filter_input

$imgsearch=(bool)filter_input(INPUT_GET,'imgsearch',FILTER_VALIDATE_BOOLEAN);
$page=(int)filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT);
$page= $page ? $page : 1;
$query=filter_input(INPUT_GET,'query');
$query=urldecode($query);
$query=mb_convert_kana($query, 'rn', 'UTF-8');
$query=str_replace(array(" ", "　"), "", $query);
$query=str_replace("〜","～",$query);//波ダッシュを全角チルダに
$query=h($query);
$radio =filter_input(INPUT_GET,'radio',FILTER_VALIDATE_INT);

if($imgsearch){
	$disp_count_of_page=30;//画像検索の時の1ページあたりの表示件数
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
				if($query===''||//空白なら
						$query!==''&&$radio===3&&stripos($s_com,$query)!==false||//本文を検索
						$query!==''&&$radio===3&&stripos($s_sub,$query)!==false||//題名を検索
						$query!==''&&($radio===1||$radio===null)&&stripos($s_name,$query)===0||//作者名が含まれる
						$query!==''&&($radio===2&&$s_name===$query)//作者名完全一致
				){
					$link='';
					$link='./?res='.$no.'#'.$time;
					$arr[]=[$no,$name,$sub,$com,$imgfile,$w,$h,$time,$link];
					++$i;
				}
					if($i>=$max_search){break;}//1掲示板あたりの最大検索数
				
			}

			if($j>=5000){break;}//1掲示板あたりの最大行数
			++$j;

		}
		fclose($cp);
	}
}
fclose($fp);


//検索結果の出力
$j=0;
$comments=[];
if($arr){
	foreach($arr as $i => $val){
		if($i > $page-2){//$iが表示するページになるまで待つ
			list($no,$name,$sub,$com,$imgfile,$w,$h,$time,$link)=$val;
			$img='';
			if($imgfile){
				if(is_file(THUMB_DIR.$time.'s.jpg')){//サムネイルはあるか？
					$img=THUMB_DIR.$time.'s.jpg';
				}
				elseif($imgsearch||is_file(IMG_DIR.$imgfile)){
					$img=IMG_DIR.$imgfile;
					}
				}

			$time=(strlen($time)>15) ? substr($time,0,-6) : substr($time,0,-3);

			$postedtime =$time ? (date("Y/m/d G:i", (int)$time)) : '';
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
			$comments[]= compact('no','name','encoded_name','sub','img','w','h','com','link','postedtime');

		}
			$j=$i+1;//表示件数
			if($i >= $page+$disp_count_of_page-2){break;}
	}
}
unset($sub,$name,$no);
unset($i,$val);

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

//クエリを検索窓に入ったままにする
$query=h($query);
//ラジオボタンのチェック
$radio_chk1=false;//作者名
$radio_chk2=false;//完全一致
$radio_chk3=false;//本文題名	
$query_l='&query='.urlencode(h($query));//クエリを次ページにgetで渡す
if($query!==''&&$radio===3){//本文題名
	$query_l.='&radio=3';
	$radio_chk3=true;
}
elseif($query!==''&&$radio===2){//完全一致
	$query_l.='&radio=2';
	$radio_chk2=true;	
}
elseif($query!==''&&($radio===null||$radio===1)){//作者名
	$query_l.='&radio=1';
	$radio_chk1=true;
}
else{//作者名	
	$query_l='';
	$radio_chk1=true;
}

$page=(int)$page;

$pageno='';
if($j&&$page>=2){
	$pageno = $page.'-'.$j.$mai_or_ken;
}
elseif($j){
		$pageno = $j.$mai_or_ken;
}
if($query!==''&&$radio===3){
	$title=$query.($en ? "'s" : "の").$img_or_com;//titleタグに入る
	$h1=$query.($en ? "'s ".$img_or_com : "の");//h1タグに入る
}
elseif($query!==''){
	$title=$en ? 'posts by '.$query :$query.'さんの'.$img_or_com;
	$h1=$en ? 'posts by '.$query : $query.'さんの';
}
else{
	$title=$en ? 'new posts ' : $boardname.'に投稿された最新の'.$img_or_com;
	$h1=$en ? 'new posts ' : $boardname.'に投稿された最新の';
}


//ページング

$nextpage=$page+$disp_count_of_page;//次ページ
$prevpage=$page-$disp_count_of_page;//前のページ
$countarr=count($arr);//配列の数
$prev=false;
$next=false;
$next_txt='';
$prev_txt='';
if($page<=$disp_count_of_page){
	$prev_txt=$en ? "Return to bulletin board" : "掲示板にもどる";
	$prev="./";//前のページ
if($countarr>=$nextpage){
	$next_txt=($en ? "next " :"次の").h($disp_count_of_page.$mai_or_ken).'≫';//次のページ
	$next=$nextpage.$search_type.$query_l;//次のページ
}
}

elseif($page>=$disp_count_of_page+1){
	$prev_txt= "≪".($en ? "prev " : "前の").$disp_count_of_page.$mai_or_ken; 
	$prev= $prevpage.$search_type.$query_l; 
	if($countarr>=$nextpage){
		$next_txt=($en ? "next " :"次の").$disp_count_of_page.$mai_or_ken."≫";
		$next=h($nextpage.$search_type.$query_l);
	}
	else{
		$prev_txt=$en ? "Return to bulletin board" : "掲示板にもどる";
		$prev="./";
	}
}


//最終更新日時を取得
$postedtime='';
$lastmodified='';
if(!empty($arr)){
	list($no,$name,$sub,$com,$imgfile,$w,$h,$postedtime,$link)=$arr[0];

	$postedtime=(strlen($postedtime)>15) ? substr($postedtime,0,-6) : substr($postedtime,0,-3);
	$lastmodified=date("Y/m/d G:i", (int)$postedtime);
}

unset($arr);

function h($str){
	return htmlspecialchars($str,ENT_QUOTES,'utf-8',false);
}
function lang_en(){//言語が日本語以外ならtrue。
	$lang = ($http_langs = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
	? explode( ',', $http_langs )[0] : '';
  return (stripos($lang,'ja')!==0);
  
}
function initial_error_message(){
	$en=lang_en();
	$msg['001']=$en ? ' does not exist.':'がありません。'; 
	$msg['002']=$en ? ' is not readable.':'を読めません。'; 
	$msg['003']=$en ? ' is not writable.':'に書けません。'; 
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
	return '';
}

//HTML出力
$templete='search.html';
return include __DIR__.'/'.$skindir.$templete;


