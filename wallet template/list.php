<?php
$this->breadcrumbs = array(
    Yii::t('responsive', 'users') => array('profile'),
    Yii::t('WalletLabels', 'My wallet'),
);
$this->beginContent('//common/profile_wrapper');
$mfpSellPath = app()->market->id == Markets::DE_MARKET ? 'sell/select-bag' : 'sell';
?>
<div class="profile-wallet">
    <div class="walletInWrapper">
	    <h3 class="<?php echo $this->isMobileDevice ? 'title-center-border text-uppercase' : 'title-left text-uppercase';?>">
		    <?php echo Yii::t(NEW_DESIGN_CAT, 'Моят портфейл'); ?>
	    </h3>
	    <div class="box-light-green">
		    <div class="d-flex flex-column flex-md-row justify-content-md-between">
			    <div class="d-flex align-items-center">
				    <img src="<?= getSiteImgUrl(); ?>/images/ld/icons/circle-arrows-green-icon.svg" width="30" height="30" alt="" />
				    <span class="text">
	                    <?= Yii::t('WalletLabels', 'sell_info_title', [
		                    '{openTag}' => '<a href="' . $this->createUrl('mfp/mfpRedirect') . '" class="text-underline" target="_blank">',
		                    '{closeTag}' => '</a>'
	                    ]); ?>
	                </span>
			    </div>
			    <a href="<?= $this->createUrl('mfp/mfpRedirect', ['path' => $mfpSellPath]); ?>"
			       class="site-btn site-btn-green d-inline-block text-uppercase mt-3 mt-md-0" target="_blank" <?= app()->gtm->websiteButtonToMfp('virtual_wallet_link'); ?>>
				    <?= Yii::t('sourcing_label', 'sell_now_link'); ?>
			    </a>
		    </div>

	    </div>
	    <div class="box-border d-flex flex-column justify-content-center align-items-center flex-grow-1 p-4 p-sm-4 mb-5">
		    <span class="icon wallet-icon mb-4"></span>
	        <div class="wallet-availability">
		        <div class="wallet-details text-center text-md-nowrap mb-3">
		            <?php echo Yii::t('WalletLabels', 'Active wallet amount'); ?>:<br>
		            <span class="show text-center mb-2"><?php echo Yii::app()->currencyFormatter->formatCurrency(Yii::app()
				            ->user->walletAmount); ?></span>
		        </div>
	        </div>
	        <div>
		        <?php if ($hasMissingCodePayoutRequest): ?>
			        <?php echo CHtml::link(Yii::t('BankAccounts', 'enter code'), app()->createUrl('iban/enterCode'), array(
				        'class' => 'site-btn site-btn-green my-1'
			        )); ?>
		        <?php elseif ( (Yii::app()->user->walletAmount > 0)): ?>
			        <div class="wallet-info-text">
				        <?php if ($hasMinWithdrawAmount): ?>
					        <div class="mt-4">
					            <?php echo Yii::t('BankAccounts', 'Как да използвам наличната сума в моя Виртуален портфейл?'); ?>
					        </div>
					        <div class="row">
						        <div class="col-sm-6 mt-4">
							        <div class="d-flex align-items-center">
								        <span class="brands-icon p-3 mr-3"></span>
								        <div class="left-text">
									        <?php echo Yii::t('BankAccounts', 'Мога да купя топ модни находки сред 15 000 продукта, които REMIX добавя всеки ден.'); ?>
								        </div>
							        </div>
						        </div>
						        <div class="col-sm-6 mt-4">
							        <div class="d-flex align-items-center">
								        <span class="pay-icon p-3 mr-3"></span>
								        <div class="right-text">
								            <?php echo Yii::t('BankAccounts',
										        "Мога да заявя превод към моя банкова сметка, като попълня данните за нея {HERE}.", [
										            '{HERE}' => CHtml::link(('<strong>' . Yii::t('clEmail', "тук") . '</strong>'), app()->createUrl('iban/payout'))
										        ]);
									        ?>
								        </div>
							        </div>
						        </div>
					        </div>
				        <?php endif; ?>
				        <?php if (marketSosDonation() === true) : ?>
                            <div class="sos-wrapper">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="d-sm-flex align-items-center <?= in_array($this->marketId, [Markets::BG_MARKET]) ? 'market1' : 'market3'; ?>">
                                            <span class="sos-wallet-logo"></span>
                                            <div class="wallet-donation-wrapper">
					                            <?php echo Yii::t('sosDonationMessages',
						                            "Включи се в кампанията на <strong>SOS Детски селища</strong> и направи своето дарение {HERE}.", [
							                            '{HERE}' => CHtml::link(('<strong>' . Yii::t('sosDonationMessages', "тук") . '</strong>'), app()->createUrl('sos/createTransaction'))
						                            ]);
					                            ?>
                                                <br>

					                            <?php echo Yii::t('sosDonationMessages',
						                            "Повече информация за каузата прочетете {HERE}.", [
							                            '{HERE}' => CHtml::link(('<strong>' . Yii::t('sosDonationMessages', "тук") . '</strong>'), app()->createUrl('site/sosDonations'))
						                            ]);
					                            ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
				        <?php endif; ?>
			        </div>
		        <?php endif; ?>
	        </div>
        </div>

	    <?php if ($hasPendingPayoutRequest): ?>
		    <h3 class="<?php echo $this->isMobileNotTablet ? 'title-center-border text-uppercase' : 'title-left text-uppercase';
		    ?>"><?php echo Yii::t('BankAccounts', 'Изплатени по банков път'); ?></h3>
		    <div class="div-table striped">
			    <div class="div-thead <?php echo $this->isMobileNotTablet ? 'd-none' : '';
			    ?>">
				    <div class="div-th"><?php echo Yii::t('WalletLabels', 'Date'); ?></div>
				    <div class="div-th"><?php echo Yii::t('WalletLabels', 'Amount'); ?></div>
				    <div class="div-th text-wrap"><?php echo Yii::t('WalletLabels', 'Status'); ?></div>
				    <div class="div-th"><?php echo Yii::t('WalletLabels', 'Description'); ?></div>
			    </div>
			    <div id="wallet-list-payout" class="div-tbody">
				    <?php foreach ($hasPendingPayoutRequest as $request): ?>
				    <div class="div-tr items">
					    <div class="div-td"><?= $request['date_created'];
					    ?></div>
					    <div class="div-td">
						    <span class="wallet-amount"><?= app()->currencyFormatter->formatCurrency($request['sum']); ?></span>
					    </div>
					    <div class="div-td"><?= t('BankAccounts', $request['status_name']); ?></div>
					    <div class="div-td">
						    <?php if ($request['status'] < PayoutRequestStatus::IN_PROCESSING): ?>
							    <a class="<?= $this->isMobileNotTablet ? 'site-btn-sm ' : ''; ?> site-btn cancel-request"
							       href="javascript:void(0);" data-id="<?= $request['id']; ?>"><?= t('BankAccounts', 'Cancel Request') ?></a>
						    <?php endif; ?>
					    </div>
				    </div>
				    <?php endforeach; ?>
			    </div>
		    </div>
	    <?php endif; ?>

			<?php
			$template = '
				<div class="div-table striped">
					<div class="div-thead ' . ($this->isMobileNotTablet ? "d-none" : "")  . '"> 
						<div class="div-tr">
							<div class="div-th">' .  Yii::t("WalletLabels", "Date") . '</div>
							<div class="div-th">' .  Yii::t("WalletLabels", "Amount") . '</div>
							<div class="div-th">' .  Yii::t("WalletLabels", "Status") . '</div>
							<div class="div-th">' .  Yii::t("WalletLabels", "Description") . '</div>
						</div>
					</div>
					{items}
				</div>
				
				<div class="d-flex align-items-center justify-content-between mt-4">
					<div class="d-none d-sm-inline-block">{summary}</div>
					<div>{pager}</div>
				</div>';

			if ($incomeDataProvider->getItemCount() <= 0) {
				$template = Yii::t('nd_reclamations', 'Няма намерени резултати.');
				echo '<div class="col-12 p-0"><div 
class="box-border d-flex flex-column justify-content-center align-items-center flex-grow-1 p-5">
			<span class="icon return-icon mb-4"></span>
			<div class="empty-result">' . $template . '</div></div></div>';
			}
			?>

			<?php if ($incomeDataProvider->totalItemCount > 0) : ?>
				<h3 class="mt-5 <?php echo $this->isMobileDevice ? 'title-center-border text-uppercase' : 'title-left text-uppercase'; ?>">
					<?php echo Yii::t('WalletLabels', 'Wallet income'); ?>
				</h3>

				<?php $this->widget('FWListView', array(
					'id' => 'wallet-list-income',
					'dataProvider' => $incomeDataProvider,
					'itemView' => 'item',
					'itemsCssClass' => 'div-tbody',
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
						'selectedPageCssClass' => 'active',
						'internalPageCssClass' => 'page-item',
						'customPagerButtonClass' => 'page-link',
						'pageSize' => 6,
						'maxButtonCount' => 5,
						'htmlOptions' => array(
							'class' => 'pagination',
						),
					),
					'template' => $template,
				)); ?>
			<?php endif; ?>
		</div>

		<div class="walletOutWrapper">
			<?php
			$template = '
			<div class="div-table striped">
					<div class="div-thead ' . ($this->isMobileNotTablet ? "d-none" : "") . '"> 
						<div class="div-tr">
							<div class="div-th">' .  Yii::t("WalletLabels", "Date") . '</div>
							<div class="div-th">' .  Yii::t("WalletLabels", "Amount") . '</div>
							<div class="div-th">' .  Yii::t("WalletLabels", "Status") . '</div>
							<div class="div-th">' .  Yii::t("WalletLabels", "Description") . '</div>
						</div>
					</div>
					{items}
				</div>
				
				<div class="d-flex align-items-center justify-content-between mt-4">
					<div class="d-none d-sm-inline-block">{summary}</div>
					<div>{pager}</div>
				</div>';

			if ($expenseDataProvider->getItemCount() <= 0) {
				$template = '{items}';
			}
			?>
			<?php if ($expenseDataProvider->totalItemCount > 0) : ?>
				<h3 class="mt-5 <?php echo $this->isMobileDevice ? 'title-center-border text-uppercase' : 'title-left text-uppercase';
				?>"><?php echo Yii::t('WalletLabels', 'Wallet expense'); ?></h3>

				<?php $this->widget('FWListView', array(
					'id' => 'wallet-list-expense',
					'dataProvider' => $expenseDataProvider,
					'itemView' => 'item',
					'itemsCssClass' => 'div-tbody',
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
						'selectedPageCssClass' => 'active',
						'internalPageCssClass' => 'page-item',
						'customPagerButtonClass' => 'page-link',
						'pageSize' => 6,
						'maxButtonCount' => 5,
						'htmlOptions' => array(
							'class' => 'pagination m-0',
						),
					),
					'template' => $template,
				)); ?>
			<?php endif; ?>
		</div>
	</div>
<?php $this->endContent('//common/profile_wrapper'); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $('.cancel-request').on('click', function () {
            var msg = "<?= Yii::t('BankAccounts', 'Are you sure?'); ?>"
            var id = $(this).data('id');

            confirmDialog('', msg, function () {
                var url = '/iban/ajaxCancelRequest?id=' + id;

                $.post(url, '', function (response) {
                    if (response.success) {
                        window.location.reload(true);
                    }
                }, 'json');
            });
        });
    });
</script>
