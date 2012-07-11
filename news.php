<?php
/*
 * News model in cakephp version 2.1, it uses php 5.3
 * 
 * model is extended to provide useful functions for retrieving news to various 
 * parts of website, like dashboard, or news section itself
 * 
 * It showcases idiomatic usage of cake framework
 * */


App::uses('AppModel', 'Model');
/**
 * News Model
 *
 * @property Account $Account
 */
class News extends AppModel {
/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'title';

	public $actsAs = array('Containable');
	
	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'Account' => array(
			'className' => 'Account',
			'foreignKey' => 'account_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);
	
	public $virtualFields = array(
		'level_id' => 'level'
	);
	
	public function beforeSave()
	{
		$this->data['News']['level'] = $this->data['News']['level_id'];
		return true;
	}
	
	public function getDashboardList($accountId,$employeeId,$level)
	{
		$news = $this->find('all',array(
				'conditions'=>array(
					'account_id' => $accountId,
					'deleted' => 0,
					'level >=' => $level
				),
				'contain'=>array(),
				'limit'=>5,
				'order'=>array('modified'=>'desc')
			)
		);
		if(count($news)>0)return $news;
		else return null;
	}
	
	public function getConditions($accountId)
	{
		return array('account_id'=>$accountId);
	}
	
	public function getAdminList($data)
	{
		$return = array(
			'grid' => "grid740",
			'name' => Inflector::pluralize($this->name),
			'id' => Inflector::pluralize($this->name)."Id",
			'modelName' => $this->name,
			'columns' => array(
				array('header'=>'Title','model'=>'News','name'=>'title','link'=>false),
				array('header'=>'Level','model'=>'News','name'=>'level','link'=>false),
				array('header'=>'Date','model'=>'News','name'=>'created','link'=>false),
				),
			'data' => $data
		);
		return $return;
	}
}
