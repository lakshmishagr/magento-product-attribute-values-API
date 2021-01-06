<?php
namespace MyModules\SearchProduct\Model;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use MyModules\SearchProduct\Api\ProductsInterface;
class Products implements ProductsInterface
{
   /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var Product[]
     */
    protected $instances = [];

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product
     */
    protected $resourceModel;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $helperFactory;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * Review model
     *
     * @var \Magento\Review\Model\ReviewFactory
     */
    protected $_reviewFactory;

     /**
     * Review resource model
     *
     * @var \Magento\Review\Model\ResourceModel\Review\CollectionFactory
     */
    protected $_reviewsColFactory;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * ProductRepository constructor.
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product $resourceModel
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param  \Magento\Review\Model\ReviewFactory $reviewFactory
     * @param  \Magento\Review\Model\ResourceModel\Review\CollectionFactory $collectionFactory
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\ResourceModel\Product $resourceModel,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Catalog\Helper\ImageFactory $helperFactory,
        \Magento\Review\Model\ReviewFactory $reviewFactory,
        \Magento\Review\Model\ResourceModel\Review\CollectionFactory $collectionFactory,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->productFactory       =  $productFactory;
        $this->storeManager         =  $storeManager;
        $this->resourceModel        =  $resourceModel;
        $this->helperFactory        =  $helperFactory;
        $this->appEmulation         =  $appEmulation;
        $this->_reviewFactory       =  $reviewFactory;
        $this->_reviewsColFactory   =  $collectionFactory;
        $this->priceCurrency        =  $priceCurrency;

    }


    /**
     * {@inheritdoc}
     */
    public function getAdditional($sku, $editMode = false, $storeId = null, $forceReload = false)
    {
        $cacheKey = $this->getCacheKey([$editMode, $storeId]);
        if (!isset($this->instances[$sku][$cacheKey]) || $forceReload) {
            $product = $this->productFactory->create();
      
	  //  $productId = $this->resourceModel->loadByAttribute('url_key',$sku)->getId();
           $productId = $this->resourceModel->getIdBySku($sku);

	   // if (!$productId) {
           //     $productId = $this->resourceModel->getIdByUrlKey($sku, true);
            //}

           // if (!$productId) {
             //   $productId = $this->resourceModel->getIdBySku($sku);
	    //}

            if (!$productId) {

                throw new NoSuchEntityException(__('Requested product doesn\'t exist'));
            }
            if ($editMode) {
                $product->setData('_edit_mode', true);
            }
            if ($storeId !== null) {
                $product->setData('store_id', $storeId);
            } else {

                $storeId = $this->storeManager->getStore()->getId();
            }
            $product->load($productId);

            //Custom Attributes Data Added here            
            $moreInformation = $this->getMoreInformation($product);                     
            $product->setCustomAttribute('brand', $moreInformation);
            // Custom Attributes Data Ends here
            $this->instances[$sku][$cacheKey] = $product;
            $this->instancesById[$product->getId()][$cacheKey] = $product;          
        }

        return $this->instancesById[$product->getId()][$cacheKey];      

   }

    /**
     * Get key for cache
     *
     * @param array $data
     * @return string
     */

    protected function getCacheKey($data)
    {
        $serializeData = [];
        foreach ($data as $key => $value) {         

            if (is_object($value)) {
                $serializeData[$key] = $value->getId();             
            } else {                
                $serializeData[$key] = $value;              
            }
        }       
        return md5(serialize($serializeData));
    }




    /**
     * Get More information of the product
     * @param \Magento\Catalog\Model\Product $product
     * @return array
    */

    protected function getMoreInformation($product)
    {
        $data = [];
        $excludeAttr = [];
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getIsVisibleOnFront() && !in_array($attribute->getAttributeCode(), $excludeAttr)) {
                $value = $attribute->getFrontend()->getValue($product);

                if (!$product->hasData($attribute->getAttributeCode())) {
                    $value = __('N/A');
                } elseif (is_string($value) && $value == '') {
                    $value = __('No');
                } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                    $value = $this->priceCurrency->convertAndFormat($value);
                }

                if (is_string($value) && strlen($value)) {
                    $data[$attribute->getAttributeCode()] = [
                        'label' => __($attribute->getStoreLabel()),
                        'value' => $value,
                        'code' => $attribute->getAttributeCode(),
                    ];
                }
            }
        }

        return $data;
    }



}
