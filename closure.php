<?php 
/*
 * Example of usage of annonymus functions in php 5.3, usefull for piping data 
 * from redis database.
 * Function then gets stats from array of data from redis database
 * 
*/

function getCurrentDayStatsAll($checks,$group,$tz,$td=false)
{
	App::Import('Vendor','Predis\Client',array('file'=>'predis.php'));
	$redis = new Predis\Client($td?Configure::read('predis-temp'):Configure::read('predis-stats'));
	
	$day = $this->getDayForDatabase($tz);
	$replies = $redis->pipeline(function($pipe) use ($checks,$td,$day) {
		foreach($checks AS $check)
		{
			if($td)$pipe->hmget($check['Check']['id']."-1-".$day,array("avg","up","total","idur","itotal"));
			else $pipe->hmget($check['Check']['id']."-1",array("avg","up","total","idur","itotal"));
		}
	});
	
	$bigTotal = 0;
	$bigUp = 0;
	$sum = 0;
	$iCount = 0;
	$iDur = 0;
	$count = 0;
	foreach($replies AS $reply)
	{
		$sum += $reply[0];
		$bigUp += $reply[1];
		$bigTotal += $reply[2];
		$iDur += $reply[3];
		$iCount += $reply[4];
		$count++;
	}
	if($count==0)$count=1;
	
	if($td)
	{
		return array('avg'=>round($sum/$count,0),'up'=>$bigUp,'total'=>$bigTotal,'idur'=>$iDur,'itotal'=>$iCount);
	}
	
	$stats = array();
	if($bigTotal==0)
	{
		$stats = generateNullStats();
	}
	else
	{
		$stats = array(
			'uptime'=>round(($bigTotal*60-$iDur)*100/($bigTotal*60),3)."%",
			'average'=>round($sum/$count,0)."ms",
			'downtime'=>formatSecs($iDur),
			'incidents'=>$iCount
		);
	}
	
	return $stats;
}

?>