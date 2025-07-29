<?php
/**
 * Created by PhpStorm.
 * User: Boris
 * Date: 3.7.2018 г.
 * Time: 17:36 ч.
 */

namespace common\components\rxorder;


use common\models\pub\Wallet;
use yii\base\Component;
use yii\base\Event;

class RxWalletEventLoader extends Component
{
    public function init()
    {
        /**
         * This is required for correct userWallet value if we delete a row.
         * @param $event
         * @return void
         */
        $beforeDelete = function ($event) {
            /**
             * @var Wallet $wallet
             */
            $wallet = $event->sender;
            $rxWallet = new RxWallet();
            $rxWallet->amount = $wallet->amount;
            $rxWallet->userId = $wallet->user_id;
            $rxWallet->marketId = $wallet->market_id;
            if($wallet->amount > 0){
                $rxWallet->subtractUserWallet();
            } else {
                $rxWallet->addUserWallet();
            }
        };
        Event::on(Wallet::class, Wallet::EVENT_BEFORE_DELETE, $beforeDelete);

        parent::init();
    }
}
