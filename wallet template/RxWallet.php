<?php

namespace common\components\rxorder;

use common\helpers\T;
use common\models\mfp\activeRecord\MfpDocument;
use common\models\pub\OrderItems;
use common\models\pub\Users;
use common\models\pub\UserWallet;
use common\models\pub\Wallet;
use Exception;
use Yii;
use yii\db\Expression;
use yii\db\Query;

/**
 * Created by PhpStorm.
 * User: Boris
 * Date: 18.1.2018 г.
 * Time: 13:49 ч.
 *
 * @property \common\models\pub\OrderDelivery $orderDelivery
 * @property \common\models\pub\Orders $order
 * @property \common\models\pub\Reclamations reclamation
 */
class RxWallet extends \yii\base\Model
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_DELETED = 'DELETED';
    const STATUS_USED = 'USED';

    const REASON_COMPENSATION = 'COMPENSATION';
    const REASON_DELIVERY = 'DELIVERY';
    const REASON_RECLAMATION = 'RECLAMATION';
    const REASON_STRIPPED_ORDER = 'STRIPPED';
    const REASON_PRODUCT = 'PRODUCT';
    const REASON_PAYMENT = 'PAYMENT';
    const REASON_BANK = 'BANK';
    const REASON_MFP = 'MFP';
    const REASON_OTHER = 'OTHER';

    const REFUND_PRODUCT = 'PRODUCT';
    const REFUND_DELIVERY = 'DELIVERY';

    const TYPE_WALLET = 'WALLET';
    const TYPE_CREDIT = 'CREDIT';

    const DOC_TYPE_RECEIPT = 'разписка';
    const DOC_TYPE_DEBIT_NOTE = 'дебитно известие';
    const DOC_TYPE_CREDIT_NOTE = 'кредитно известие';

    public $credit_note_number;
    public $document_type;

    public $userId;
    public $marketId;
    public $amount;
    public $type;
    public $orderId;
    public $orderItemId;
    public $creditNoteId;
    public $withdrawReason;
    public $orderDeliveryId;
    public $returnItem;
    public $packageCode;
    public $payoutRequestId;

    public $order;
    public $orderDelivery;
    public $reclamationOrder;
    public $reclamation;
    public $sfpDocumentItem;
    public $mfpDocument;
    public $walletReason;

    /**
     * @return Wallet[]
     * @throws Exception
     */
    public function withdraw()
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($this->amount <= 0) {
                throw new Exception('Amount to withdraw from wallet is not larger than 0');
            }

            $command = (new Query())
                ->select([
                    'w.type',
                    'IF(w.type = :wallet, 1, 2) as type_sort',
                    'market_id',
                    new Expression("COALESCE(IF((sfp_document_item_id IS NOT NULL OR w.mfp_document_id IS NOT NULL), 'RECEIPT', 'CREDIT_NOTE'),  'DEBIT_NOTE') as document_type"),
                    new Expression("COALESCE(d.number, i.invoice_number, md.number) AS credit_note_number"),
                    new Expression("SUM(amount) AS amount"),
                    'w.sfp_document_item_id',
                    'w.credit_note_id',
                    'reclamation_id',
                    'w.mfp_document_id'
                ])
                ->from('wallet w')
                ->leftJoin('sfp_document_item di', 'di.id = w.sfp_document_item_id')
                ->leftJoin('sfp_document d', 'd.id = di.document_id')
                ->leftJoin('invoices i', 'i.id = w.credit_note_id')
                ->leftJoin('remix_mfp.mfp_document md', 'md.id = w.mfp_document_id')
                ->andWhere(['w.user_id' => $this->userId])
                ->andWhere(['w.market_id' => $this->marketId])
                ->andWhere('w.status != :status', ['status' => self::STATUS_PENDING])
                ->groupBy('w.type, w.credit_note_id, w.sfp_document_item_id, w.mfp_document_id')
                ->having('amount > 0')
                ->orderBy('type_sort, w.id ASC');

            $command->params['wallet'] = self::TYPE_WALLET;
            $command->andFilterWhere(['w.type' => $this->type]);

            $wallets = $command->createCommand()->queryAll();

            $result = [];

            $total = 0;
            if ($wallets) {
                $balanceRemaining = $this->amount;

                $user = Users::findOne($this->userId);
                $lang = $user->market->l18n->code;

                foreach ($wallets as $wallet) {
                    $total += $wallet['amount'];
                    if (round($balanceRemaining, 2) == 0) {
                        continue;
                    }
                    $withdrawal = min($wallet['amount'], $balanceRemaining);
                    $balanceRemaining -= $withdrawal;

                    $walletWithdrawal = new Wallet();
                    $walletWithdrawal->user_id = $this->userId;
                    $walletWithdrawal->amount = -$withdrawal;
                    $walletWithdrawal->date_activated = date('Y-m-d H:i:s');
                    $walletWithdrawal->status = self::STATUS_USED;
                    $walletWithdrawal->reclamation_id = $wallet['reclamation_id'];
                    $walletWithdrawal->default_order_id = $this->orderId;
                    $walletWithdrawal->order_id = $this->orderId;
                    $walletWithdrawal->order_delivery_id = $this->orderDeliveryId;
                    $walletWithdrawal->reclamation_order_id = $this->reclamationOrder ? $this->reclamationOrder->id : null;
                    $walletWithdrawal->description = "-";
                    $walletWithdrawal->sfp_document_item_id = $wallet['sfp_document_item_id'];
                    $walletWithdrawal->credit_note_id = $wallet['credit_note_id'];
                    $walletWithdrawal->mfp_document_id = $wallet['mfp_document_id'];
                    $walletWithdrawal->payout_id = $this->payoutRequestId ?? null;

                    switch ($this->withdrawReason) {
                        case static::REASON_BANK:
                            $walletWithdrawal->description = Yii::t('wallet_description', 'Банков превод', array(),
                                $lang);
                            $walletWithdrawal->reason = static::REASON_BANK;
                            break;
                        case static::REASON_PRODUCT:
                            if ($orderItemModel = OrderItems::findOne($this->orderItemId)) {
                                $walletWithdrawal->description = Yii::t('wallet_description',
                                    "Възстановена сума за продукт {sku} от поръчка #{order_num}", [
                                        'sku' => $orderItemModel->product->sku,
                                        'order_num' => $orderItemModel->order->order_num,
                                    ], $lang);
                            }
                            break;
                        case static::REASON_DELIVERY:
                            if ($orderItemModel = OrderItems::findOne($this->orderItemId)) {
                                $walletWithdrawal->description = Yii::t('wallet_description',
                                    "Доставка по поръчка #{order_num}", [
                                        'order_num' => $orderItemModel->order->order_num
                                    ], $lang);
                            }
                            break;
                        case static::REASON_PAYMENT:
                            //TODO TEMP - because all orders are migrated - remove when frontend is LIVE
                            if (false && $walletWithdrawal->order) {
                                $walletWithdrawal->description = Yii::t('wallet_description',
                                    "Използван в поръчка #{order_num}", [
                                        'order_num' => $walletWithdrawal->order->order_num
                                    ], $lang);
                            } elseif ($walletWithdrawal->order_delivery_id) {
                                $walletWithdrawal->description = Yii::t('wallet_description',
                                    "Използван в доставка #{delivery}", [
                                        'delivery' => $walletWithdrawal->order_delivery_id
                                    ], $lang);
                            }
                            break;
                        case static::REASON_RECLAMATION:
                            if ($this->reclamationOrder) {
                                $walletWithdrawal->description = Yii::t('wallet_description',
                                    'Доставка по рекламация #{reclamation_number}', [
                                        'reclamation_number' => $this->reclamationOrder->number,
                                    ], $lang);
                            }
                            break;
                        case static::REASON_MFP:
                            if ($this->mfpDocument && $this->mfpDocument->order) {
                                $walletWithdrawal->description = Yii::t('wallet_description',
                                    'Заплащане на доставка по продажба #{mfp_order}', [
                                        'mfp_order' => $this->mfpDocument->order->package->code
                                    ], $lang);
                            } elseif ($walletWithdrawal->order) {
                                $orderNum = $this->packageCode ?: $walletWithdrawal->order->order_num;

                                $walletWithdrawal->description = Yii::t('wallet_description',
                                    'Заплащане на услуга връщане #{order_num}', [
                                        'order_num' => $orderNum
                                    ], $lang);
                            }

                            break;
                    }

                    switch ($wallet['document_type']) {
                        case 'RECEIPT' :
                            $walletWithdrawal->document_type = self::DOC_TYPE_RECEIPT;
                            break;
                        case 'DEBIT_NOTE' :
                            $walletWithdrawal->document_type = self::DOC_TYPE_DEBIT_NOTE;
                            break;
                        default :
                            $walletWithdrawal->document_type = self::DOC_TYPE_CREDIT_NOTE;
                            break;
                    }

                    $walletWithdrawal->market_id = $wallet['market_id'];
                    $walletWithdrawal->type = $wallet['type'];

                    if (!$walletWithdrawal->save()) {
                        throw new Exception(current($walletWithdrawal->getErrors())[0]);
                    }

                    $result[] = $walletWithdrawal;
                }

                if (round($total, 2) < round($this->amount, 2)) {
                    throw new Exception('Trying to withdraw more money than are available in wallets');
                }
            }

            $this->subtractUserWallet();

            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollback();
            throw new Exception($e->getMessage());
        }
        return $result;
    }


    /**
     * @return array
     * @throws \yii\db\Exception
     */
    public function getCreditsForOrder()
    {
        $sql = "
            SELECT
                COALESCE(IF((sfp_document_item_id IS NOT NULL OR w.mfp_document_id IS NOT NULL), 'RECEIPT', NULL),'CREDIT_NOTE') AS doc_type,
				IFNULL(IFNULL(sd.number, md.number), i.invoice_number) AS credit_note_number,
				SUM(amount) AS amount
            FROM
                wallet w
            LEFT JOIN
                invoices i ON i.id = w.credit_note_id
            LEFT JOIN
				sfp_document_item sdi ON sdi.id = w.sfp_document_item_id
			LEFT JOIN
				sfp_document sd ON sd.id = sdi.document_id	
			LEFT JOIN
			    remix_mfp.mfp_document md ON md.id = w.mfp_document_id     
            WHERE
            	w.order_id = :order_id
            AND w.type = :credit
            GROUP BY doc_type, credit_note_number
            HAVING amount < 0
            ORDER BY w.id DESC
            ";

        $wallets = Yii::$app->db->createCommand($sql, [
            'order_id' => $this->orderId,
            'credit' => self::TYPE_CREDIT
        ])->queryAll();

        $result = [];

        if ($wallets) {
            foreach ($wallets as $wallet) {
                $walletWithdrawal = new Wallet();
                $walletWithdrawal->amount = $wallet['amount'];
                $walletWithdrawal->credit_note_number = $wallet['credit_note_number'];
                switch ($wallet['doc_type']) {
                    case 'RECEIPT' :
                        $walletWithdrawal->document_type = self::DOC_TYPE_RECEIPT;
                        break;
                    case 'CREDIT_NOTE' :
                        $walletWithdrawal->document_type = self::DOC_TYPE_CREDIT_NOTE;
                        break;
                }

                $result[] = $walletWithdrawal;
            }
        }

        return $result;
    }


    /**
     * @return array
     * @throws \yii\db\Exception
     */
    public function getCreditsForOrderDelivery()
    {
        $sql = "
            SELECT
                COALESCE(IF((sfp_document_item_id IS NOT NULL OR w.mfp_document_id IS NOT NULL), 'RECEIPT', NULL),'CREDIT_NOTE') AS doc_type,				
				COALESCE(sd.number, md.number, i.invoice_number) AS credit_note_number,
                SUM(amount) AS amount
            FROM
                wallet w
            LEFT JOIN
                invoices i ON i.id = w.credit_note_id
            LEFT JOIN
					sfp_document_item sdi ON sdi.id = w.sfp_document_item_id
			LEFT JOIN
					sfp_document sd ON sd.id = sdi.document_id	
			LEFT JOIN
			    remix_mfp.mfp_document md ON md.id = w.mfp_document_id
            WHERE
            	w.order_delivery_id = :order_delivery_id
            AND w.type = :credit
            AND w.order_id IS NULL
            GROUP BY doc_type, credit_note_number
            HAVING amount < 0
            ORDER BY w.id DESC
            ";

        $wallets = Yii::$app->db->createCommand($sql, [
            'order_delivery_id' => $this->orderDeliveryId,
            'credit' => self::TYPE_CREDIT
        ])->queryAll();

        $result = [];

        if ($wallets) {
            foreach ($wallets as $wallet) {
                $walletWithdrawal = new Wallet();
                $walletWithdrawal->amount = $wallet['amount'];
                $walletWithdrawal->credit_note_number = $wallet['credit_note_number'];
                switch ($wallet['doc_type']) {
                    case 'RECEIPT' :
                        $walletWithdrawal->document_type = self::DOC_TYPE_RECEIPT;
                        break;
                    case 'CREDIT_NOTE' :
                        $walletWithdrawal->document_type = self::DOC_TYPE_CREDIT_NOTE;
                        break;
                }

                $result[] = $walletWithdrawal;
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function create()
    {
        if ($this->amount <= 0) {
            throw new Exception('Trying to create wallet with negative value: ' . $this->amount);
        }
        $wallet = new Wallet();

        if ($this->reclamation) {
            $user = Users::findOne($this->reclamation->order->user_id);
            $lang = $user->market->l18n->code;
            $wallet->user_id = $user->id;
            $wallet->reclamation_order_id = $this->reclamation->reclamation_order_id;

            //return to wallet - you can't remove products with from_combined 1
            if ($this->returnItem) {
                $wallet->order_id = $this->order->id;
                //Todo: get product SKU from order item
                if ($orderItem = OrderItems::findOne($this->returnItem)) {
                    $wallet->default_order_id = $orderItem->from_combined ?: $this->order->id;
                }
            }
            $wallet->reclamation_id = $this->reclamation->id;
            $wallet->market_id = $this->reclamation->order->market_id;

        } elseif ($this->order) {
            $user = Users::findOne($this->order->user_id);
            $lang = $user->market->l18n->code;
            $wallet->user_id = $user->id;
            $wallet->market_id = $this->order->market_id;
            $wallet->order_id = $this->order->id;
            $wallet->default_order_id = $this->order->id;

            if ($this->sfpDocumentItem) {
                $wallet->sfp_document_item_id = $this->sfpDocumentItem->id;
            }
        } elseif ($this->orderDelivery) {
            $user = Users::findOne($this->orderDelivery->user_id);
            $lang = $user->market->l18n->code;
            $wallet->user_id = $user->id;
            $wallet->market_id = $this->orderDelivery->market_id;
            $wallet->order_delivery_id = $this->orderDelivery->id;

            if ($this->sfpDocumentItem) {
                $wallet->sfp_document_item_id = $this->sfpDocumentItem->id;
            }
        } elseif ($this->mfpDocument) {
            $mfpOrder = $this->mfpDocument->order;
            $user = $mfpOrder->user;
            $lang = $user->market->l18n->code;
            $wallet->user_id = $user->id;
            $wallet->market_id = $mfpOrder->market_id;
            $wallet->mfp_document_id = $this->mfpDocument->id;
        } else {
            throw new Exception('Either a reclamation or an order needs to be passed to create wallet');
        }

        switch ($this->walletReason) {
            case self::REASON_RECLAMATION:
                if ($this->reclamation) {
                    $wallet->description = Yii::t('wallet_description', 'Върнати от рекламация #{reclamation_number}', [
                        'reclamation_number' => $this->reclamation->reclamationOrder->number
                    ], $lang);
                }
                break;
            case self::REASON_STRIPPED_ORDER:
                $orderNum = '';
                if ($this->order) {
                    $orderNum = $this->order->order_delivery_id;
                } elseif ($this->reclamation) {
                    $orderNum = $this->reclamation->order->order_delivery_id;
                }
                $wallet->description = Yii::t('wallet_description', 'Върнати от поръчка #{order_num}', [
                    'order_num' => $orderNum
                ], $lang);
                break;
            case self::REASON_PRODUCT:
                if ($orderItemModel = OrderItems::findOne($this->returnItem)) {
                    switch ($orderItemModel->status) {
                        case OrderItems::STATUS_DELETED :
                            $wallet->description = Yii::t('wallet_description',
                                "Възстановена сума за продукт {sku} от поръчка #{order_num}", [
                                    'sku' => $orderItemModel->product->sku,
                                    'order_num' => $orderItemModel->order->order_num,
                                ], $lang);
                            break;
                    }
                }
                break;
            case self::REASON_DELIVERY:
                if (!$this->order && $this->reclamation) {
                    $this->order = $this->reclamation->order;
                }

                if ($this->order) {
                    $wallet->description = Yii::t('wallet_description',
                        'Възстановена доставка по поръчка #{order_num}', [
                            'order_num' => $this->order->order_num,
                        ], $lang);
                }

                if ($this->orderDelivery) {
                    $wallet->description = Yii::t('wallet_description',
                        'Възстановена доставка по поръчка #{delivery}', [
                            'delivery' => $this->orderDelivery->id,
                        ], $lang);
                }
                break;
            case self::REASON_MFP:
                if ($this->mfpDocument && ($mfpOrder = $this->mfpDocument->order)) {
                    if ($this->mfpDocument->sub_type === MfpDocument::SUB_TYPE_PRODUCTS) {
                        $message = "MFP Order";
                    } else {
                        $message = "MFP Scrap";
                    }

                    $wallet->description = Yii::t(T::MFP_LABEL, $message . " #{package_code}", [
                        'package_code' => $mfpOrder->package->code
                    ], $lang);
                }
                break;
        }

        $wallet->amount = $this->amount;
        $wallet->date_activated = new Expression('NOW()');
        $wallet->status = self::STATUS_ACTIVE;
        $wallet->type = $this->type;
        $wallet->credit_note_id = $this->creditNoteId;

        $tr = Wallet::getDb()->beginTransaction();
        try {
            $this->addUserWallet();

            if (!$wallet->save()) {
                throw new \Exception(current($wallet->getFirstErrors()));
            }
            $tr->commit();
        } catch (Exception $e) {
            $tr->rollBack();
            throw $e;
        }

        return $wallet;
    }

    /**
     * @return float|null
     */
    public function getUserWalletAmount(): ?float
    {
        return UserWallet::find()
            ->select(new Expression('SUM(amount)'))
            ->where([
                'id' => $this->userId,
                'market_id' => $this->marketId,
            ])
            ->scalar();
    }

    /**
     * @return UserWallet
     */
    public function getUserWallet()
    {
        $userWallet = UserWallet::find()
            ->where(['id' => $this->userId])
            ->andWhere(['market_id' => $this->marketId])
            ->one();

        if (!$userWallet) {
            $userWallet = new UserWallet();
            $userWallet->id = $this->userId;
            $userWallet->market_id = $this->marketId;
        }

        return $userWallet;
    }

    public function addUserWallet()
    {
        $this->updateUserWallet();
    }

    public function subtractUserWallet()
    {
        $this->updateUserWallet(true);
    }

    public function updateUserWallet($withdraw = false)
    {
        $userWallet = $this->getUserWallet();
        $userWallet->amount += ($withdraw ? -1 : 1) * abs($this->amount);
        if (!$userWallet->save()) {
            throw new \Exception(current($userWallet->getFirstErrors()));
        }
    }
}
