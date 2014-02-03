<?php
/**
*	class to manipulate Adwords
* 	accounts
*/

define('SRC_PATH', Mage::getBaseDir('lib') . '/AdWordsApi/src/');
define('LIB_PATH', 'Google/Api/Ads/AdWords/Lib');
define('UTIL_PATH', 'Google/Api/Ads/Common/Util');
define('ADWORDS_UTIL_PATH', 'Google/Api/Ads/AdWords/Util');
define('ADWORDS_VERSION', 'v201309');

// Configure include path
ini_set('include_path', implode(array(
    ini_get('include_path'), PATH_SEPARATOR, SRC_PATH
)));

// Include the AdWordsUser
require_once LIB_PATH . '/AdWordsUser.php';

class Diogo_Adwords_Model_Adwords extends Mage_Core_Model_Abstract
{

	// Possible Ad status
	const PAUSED = 'PAUSED';
	const ENABLED = 'ENABLED';
	
	private $product;
	private $user;
	private $ads = array();

	public function __construct(){
		try {
		// Get AdWordsUser from credentials in "../auth.ini"
			// relative to the AdWordsUser.php file's directory.
		  	$this->user = new AdWordsUser();
		} catch (Exception $e) {
			printf("An error has occurred when try to construct an AdWordsUser: %s\n", $e->getMessage());
		}
	}

	public function log($message){
		if(Mage::getStoreConfig('google/diogo_adwords/active_log')){
			$date = new DateTime();
			Mage::log($message, null, 'adwords-api-'. $date->format('Y-m-d') .'.log');
		}
	}

	/**
	* @param
	*/
	public function setProduct(Mage_Catalog_Model_Product $product){
		$this->product = $product;
		return $this;
	}

	/**
	* search an Ad by product URL
	* @return array of AdGroupAd Object the url matches with
	* some Ad Object
	*/
	public function find(){
		try {
			return $this->getTextAdsByUrl($this->product);
		} catch (Exception $e) {
			printf("An error has occurred: %s\n", $e->getMessage());
		}
	}

	/**
 	* @param AdWordsUser $user the user to run the example with
 	*/
	private function getTextAdsByUrl(Mage_Catalog_Model_Product $product) {
		// Get the service, which loads the required classes.
		$adGroupAdService = $this->user->GetService('AdGroupAdService', ADWORDS_VERSION);
		
		//LOG REQ AND RESPONSE
		//$this->user->LogAll();

	 	// Create selector.
	 	$selector = new Selector();
		$selector->fields = array('Headline', 'Id');
	 	$selector->ordering[] = new OrderBy('Headline', 'ASCENDING');

	 	//ONLY FO DEV
	 	$product_url = preg_replace('/magento\.dv/', 'www.sitedospes.com.br', $product->getProductUrl());

	 	// Create predicates.
	  	$selector->predicates[] = new Predicate('Url', 'EQUALS', $product_url);
	  	// By default disabled ads aren't returned by the selector. To return them
	  	// include the DISABLED status in a predicate.
	  	$selector->predicates[] = new Predicate('Status', 'IN', array('ENABLED', 'PAUSED'));

	  	// Create paging controls.
	  	$selector->paging = new Paging(0, AdWordsConstants::RECOMMENDED_PAGE_SIZE);

		do {
	    	// Make the get request.
	    	$page = $adGroupAdService->get($selector);

	    	// Display results.
	    	if (isset($page->entries)) {
	      		foreach ($page->entries as $adGroupAd) {
	      			$this->ads[] = $adGroupAd;
	      		}
	    	}

	    	// Advance the paging index.
	    	$selector->paging->startIndex += AdWordsConstants::RECOMMENDED_PAGE_SIZE;
	  	} while ($page->totalNumEntries > $selector->paging->startIndex);

	  	return $this->ads;
	}

	public function enableAd(AdGroupAd $adGroupAd){
		try {
			$this->changeAdStatus($adGroupAd);
		} catch (Exception $e){
			printf("An error has occurred when try to enabled an Ad: %s\n", $e->getMessage());
		}
	}
	
	public function pauseAd(AdGroupAd $adGroupAd){
		try {
			$this->changeAdStatus($adGroupAd, self::PAUSED);
		} catch (Exception $e){
			printf("An error has occurred when try to oause an Ad: %s\n", $e->getMessage());
		}
	}

	private function changeAdStatus(AdGroupAd $adGroupAd, $status = self::ENABLED) {
  		// Get the service, which loads the required classes.
		$adGroupAdService = $this->user->GetService('AdGroupAdService', ADWORDS_VERSION);

		// Create ad using an existing ID. Use the base class Ad instead of TextAd to
	 	// avoid having to set ad-specific fields.
	  	/*$ad = new Ad();
	  	$ad->id = $adId;

		// Create ad group ad.
	  	$adGroupAd = new AdGroupAd();
	  	$adGroupAd->adGroupId = $adGroupId;
	  	$adGroupAd->ad = $ad;*/

	  	// Update the status.
	  	$adGroupAd->status = $status;

	  	// Create operation.
	  	$operation = new AdGroupAdOperation();
	  	$operation->operand = $adGroupAd;
	  	$operation->operator = 'SET';

	  	$operations = array($operation);

	  	// Make the mutate request.
	 	 $result = $adGroupAdService->mutate($operations);

	  	// Display result.
	  	$adGroupAd = $result->value[0];
	  	
	  	$this->log(
	  		"Adwords response: Ad has updated status '{$status}'.\n" . 
	  		print_r($adGroupAd, true)
	  	);
	}

}