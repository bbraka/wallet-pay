<div class="div-tr">
	<div class="div-td">
		<?php echo  Yii::app()->dateFormatter->formatDateTime(
			CDateTimeParser::parse(
				Yii::app()->market->formatDate($data['date_added']),
				'yyyy-MM-dd hh:mm:ss'
			),
			'short','short'
		); ?>
	</div>
	<div class="div-td"><span class="wallet-amount"><?php echo Yii::app()->currencyFormatter->formatCurrency($data['amount']) ?></span></div>
	<div class="div-td"><?php echo Yii::t('WalletLabels', $data['status']); ?></div>
	<div class="div-td"><div class="last-col-table"><?php echo $data['description']; ?></div></div>
</div>