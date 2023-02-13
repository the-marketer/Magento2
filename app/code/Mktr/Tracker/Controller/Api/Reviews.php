<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/
namespace Mktr\Tracker\Controller\Api;

use Magento\Framework\App\Action\Action;
use Mktr\Tracker\Model\MktrAction;
use Mktr\Tracker\Model\MktrApi;
use Mktr\Tracker\Model\MktrHelp;

class Reviews extends Action
{
    private static $ins = [
        "Help" => null,
        "Config" => null
    ];

    private static $error = null;

    private static function status()
    {
        return self::$error == null;
    }

    public function __construct(\Magento\Framework\App\Action\Context $context)
    {
        parent::__construct($context);
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$ins["Help"] == null) {
            self::$ins["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$ins["Help"];
    }

    public function execute()
    {
        self::$error = self::getHelp()->getFunc->isParamValid(array(
            'key' => 'Required|Key',
            'start_date' => 'Required|DateCheck|StartDate'
        ));
        if (self::status()) {
            return self::getHelp()->getFunc->Output('reviews', json_decode(json_encode(self::getHelp()->getPagesReviews->execute()), true));
        }

        return self::getHelp()->getFunc->Output('status', self::$error);
    }
}
