<?php
/**
* class for check product status
* for pause and activte Adwords ad's
*/
class Diogo_Adwords_Model_Observer 
{
	private $prouducts_ids = array();
	private $date_time_filter;

	/**
	*	check product status
	*/

	public function __construct(){

		if(!Mage::getStoreConfig('google/diogo_adwords/active')){
			return;
		}
		
		$today = new DateTime();
		$yesterday = new DateTime();
		$yesterday->sub(new DateInterval('P1D'));
		
		$this->date_time_filter = array(
			'from' =>  $yesterday->format('Y-m-d H:i:s'),
			'to'   =>  $today->format('Y-m-d H:i:s'),
			'datetime' => true
		);
	}

	public function checkProductStock(){
		$this->pushToProductsIdsFromCollection('sales/order_item', 'product_id', 'getProductId', array('product_type' => 'simple'));
		$this->pushToProductsIdsFromCollection('catalog/product', 'entity_id', 'getId');
			
		foreach ($this->prouducts_ids as $prouduct_id) {
			$product = Mage::getModel('catalog/product')->load($prouduct_id);
			$this->changeAdStatus($product);
		}
	}

	public function pushToProductsIdsFromCollection($magento_model, $attributte_to_select, $getMethod, array $addAttributeToFilter = null){
		// construct the collection objetc
		$collection = Mage::getModel($magento_model)->getCollection()
			->distinct(true)
			->addAttributeToSelect($attributte_to_select)
			->addAttributeToFilter('updated_at', $this->date_time_filter);
		
		if (!empty($addAttributeToFilter)) {
			foreach ($addAttributeToFilter as $key => $filter) {
				$collection->addAttributeToFilter($key, $filter);
			}	
		}

		//push to $this->products_ids
		foreach ($collection as $item) {
			$this->prouducts_ids[$item->$getMethod()] = $item->$getMethod();
		}

	}

	/**
	* change Ad status active|paused
	* @param Mage_Catalog_Model_Product $product
	*/
	public function changeAdStatus(Mage_Catalog_Model_Product $product){
		$ads  = array();
		$adwords = Mage::getModel('diogo_adwords/adwords');
		$ads = $adwords->setProduct($product)->find();
		
		if(!empty($ads)){		
			foreach ($ads as $ad) {		
				if($product->isSalable()){
					
					if($ad->status == $adwords::PAUSED){					
						$adwords->log(print_r("ACTIVE this ad product: {$product->getSku()}", true));
						$adwords->enableAd($ad);
					} else {
						$adwords->log(print_r("Ad is already active! product: {$product->getSku()}", true));
					}

				} else {
					$adwords->log(print_r("PAUSE this ad product: {$product->getSku()}", true));
					$adwords->pauseAd($ad);
				}
			}
		} else {
			$adwords->log(print_r("there isn't ads for this product: {$product->getSku()}", true));
		}

	}
}