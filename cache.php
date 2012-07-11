<?php ?

/*
 * Example of use of custom cache to speed up data gathering from database
 * 
 * Log messages are kept in cache because they don't change often so every
 * incident can just lookup from cache to sort itself up,
 * 
 * If no log messages are available from cache, they are generated again,
 * and then written to cache
 */

function _adjustMessages($incidents)
	{
		//Cache::delete("log_messages");
		if(($logMessages = Cache::read("log_messages")) === false )
		{
			$logMessages = $this->_getLogMessages();
			Cache::set(array('duration' => '+1 hours'));
			Cache::write("log_messages",$logMessages);
		}
		foreach($incidents AS $key=>$incident)
		{
			$data = array();
			$msgs = explode(",",$incident['Incident']['msg']);
			foreach($msgs AS $msg)
			{
				$parts = explode("-",$msg);
				if(count($parts)>1)
				{
					$type = array_shift($parts);
					$append = "";
					if(strcmp($type,"KWE")==0)
					{
						$this->loadModel("Keyword");
						$append= $this->Keyword->getDescFromIds($parts);
					}
					array_push($data,array('id'=>$type,'header'=>$logMessages[$type]['header'],'main'=>$logMessages[$type]['main'],'desc'=>$logMessages[$type]['desc'].$append));
				}
				else
				{
					array_push($data,array('id'=>$msg,'header'=>$logMessages[$msg]['header'],'main'=>$logMessages[$msg]['main'],'desc'=>$logMessages[$msg]['desc']));
				}
			}
			$incidents[$key]['LogMessages']['data'] = $data;
			if(count($msgs)>1)
			{
				$incidents[$key]['LogMessages']['title'] = "Multiple problems";
			}
			else
			{
				$parts = explode("-",$incident['Incident']['msg']);
				$incidents[$key]['LogMessages']['title'] = $logMessages[$parts[0]]['header'];
			}
			
		}
		return $incidents;
	}
?>