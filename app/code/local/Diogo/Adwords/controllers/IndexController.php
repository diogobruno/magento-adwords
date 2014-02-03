<?php

class Diogo_Adwords_IndexController extends Mage_Core_Controller_Front_Action 
{
	
	public function indexAction(){
		$observer = Mage::getModel('diogo_adwords/observer');
		$observer->checkProductStock();
	}

	public function helloAction(){
		$adwords = Mage::getModel('diogo_adwords/adwords');
		echo "<pre>";print_r($adwords->hello());
	}
}