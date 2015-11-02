jQuery(document).ready(function ($) {

    $('.gaddon-setting-select-custom').on('change', function () {

        if ($(this).val() == 'gf_custom')
            $(this).hide().siblings('.gaddon-setting-select-custom-container').show();

    });

    $('.gaddon-setting-select-custom-container .select-custom-reset').on('click', function (event) {
        event.preventDefault();

        var $input = $(this).closest('.gaddon-setting-select-custom-container'),
            $select = $input.prev('select.gaddon-setting-select-custom');

        $input.fadeOut(function () {
            $input.find('input').val('').change();
            $select.fadeIn().focus().val('');
        });
    });

});