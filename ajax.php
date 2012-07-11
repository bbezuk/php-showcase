<?php 

/*
 *	Example of ajax action in cakephp controller, this action returns
 * data in json format.
 *
 */

function chart_data()
	{
		Configure::write('debug', 2);
		
		if($this->RequestHandler->isAjax() && $this->Auth->user() && !empty($this->params['form']) && $this->Check->canAccess($this->params['form']['id'],$this->Session->read('Auth.User.account_id')) )
		{
			if($this->params['form']['type']==1)
			{
				$this->set('content',json_encode(array('status'=>'success','ret'=>$this->Check->getActiveChartData($this->Session->read('Auth.User.account_id'),$this->params))));
			}
			elseif($this->params['form']['type']==2)
			{
				$this->loadModel('Account');
				$this->loadModel('DailyStat');
				$tz = $this->Account->getTimezone($this->Session->read('Auth.User.account_id'));
				$return = $this->Check->getWeeklyChartData($tz,$this->params);
				$this->set('content',json_encode(array('status'=>'success','ret'=>array('results'=>$return,'stats'=>$this->DailyStat->getLastXDays($this->params['form']['id'],$this->params['form']['group'],7,$tz)))));
			}
		}
		else
		{
			$this->set('content',json_encode(array('status'=>'Error accessing data')));
		}
	}

?>