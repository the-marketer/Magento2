<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Mktr\Tracker\Helper\Data;

class Category extends Action
{
    /**
     * @var Data
     */
    private $helper;

    private $fileName = "categories";
    private $secondName = "category";

    /**
     * @var array
     */
    private $exportData = [];

    /**
     * @var string|null
     */
    private $exportUrl;

    /**
     * @var bool
     */
    private $rmExt = false;

    /**
     * @var string|null
     */
    private $imageLink;

    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->helper = $helper;
    }

    public function execute()
    {
        $error = $this->helper->getFunc->isParamValid([
            'key' => 'KeyAuth'
        ]);

        if ($error === null) {
            $params = $this->helper->getRequest->getParams();
            $this->rmExt = isset($params['rmExt']) && $params['rmExt'] == 1;
            return $this->helper->getFunc->readOrWrite($this->fileName, $this->secondName, $this);
        }

        return $this->helper->getFunc->Output('status', $error);
    }

    public function hierarchy($category)
    {
        $breadcrumb = [$category->getName()];

        while ($category->getLevel() > 2) {
            $category = $this->helper->getCategoryRepo->load($category->getParentId());
            $breadcrumb[] = $category->getName();
        }
        $breadcrumb = array_reverse($breadcrumb);
        return implode("|", $breadcrumb);
    }

    private function buildImageUrl($img): string
    {
        if ($img === null) {
            $img = '';
        }
        if ($this->imageLink === null) {
            $this->imageLink = $this->helper->getStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);

            if (substr($this->imageLink, -1) === '/') {
                $this->imageLink = substr($this->imageLink, 0, -1);
            }
        }

        return $this->imageLink . (substr($img, 0, 1) === '/' ? '' : '/') . $img;
    }

    public function build($category)
    {
        $newList = [
            "name" => $category->getName(),
            "url" => $this->rmExt === true
                ? $this->exportUrl . $category->getUrlPath()
                : $this->exportUrl . $category->getUrlPath() . '.html',
            'id' => $category->getId(),
            "hierarchy" => $this->hierarchy($category),
            "image_url" => $category->getImageUrl()
        ];

        if (empty($newList["image_url"])) {
            unset($newList["image_url"]);
        } else {
            $newList["image_url"] = $this->buildImageUrl($newList["image_url"]);
        }

        $this->exportData[] = $newList;
    }

    public function freshData(): array
    {
        $categories = $this->helper->getCategoriesData->getStoreCategories(false, true, true);
        $this->exportData = [];
        $this->exportUrl = $this->helper->getBaseUrl;
        $this->imageLink = null;
        foreach ($categories as $category) {
            $cat = $this->helper->getCategoryRepo->load($category->getId());
            $this->build($cat);
        }

        return $this->exportData;
    }
}
