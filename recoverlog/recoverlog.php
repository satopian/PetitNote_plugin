<?php
//全体ログを個別スレッドのログをもとに修復
//作成されるログファイルの名前はnew_alllog.log
$logfiles_arr =(glob('./log/*.log', GLOB_BRACE));//ログファイルをglob

	$arr_logs=[];
	$logs=[];
	foreach($logfiles_arr as $i=>$logfile){//ログファイルを一つずつ開いて読み込む
	if(strpos($logfile,'alllog')===false){
		$fp=fopen($logfile,"r");
		$line =fgets($fp);
		if(trim($line)){
			list($r_no,$oyasub,$n_,$v_,$c_,$u_,$img_,$_,$_,$thumb_,$pt_,$md5_,$to_,$pch_,$postedtime,$fp_time_,$h_,$uid_,$h_,$r_oya)=explode("\t",trim($line));
			if($r_oya==='oya'){
				$logs[$r_no]=$line;//1スレッド分
			}
		}
		fclose($fp);
	}
	}
	krsort($logs);
	$logs=array_values($logs);
file_put_contents('./log/new_alllog.log',$logs);
chmod('./log/new_alllog.log',0600);	

echo '再構築が完了しました。<br>new_alllog.logに再構築したログファイルを出力しました。';
