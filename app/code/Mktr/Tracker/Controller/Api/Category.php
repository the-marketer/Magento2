<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Newsletter\Model\Subscriber;
use Mktr\Tracker\Helper\Data;
use Mktr\Tracker\Model\Array2XML;
use Mktr\Tracker\Model\MktrApi;
use Mktr\Tracker\Model\MktrHelp;
use mysql_xdevapi\Exception;

class Category extends Action
{
    // private static $cons = null;
    private static $ins = [
        "Help" => null
    ];

    private static $error = null;
    private static $fileName = "categories";
    private static $secondName = "category";

    private static $data;
    private static $url;

    public function __construct(Context $context, Data $help) {
        parent::__construct($context);
        self::$ins['Help'] = $help;
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    private static function status()
    {
        return self::$error == null;
    }

    /** @noinspection PhpUnused */
    public function execute()
    {
        self::$error =  self::getHelp()->getFunc->isParamValid([
            'key' => 'Required|Key'
        ]);

        if ($this->status())
        {
            return self::getHelp()->getFunc->readOrWrite(self::$fileName, self::$secondName, $this);
        }

        return self::getHelp()->getFunc->Output('status', self::$error);
    }

    public static function hierarchy($category)
    {
        $breadcrumb = [ $category->getName() ];

        while ($category->getLevel() > 2) {
            $category = self::getHelp()->getCategoryRepo->load($category->getParentId());
            $breadcrumb[] = $category->getName();
        }
        $breadcrumb = array_reverse($breadcrumb);
        return implode("|", $breadcrumb);
    }

    public static function build($category){

        $newList = array(
            "name" => $category->getName(),
            "url" => self::$url. $category->getUrlPath().'.html',
            'id'=> $category->getId(),
            "hierarchy" => self::hierarchy($category),
            "image_url" => $category->getImageUrl()
        );

        if (empty($newList["image_url"]))
        {
            unset($newList["image_url"]);
        }

        self::$data[] = $newList;
    }

    public static function freshData(): array
    {
        $categories = self::getHelp()->getCategoriesData->getStoreCategories(false,true,true);
        self::$data = array();
        self::$url = self::getHelp()->getBaseUrl;
        foreach ($categories as $category) {
            $cat = self::getHelp()->getCategoryRepo->load($category->getId());
            self::build($cat);
        }

        return self::$data;
    }
}
