jQuery(function ($) {
    var frame;
    $('#caswell-logo-choose').on('click', function (e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({
            title:    'Choose Logo',
            button:   { text: 'Use as Logo' },
            library:  { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $('#branding_logo_url').val(att.url);
            $('#branding_logo_id').val(att.id);
            $('.caswell-logo-preview').html(
                '<img src="' + att.url + '" alt="" style="max-height:80px;max-width:300px;" />'
            );
            $('#caswell-logo-remove').show();
        });
        frame.open();
    });
    $('#caswell-logo-remove').on('click', function (e) {
        e.preventDefault();
        $('#branding_logo_url').val('');
        $('#branding_logo_id').val('');
        $('.caswell-logo-preview').html('<span class="description">No logo selected.</span>');
        $(this).hide();
    });
});
