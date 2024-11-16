<?php
//全体ログを個別スレッドのログをもとに修復
//作成されるログファイルの名前はnew_alllog.log
$logfiles_arr=[];
$logfiles_arr =(glob('./log/*.log', GLOB_BRACE));//ログファイルをglob

	$logs=[];
	foreach($logfiles_arr as $i=>$logfile){//ログファイルを一つずつ開いて読み込む
	if(strpos($logfile,'alllog')===false){
		$fp=fopen($logfile,"r");
		$line =fgets($fp);
		if(trim($line)){

			list($no,$sub,$name,$verified,$com,$url,$imgfile,$w,$h,$thumbnail,$painttime,$img_md5,$tool,$pchext,$time,$first_posted_time,$host,$userid,$hash,$oya)=explode("\t",trim($line));
			if($oya==='oya'){
				$strcut_com=mb_strcut($com,0,120);
				$newline = "$no\t$sub\t$name\t$verified\t$strcut_com\t$url\t$imgfile\t$w\t$h\t$thumbnail\t$painttime\t$img_md5\t$tool\t$pchext\t$time\t$first_posted_time\t$host\t$userid\t$hash\toya\n";
				$logs[$no]=$newline;//1スレッド分
			}
		}
		fclose($fp);
	}
	}
	krsort($logs);
	$logs=array_values($logs);
file_put_contents('./log/new_alllog.log',implode("",$logs));
chmod('./log/new_alllog.log',0600);	

echo '再構築が完了しました。<br>new_alllog.logに再構築したログファイルを出力しました。';
