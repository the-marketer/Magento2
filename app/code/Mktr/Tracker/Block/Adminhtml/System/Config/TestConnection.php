<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 */

namespace Mktr\Tracker\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestConnection extends Field
{
    protected $_template = 'Mktr_Tracker::system/config/test_connection.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getButtonHtml()
    {
        return $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData([
            'id' => 'mktr_test_connection_button',
            'label' => __('Test connection'),
            'onclick' => "setLocation('{$this->getTestConnectionUrl()}')"
        ])->toHtml();
    }

    public function getTestConnectionUrl()
    {
        $params = ['_secure' => true];
        foreach (['website', 'store'] as $scopeParam) {
            $scopeValue = $this->getRequest()->getParam($scopeParam);
            if ($scopeValue !== null) {
                $params[$scopeParam] = $scopeValue;
            }
        }

        return $this->getUrl('mktr/config/testconnection', $params);
    }
}
