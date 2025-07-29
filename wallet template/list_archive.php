<?php
$this->breadcrumbs = array(
	Yii::t('responsive', 'users') => array('profile'),
	Yii::t('WalletLabels', 'My wallet'),
);
$this->beginContent('//common/profile_wrapper');
$isMobileApp = Yii::app()->user->getState('mobileApp'); ?>
<div class="profile-wallet">
	<h3 class="<?php echo $this->isMobileDevice ? 'title-center-border text-uppercase' : 'title-left text-uppercase';
	?>"><?php echo Yii::t(NEW_DESIGN_CAT, 'My wallet'); ?></h3>
	<div class="walletInWrapper">
		<h3 class="<?php echo $this->isMobileNotTablet ? 'title-center-border text-uppercase' : 'title-left text-uppercase';
		?>"><?php echo Yii::t(NEW_DESIGN_CAT, 'Моят портфейл'); ?></h3>
		<div class="box-border d-flex flex-column justify-content-center align-items-center flex-grow-1 p-5">
			<span class="icon wallet-icon mb-4"></span>
			<div class="wallet-availability">
				<?php echo Yii::t('WalletLabels', 'Active wallet amount'); ?>:<br>
				<span><?php echo Yii::app()->currencyFormatter->formatCurrency(Yii::app()->user->walletAmount); ?></span>
			</div>
		</div>

		<?php
		$template = '
			{items}
			
			<div class="d-flex justify-content-between">
	                <div>{summary}</div>
	                <div>{pager}</div>
			</div>
		';

		if ($incomeDataProvider->getItemCount() <= 0) {
			$template = Yii::t('nd_reclamations', 'Няма намерени резултати.');
			echo '<div class="col-12 p-0"><div 
class="box-border d-flex flex-column justify-content-center align-items-center flex-grow-1 p-5">
			<span class="icon return-icon mb-4"></span>
			<div class="empty-result">' . $template . '</div></div></div>';
		}
		?>

		<?php if ($incomeDataProvider->totalItemCount > 0) : ?>
			<h3 class="mt-5 <?php echo $this->isMobileDevice ? 'title-center-border text-uppercase' : 'title-left text-uppercase ';
			?>"><?php echo Yii::t('WalletLabels', 'Wallet income'); ?></h3>

			<div class="row reclamationHeadings hidden-xs <?php echo $this->isMobileApp ? 'd-none' : '';
            ?>">
				<div class="col-md-3"><?php echo Yii::t('WalletLabels', 'Date'); ?></div>
				<div class="col-md-2"><?php echo Yii::t('WalletLabels', 'Amount'); ?></div>
				<div class="col-md-3"><?php echo Yii::t('WalletLabels', 'Status'); ?></div>
				<div class="col-md-4"><?php echo Yii::t('WalletLabels', 'Description'); ?></div>
			</div>

		<?php $this->widget('FWListView', [
			'id' => 'wallet-list-income',
			'dataProvider' => $incomeDataProvider,
			'itemView' => 'item_archive',
			'itemsCssClass' => 'div-tbody',
			'ajaxUpdate' => false,
			'enableHistory' => true,
			'enablePagination' => true,
			'pager' => [
				'prevPageLabel' => '&lt;',
				'nextPageLabel' => '&gt;',
				'firstPageLabel' => '',
				'firstPageCssClass' => 'hidden',
				'lastPageLabel' => '',
				'lastPageCssClass' => 'hidden',
				'header' => '',
				'selectedPageCssClass' => 'active',
				'internalPageCssClass' => 'page-item',
				'customPagerButtonClass' => 'page-link',
				'pageSize' => 6,
				'maxButtonCount' => 5,
				'htmlOptions' => [
					'class' => 'pagination right',
				],
			],
			'template' => $template,
			]); ?>
        <?php endif; ?>
	</div>

	<div class="walletOutWrapper">
		<?php
		$template = '
			{items}
			
			<div class="d-flex justify-content-between">
	                <div>{summary}</div>
	                <div>{pager}</div>
			</div>
		';

		if ($expenseDataProvider->getItemCount() <= 0) {
			$template = '{items}';
		}
		?>
		<?php if ($expenseDataProvider->totalItemCount > 0) : ?>
			<h4 class="mt-5 <?php echo $this->isMobileDevice ? 'title-center-border text-uppercase' : 'title-left text-uppercase';
			?>"><?php echo Yii::t('WalletLabels', 'Wallet expense'); ?></h4>
			<div class="row reclamationHeadings hidden-xs <?php echo $this->isMobileApp ? 'd-none' : '';
			    ?>">
				<div class="col-md-3"><?php echo Yii::t('WalletLabels', 'Date'); ?></div>
				<div class="col-md-2"><?php echo Yii::t('WalletLabels', 'Amount'); ?></div>
				<div class="col-md-3"><?php echo Yii::t('WalletLabels', 'Status'); ?></div>
				<div class="col-md-4"><?php echo Yii::t('WalletLabels', 'Description'); ?></div>
			</div>
			<?php $this->widget('FWListView', array(
				'id' => 'wallet-list-expense',
				'dataProvider' => $expenseDataProvider,
				'itemView' => 'item_archive',
				'ajaxUpdate' => false,
				'enableHistory' => true,
				'enablePagination' => true,
				'pager' => array(
					'prevPageLabel' => '&lt;',
					'nextPageLabel' => '&gt;',
					'firstPageLabel' => '',
					'firstPageCssClass' => 'hidden',
					'lastPageLabel' => '',
					'lastPageCssClass' => 'hidden',
					'header' => '',
					'selectedPageCssClass' => 'active hidden-xs',
					'internalPageCssClass' => 'page hidden-xs',
					'pageSize' => 6,
					'maxButtonCount' => 5,
					'htmlOptions' => array(
						'class' => 'pagination right',
					),
				),
				'template' => $template,
			)); ?>
		<?php endif; ?>
	</div>
</div>
<?php $this->endContent('//common/profile_wrapper'); ?>
