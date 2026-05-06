(function ($) {
	'use strict';

	$(function () {
		$('#broadcastergf-test-connection').on('click', function () {
			var $btn = $(this);
			var $out = $('#broadcastergf-test-result');

			$btn.prop('disabled', true);
			$out.text(broadcastergfAdmin.i18n.testing).css('color', '');

			$.post(broadcastergfAdmin.ajaxUrl, {
				action: 'broadcastergf_test_connection',
				nonce: broadcastergfAdmin.nonce
			}).done(function (response) {
				if (response && response.success) {
					$out.text(response.data.message).css('color', '#2e7d32');
				} else {
					var msg = (response && response.data && response.data.message)
						? response.data.message
						: broadcastergfAdmin.i18n.reqFailed;
					$out.text(msg).css('color', '#c62828');
				}
			}).fail(function () {
				$out.text(broadcastergfAdmin.i18n.reqFailed).css('color', '#c62828');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});
	});
})(jQuery);
