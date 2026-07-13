<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 */

namespace Mktr\Tracker\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Connect extends Field
{
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $url = $this->getUrl('mktr/connect/start', ['_current' => ['website', 'store']]);

        return $this->getLayout()->createBlock(Button::class)->setData(
            [
                'id' => 'mktr_connect_button',
                'label' => __('Connect to theMarketer'),
                'class' => 'action-primary',
                'onclick' => "setLocation('" . $this->escapeJsQuote($url) . "')",
            ]
        )->toHtml();
    }
}
