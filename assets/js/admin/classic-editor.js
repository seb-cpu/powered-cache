/* global jQuery  */
(function ($) {
	$('#swiftpress_specific_critical_css').on('change', function () {
		if ($(this).is(':checked')) {
			$('#swiftpress_disable_critical_css').attr('disabled', 'disabled');
		} else {
			$('#swiftpress_disable_critical_css').removeAttr('disabled');
		}
	});

	$('#swiftpress_disable_critical_css').on('change', function () {
		if ($(this).is(':checked')) {
			$('#swiftpress_specific_critical_css').attr('disabled', 'disabled');
		} else {
			$('#swiftpress_specific_critical_css').removeAttr('disabled');
		}
	});

	$('#swiftpress_specific_ucss').on('change', function () {
		if ($(this).is(':checked')) {
			$('#swiftpress_disable_ucss').attr('disabled', 'disabled');
		} else {
			$('#swiftpress_disable_ucss').removeAttr('disabled');
		}
	});

	$('#swiftpress_disable_ucss').on('change', function () {
		if ($(this).is(':checked')) {
			$('#swiftpress_specific_ucss').attr('disabled', 'disabled');
		} else {
			$('#swiftpress_specific_ucss').removeAttr('disabled');
		}
	});
})(jQuery);
