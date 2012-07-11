<?php
/*
 * Model for CakePHP ( cakephp.org ) web framework that gathers data from database,
 * example of usage of code to extend usefulnes of model in framework, 
 * and do some heavy lifting with data before it is passed to controller.
 * 
 * Old data is retrieved from mysql database, and fresh data is loaded from 
 * redis database, and merged together.
 */

class DailyValue extends AppModel {

	var $name = 'DailyValue';

	var $actsAs = array('Containable');
	var $useDbConfig = 'data';
	
	//The Associations below have been created with all possible keys, those that are not needed can be removed
	var $belongsTo = array(
		'Check' => array(
			'className' => 'Check',
			'foreignKey' => 'check_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'Group' => array(
			'className' => 'Group',
			'foreignKey' => 'group_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);
	
	function getLastXDays($timezone,$checkId,$group,$duration,$interval,$clusterSize)
	{
		$data = $this->find('all',array(
			'contain'=>array(),
			'fields'=>array($duration.'-DATEDIFF(CURDATE(),day) AS slot','DATE_FORMAT(day,\'%W\') AS title','values','problems','duration'),
			'conditions'=>array('check_id'=>$checkId,'group_id'=>$group,'day > DATE_SUB(CURDATE(), INTERVAL '.$duration.' DAY )'),
			'order'=>array('day')
		));
		App::Import('Vendor','Rediska',array('file'=>'Rediska.php'));
		$rediska = new Rediska(Configure::read('redis-chart'));
		$border = $this->getBorder($timezone);
		$newData = Array();
		$list = new Rediska_Key_List($checkId."-".$group);
		foreach($list->toArray(0,-1) as $element) {
			$temp = explode(":",$element);
			if($temp[2]>$border)
			{
				array_push($newData,$element);
			}
		}
		$newData = $this->reduceVals($newData,$border,$interval,$clusterSize);
		$incident = ClassRegistry::init('Incident');
		$newProblems = $incident->generateIncidentsForWeekly($checkId,$group,$border);
		array_push($data,array('0'=>array('title'=>$this->getTodayWeek($timezone),'slot'=>$duration),'DailyValue'=>array('values'=>implode(',',$newData),'problems'=>implode(':',$newProblems['problems']),'duration'=>$newProblems['duration'])));
		$output = array('type'=>'week','normal_result'=>array(),'red_results'=>array());
		$i = 1;
		foreach($data AS $element)
		{
			while($i!=$element['0']['slot']){
				array_push($output['normal_result'],$this->getEmptyNormal($duration,$i,$timezone));
				array_push($output['red_results'],$this->getEmptyProblems($duration,$i));
				$i++;
			}
			array_push($output['normal_result'],
				array(	'title'=>$element['0']['title'],
					'slot'=>$element['0']['slot'],
					'values'=>explode(",",$element['DailyValue']['values'])
				)
			);
			$problems = explode(":",$element['DailyValue']['problems']);
			$tempProblems = array();
			foreach($problems AS $problem)
			{
				$temp = explode(",",$problem);
				if(count($temp)==2)array_push($tempProblems,array('start'=>$temp[0],'end'=>$temp[1]));
			}
			array_push($output['red_results'],
				array(	'slot'=>$element['0']['slot'],
					'problems'=>$tempProblems,
					'duration'=>formatSecs($element['DailyValue']['duration'],true)
				)
			);
			$i++;
		}
		return $output;
	}
	
	function getEmptyNormal($duration,$i,$timezone)
	{
		return array('title'=>$this->getTodayWeek($timezone,$duration-$i),'slot'=>$i,'values'=>array_fill(0,DAILY_LIMIT,-1));
	}
	
	function getEmptyProblems($duration,$i)
	{
		return array('slot'=>$i,'problems'=>array(),'duration'=>null);
	}
	
	function getBorder($timezone)
	{
		$date = new DateTime(null,$timezone);
		$date->setTime(0,0,0);
		return $date->format('U');
	}
	
	function getTodayWeek($timezone,$offset = 0)
	{
		$date = new DateTime(null,$timezone);
		if($offset>0)
		{
			$date->sub(new DateInterval("P".$offset."D"));
		}
		return $date->format('l');
	}
	
	function reduceVals($newData,$border,$interval,$clusterSize)
	{
		$tempList = Array();
		$results = Array();
		$mode = 1;
		
		while((24*3600 / (DAILY_LIMIT / $mode )) < (60*$interval*$clusterSize))
		{
			$mode = $mode*2;
		}
		
		$size = 24*3600 / (DAILY_LIMIT / $mode);
		
		foreach($newData AS $element)
		{
			$segment = explode(":",$element);
			$slot = floor(($segment[2] - $border) /  $size);
			$oldVals = array(0,0);
			if(isset($tempList[$slot]))
			{
				$oldVals = explode(":",$tempList[$slot]);
			}
			if($segment[0]==1)
			{
				$tempList[$slot] = (1+$oldVals[0]).":".($segment[1]+$oldVals[1]);
				$results[$slot] = round(($segment[1]+$oldVals[1])/(1+$oldVals[0]),6);
			}
			else
			{
				$tempList[$slot] = $oldVals[0].":".$oldVals[1];
				$results[$slot] = ($oldVals[0]==0)?0:round(($oldVals[1]/$oldVals[0]),6);
			}
		}
		
		for($i = 0; $i<(DAILY_LIMIT/$mode);$i++)
		{
			if(!isset($results[$i]))
			{
				$results[$i] = -1;
			}
		}
		if($mode>1)
		{
			$results = inflate_results($mode,$results);
		}
		
		return $results;
	}
	
	function inflate_results($mode,$results)
	{
		$new = Array();
		
		for($i = 0;$i<(DAILY_LIMIT/$mode);$i++)
		{
			for($k= $i*$mode ; $k < (($i+1)*$mode);$k++)
			{
				if($k==($i+1)*$mode-1){$new[$k] = $old[$i];}
				else{$new[$k]=0;}
			}
		}
		return $new;
	}
}
?>