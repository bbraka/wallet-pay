<?php
 if (is_array($data)) {
     $data = json_decode(json_encode($data));
 }
?>
<div class="wallet-item-wrapper row">
	<div class="col-xs-12 col-sm-4 col-md-4 col-lg-4">
		<?php echo  Yii::app()->dateFormatter->formatDateTime(
			CDateTimeParser::parse(
				$data->date,
				'yyyy-MM-dd hh:mm:ss'
			),
			'short','short'
		); ?>
	</div>
	<div class="col-xs-12 col-sm-4 col-md-4 col-lg-4">
		<span class="wallet-amount">
			<?php echo Yii::app()->currencyFormatter->formatCurrency($data->amount_remaining) ?>
		</span>
	</div>
	<div class="col-xs-12 col-sm-4 col-md-4 col-lg-4">
		<?= ($data->reclamation_id && $data->amount_remaining  > 0) ? t('nd_labels', 'Доставка за връщане') : $data->order_id + ORDER_NUM ?>
	</div>
</div>